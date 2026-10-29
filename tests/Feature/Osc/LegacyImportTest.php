<?php

declare(strict_types=1);

namespace Tests\Feature\Osc;

use App\Models\Gis\GisLayer;
use App\Models\Osc\LayoutImage;
use App\Models\Osc\LegacyPaper;
use App\Models\User;
use App\Services\Osc\LegacyImportService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
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
        foreach ($this->tmpDirs as $d) {
            array_map('unlink', glob($d.DIRECTORY_SEPARATOR.'*') ?: []);
            @rmdir($d);
        }
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
        $this->assertEqualsWithDelta(100.46021, $point->x, 1e-6);
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
                ['Dilangkau: kedudukan tidak sepadan', 0],
                ['Dilangkau: fail dikongsi', 0],
                ['Dilangkau: fail rosak', 0],
                ['Dilangkau: diletak secara manual', 0],
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

    /** Builds a temp plan folder: name => fixture file to copy, or raw content when not a fixture name. */
    private function plans(array $files): string
    {
        $dir = sys_get_temp_dir().DIRECTORY_SEPARATOR.'plans_'.bin2hex(random_bytes(4));
        mkdir($dir);
        foreach ($files as $name => $source) {
            $fixture = $this->dir().DIRECTORY_SEPARATOR.$source;
            file_put_contents($dir.DIRECTORY_SEPARATOR.$name, is_file($fixture) ? file_get_contents($fixture) : $source);
        }
        $this->tmpDirs[] = $dir;

        return $dir;
    }

    private array $tmpDirs = [];

    /** @return array<string, int> */
    private function runImages(string $dir, array $plan): array
    {
        $this->artisan('osc:import-legacy', ['--points' => true])->assertSuccessful();
        DB::table('core.osc_legacy_papers')->update(['plan_file' => null]);
        DB::table('core.osc_legacy_papers')->update(['pelan_tapak_scan' => null]);
        foreach ($plan as $id => $file) {
            [$scan, $planFile] = is_array($file) ? $file : [null, $file];
            DB::table('core.osc_legacy_papers')->where('id', $id)->update(['plan_file' => $planFile, 'pelan_tapak_scan' => $scan]);
        }

        return app(LegacyImportService::class)->importImages($dir, 990001);
    }

    public function test_pelan_tapak_scan_is_preferred_and_plan_is_the_fallback(): void
    {
        $dir = $this->plans(['scan.jpg' => 't1.jpg', 'scan.jgw' => 't1.jgw', 'plan.jpg' => 't1.jpg', 'plan.jgw' => 't1.jgw']);
        $this->runImages($dir, [101 => ['scan.jpg', 'plan.jpg']]);
        $this->assertSame(1, LayoutImage::query()->count());
        $this->assertSame(hash('sha256', file_get_contents($dir.'/scan.jpg')), LayoutImage::query()->first()->sha256);

        // Blank scan field: the plan_ file is used.
        $dir = $this->plans(['plan.jpg' => 't1.jpg', 'plan.jgw' => 't1.jgw']);
        LayoutImage::query()->delete();
        $counts = $this->runImages($dir, [101 => [null, 'plan.jpg']]);
        $this->assertSame(1, $counts['Diletakkan']);

        // Scan is a TIFF and plan_ has a world file: plan_ places.
        LayoutImage::query()->delete();
        $counts = $this->runImages($dir, [101 => ['Rectify1.tif', 'plan.jpg']]);
        $this->assertSame(1, $counts['Diletakkan']);
    }

    public function test_underscore_suffix_is_never_guessed(): void
    {
        $dir = $this->plans(['t1.jpg' => 't1.jpg', 't1.jgw' => 't1.jgw']);
        $counts = $this->runImages($dir, [101 => 'REF@1_t1.jpg']);

        $this->assertSame(0, $counts['Diletakkan']);
        $this->assertSame(1, $counts['Dilangkau: tiada fail']);

        $dir = $this->plans(['t1.jpg' => 't1.jpg', 't1.jgw' => 't1.jgw', 'REF@1_t1.jpg' => 't1.jpg']);
        $counts = app(LegacyImportService::class)->importImages($dir, 990001);
        $this->assertSame(1, $counts['Dilangkau: tiada world file']);
        $this->assertSame(0, LayoutImage::query()->count());
    }

    public function test_file_claimed_by_two_papers_is_placed_for_neither(): void
    {
        $dir = $this->plans(['gen.jpg' => 't1.jpg', 'gen.jgw' => 't1.jgw']);
        $counts = $this->runImages($dir, [101 => 'gen.jpg', 102 => 'GEN.jpg']);

        $this->assertSame(2, $counts['Dilangkau: fail dikongsi']);
        $this->assertSame(0, LayoutImage::query()->count());
    }

    public function test_shared_file_is_placed_for_all_claimants_when_every_point_is_inside(): void
    {
        $dir = $this->plans(['gen.jpg' => 't1.jpg', 'gen.jgw' => 't1.jgw']);
        $this->artisan('osc:import-legacy', ['--points' => true])->assertSuccessful();
        DB::table('core.osc_legacy_papers')->update(['plan_file' => null]);
        DB::table('core.osc_legacy_papers')->whereIn('id', [101, 102])->update(['plan_file' => 'gen.jpg']);
        DB::statement('UPDATE core.osc_legacy_papers SET geom = (SELECT geom FROM core.osc_legacy_papers WHERE id = 101) WHERE id = 102');

        $counts = app(LegacyImportService::class)->importImages($dir, 990001);

        $this->assertSame(2, $counts['Diletakkan']);
        $this->assertSame(0, $counts['Dilangkau: fail dikongsi']);
        $this->assertSame(2, LayoutImage::query()->count());
        $this->assertSame(1, LayoutImage::query()->distinct()->count('sha256'));
        $this->assertSame(1, LayoutImage::query()->distinct()->count('path'));

        // A rerun after one point moves 2 km away removes both placements.
        DB::statement('UPDATE core.osc_legacy_papers SET geom = ST_SetSRID(ST_MakePoint(100.46021 + 0.02, 5.33736), 4326) WHERE id = 102');
        $counts = app(LegacyImportService::class)->importImages($dir, 990001);
        $this->assertSame(2, $counts['Dilangkau: fail dikongsi']);
        $this->assertSame(0, LayoutImage::query()->count());
    }

    public function test_shared_file_with_a_claimant_without_a_point_is_placed_for_none(): void
    {
        $dir = $this->plans(['gen.jpg' => 't1.jpg', 'gen.jgw' => 't1.jgw']);
        $this->artisan('osc:import-legacy', ['--points' => true])->assertSuccessful();
        DB::table('core.osc_legacy_papers')->update(['plan_file' => null]);
        DB::table('core.osc_legacy_papers')->whereIn('id', [101, 102])->update(['plan_file' => 'gen.jpg']);
        DB::statement('UPDATE core.osc_legacy_papers SET geom = NULL WHERE id = 102');

        $counts = app(LegacyImportService::class)->importImages($dir, 990001);

        $this->assertSame(0, $counts['Diletakkan']);
        $this->assertSame(1, $counts['Dilangkau: fail dikongsi']);
        $this->assertSame(1, $counts['Dilangkau: tiada XY']);
        $this->assertSame(0, LayoutImage::query()->count());
    }

    public function test_placement_far_from_the_papers_point_is_skipped(): void
    {
        $dir = $this->plans(['t1.jpg' => 't1.jpg', 't1.jgw' => 't1.jgw']);
        $this->artisan('osc:import-legacy', ['--points' => true])->assertSuccessful();
        DB::table('core.osc_legacy_papers')->update(['plan_file' => null]);
        DB::table('core.osc_legacy_papers')->where('id', 101)->update(['plan_file' => 't1.jpg']);
        $service = app(LegacyImportService::class);

        $this->assertSame(1, $service->importImages($dir, 990001)['Diletakkan']);
        $this->assertSame(1, LayoutImage::query()->count());
        $path = LayoutImage::query()->first()->path;

        // Point about 150 m away: a rerun converges by removing the row and keeping the stored file.
        DB::statement('UPDATE core.osc_legacy_papers SET geom = ST_SetSRID(ST_MakePoint(100.46021 + 0.0014, 5.33736), 4326) WHERE id = 101');
        $counts = $service->importImages($dir, 990001);

        $this->assertSame(1, $counts['Dilangkau: kedudukan tidak sepadan']);
        $this->assertSame(0, LayoutImage::query()->count());
        Storage::disk('local')->assertExists($path);
    }

    public function test_upper_case_names_resolve(): void
    {
        $dir = $this->plans(['T1.JPG' => 't1.jpg', 'T1.JGW' => 't1.jgw']);
        $counts = $this->runImages($dir, [101 => 't1.jpg']);

        $this->assertSame(1, $counts['Diletakkan']);
    }

    public function test_unreadable_image_is_counted_as_broken(): void
    {
        $dir = $this->plans(['t1.jpg' => 'not an image', 't1.jgw' => 't1.jgw']);
        $counts = $this->runImages($dir, [101 => 't1.jpg']);

        $this->assertSame(1, $counts['Dilangkau: fail rosak']);
        $this->assertSame(0, LayoutImage::query()->count());
    }

    /** Inserts a layout row directly; corners are a small square around the fixture point. */
    private function layoutRow(string $ownerType, string $ownerId, ?int $placedBy = null): string
    {
        $id = (string) Str::ulid();
        DB::insert(
            "INSERT INTO core.osc_layout_images (id, owner_type, owner_id, path, mime_type, width, height, sha256, corners, source_srid, placed_by, row_version, created_at, updated_at)
             VALUES (?, ?, ?, 'osc/layouts/manual/x.jpg', 'image/jpeg', 10, 10, ?, ST_MakeEnvelope(100.46, 5.337, 100.461, 5.338, 4326), NULL, ?, 1, now(), now())",
            [$id, $ownerType, $ownerId, str_repeat('a', 64), $placedBy],
        );

        return $id;
    }

    /** @return array<int, object> */
    private function layoutSnapshot(): array
    {
        return DB::select('SELECT id, owner_type, owner_id, sha256, row_version, placed_by, ST_AsText(corners) AS wkt FROM core.osc_layout_images ORDER BY id');
    }

    private function placeT1(string $dir): void
    {
        $this->runImages($dir, [101 => 't1.jpg']);
        $this->assertSame(1, LayoutImage::query()->where('owner_type', 'legacy')->count());
    }

    public function test_missing_directory_aborts_before_any_write(): void
    {
        $dir = $this->plans(['t1.jpg' => 't1.jpg', 't1.jgw' => 't1.jgw']);
        $this->placeT1($dir);
        $this->layoutRow('agenda_item', 'A1');
        $before = $this->layoutSnapshot();
        DB::table('core.osc_legacy_papers')->where('id', 101)->update(['title' => 'Lama']);
        $missing = $dir.DIRECTORY_SEPARATOR.'tiada';

        $this->artisan('osc:import-legacy', ['--points' => true, '--images' => true, '--dir' => $missing])
            ->expectsOutputToContain('Direktori pelan tidak ditemui: '.$missing)
            ->assertExitCode(1);

        $this->assertEquals($before, $this->layoutSnapshot());
        // Points were not imported either: the check runs before any write.
        $this->assertSame('Lama', LegacyPaper::query()->find(101)->title);
    }

    public function test_empty_directory_aborts_before_any_write(): void
    {
        $dir = $this->plans(['t1.jpg' => 't1.jpg', 't1.jgw' => 't1.jgw']);
        $this->placeT1($dir);
        $before = $this->layoutSnapshot();
        $empty = $this->plans([]);

        $this->artisan('osc:import-legacy', ['--images' => true, '--dir' => $empty])
            ->expectsOutputToContain('Direktori pelan kosong: '.$empty)
            ->assertExitCode(1);

        $this->assertEquals($before, $this->layoutSnapshot());
    }

    public function test_placement_survives_a_rerun_while_its_file_is_missing(): void
    {
        $dir = $this->plans(['t1.jpg' => 't1.jpg', 't1.jgw' => 't1.jgw', 'other.txt' => 'x']);
        $this->placeT1($dir);
        $before = $this->layoutSnapshot();
        unlink($dir.DIRECTORY_SEPARATOR.'t1.jpg');

        $counts = app(LegacyImportService::class)->importImages($dir, 990001);

        $this->assertSame(1, $counts['Dilangkau: tiada fail']);
        $this->assertEquals($before, $this->layoutSnapshot());

        // A point that disappears is also not a reason to drop the placement.
        DB::statement('UPDATE core.osc_legacy_papers SET geom = NULL WHERE id = 101');
        $counts = app(LegacyImportService::class)->importImages($dir, 990001);
        $this->assertSame(1, $counts['Dilangkau: tiada XY']);
        $this->assertEquals($before, $this->layoutSnapshot());
    }

    public function test_hand_placed_row_is_never_overwritten_or_removed(): void
    {
        $user = User::factory()->create();
        $dir = $this->plans(['t1.jpg' => 't1.jpg', 't1.jgw' => 't1.jgw']);
        $this->artisan('osc:import-legacy', ['--points' => true])->assertSuccessful();
        $this->layoutRow('legacy', '101', $user->id);
        $before = $this->layoutSnapshot();

        $counts = $this->runImages($dir, [101 => 't1.jpg']);

        $this->assertSame(0, $counts['Diletakkan']);
        $this->assertSame(1, $counts['Dilangkau: diletak secara manual']);
        $this->assertEquals($before, $this->layoutSnapshot());

        // A content failure (point 150 m away) and cleared names leave it alone too.
        DB::statement('UPDATE core.osc_legacy_papers SET geom = ST_SetSRID(ST_MakePoint(100.46021 + 0.0014, 5.33736), 4326) WHERE id = 101');
        $this->assertSame(1, app(LegacyImportService::class)->importImages($dir, 990001)['Dilangkau: diletak secara manual']);
        DB::table('core.osc_legacy_papers')->where('id', 101)->update(['plan_file' => null]);
        app(LegacyImportService::class)->importImages($dir, 990001);
        $this->assertEquals($before, $this->layoutSnapshot());
    }

    public function test_paper_whose_names_were_cleared_loses_its_imported_row_only(): void
    {
        $dir = $this->plans(['t1.jpg' => 't1.jpg', 't1.jgw' => 't1.jgw']);
        $this->placeT1($dir);
        $this->layoutRow('agenda_item', '101');
        $this->layoutRow('application', '101');
        $others = DB::select("SELECT id, row_version FROM core.osc_layout_images WHERE owner_type <> 'legacy' ORDER BY id");

        DB::table('core.osc_legacy_papers')->where('id', 101)->update(['plan_file' => null, 'pelan_tapak_scan' => null]);
        app(LegacyImportService::class)->importImages($dir, 990001);

        $this->assertSame(0, LayoutImage::query()->where('owner_type', 'legacy')->count());
        $this->assertEquals($others, DB::select("SELECT id, row_version FROM core.osc_layout_images WHERE owner_type <> 'legacy' ORDER BY id"));
    }
}
