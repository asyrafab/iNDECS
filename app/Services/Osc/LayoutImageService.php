<?php

declare(strict_types=1);

namespace App\Services\Osc;

use App\Contracts\DocumentScanner;
use App\Models\AuditLog;
use App\Models\Osc\AgendaItem;
use App\Models\Osc\LayoutImage;
use App\Models\User;
use App\Services\Gis\AffinePlacement;
use App\Services\Gis\JpegOrientation;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use InvalidArgumentException;
use Symfony\Component\HttpFoundation\StreamedResponse;
use Throwable;

/** Places a scanned layout plan on the map from three control points, and serves the stored image. */
final class LayoutImageService
{
    private const MAX_BYTES = 20 * 1024 * 1024;

    private const MAX_PIXELS = 36_000_000;

    private const MIMES = ['image/jpeg' => 'jpg', 'image/png' => 'png'];

    private const EARTH_RADIUS = 6378137.0;

    private const STALE = 'Rekod telah berubah. Muat semula sebelum meneruskan.';

    public function __construct(private readonly DocumentScanner $scanner) {}

    public static function canPlace(User $user, AgendaItem $item): bool
    {
        return OscAccess::canManage($user) || $item->presenter_id === $user->id || $item->proposed_by === $user->id;
    }

    /**
     * @param  array<int, mixed>  $points  three {pixel: [col, row], map: [lon, lat]}
     */
    public function place(AgendaItem $item, UploadedFile $file, array $points, ?int $expectedVersion, User $actor): LayoutImage
    {
        abort_unless(self::canPlace($actor, $item), 403);

        [$mime, $width, $height] = $this->inspect($file);
        $corners = $this->corners($points, $width, $height);
        $realPath = (string) $file->getRealPath();
        $sha256 = hash_file('sha256', $realPath);
        $path = 'osc/layouts/items/'.$item->id.'/'.$sha256.'.'.self::MIMES[$mime];
        $disk = Storage::disk('local');
        $temp = 'osc/layouts/tmp/'.Str::ulid().'.'.self::MIMES[$mime];
        $scanError = null;

        try {
            // Write to a per-attempt temporary path and scan it before any lock is taken: a slow scan must not hold
            // the item and layout rows, and concurrent attempts never share a file until the final move.
            if (! $disk->put($temp, (string) file_get_contents($realPath))) {
                throw ValidationException::withMessages(['layout' => 'Imej tidak dapat disimpan.']);
            }
            try {
                $this->scanner->assertClean($disk->path($temp));
            } catch (Throwable $rejection) {
                $scanError = $rejection;
                throw ValidationException::withMessages(['layout' => 'Imej gagal imbasan keselamatan dan tidak diterima.']);
            }

            return DB::transaction(function () use ($item, $actor, $mime, $width, $height, $corners, $sha256, $path, $temp, $expectedVersion, $disk): LayoutImage {
                AgendaItem::query()->lockForUpdate()->findOrFail($item->id); // serialises placements for this item
                $existing = LayoutImage::query()->where('owner_type', 'agenda_item')->where('owner_id', $item->id)->lockForUpdate()->first();
                if ($existing !== null && $existing->row_version !== $expectedVersion) {
                    throw ValidationException::withMessages(['layout' => self::STALE]);
                }
                // Under the lock: promote the scanned file to its content-addressed path, or reuse the one already there.
                if ($disk->exists($path)) {
                    $disk->delete($temp);
                } elseif (! $disk->move($temp, $path)) {
                    throw ValidationException::withMessages(['layout' => 'Imej tidak dapat disimpan.']);
                }

                $before = $existing === null ? null : ['sha256' => $existing->sha256, 'corners' => $this->cornersOf($existing->id)];
                $wkt = $this->wkt($corners);
                if ($existing === null) {
                    $id = (string) Str::ulid();
                    DB::insert(
                        "INSERT INTO core.osc_layout_images (id, owner_type, owner_id, path, mime_type, width, height, sha256, corners, source_srid, placed_by, row_version, created_at, updated_at)
                         VALUES (?, 'agenda_item', ?, ?, ?, ?, ?, ?, ST_GeomFromText(?, 4326), 4326, ?, 1, now(), now())",
                        [$id, $item->id, $path, $mime, $width, $height, $sha256, $wkt, $actor->id],
                    );
                } else {
                    $id = $existing->id;
                    DB::update(
                        'UPDATE core.osc_layout_images
                         SET path = ?, mime_type = ?, width = ?, height = ?, sha256 = ?, corners = ST_GeomFromText(?, 4326), source_srid = 4326,
                             placed_by = ?, row_version = row_version + 1, updated_at = now()
                         WHERE id = ?',
                        [$path, $mime, $width, $height, $sha256, $wkt, $actor->id, $id],
                    );
                }
                AuditLog::query()->create([
                    'auditable_type' => AgendaItem::class, 'auditable_id' => $item->id, 'event' => 'osc_layout_placed',
                    'actor_id' => $actor->id, 'before_values' => $before,
                    'after_values' => ['layout_id' => $id, 'sha256' => $sha256, 'corners' => $this->cornersOf($id)],
                    'ip_address' => request()?->ip(),
                ]);

                return LayoutImage::query()->findOrFail($id);
            });
        } catch (Throwable $error) {
            // Only this attempt's temporary file is ever removed; content-addressed files may belong to a committed row.
            if ($scanError !== null) {
                Log::warning('eOSC+ layout plan rejected by security scan.', [
                    'item_id' => $item->id, 'actor_id' => $actor->id, 'error' => $scanError->getMessage(),
                ]);
            }
            throw $error;
        } finally {
            $disk->delete($temp); // no-op once promoted or reused
        }
    }

