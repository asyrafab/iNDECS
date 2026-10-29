<?php

declare(strict_types=1);

namespace Tests\Feature\Gis;

use App\Models\Gis\GisLayer;
use App\Models\Gis\GisLayerSnapshot;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

final class VectorTileTest extends TestCase
{
    use RefreshDatabase;

    private User $secretary;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed();
        $this->secretary = User::factory()->create(['role' => 'osc_secretary', 'department_id' => 'JPBD']);
    }

    /** @return array{0: int, 1: int} */
    private function tileFor(float $lon, float $lat, int $z): array
    {
        $n = 2 ** $z;

        return [
            (int) floor(($lon + 180) / 360 * $n),
            (int) floor((1 - asinh(tan(deg2rad($lat))) / M_PI) / 2 * $n),
        ];
    }

    private function layer(string $id, string $kind = 'reference', bool $active = true): GisLayer
    {
        return GisLayer::query()->updateOrCreate(['id' => $id], [
            'label' => $id, 'kind' => $kind, 'source_url' => 'https://example.test/'.$id, 'is_active' => $active,
        ]);
    }

    private function snapshot(GisLayer $layer, string $status = 'active'): GisLayerSnapshot
    {
        return GisLayerSnapshot::query()->create(['layer_id' => $layer->id, 'status' => $status, 'feature_count' => 1]);
    }

    private function feature(GisLayerSnapshot $snapshot, float $lon, float $lat, array $attributes = []): void
    {
        $wkt = sprintf('POLYGON((%1$F %2$F,%3$F %2$F,%3$F %4$F,%1$F %4$F,%1$F %2$F))', $lon, $lat, $lon + 0.0005, $lat + 0.0005);
        DB::insert(
            'INSERT INTO core.gis_features (snapshot_id, attributes, geom, geom_web)
             VALUES (?, ?::jsonb, ST_GeomFromText(?, 4326), ST_Transform(ST_GeomFromText(?, 4326), 3857))',
            [$snapshot->id, json_encode($attributes), $wkt, $wkt],
        );
    }

    public function test_tile_contains_features_of_active_snapshot_only(): void
    {
        $layer = $this->layer('zoning');
        $old = $this->snapshot($layer, 'superseded');
        $this->feature($old, 100.30, 5.30);
        $active = $this->snapshot($layer);
        $this->feature($active, 100.40, 5.40, ['lot' => '1', 'owner' => 'Secret Name']);
        $layer->update(['active_snapshot_id' => $active->id]);

        [$x, $y] = $this->tileFor(100.4002, 5.4002, 16);
        $response = $this->actingAs($this->secretary)->get("/gis/tiles/zoning/16/$x/$y.pbf");
        $response->assertOk();
        $this->assertStringStartsWith('application/vnd.mapbox-vector-tile', $response->headers->get('Content-Type'));
        $this->assertStringContainsString('private', $response->headers->get('Cache-Control'));
        $this->assertStringContainsString('max-age=86400', $response->headers->get('Cache-Control'));
        $this->assertSame('"'.$active->id.'"', $response->headers->get('ETag'));
        $body = $response->baseResponse->getContent();
        $this->assertNotSame('', $body);
        $this->assertStringContainsString('zoning', $body);
        $this->assertStringNotContainsString('Secret Name', $body);
        $this->assertStringNotContainsString('owner', $body);

        [$ox, $oy] = $this->tileFor(100.3002, 5.3002, 16);
        $superseded = $this->actingAs($this->secretary)->get("/gis/tiles/zoning/16/$ox/$oy.pbf");
        $superseded->assertOk();
        $this->assertSame('', $superseded->baseResponse->getContent());
    }

    public function test_only_whitelisted_properties_are_encoded(): void
    {
        config(['gis.layers.zoning.properties' => ['lot']]);
        $layer = $this->layer('zoning');
        $active = $this->snapshot($layer);
        $this->feature($active, 100.40, 5.40, ['lot' => 'LOT-777', 'owner' => 'Secret Name']);
        $layer->update(['active_snapshot_id' => $active->id]);

        [$x, $y] = $this->tileFor(100.4002, 5.4002, 16);
        $body = $this->actingAs($this->secretary)->get("/gis/tiles/zoning/16/$x/$y.pbf")->baseResponse->getContent();
        $this->assertStringContainsString('LOT-777', $body);
        $this->assertStringNotContainsString('Secret Name', $body);
    }

    public function test_zoning_tiles_carry_the_zone_class_only(): void
    {
        $this->assertSame(['gunatanah1', 'gunatanah3', 'kod_gtn'], config('gis.layers.zoning.properties'));
        $layer = $this->layer('zoning');
        $active = $this->snapshot($layer);
        $this->feature($active, 100.40, 5.40, [
            'gunatanah1' => 'Perumahan', 'gunatanah3' => 'Kampung Tradisi', 'kod_gtn' => 'TRM304',
            'nama' => 'Nama Pemilik', 'upi' => '07011500055',
        ]);
        $layer->update(['active_snapshot_id' => $active->id]);

        [$x, $y] = $this->tileFor(100.4002, 5.4002, 16);
        $body = $this->actingAs($this->secretary)->get("/gis/tiles/zoning/16/$x/$y.pbf")->baseResponse->getContent();
        foreach (['gunatanah1', 'Perumahan', 'Kampung Tradisi', 'TRM304'] as $expected) {
            $this->assertStringContainsString($expected, $body);
        }
        $this->assertStringNotContainsString('Nama Pemilik', $body);
        $this->assertStringNotContainsString('07011500055', $body);
    }

    public function test_matching_etag_returns_304(): void
    {
        $layer = $this->layer('zoning');
        $active = $this->snapshot($layer);
        $this->feature($active, 100.40, 5.40);
        $layer->update(['active_snapshot_id' => $active->id]);

        [$x, $y] = $this->tileFor(100.4002, 5.4002, 16);
        $this->actingAs($this->secretary)->get("/gis/tiles/zoning/16/$x/$y.pbf", ['If-None-Match' => '"'.$active->id.'"'])
            ->assertStatus(304);
    }

    public function test_unknown_or_legacy_layer_is_404(): void
    {
        $this->actingAs($this->secretary)->get('/gis/tiles/nope/16/1/1.pbf')->assertNotFound();

        $legacy = $this->layer('legacy_mjkp', 'legacy_papers');
        $snapshot = $this->snapshot($legacy);
        $legacy->update(['active_snapshot_id' => $snapshot->id]);
        $this->actingAs($this->secretary)->get('/gis/tiles/legacy_mjkp/16/1/1.pbf')->assertNotFound();

        $inactive = $this->layer('utilities', 'reference', false);
        $snap = $this->snapshot($inactive);
        $inactive->update(['active_snapshot_id' => $snap->id]);
        $this->actingAs($this->secretary)->get('/gis/tiles/utilities/16/1/1.pbf')->assertNotFound();

        $noSnapshot = $this->layer('lots');
        $this->assertNull($noSnapshot->active_snapshot_id);
        $this->actingAs($this->secretary)->get('/gis/tiles/lots/16/1/1.pbf')->assertNotFound();

        $this->actingAs($this->secretary)->get('/gis/tiles/lots/23/1/1.pbf')->assertNotFound();
        $this->actingAs($this->secretary)->get('/gis/tiles/lots/2/9/1.pbf')->assertNotFound();
    }

    public function test_low_zoom_and_oversized_coordinates_are_404(): void
    {
        $layer = $this->layer('lots');
        $active = $this->snapshot($layer);
        $this->feature($active, 100.40, 5.40);
        $layer->update(['active_snapshot_id' => $active->id]);

        [$x, $y] = $this->tileFor(100.4002, 5.4002, 11);
        $this->actingAs($this->secretary)->get("/gis/tiles/lots/11/$x/$y.pbf")->assertNotFound();
        $this->actingAs($this->secretary)->get('/gis/tiles/lots/0/0/0.pbf')->assertNotFound();
        [$x, $y] = $this->tileFor(100.4002, 5.4002, 12);
        $this->actingAs($this->secretary)->get("/gis/tiles/lots/12/$x/$y.pbf")->assertOk();

        $this->actingAs($this->secretary)->get('/gis/tiles/lots/16/12345678/1.pbf')->assertNotFound();
        $this->actingAs($this->secretary)->get('/gis/tiles/lots/16/1/12345678.pbf')->assertNotFound();
        $this->actingAs($this->secretary)->get('/gis/tiles/lots/16/'.str_repeat('9', 40).'/1.pbf')->assertNotFound();
    }

    public function test_tiles_are_throttled_per_user(): void
    {
        $route = app('router')->getRoutes()->getByName('gis.tiles');
        $this->assertContains('throttle:600,1', $route->gatherMiddleware());
    }

    public function test_tiles_require_osc_access(): void
    {
        $layer = $this->layer('zoning');
        $active = $this->snapshot($layer);
        $layer->update(['active_snapshot_id' => $active->id]);

        $this->get('/gis/tiles/zoning/16/1/1.pbf')->assertRedirect();
        $stranger = User::factory()->create(['role' => 'intake_officer', 'department_id' => 'JBANG']);
        $this->actingAs($stranger)->get('/gis/tiles/zoning/16/1/1.pbf')->assertForbidden();
    }
}
