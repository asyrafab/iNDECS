<?php

namespace App\Services\Osc;

use App\Models\Gis\GisLayer;
use App\Services\Gis\AffinePlacement;
use App\Services\Gis\LayerReplicationService;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use RuntimeException;

class LegacyImportService
{
    public const LAYER = 'legacy_mjkp';

    public const SRIDS = [990001, 990002];

    public const PLACED = 'Diletakkan';

    public const SKIP_NO_XY = 'Dilangkau: tiada XY';

    public const SKIP_NO_FILE = 'Dilangkau: tiada fail';

    public const SKIP_NO_WORLD = 'Dilangkau: tiada world file';

    public const SKIP_TIFF = 'Dilangkau: format TIFF belum disokong';

    public const SKIP_OUTSIDE = 'Dilangkau: di luar kawasan';

    public const SKIP_BROKEN = 'Dilangkau: fail rosak';

    public function __construct(private readonly LayerReplicationService $replication) {}

    /**
     * Replicates the MJKP layer and upserts core.osc_legacy_papers by ID.
     *
     * @return array{total: int, with_point: int, no_xy: int}
     */
    public function importPoints(): array
    {
        $layer = GisLayer::query()->findOrFail(self::LAYER);
        $snapshot = $this->replication->replicate($layer);
        [$minX, $minY, $maxX, $maxY] = config('gis.region_bbox');

        DB::statement(
            "INSERT INTO core.osc_legacy_papers
                (id, title, status_code, status_kini, year, month, mukim, seksyen, kategori, rujukan, no_fail, plan_file, geom, imported_at)
             SELECT DISTINCT ON (id) id, title, status_code, status_kini, year, month, mukim, seksyen, kategori, rujukan, no_fail, plan_file,
                    CASE WHEN g IS NOT NULL AND NOT ST_IsEmpty(g) AND ST_Intersects(g, ST_MakeEnvelope(?, ?, ?, ?, 4326)) THEN g END,
                    now()
             FROM (
                 SELECT (attributes->>'id')::int AS id,
                        COALESCE(NULLIF(attributes->>'tajuk', ''), '-') AS title,
                        NULLIF(attributes->>'status', '') AS status_code,
                        NULLIF(attributes->>'status_kini', '') AS status_kini,
                        NULLIF(attributes->>'tahun', '') AS year,
                        NULLIF(attributes->>'bulan', '') AS month,
                        NULLIF(attributes->>'mukim', '') AS mukim,
                        NULLIF(attributes->>'seksyen', '') AS seksyen,
                        NULLIF(attributes->>'kategori', '') AS kategori,
                        NULLIF(attributes->>'rujukan', '') AS rujukan,
                        NULLIF(attributes->>'no_fail', '') AS no_fail,
                        NULLIF(btrim(attributes->>'plan_'), '') AS plan_file,
                        ST_GeometryN(geom, 1) AS g
                 FROM core.gis_features
                 WHERE snapshot_id = ? AND attributes->>'id' ~ '^[0-9]+\$'
             ) s
             ORDER BY id
             ON CONFLICT (id) DO UPDATE SET
                title = EXCLUDED.title, status_code = EXCLUDED.status_code, status_kini = EXCLUDED.status_kini,
                year = EXCLUDED.year, month = EXCLUDED.month, mukim = EXCLUDED.mukim, seksyen = EXCLUDED.seksyen,
                kategori = EXCLUDED.kategori, rujukan = EXCLUDED.rujukan, no_fail = EXCLUDED.no_fail,
                plan_file = EXCLUDED.plan_file, geom = EXCLUDED.geom, imported_at = EXCLUDED.imported_at",
            [$minX, $minY, $maxX, $maxY, $snapshot->id],
        );

        $total = (int) DB::table('core.osc_legacy_papers')->count();
        $withPoint = (int) DB::table('core.osc_legacy_papers')->whereNotNull('geom')->count();

        return ['total' => $total, 'with_point' => $withPoint, 'no_xy' => $total - $withPoint];
    }

    /**
     * Places legacy layout plans from $dir using their world files.
     *
     * @return array<string, int> counts per outcome (placed, then one per skip reason)
     */
    public function importImages(string $dir, int $srid): array
    {
        if (! in_array($srid, self::SRIDS, true)) {
            throw new RuntimeException('SRID tidak disokong: '.$srid);
        }

        $counts = [
            self::PLACED => 0,
            self::SKIP_NO_XY => 0,
            self::SKIP_NO_FILE => 0,
            self::SKIP_NO_WORLD => 0,
            self::SKIP_TIFF => 0,
            self::SKIP_OUTSIDE => 0,
            self::SKIP_BROKEN => 0,
        ];

        $papers = DB::table('core.osc_legacy_papers')
            ->whereNotNull('plan_file')
            ->orderBy('id')
            ->get(['id', 'plan_file', DB::raw('geom IS NOT NULL AS has_point')]);

        foreach ($papers as $paper) {
            $counts[$this->placeOne((int) $paper->id, (string) $paper->plan_file, (bool) $paper->has_point, $dir, $srid)]++;
        }

        return $counts;
    }

