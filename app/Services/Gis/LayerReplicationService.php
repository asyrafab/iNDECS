<?php

namespace App\Services\Gis;

use App\Models\AuditLog;
use App\Models\Gis\GisLayer;
use App\Models\Gis\GisLayerSnapshot;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use RuntimeException;
use Symfony\Component\Process\Process;
use Throwable;

class LayerReplicationService
{
    private const TIMEOUT_SECONDS = 1800;

    private const PAGE_SIZE = 2000;

    public function __construct(private readonly ArcGisToken $tokens) {}

    public function replicate(GisLayer $layer, ?User $actor = null): GisLayerSnapshot
    {
        // Check and create under one row lock so two concurrent starts cannot both pass.
        $snapshot = DB::transaction(function () use ($layer, $actor): GisLayerSnapshot {
            GisLayer::query()->whereKey($layer->id)->lockForUpdate()->firstOrFail();

            $running = GisLayerSnapshot::query()
                ->where('layer_id', $layer->id)
                ->where('status', 'importing')
                ->where('started_at', '>', now()->subHours(2)->toIso8601String())
                ->exists();
            if ($running) {
                throw new RuntimeException("Kemas kini lapisan {$layer->id} sedang berjalan.");
            }

            return GisLayerSnapshot::query()->create([
                'layer_id' => $layer->id,
                'status' => 'importing',
                'feature_count' => 0,
                'started_at' => now(),
                'triggered_by' => $actor?->id,
            ]);
        });
        $staging = 'core.gis_import_'.$snapshot->id;
        $token = null;

        try {
            $token = $this->tokens->get();
            $this->runOgr2ogr($layer, $staging, $token);

            DB::transaction(function () use ($layer, $snapshot, $staging, $actor): void {
                $this->promote($layer, $snapshot, $staging, $actor);
            });
        } catch (Throwable $e) {
            $this->dropStaging($staging);
            $message = $this->redact($e->getMessage(), $token);
            $snapshot->forceFill(['status' => 'failed', 'error' => $message, 'finished_at' => now()])->save();
            $this->audit($layer, 'gis_layer_replication_failed', $actor, null, [
                'snapshot_id' => $snapshot->id,
                'error' => $message,
            ]);

            // Never chain the original exception: its message and trace arguments may carry the token.
            throw new RuntimeException($message);
        }

        try {
            $this->pruneOldSnapshots($layer);
        } catch (Throwable $e) {
            Log::warning('Pemangkasan snapshot GIS gagal.', ['layer' => $layer->id, 'error' => $this->redact($e->getMessage(), null)]);
        }

        return $snapshot->refresh();
    }

    private function promote(GisLayer $layer, GisLayerSnapshot $snapshot, string $staging, ?User $actor): void
    {
        // Serialise promotions per layer and read the previous active snapshot under the lock.
        $locked = GisLayer::query()->whereKey($layer->id)->lockForUpdate()->firstOrFail();
        $exists = DB::selectOne('SELECT to_regclass(?) AS t', [$staging])->t !== null;
        if (! $exists) {
            throw new RuntimeException('Lapisan sumber tidak mengandungi rekod.');
        }

        DB::insert(
            "INSERT INTO core.gis_features (snapshot_id, geom, geom_web, attributes)
             SELECT ?, g, ST_Transform(g, 3857), attrs
             FROM (
                 SELECT ST_MakeValid(s.geom) AS g, to_jsonb(s) - 'geom' - 'ogc_fid' AS attrs
                 FROM {$staging} s
                 WHERE s.geom IS NOT NULL
             ) t
             WHERE g IS NOT NULL",
            [$snapshot->id],
        );
        DB::statement("DROP TABLE IF EXISTS {$staging}");

        $count = (int) DB::table('core.gis_features')->where('snapshot_id', $snapshot->id)->count();
        if ($count === 0) {
            throw new RuntimeException('Lapisan sumber tidak mengandungi rekod.');
        }

        $previousId = $locked->active_snapshot_id;
        $snapshot->forceFill(['status' => 'ready', 'feature_count' => $count, 'finished_at' => now()])->save();
        $locked->forceFill(['active_snapshot_id' => $snapshot->id])->save();
        $layer->setRawAttributes($locked->getAttributes(), true);
        if ($previousId !== null && $previousId !== $snapshot->id) {
            GisLayerSnapshot::query()->whereKey($previousId)->where('status', 'ready')->update(['status' => 'superseded']);
        }

        $this->audit($layer, 'gis_layer_replicated', $actor, ['active_snapshot_id' => $previousId], [
            'snapshot_id' => $snapshot->id,
            'feature_count' => $count,
        ]);
    }

