<?php

declare(strict_types=1);

namespace Tests\Feature\Gis;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Tests\TestCase;

final class GisFoundationTest extends TestCase
{
    use RefreshDatabase;

    public function test_postgis_tables_and_srids_exist(): void
    {
        $this->assertNotEmpty(DB::select("SELECT 1 FROM pg_extension WHERE extname = 'postgis'"));

        foreach (['gis_layers', 'gis_layer_snapshots', 'gis_features', 'osc_legacy_papers', 'osc_layout_images'] as $table) {
            $this->assertTrue(Schema::hasTable("core.$table"), "core.$table is missing");
        }
        $this->assertTrue(Schema::hasColumn('sites', 'boundary'));
        $this->assertTrue(Schema::hasColumn('core.osc_agenda_items', 'boundary'));
    }

    public function test_srid_990001_matches_arcgis_within_one_metre(): void
    {
        $fixture = json_decode(file_get_contents(base_path('tests/Fixtures/gis/srid-990001-check.json')), true);

        $row = DB::selectOne(
            'SELECT ST_Distance(
                ST_Transform(ST_SetSRID(ST_Point(?, ?), 990001), 4326)::geography,
                ST_SetSRID(ST_Point(?, ?), 4326)::geography
            ) AS metres',
            [$fixture['native']['x'], $fixture['native']['y'], $fixture['wgs84']['lon'], $fixture['wgs84']['lat']],
        );

        $this->assertLessThan(1.0, (float) $row->metres);
    }

    public function test_srid_990002_is_registered(): void
    {
        $this->assertNotEmpty(DB::select('SELECT 1 FROM spatial_ref_sys WHERE srid = 990002'));
    }

    public function test_config_exposes_region_box_and_imagery(): void
    {
        $this->assertSame([100.33, 5.10, 100.60, 5.62], config('gis.region_bbox'));
        $this->assertCount(3, config('gis.imagery'));
        foreach (config('gis.imagery') as $entry) {
            $this->assertSame(['label', 'url', 'attribution'], array_keys($entry));
        }
    }
}