    public function show(LayoutImage $image, User $actor): StreamedResponse
    {
        $visible = match ($image->owner_type) {
            'agenda_item' => ($item = AgendaItem::query()->with('meeting')->find($image->owner_id)) !== null && OscAccess::canViewItem($actor, $item),
            'legacy' => $actor->canPerformOsc('osc.meeting.view') || OscAccess::canManage($actor),
            default => false,
        };
        abort_unless($visible, 403);

        $disk = Storage::disk('local');
        $stream = $disk->exists($image->path) ? $disk->readStream($image->path) : null;
        abort_unless(is_resource($stream), 404);

        return response()->stream(function () use ($stream): void {
            fpassthru($stream);
            fclose($stream);
        }, 200, [
            'Content-Type' => $image->mime_type,
            'Cache-Control' => 'private, no-store',
            'X-Content-Type-Options' => 'nosniff',
            'Content-Disposition' => 'inline; filename="pelan.'.($image->mime_type === 'image/png' ? 'png' : 'jpg').'"',
        ]);
    }

    /** @return array{0: string, 1: int, 2: int} MIME type from the content, and pixel size. */
    private function inspect(UploadedFile $file): array
    {
        $message = 'Pelan mestilah imej JPG atau PNG tidak melebihi 20 MB.';
        $realPath = $file->getRealPath();
        if (! $file->isValid() || $realPath === false || $file->getSize() === 0 || $file->getSize() > self::MAX_BYTES) {
            throw ValidationException::withMessages(['layout' => $message]);
        }
        $mime = (new \finfo(FILEINFO_MIME_TYPE))->file($realPath);
        $size = $mime !== false && isset(self::MIMES[$mime]) ? @getimagesize($realPath) : false;
        if ($size === false || $size[2] !== ($mime === 'image/png' ? IMAGETYPE_PNG : IMAGETYPE_JPEG) || $size[0] < 1 || $size[1] < 1) {
            throw ValidationException::withMessages(['layout' => $message]);
        }
        if ($size[0] * $size[1] > self::MAX_PIXELS) {
            throw ValidationException::withMessages(['layout' => 'Imej terlalu besar (maksimum 36 juta piksel).']);
        }
        // Browsers rotate by EXIF orientation but the stored pixel size is raw; the placement would not match the preview.
        $orientation = $mime === 'image/jpeg' ? JpegOrientation::read($realPath) : null;
        if ($orientation !== null && $orientation !== 1) {
            throw ValidationException::withMessages(['layout' => 'Imej mempunyai orientasi EXIF; simpan semula tanpa putaran.']);
        }

        return [$mime, $size[0], $size[1]];
    }