    /**
     * The bundled GDAL build ships without the PostgreSQL driver, so ogr2ogr writes a PGDUMP SQL script
     * that psql loads into the staging table. The script is atomic (BEGIN/COMMIT) and the staging table
     * is replaced with DROP TABLE IF EXISTS, which matches -overwrite semantics.
     */
    private function runOgr2ogr(GisLayer $layer, string $staging, ?string $token): void
    {
        $conn = config('database.connections.pgsql');
        $script = tempnam(sys_get_temp_dir(), 'gis_');
        if ($script === false) {
            throw new RuntimeException('Fail sementara tidak dapat dicipta.');
        }

        $source = null;

        try {
            $source = $this->fetchSource($layer, $token);
            $this->run([
                (string) config('gis.ogr2ogr'),
                '-f', 'PGDUMP',
                $script,
                $source,
                '-nln', $staging,
                '-nlt', 'PROMOTE_TO_MULTI',
                '-lco', 'GEOMETRY_NAME=geom',
                '-lco', 'SCHEMA=core',
                '-lco', 'CREATE_SCHEMA=OFF',
                '-lco', 'DROP_TABLE=IF_EXISTS',
                '-lco', 'SPATIAL_INDEX=NONE',
                '-t_srs', 'EPSG:4326',
                '-overwrite',
            ], [], 'ogr2ogr');

            $this->run([
                $this->psql(),
                '-X', '-q',
                '-v', 'ON_ERROR_STOP=1',
                '-h', (string) $conn['host'],
                '-p', (string) $conn['port'],
                '-U', (string) $conn['username'],
                '-d', (string) $conn['database'],
                '-f', $script,
            ], ['PGPASSWORD' => (string) $conn['password']], 'psql');
        } finally {
            @unlink($script);
            if ($source !== null && ! str_starts_with($layer->source_url, 'file://')) {
                @unlink($source);
            }
        }
    }

    private function run(array $command, array $env, string $name): void
    {
        $process = new Process($command, base_path(), $env, null, self::TIMEOUT_SECONDS);
        $process->run();

        if (! $process->isSuccessful()) {
            $output = trim($process->getErrorOutput() ?: $process->getOutput());

            throw new RuntimeException($name.' gagal: '.mb_substr($output, 0, 2000));
        }
    }

    private function psql(): string
    {
        $ogr = (string) config('gis.ogr2ogr');
        foreach (['psql.exe', 'psql'] as $name) {
            $candidate = dirname($ogr).DIRECTORY_SEPARATOR.$name;
            if (dirname($ogr) !== '.' && is_file($candidate)) {
                return $candidate;
            }
        }

        return 'psql';
    }

