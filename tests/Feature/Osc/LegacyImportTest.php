<?php

declare(strict_types=1);

namespace Tests\Feature\Osc;

use App\Models\Gis\GisLayer;
use App\Models\Osc\LayoutImage;
use App\Models\Osc\LegacyPaper;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

final class LegacyImportTest extends TestCase
{
    use RefreshDatabase;

    private ?array $pgConfig = null;

    protected function setUp(): void
    {
        parent::setUp();

        if (! env('GIS_OGR2OGR')) {
            $this->markTestSkipped('ogr2ogr tidak dikonfigurasi');
        }
        $this->dropCommittedStagingTables();
        Storage::fake('local');

        GisLayer::query()->create([
            'id' => 'legacy_mjkp',
            'label' => 'Keputusan terdahulu',
            'kind' => 'legacy_papers',
            'source_url' => 'file://tests/Fixtures/gis/mjkp.esrijson',
            'where_clause' => '1=1',
            'is_active' => true,
            'sort_order' => 90,
        ]);
    }

    protected function tearDown(): void
    {
        parent::tearDown();
        $this->dropCommittedStagingTables();
    }

    private function dropCommittedStagingTables(): void
    {
        $this->pgConfig ??= config('database.connections.pgsql');
        $c = $this->pgConfig;
        $pdo = new \PDO(sprintf('pgsql:host=%s;port=%s;dbname=%s', $c['host'], $c['port'], $c['database']), $c['username'], $c['password']);
        foreach ($pdo->query("SELECT tablename FROM pg_tables WHERE schemaname = 'core' AND tablename LIKE 'gis_import_%'")->fetchAll(\PDO::FETCH_COLUMN) as $table) {
            $pdo->exec('DROP TABLE IF EXISTS core."'.$table.'"');
        }
    }

    private function dir(): string
    {
        return base_path('tests/Fixtures/gis/legacy');
    }

    public function test_points_import_is_idempotent(): void
    {
        $this->artisan('osc:import-legacy', ['--points' => true])->assertSuccessful();
        $this->assertSame(5, LegacyPaper::query()->count());

        $paper = LegacyPaper::query()->find(101);
        $this->assertSame('Cadangan A', $paper->title);
        $this->assertSame('L', $paper->status_code);
        $this->assertSame('2015', $paper->year);
        $this->assertSame('Mukim 1', $paper->mukim);
        $this->assertSame('F/1', $paper->no_fail);
        $this->assertSame('t1.jpg', $paper->plan_file);
        $point = DB::selectOne('SELECT ST_SRID(geom) AS s, ST_X(geom) AS x, ST_Y(geom) AS y FROM core.osc_legacy_papers WHERE id = 101');
        $this->assertSame(4326, $point->s);
        $this->assertEqualsWithDelta(100.40, $point->x, 1e-6);
        // The point without usable XY keeps its record but has no geometry.
        $this->assertNull(DB::selectOne('SELECT geom FROM core.osc_legacy_papers WHERE id = 105')->geom);

        DB::table('core.osc_legacy_papers')->where('id', 101)->update(['title' => 'Lama']);
        $this->artisan('osc:import-legacy', ['--points' => true])->assertSuccessful();

        $this->assertSame(5, LegacyPaper::query()->count());
        $this->assertSame('Cadangan A', LegacyPaper::query()->find(101)->title);
    }

    public function test_images_import_places_and_skips_with_reasons(): void
    {
        $this->artisan('osc:import-legacy', ['--points' => true])->assertSuccessful();

        $this->artisan('osc:import-legacy', ['--images' => true, '--dir' => $this->dir()])
            ->expectsTable(['Hasil', 'Bilangan'], [
                ['Diletakkan', 1],
                ['Dilangkau: tiada XY', 1],
                ['Dilangkau: tiada fail', 1],
                ['Dilangkau: tiada world file', 1],
                ['Dilangkau: format TIFF belum disokong', 1],
                ['Dilangkau: di luar kawasan', 0],
            ])
            ->assertSuccessful();

        $this->assertSame(1, LayoutImage::query()->count());
        $image = LayoutImage::query()->first();
        $this->assertSame('legacy', $image->owner_type);
        $this->assertSame('101', $image->owner_id);
        $this->assertSame(40, $image->width);
        $this->assertSame(20, $image->height);
        $this->assertSame(990001, $image->source_srid);
        $this->assertSame('image/jpeg', $image->mime_type);
        $this->assertSame('osc/layouts/legacy/'.$image->sha256.'.jpg', $image->path);
        Storage::disk('local')->assertExists($image->path);

        $row = DB::selectOne(
            'SELECT ST_SRID(corners) AS srid, ST_NPoints(corners) AS n, ST_Intersects(corners, ST_MakeEnvelope(?, ?, ?, ?, 4326)) AS inside FROM core.osc_layout_images',
            config('gis.region_bbox'),
        );
        $this->assertSame(4326, $row->srid);
        $this->assertSame(5, $row->n);
        $this->assertTrue($row->inside);

        $this->artisan('osc:import-legacy', ['--images' => true, '--dir' => $this->dir()])->assertSuccessful();
        $this->assertSame(1, LayoutImage::query()->count());
    }
}