    /**
     * Corners (top-left, top-right, bottom-right, bottom-left) in EPSG:4326 from three pixel/map pairs.
     *
     * @param  array<int, mixed>  $points
     * @return array<int, array{0: float, 1: float}>
     */
    private function corners(array $points, int $width, int $height): array
    {
        $invalid = fn (): ValidationException => ValidationException::withMessages(['control_points' => 'Titik kawalan tidak sah.']);
        if (count($points) !== 3) {
            throw $invalid();
        }
        $pixel = [];
        $map = [];
        foreach (array_values($points) as $point) {
            $p = is_array($point) ? ($point['pixel'] ?? null) : null;
            $m = is_array($point) ? ($point['map'] ?? null) : null;
            if (! is_array($p) || ! is_array($m) || count($p) !== 2 || count($m) !== 2
                || ! is_numeric($p[0] ?? null) || ! is_numeric($p[1] ?? null) || ! is_numeric($m[0] ?? null) || ! is_numeric($m[1] ?? null)) {
                throw $invalid();
            }
            [$col, $row, $lon, $lat] = [(float) $p[0], (float) $p[1], (float) $m[0], (float) $m[1]];
            if (! is_finite($col + $row + $lon + $lat) || $col < -1 || $col > $width || $row < -1 || $row > $height
                || abs($lon) > 180 || abs($lat) > 85) {
                throw $invalid();
            }
            $pixel[] = [$col, $row];
            $map[] = $this->toMercator($lon, $lat);
        }

        try {
            $corners = AffinePlacement::fromControlPoints($pixel, $map, $width, $height);
        } catch (InvalidArgumentException) {
            throw $invalid();
        }

        [$west, $south, $east, $north] = array_map('floatval', (array) config('gis.region_bbox'));
        $result = [];
        foreach ($corners as [$x, $y]) {
            $lonLat = is_finite($x + $y) ? $this->fromMercator($x, $y) : null;
            if ($lonLat === null || $lonLat[0] < $west || $lonLat[0] > $east || $lonLat[1] < $south || $lonLat[1] > $north) {
                throw ValidationException::withMessages(['control_points' => 'Lokasi berada di luar kawasan MBSP.']);
            }
            $result[] = $lonLat;
        }

        return $result;
    }

    /** @return array{0: float, 1: float} */
    private function toMercator(float $lon, float $lat): array
    {
        return [
            self::EARTH_RADIUS * deg2rad($lon),
            self::EARTH_RADIUS * log(tan(M_PI / 4 + deg2rad($lat) / 2)),
        ];
    }

    /** @return array{0: float, 1: float} */
    private function fromMercator(float $x, float $y): array
    {
        return [
            rad2deg($x / self::EARTH_RADIUS),
            rad2deg(2 * atan(exp($y / self::EARTH_RADIUS)) - M_PI / 2),
        ];
    }

    /** @param  array<int, array{0: float, 1: float}>  $corners */
    private function wkt(array $corners): string
    {
        $ring = array_map(fn (array $c): string => sprintf('%.9F %.9F', $c[0], $c[1]), [...$corners, $corners[0]]);

        return 'POLYGON(('.implode(',', $ring).'))';
    }

    /** @return array<int, array{0: float, 1: float}> */
    private function cornersOf(string $id): array
    {
        $row = DB::selectOne('SELECT ST_AsGeoJSON(corners) AS geojson FROM core.osc_layout_images WHERE id = ?', [$id]);

        return array_slice(json_decode($row->geojson, true)['coordinates'][0], 0, 4);
    }
}
