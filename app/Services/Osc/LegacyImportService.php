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

    public const SKIP_MISMATCH = 'Dilangkau: kedudukan tidak sepadan';

    public const SKIP_SHARED = 'Dilangkau: fail dikongsi';

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
                (id, title, status_code, status_kini, year, month, mukim, seksyen, kategori, rujukan, no_fail, plan_file, pelan_tapak_scan, geom, imported_at)
             SELECT DISTINCT ON (id) id, title, status_code, status_kini, year, month, mukim, seksyen, kategori, rujukan, no_fail, plan_file, pelan_tapak_scan,
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
                        NULLIF(btrim(attributes->>'pelan_tapak_scan'), '') AS pelan_tapak_scan,
                        ST_GeometryN(geom, 1) AS g
                 FROM core.gis_features
                 WHERE snapshot_id = ? AND attributes->>'id' ~ '^[0-9]+\$'
             ) s
             ORDER BY id
             ON CONFLICT (id) DO UPDATE SET
                title = EXCLUDED.title, status_code = EXCLUDED.status_code, status_kini = EXCLUDED.status_kini,
                year = EXCLUDED.year, month = EXCLUDED.month, mukim = EXCLUDED.mukim, seksyen = EXCLUDED.seksyen,
                kategori = EXCLUDED.kategori, rujukan = EXCLUDED.rujukan, no_fail = EXCLUDED.no_fail,
                plan_file = EXCLUDED.plan_file, pelan_tapak_scan = EXCLUDED.pelan_tapak_scan, geom = EXCLUDED.geom, imported_at = EXCLUDED.imported_at",
            [$minX, $minY, $maxX, $maxY, $snapshot->id],
        );

        $total = (int) DB::table('core.osc_legacy_papers')->count();
        $withPoint = (int) DB::table('core.osc_legacy_papers')->whereNotNull('geom')->count();

        return ['total' => $total, 'with_point' => $withPoint, 'no_xy' => $total - $withPoint];

    }

    /**
     * Places legacy layout plans from $dir using their world files. A rerun converges: papers whose plan no
     * longer passes the guards lose their imported placement (the stored file is kept).
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
            self::SKIP_MISMATCH => 0,
            self::SKIP_SHARED => 0,
            self::SKIP_BROKEN => 0,
        ];

        $index = $this->directoryIndex($dir);
        $papers = DB::table('core.osc_legacy_papers')
            ->where(fn ($q) => $q->whereNotNull('plan_file')->orWhereNotNull('pelan_tapak_scan'))
            ->orderBy('id')
            ->get(['id', 'plan_file', 'pelan_tapak_scan', DB::raw('geom IS NOT NULL AS has_point')]);

        // Pass 1: resolve each paper to one image file and its corners. Papers without a point still claim
        // their file (they block sharing) but are never placed.
        $resolved = [];
        $claims = [];
        $noPoint = [];
        $unplaced = [];
        foreach ($papers as $paper) {
            $id = (int) $paper->id;
            $result = $this->resolve([$paper->pelan_tapak_scan, $paper->plan_file], true, $index, $srid);
            if (! (bool) $paper->has_point) {
                $unplaced[$id] = self::SKIP_NO_XY;
                $noPoint[$id] = true;
            } elseif (is_string($result)) {
                $unplaced[$id] = $result;
            }
            if (is_array($result)) {
                $resolved[$id] = $result;
                $claims[strtolower($result['file'])][] = $id;
            }
        }

        // Pass 2: containment guard per claimant. A file shared by several papers is placed for all of them only
        // when every claimant has a point and passes the guard; otherwise for none.
        foreach ($claims as $ids) {
            $checked = [];
            $reason = null;
            foreach ($ids as $id) {
                if (isset($noPoint[$id])) {
                    $reason = count($ids) > 1 ? self::SKIP_SHARED : self::SKIP_NO_XY;

                    continue;
                }
                $row = $this->check($id, $resolved[$id], $srid);
                if (is_string($row)) {
                    $unplaced[$id] = $row;
                    $reason = count($ids) > 1 ? self::SKIP_SHARED : $row;
                } else {
                    $checked[$id] = $row;
                }
            }
            foreach ($checked as $id => $row) {
                if ($reason === null) {
                    $this->store($id, $resolved[$id], $srid, $row);
                } else {
                    $unplaced[$id] = self::SKIP_SHARED;
                }
            }
            if ($reason === self::SKIP_SHARED) {
                foreach ($ids as $id) {
                    if (! isset($noPoint[$id])) {
                        $unplaced[$id] = self::SKIP_SHARED;
                    }
                }
            }
        }

        foreach ($papers as $paper) {
            $counts[$unplaced[(int) $paper->id] ?? self::PLACED]++;
        }

        if ($unplaced !== []) {
            // Imported placements only; rows a user placed by hand (placed_by set) are never touched.
            DB::table('core.osc_layout_images')
                ->where('owner_type', 'legacy')
                ->whereNull('placed_by')
                ->whereIn('owner_id', array_map('strval', array_keys($unplaced)))
                ->delete();
        }

        return $counts;
    }

    /** @return array<string, string> lower-cased basename => actual filename; key "" holds the directory */
    private function directoryIndex(string $dir): array
    {
        $index = [];
        foreach (@scandir($dir) ?: [] as $entry) {
            if ($entry !== '.' && $entry !== '..') {
                $index[strtolower($entry)] ??= $entry;
            }
        }
        $index[''] = $dir;

        return $index;
    }

    private function path(array $index, string $actual): string
    {
        return rtrim($index[''], '\\/').DIRECTORY_SEPARATOR.$actual;
    }

    /**
     * @param  array<string, string>  $index
     * @return array{file: string, wkt: string, width: int, height: int, mime: string}|string a skip reason on failure
     */
    private function resolve(array $names, bool $hasPoint, array $index, int $srid): array|string
    {
        if (! $hasPoint) {
            return self::SKIP_NO_XY;
        }

        // Order: the pelan_tapak_scan attribute, then the plan_ file name. The first usable file wins; when none
        // is usable the reason for the first named file is reported.
        $firstReason = null;
        foreach (array_filter($names, fn ($n): bool => is_string($n) && trim($n) !== '') as $name) {
            $result = $this->resolveName(basename(str_replace('\\', '/', trim($name))), $index, $srid);
            if (is_array($result)) {
                return $result;
            }
            $firstReason ??= $result;
        }

        return $firstReason ?? self::SKIP_NO_FILE;
    }

    /** @return array{file: string, wkt: string, width: int, height: int, mime: string}|string */
    private function resolveName(string $name, array $index, int $srid): array|string
    {
        if (in_array(strtolower(pathinfo($name, PATHINFO_EXTENSION)), ['tif', 'tiff'], true)) {
            return self::SKIP_TIFF;
        }

        $actual = $index[strtolower($name)] ?? null;
        if ($actual === null) {
            return self::SKIP_NO_FILE;
        }

        $file = $this->path($index, $actual);
        $result = $this->cornersFor($file, $srid, $index);
        if ($result === null) {
            return self::SKIP_NO_WORLD;
        }
        if ($result === false) {
            return self::SKIP_BROKEN;
        }

        return ['file' => $file] + $result;
    }

    /** @return object|string the corner row to store, or a skip reason */
    private function check(int $id, array $info, int $srid): object|string
    {
        $row = DB::selectOne(
            'SELECT ST_AsText(c) AS wkt,
                    ST_Intersects(c, ST_MakeEnvelope(?, ?, ?, ?, 4326)) AS inside,
                    ST_DWithin(c::geography, (SELECT geom FROM core.osc_legacy_papers WHERE id = ?)::geography, 100) AS near
             FROM (SELECT ST_Transform(ST_GeomFromText(?, ?), 4326) AS c) t',
            [...config('gis.region_bbox'), $id, $info['wkt'], $srid],
        );
        if (! $row->inside) {
            return self::SKIP_OUTSIDE;
        }
        if (! $row->near) {
            return self::SKIP_MISMATCH;
        }

        return $row;
    }

    private function store(int $id, array $info, int $srid, object $row): void
    {
        $file = $info['file'];
        $ext = strtolower(pathinfo($file, PATHINFO_EXTENSION));
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
                [(string) Str::ulid(), (string) $id, $path, $info['mime'], $info['width'], $info['height'], $sha, $row->wkt, $srid],
            );
        } else {
            $changed = $existing->sha256 !== $sha || $existing->wkt !== $row->wkt;
            DB::update(
                'UPDATE core.osc_layout_images
                 SET path = ?, mime_type = ?, width = ?, height = ?, sha256 = ?, corners = ST_GeomFromText(?, 4326), source_srid = ?,
                     row_version = row_version + ?, updated_at = now()
                 WHERE id = ?',
                [$path, $info['mime'], $info['width'], $info['height'], $sha, $row->wkt, $srid, $changed ? 1 : 0, $existing->id],
            );
        }

    }

    /**
     * Reads the image header and world file and returns the corner polygon (WKT in $srid), or null when no
     * world file exists, or false when the image or world file cannot be read. With a directory $index the
     * world file is found case-insensitively without touching the file system again.
     *
     * @param  array<string, string>|null  $index
     * @return array{wkt: string, width: int, height: int, mime: string}|null|false
     */
    public function cornersFor(string $imageFile, int $srid, ?array $index = null): array|null|false
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
        if ($index !== null) {
            $actual = $index[strtolower(basename($base).$worldExt)] ?? null;
            $world = $actual === null ? null : $this->path($index, $actual);
        } else {
            $world = collect([$worldExt, strtoupper($worldExt)])->map(fn (string $e): string => $base.$e)->first(fn (string $p): bool => is_file($p));
        }
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