    private function placeOne(int $id, string $planFile, bool $hasPoint, string $dir, int $srid): string
    {
        if (! $hasPoint) {
            return self::SKIP_NO_XY;
        }

        $name = basename(str_replace('\\', '/', $planFile));
        $ext = strtolower(pathinfo($name, PATHINFO_EXTENSION));
        if (in_array($ext, ['tif', 'tiff'], true)) {
            return self::SKIP_TIFF;
        }

        // Most MJKP plan_ names are "<file ref>_<scan>.jpg" and only the scan part carries a world file, so the
        // part after the last underscore is tried when the full name has none.
        $candidates = [$name];
        if (($pos = strrpos($name, '_')) !== false && $pos < strlen($name) - 1) {
            $candidates[] = substr($name, $pos + 1);
        }

        $found = false;
        $file = null;
        $corners = null;
        foreach ($candidates as $candidate) {
            $path = rtrim($dir, '\/').DIRECTORY_SEPARATOR.$candidate;
            if (! is_file($path)) {
                continue;
            }
            $found = true;
            $result = $this->cornersFor($path, $srid);
            if ($result === false) {
                return self::SKIP_BROKEN;
            }
            if ($result !== null) {
                $file = $path;
                $corners = $result;
                break;
            }
        }
        if (! $found) {
            return self::SKIP_NO_FILE;
        }
        if ($corners === null) {
            return self::SKIP_NO_WORLD;
        }
        $ext = strtolower(pathinfo($file, PATHINFO_EXTENSION));

        $row = DB::selectOne(
            'SELECT ST_AsText(c) AS wkt, ST_Intersects(c, ST_MakeEnvelope(?, ?, ?, ?, 4326)) AS inside
             FROM (SELECT ST_Transform(ST_GeomFromText(?, ?), 4326) AS c) t',
            [...config('gis.region_bbox'), $corners['wkt'], $srid],
        );
        if (! $row->inside) {
            return self::SKIP_OUTSIDE;
        }

        $sha = hash_file('sha256', $file);
        $path = 'osc/layouts/legacy/'.$sha.'.'.($ext === 'jpeg' ? 'jpg' : $ext);
        $disk = Storage::disk('local');
        if (! $disk->exists($path)) {
            $stream = fopen($file, 'rb');
            try {
                $disk->put($path, $stream);
            } finally {
                fclose($stream);
            }
        }

        $existing = DB::selectOne(
            "SELECT id, sha256, ST_AsText(corners) AS wkt FROM core.osc_layout_images WHERE owner_type = 'legacy' AND owner_id = ?",
            [(string) $id],
        );
        if ($existing === null) {
            DB::insert(
                "INSERT INTO core.osc_layout_images (id, owner_type, owner_id, path, mime_type, width, height, sha256, corners, source_srid, row_version, created_at, updated_at)
                 VALUES (?, 'legacy', ?, ?, ?, ?, ?, ?, ST_GeomFromText(?, 4326), ?, 1, now(), now())",
                [(string) Str::ulid(), (string) $id, $path, $corners['mime'], $corners['width'], $corners['height'], $sha, $row->wkt, $srid],
            );
        } else {
            $changed = $existing->sha256 !== $sha || $existing->wkt !== $row->wkt;
            DB::update(
                'UPDATE core.osc_layout_images
                 SET path = ?, mime_type = ?, width = ?, height = ?, sha256 = ?, corners = ST_GeomFromText(?, 4326), source_srid = ?,
                     row_version = row_version + ?, updated_at = now()
                 WHERE id = ?',
                [$path, $corners['mime'], $corners['width'], $corners['height'], $sha, $row->wkt, $srid, $changed ? 1 : 0, $existing->id],
            );
        }

        return self::PLACED;
    }

    /**
     * Reads the image header and world file and returns the corner polygon (WKT in $srid), or null when no
     * world file exists, or false when the image or world file cannot be read.
     *
     * @return array{wkt: string, width: int, height: int, mime: string}|null|false
     */
    public function cornersFor(string $imageFile, int $srid): array|null|false
    {
        $ext = strtolower(pathinfo($imageFile, PATHINFO_EXTENSION));
        $worldExt = match ($ext) {
            'jpg', 'jpeg' => 'jgw',
            'png' => 'pgw',
            default => null,
        };
        if ($worldExt === null) {
            return null;
        }

        $base = substr($imageFile, 0, -strlen($ext));
        $world = collect([$worldExt, strtoupper($worldExt)])->map(fn (string $e): string => $base.$e)->first(fn (string $p): bool => is_file($p));
        if ($world === null) {
            return null;
        }

        $values = preg_split('/\s+/', trim((string) file_get_contents($world)), -1, PREG_SPLIT_NO_EMPTY);
        $info = @getimagesize($imageFile);
        if ($info === false || count($values) < 6 || ! collect(array_slice($values, 0, 6))->every(fn ($v): bool => is_numeric($v))) {
            return false;
        }

        $corners = AffinePlacement::fromWorldFile(array_slice($values, 0, 6), (int) $info[0], (int) $info[1]);
        $ring = collect([...$corners, $corners[0]])->map(fn (array $p): string => sprintf('%.9F %.9F', $p[0], $p[1]))->implode(',');

        return ['wkt' => "POLYGON(({$ring}))", 'width' => (int) $info[0], 'height' => (int) $info[1], 'mime' => (string) $info['mime']];
    }
}
