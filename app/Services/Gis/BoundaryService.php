<?php

declare(strict_types=1);

namespace App\Services\Gis;

use App\Models\Application;
use App\Models\AuditLog;
use App\Models\User;
use Illuminate\Database\QueryException;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

/** Validates and normalises drawn site boundaries with PostGIS and stores them on the primary site. */
final class BoundaryService
{
    public const INVALID = 'Sempadan tidak sah.';

    public const SELF_INTERSECTING = 'Sempadan bertindih sendiri.';

    public const OUTSIDE = 'Lokasi berada di luar kawasan MBSP.';

    private const MAX_BYTES = 1048576;

    private const MAX_VERTICES = 10000;

    /**
     * Returns WKB hex (EWKB, SRID 4326) of a MultiPolygon.
     *
     * @throws ValidationException
     */
    public function normalise(string $geojson, string $field = 'boundary'): string
    {
        $invalid = fn (string $message = self::INVALID) => ValidationException::withMessages([$field => $message]);

        if (strlen($geojson) > self::MAX_BYTES) {
            throw $invalid();
        }
        $decoded = json_decode($geojson, true, 16);
        if (! is_array($decoded) || ! in_array($decoded['type'] ?? null, ['Polygon', 'MultiPolygon'], true)
            || ! is_array($decoded['coordinates'] ?? null) || $decoded['coordinates'] === []) {
            throw $invalid();
        }
        $vertices = 0;
        if (! $this->validCoordinates($decoded['coordinates'], $decoded['type'] === 'Polygon' ? 2 : 3, $vertices) || $vertices > self::MAX_VERTICES) {
            throw $invalid();
        }
        // Re-encode only the type and coordinates so that nothing else in the input reaches PostGIS (crs, members).
        $clean = json_encode(['type' => $decoded['type'], 'coordinates' => $decoded['coordinates']]);

        [$west, $south, $east, $north] = array_map('floatval', (array) config('gis.region_bbox'));

        try {
            // A nested transaction is a savepoint, so a PostGIS parse error cannot poison the caller's transaction.
            $row = DB::transaction(fn () => DB::selectOne(
                'WITH raw AS (SELECT ST_SetSRID(ST_GeomFromGeoJSON(?), 4326) AS geom),
                      fixed AS (
                          SELECT geom, ST_Multi(ST_CollectionExtract(ST_MakeValid(geom), 3)) AS repaired FROM raw
                      )
                 SELECT ST_IsValid(geom) AS valid,
                        ST_IsEmpty(repaired) AS empty,
                        ST_Area(geom) AS raw_area,
                        ST_Area(repaired) AS repaired_area,
                        ST_Area(repaired::geography) AS area_m2,
                        ST_Intersects(repaired, ST_MakeEnvelope(?, ?, ?, ?, 4326)) AS inside,
                        ST_AsHEXEWKB(repaired) AS hex
                 FROM fixed',
                [$clean, $west, $south, $east, $north],
            ));
        } catch (QueryException) {
            throw $invalid();
        }

        if ($row === null || $row->empty || $row->hex === null || (float) $row->area_m2 < 1.0) {
            throw $invalid();
        }
        if (! $row->valid && abs((float) $row->raw_area - (float) $row->repaired_area) > 0.01 * (float) $row->repaired_area) {
            throw $invalid(self::SELF_INTERSECTING);
        }
        if (! $row->inside) {
            throw $invalid(self::OUTSIDE);
        }

        return $row->hex;
    }

    public function storeForApplication(Application $application, string $geojson, int $expectedVersion, User $actor): void
    {
        abort_unless($this->canSet($actor, $application), 403);
        $hex = $this->normalise($geojson, 'boundary');

        DB::transaction(function () use ($application, $hex, $expectedVersion, $actor): void {
            $record = Application::query()->lockForUpdate()->findOrFail($application->id);
            if ($record->row_version !== $expectedVersion) {
                throw ValidationException::withMessages(['boundary' => 'Rekod telah berubah. Muat semula sebelum meneruskan.']);
            }
            $site = $record->sites()->wherePivot('is_primary', true)->first();
            if ($site === null) {
                throw ValidationException::withMessages(['boundary' => 'Permohonan ini tiada tapak utama.']);
            }
            $before = DB::selectOne('SELECT ST_Area(boundary::geography) AS area FROM sites WHERE id = ? AND boundary IS NOT NULL', [$site->id]);
            DB::update('UPDATE sites SET boundary = ?::geometry, updated_at = ? WHERE id = ?', [$hex, now(), $site->id]);
            $after = DB::selectOne('SELECT ST_Area(boundary::geography) AS area FROM sites WHERE id = ?', [$site->id]);
            $record->update(['row_version' => $record->row_version + 1]);
            AuditLog::query()->create([
                'auditable_type' => Application::class, 'auditable_id' => $record->id,
                'event' => 'site_boundary_set', 'actor_id' => $actor->id,
                'before_values' => ['area_m2' => $before === null ? null : round((float) $before->area, 2)],
                'after_values' => ['area_m2' => round((float) $after->area, 2)],
                'ip_address' => request()?->ip(),
            ]);
        });
    }

    public function canSet(User $actor, Application $application): bool
    {
        if (! $actor->canAccessApplication($application)) {
            return false;
        }

        return $application->application_type_id === 'PKM'
            ? $actor->canPerformPkm('perancang.pkm.register')
            : $actor->canPerformBangunan('register', $application->application_type_id);
    }

    /** @param array<mixed> $node */
    private function validCoordinates(array $node, int $depth, int &$vertices): bool
    {
        if ($depth === 0) {
            $vertices++;

            // Two-dimensional positions only: the boundary columns are 2D and a Z value would fail at UPDATE.
            return count($node) === 2
                && (is_int($node[0] ?? null) || is_float($node[0] ?? null))
                && (is_int($node[1] ?? null) || is_float($node[1] ?? null));
        }
        if ($node === [] || ! array_is_list($node)) {
            return false;
        }
        foreach ($node as $child) {
            if (! is_array($child) || $vertices > self::MAX_VERTICES || ! $this->validCoordinates($child, $depth - 1, $vertices)) {
                return false;
            }
        }

        return true;
    }
}
