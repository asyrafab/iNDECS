<?php

declare(strict_types=1);

namespace Tests\Feature\Gis;

use App\Models\AuditLog;
use App\Models\Gis\GisLayer;
use App\Models\Gis\GisLayerSnapshot;
use App\Services\Gis\ArcGisToken;
use App\Services\Gis\LayerReplicationService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use RuntimeException;
use Tests\TestCase;

final class LayerReplicationTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        if (! env('GIS_OGR2OGR')) {
            $this->markTestSkipped('ogr2ogr tidak dikonfigurasi');
        }

        $this->dropCommittedStagingTables();
    }

    private ?array $pgConfig = null;

    protected function tearDown(): void
    {
        parent::tearDown();

        // ogr2ogr creates staging tables outside the test transaction, so the service's in-transaction DROP
        // is rolled back with it. Remove the committed leftovers on a separate connection.
        $this->dropCommittedStagingTables();
    }

    private function dropCommittedStagingTables(): void
    {
        $c = $this->pgConfig ?? config('database.connections.pgsql');
        $this->pgConfig = $c;
        $pdo = new \PDO(sprintf('pgsql:host=%s;port=%s;dbname=%s', $c['host'], $c['port'], $c['database']), $c['username'], $c['password']);
        foreach ($pdo->query("SELECT tablename FROM pg_tables WHERE schemaname = 'core' AND tablename LIKE 'gis_import_%'")->fetchAll(\PDO::FETCH_COLUMN) as $table) {
            $pdo->exec('DROP TABLE IF EXISTS core."'.$table.'"');
        }
    }

    private function layer(string $id, string $source): GisLayer
    {
        return GisLayer::query()->create([
            'id' => $id,
            'label' => 'Lapisan '.$id,
            'kind' => 'reference',
            'source_url' => 'file://tests/Fixtures/gis/'.$source,
            'where_clause' => '1=1',
            'is_active' => true,
            'sort_order' => 1,
        ]);
    }

    private function service(): LayerReplicationService
    {
        return $this->app->make(LayerReplicationService::class);
    }

    private function stagingTables(): array
    {
        return DB::select("SELECT tablename FROM pg_tables WHERE schemaname = 'core' AND tablename LIKE 'gis_import_%'");
    }

    public function test_replicates_file_source_into_ready_snapshot(): void
    {
        $layer = $this->layer('lots', 'lots.esrijson');

        $snapshot = $this->service()->replicate($layer);

        $this->assertSame('ready', $snapshot->status);
        $this->assertSame(3, $snapshot->feature_count);
        $this->assertSame($snapshot->id, $layer->fresh()->active_snapshot_id);
        $row = DB::selectOne('SELECT ST_SRID(geom_web) AS srid, ST_SRID(geom) AS s4326, attributes FROM core.gis_features WHERE snapshot_id = ? ORDER BY id LIMIT 1', [$snapshot->id]);
        $this->assertSame(3857, $row->srid);
        $this->assertSame(4326, $row->s4326);
        $this->assertSame('101', json_decode($row->attributes, true)['lot']);
        $this->assertSame([], $this->stagingTables());
        $this->assertTrue(AuditLog::query()->where('event', 'gis_layer_replicated')->where('auditable_id', 'lots')->exists());
    }

    public function test_failed_refresh_keeps_previous_snapshot_live(): void
    {
        $layer = $this->layer('lots', 'lots.esrijson');
        $good = $this->service()->replicate($layer);

        $layer->update(['source_url' => 'file://tests/Fixtures/gis/broken.esrijson']);

        try {
            $this->service()->replicate($layer->fresh());
            $this->fail('RuntimeException was expected.');
        } catch (RuntimeException) {
        }

        $this->assertSame($good->id, $layer->fresh()->active_snapshot_id);
        $this->assertSame(1, GisLayerSnapshot::query()->where('status', 'failed')->count());
        $this->assertSame(3, DB::table('core.gis_features')->where('snapshot_id', $good->id)->count());
        $this->assertSame([], $this->stagingTables());
        $this->assertTrue(AuditLog::query()->where('event', 'gis_layer_replication_failed')->exists());
    }

    public function test_empty_source_is_refused(): void
    {
        $layer = $this->layer('lots', 'empty.esrijson');

        try {
            $this->service()->replicate($layer);
            $this->fail('RuntimeException was expected.');
        } catch (RuntimeException $e) {
            $this->assertSame('Lapisan sumber tidak mengandungi rekod.', $e->getMessage());
        }

        $this->assertNull($layer->fresh()->active_snapshot_id);
        $this->assertSame('failed', GisLayerSnapshot::query()->firstOrFail()->status);
        $this->assertSame([], $this->stagingTables());
    }

    public function test_token_is_redacted_from_snapshot_error(): void
    {
        $this->app->instance(ArcGisToken::class, new class extends ArcGisToken
        {
            public function get(): ?string
            {
                return 'SECRETTOKEN';
            }
        });
        $layer = $this->layer('lots', 'lots.esrijson');
        $layer->update(['source_url' => 'http://127.0.0.1:9/arcgis/rest/services/X/FeatureServer/0']);

        try {
            $this->service()->replicate($layer->fresh());
            $this->fail('RuntimeException was expected.');
        } catch (RuntimeException) {
        }

        $snapshot = GisLayerSnapshot::query()->firstOrFail();
        $this->assertSame('failed', $snapshot->status);
        $this->assertNotEmpty($snapshot->error);
        $this->assertStringNotContainsString('SECRETTOKEN', $snapshot->error);
        $this->assertStringNotContainsString('SECRETTOKEN', json_encode(AuditLog::query()->pluck('after_values')));
    }

    public function test_command_continues_after_a_failed_layer_and_exits_non_zero(): void
    {
        $this->layer('a_bad', 'broken.esrijson');
        $this->layer('b_good', 'lots.esrijson');

        $this->artisan('gis:replicate', ['--all' => true])
            ->expectsOutputToContain('Lapisan b_good: 3 rekod')
            ->assertExitCode(1);

        $this->assertNotNull(GisLayer::query()->find('b_good')->active_snapshot_id);
    }

    public function test_only_two_superseded_snapshots_keep_features(): void
    {
        $layer = $this->layer('lots', 'lots.esrijson');
        $ids = [];
        for ($i = 0; $i < 5; $i++) {
            $ids[] = $this->service()->replicate($layer->fresh())->id;
        }

        $counts = collect($ids)->map(fn (int $id) => DB::table('core.gis_features')->where('snapshot_id', $id)->count())->all();
        $this->assertSame([0, 0, 3, 3, 3], $counts);
        $this->assertSame('ready', GisLayerSnapshot::query()->find($ids[4])->status);
    }

    private function fakeTokenService(): void
    {
        $this->app->instance(ArcGisToken::class, new class extends ArcGisToken
        {
            public function get(): ?string
            {
                return 'SECRETTOKEN';
            }
        });
    }

    public function test_exception_chain_never_contains_the_token(): void
    {
        $this->fakeTokenService();
        $layer = $this->layer('lots', 'lots.esrijson');
        $layer->update(['source_url' => 'http://127.0.0.1:9/arcgis/rest/services/X/FeatureServer/0']);

        try {
            $this->service()->replicate($layer->fresh());
            $this->fail('RuntimeException was expected.');
        } catch (RuntimeException $e) {
            for ($x = $e; $x !== null; $x = $x->getPrevious()) {
                $this->assertStringNotContainsString('SECRETTOKEN', $x->getMessage());
            }
            $this->assertNull($e->getPrevious());
        }
    }

    public function test_runaway_paging_is_stopped(): void
    {
        $page = ['geometryType' => 'esriGeometryPoint', 'fields' => [['name' => 'OBJECTID', 'type' => 'esriFieldTypeOID']],
            'exceededTransferLimit' => true,
            'features' => [['attributes' => ['OBJECTID' => 1], 'geometry' => ['x' => 100.4, 'y' => 5.3]]]];
        Http::fake(function ($request) use ($page) {
            return Http::response(str_contains($request->url(), 'returnCountOnly') ? ['count' => 3] : $page);
        });
        $layer = $this->layer('lots', 'lots.esrijson');
        $good = $this->service()->replicate($layer);
        $layer->update(['source_url' => 'https://example.test/arcgis/rest/services/X/FeatureServer/0']);
        $before = glob(sys_get_temp_dir().DIRECTORY_SEPARATOR.'gis_src_*') ?: [];

        try {
            $this->service()->replicate($layer->fresh());
            $this->fail('RuntimeException was expected.');
        } catch (RuntimeException $e) {
            $this->assertSame('Sumber ArcGIS memulangkan lebih banyak rekod daripada dijangka.', $e->getMessage());
        }

        $this->assertSame('failed', GisLayerSnapshot::query()->orderByDesc('id')->firstOrFail()->status);
        $this->assertSame($good->id, $layer->fresh()->active_snapshot_id);
        $this->assertSame($before, glob(sys_get_temp_dir().DIRECTORY_SEPARATOR.'gis_src_*') ?: []);
    }

    public function test_concurrent_replication_of_same_layer_is_refused(): void
    {
        $layer = $this->layer('lots', 'lots.esrijson');
        GisLayerSnapshot::query()->create(['layer_id' => 'lots', 'status' => 'importing', 'started_at' => now()->subMinutes(5)]);

        try {
            $this->service()->replicate($layer);
            $this->fail('RuntimeException was expected.');
        } catch (RuntimeException $e) {
            $this->assertSame('Kemas kini lapisan lots sedang berjalan.', $e->getMessage());
        }
        $this->assertSame(1, GisLayerSnapshot::query()->count());

        GisLayerSnapshot::query()->update(['started_at' => now()->subHours(3)]);
        $this->assertSame('ready', $this->service()->replicate($layer)->status);
    }

    public function test_promote_reads_previous_snapshot_under_layer_lock(): void
    {
        $layer = $this->layer('lots', 'lots.esrijson');
        $first = $this->service()->replicate($layer);
        // A stale model instance (as a concurrent worker would hold) must not lose the real previous snapshot.
        $stale = GisLayer::query()->find('lots');
        $stale->active_snapshot_id = null;
        $second = $this->service()->replicate($stale);

        $this->assertSame('superseded', $first->fresh()->status);
        $this->assertSame($second->id, GisLayer::query()->find('lots')->active_snapshot_id);
    }

    public function test_missing_count_gives_distinct_error(): void
    {
        Http::fake(fn () => Http::response(['error' => null]));
        $layer = $this->layer('lots', 'lots.esrijson');
        $layer->update(['source_url' => 'https://example.test/arcgis/rest/services/X/FeatureServer/0']);

        try {
            $this->service()->replicate($layer->fresh());
            $this->fail('RuntimeException was expected.');
        } catch (RuntimeException $e) {
            $this->assertSame('Bilangan rekod sumber ArcGIS tidak dapat ditentukan.', $e->getMessage());
        }
        $this->assertSame('failed', GisLayerSnapshot::query()->firstOrFail()->status);
    }

    public function test_token_requests_send_the_app_url_as_referer(): void
    {
        config(['app.url' => 'https://indecs.example.test']);
        Http::fake(fn () => Http::response(['error' => null]));
        $layer = $this->layer('lots', 'lots.esrijson');
        $layer->update(['source_url' => 'https://example.test/arcgis/rest/services/X/FeatureServer/0']);

        try {
            $this->service()->replicate($layer->fresh());
        } catch (RuntimeException) {
        }
        Http::assertSent(fn ($request) => ! $request->hasHeader('Referer'));

        $this->app->instance(ArcGisToken::class, new class extends ArcGisToken
        {
            public function get(): ?string
            {
                return 'SECRETTOKEN';
            }
        });
        Http::fake(fn () => Http::response(['error' => null]));
        try {
            $this->service()->replicate($layer->fresh());
        } catch (RuntimeException) {
        }
        Http::assertSent(fn ($request) => str_contains($request->url(), 'token=SECRETTOKEN')
            && $request->header('Referer') === ['https://indecs.example.test']);
    }

    public function test_legacy_paper_layers_are_left_to_the_legacy_import(): void
    {
        $this->layer('b_good', 'lots.esrijson');
        $legacy = $this->layer('legacy_mjkp', 'lots.esrijson');
        $legacy->update(['kind' => 'legacy_papers']);

        $this->artisan('gis:replicate', ['--all' => true])
            ->expectsOutputToContain('Lapisan b_good: 3 rekod')
            ->doesntExpectOutputToContain('legacy_mjkp')
            ->assertExitCode(0);
        $this->assertNull($legacy->fresh()->active_snapshot_id);

        $this->artisan('gis:replicate', ['layer' => 'legacy_mjkp'])
            ->expectsOutputToContain('Lapisan legacy_mjkp dikemas kini melalui osc:import-legacy --points sahaja.')
            ->assertExitCode(1);
        $this->assertSame(0, GisLayerSnapshot::query()->where('layer_id', 'legacy_mjkp')->count());
    }

    public function test_command_reports_busy_layer_as_failure_and_continues(): void
    {
        $this->layer('a_busy', 'lots.esrijson');
        $this->layer('b_good', 'lots.esrijson');
        GisLayerSnapshot::query()->create(['layer_id' => 'a_busy', 'status' => 'importing', 'started_at' => now()]);

        $this->artisan('gis:replicate', ['--all' => true])
            ->expectsOutputToContain('Kemas kini lapisan a_busy sedang berjalan.')
            ->expectsOutputToContain('Lapisan b_good: 3 rekod')
            ->assertExitCode(1);
    }
}