    /**
     * Returns a local ESRIJSON file path. file:// sources are used as-is. For http(s) sources the layer is
     * paged here (not by GDAL's FEATURE_SERVER_PAGING, which depends on one unpaged request and on an
     * uninterrupted TLS stream) with retries, and the result is verified against the server's own count.
     */
    private function fetchSource(GisLayer $layer, ?string $token): string
    {
        $url = rtrim($layer->source_url, '/');

        if (str_starts_with($url, 'file://')) {
            return base_path(substr($url, 7));
        }

        $where = $layer->where_clause ?: '1=1';
        $auth = $token !== null ? ['token' => $token] : [];
        $get = fn (string $path, array $query): array => $this->getJson($url.$path, $query + $auth);

        $countResponse = $get('/query', ['where' => $where, 'returnCountOnly' => 'true', 'f' => 'json']);
        if (! isset($countResponse['count']) || ! is_numeric($countResponse['count'])) {
            throw new RuntimeException('Bilangan rekod sumber ArcGIS tidak dapat ditentukan.');
        }
        $expected = (int) $countResponse['count'];
        $meta = $get('', ['f' => 'json']);
        $oid = collect($meta['fields'] ?? [])->firstWhere('type', 'esriFieldTypeOID')['name'] ?? 'OBJECTID';

        $path = tempnam(sys_get_temp_dir(), 'gis_src_');
        if ($path === false || ($handle = fopen($path, 'wb')) === false) {
            throw new RuntimeException('Fail sementara tidak dapat dicipta.');
        }

        $written = 0;
        try {
            $maxPages = (int) ceil($expected / self::PAGE_SIZE) + 2;
            for ($offset = 0, $pages = 0; ; $offset += self::PAGE_SIZE) {
                if (++$pages > $maxPages) {
                    throw new RuntimeException('Sumber ArcGIS memulangkan lebih banyak rekod daripada dijangka.');
                }
                $page = $get('/query', [
                    'where' => $where, 'outFields' => '*', 'outSR' => 4326, 'f' => 'json',
                    'orderByFields' => $oid, 'resultOffset' => $offset, 'resultRecordCount' => self::PAGE_SIZE,
                ]);
                if (isset($page['error'])) {
                    throw new RuntimeException('ArcGIS ralat: '.json_encode($page['error']));
                }
                if ($offset === 0) {
                    $header = json_encode([
                        'geometryType' => $page['geometryType'] ?? null,
                        'spatialReference' => ['wkid' => 4326],
                        'fields' => $page['fields'] ?? [],
                    ], JSON_UNESCAPED_SLASHES | JSON_INVALID_UTF8_SUBSTITUTE);
                    fwrite($handle, substr($header, 0, -1));
                    fwrite($handle, ',"features":[');
                }
                foreach ($page['features'] ?? [] as $feature) {
                    fwrite($handle, ($written > 0 ? ',' : '').json_encode($feature, JSON_UNESCAPED_SLASHES | JSON_INVALID_UTF8_SUBSTITUTE));
                    $written++;
                }
                if ($written > $expected) {
                    throw new RuntimeException('Sumber ArcGIS memulangkan lebih banyak rekod daripada dijangka.');
                }
                if (empty($page['exceededTransferLimit']) || ($page['features'] ?? []) === []) {
                    break;
                }
            }
            fwrite($handle, ']}');
        } catch (Throwable $e) {
            fclose($handle);
            @unlink($path);

            throw $e;
        }
        fclose($handle);

        if ($written !== $expected) {
            @unlink($path);

            throw new RuntimeException("ArcGIS memulangkan {$written} rekod, dijangka {$expected}.");
        }

        return $path;
    }

    private function getJson(string $url, array $query): array
    {
        // Portal tokens are generated with client=referer (ArcGisToken), so requests carrying one must send that referer.
        $headers = isset($query['token']) ? ['Referer' => (string) config('app.url')] : [];
        $response = Http::retry(3, 1500, throw: false)->timeout(180)->withHeaders($headers)->get($url, $query);
        if (! $response->successful()) {
            throw new RuntimeException('ArcGIS HTTP '.$response->status().' untuk '.$url);
        }

        return $response->json() ?? throw new RuntimeException('Jawapan ArcGIS tidak sah.');
    }

    private function dropStaging(string $staging): void
    {
        try {
            DB::statement("DROP TABLE IF EXISTS {$staging}");
        } catch (Throwable) {
            // Staging cleanup is best-effort.
        }
    }

    private function redact(string $message, ?string $token): string
    {
        if ($token !== null && $token !== '') {
            $message = str_replace([$token, rawurlencode($token)], '[token]', $message);
        }

        return (string) preg_replace('/([?&]token=)[^&\s\'"]+/i', '$1[token]', $message);
    }

    private function pruneOldSnapshots(GisLayer $layer): void
    {
        $stale = GisLayerSnapshot::query()
            ->where('layer_id', $layer->id)
            ->where('status', 'superseded')
            ->orderByDesc('id')
            ->skip(2)
            ->take(PHP_INT_MAX)
            ->pluck('id');

        if ($stale->isNotEmpty()) {
            DB::table('core.gis_features')->whereIn('snapshot_id', $stale)->delete();
        }
    }

    private function audit(GisLayer $layer, string $event, ?User $actor, ?array $before, array $after): void
    {
        AuditLog::query()->create([
            'auditable_type' => GisLayer::class,
            'auditable_id' => $layer->id,
            'event' => $event,
            'actor_id' => $actor?->id,
            'before_values' => $before,
            'after_values' => $after,
        ]);
    }
}
