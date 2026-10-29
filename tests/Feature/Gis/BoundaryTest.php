<?php

declare(strict_types=1);

namespace Tests\Feature\Gis;

use App\Enums\Osc\TablingPurpose;
use App\Models\Application;
use App\Models\Osc\AgendaItem;
use App\Models\User;
use App\Services\Osc\MeetingService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use PHPUnit\Framework\AssertionFailedError;
use Tests\Support\TestDocumentCatalogues;
use Tests\TestCase;

final class BoundaryTest extends TestCase
{
    use RefreshDatabase;

    private User $officer;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed();
        $this->officer = User::factory()->create(['department_id' => 'JBANG', 'role' => 'building_officer']);
        TestDocumentCatalogues::seed($this->officer);
        $this->actingAs($this->officer);
    }

    private function square(float $lon = 100.40, float $lat = 5.40, float $size = 0.001): string
    {
        return json_encode(['type' => 'Polygon', 'coordinates' => [[
            [$lon, $lat], [$lon + $size, $lat], [$lon + $size, $lat + $size], [$lon, $lat + $size], [$lon, $lat],
        ]]]);
    }

    private function bowTie(): string
    {
        return json_encode(['type' => 'Polygon', 'coordinates' => [[
            [100.40, 5.40], [100.401, 5.401], [100.401, 5.40], [100.40, 5.401], [100.40, 5.40],
        ]]]);
    }

    /** @return array<string, mixed> */
    private function payload(?string $boundary = null): array
    {
        $site = ['development_address' => 'Jalan Ujian', 'district' => 'Seberang Perai Tengah', 'mukim' => 'Mukim 11'];
        if ($boundary !== null) {
            $site['boundary'] = $boundary;
        }

        return [
            'application_type_id' => 'PB', 'application_title' => 'Cadangan bangunan pejabat',
            'submission_date' => '2026-10-01', 'external_reference' => 'OSC/2026/001', 'priority' => 'normal',
            'applicant' => ['party_type' => 'organisation', 'name' => 'Pemohon Ujian', 'registration_number' => 'TEST-001'],
            'site' => $site,
            'lots' => [['lot_number' => '101']],
            'bangunan' => ['building_category' => 'Komersial', 'building_use' => 'Pejabat'],
        ];
    }

    private function registered(): Application
    {
        $this->post(route('bangunan.store'), $this->payload())->assertSessionHasNoErrors();

        return Application::query()->firstOrFail();
    }

    public function test_registration_stores_boundary_as_multipolygon_4326(): void
    {
        $this->post(route('bangunan.store'), $this->payload($this->square()))->assertSessionHasNoErrors()->assertRedirect();
        $row = DB::selectOne('SELECT ST_GeometryType(boundary) AS type, ST_SRID(boundary) AS srid, ST_Area(boundary::geography) AS area FROM sites');
        $this->assertSame('ST_MultiPolygon', $row->type);
        $this->assertSame(4326, $row->srid);
        $this->assertEqualsWithDelta(12300, $row->area, 800);
    }

    public function test_registration_without_boundary_still_works(): void
    {
        $this->post(route('bangunan.store'), $this->payload())->assertSessionHasNoErrors();
        $this->assertNull(DB::selectOne('SELECT boundary FROM sites')->boundary);
    }

    public function test_pkm_registration_stores_boundary(): void
    {
        $planner = User::factory()->create(['department_id' => 'JPBD', 'role' => 'planning_officer']);
        $payload = [
            'application_title' => 'Cadangan Perumahan', 'external_reference' => 'OSC/2026/0142', 'submission_date' => '2026-07-14', 'priority' => 'normal',
            'applicant' => ['party_type' => 'organisation', 'name' => 'Pemaju Sdn. Bhd.', 'registration_number' => '2026'],
            'site' => ['development_address' => 'Lot 101', 'district' => 'Seberang Perai Tengah', 'mukim' => 'Mukim 6', 'state' => 'Pulau Pinang', 'site_area' => '4.25', 'site_area_unit' => 'hectare', 'boundary' => $this->square()],
            'lots' => [['lot_number' => 'Lot 101']],
            'pkm' => ['permission_category' => 'Pembangunan Baharu', 'permission_type' => 'Kebenaran Merancang Penuh', 'existing_land_use' => 'Kosong', 'proposed_land_use' => 'Perumahan'],
        ];
        $this->actingAs($planner)->post(route('perancang.store'), $payload)->assertSessionHasNoErrors()->assertRedirect();
        $this->assertSame('ST_MultiPolygon', DB::selectOne('SELECT ST_GeometryType(boundary) AS type FROM sites')->type);
    }

    public function test_self_intersecting_boundary_rejected_and_nothing_stored(): void
    {
        $this->post(route('bangunan.store'), $this->payload($this->bowTie()))->assertSessionHasErrors(['site.boundary' => 'Sempadan bertindih sendiri.']);
        $this->assertSame(0, Application::query()->count());
        $this->assertSame(0, DB::table('sites')->count());
    }

    public function test_line_or_empty_or_tiny_boundary_rejected(): void
    {
        $bad = [
            'line' => json_encode(['type' => 'LineString', 'coordinates' => [[100.4, 5.4], [100.401, 5.401]]]),
            'empty' => json_encode(['type' => 'Polygon', 'coordinates' => []]),
            'tiny' => $this->square(100.4, 5.4, 0.0000001),
            'garbage' => 'not json',
            'twopoints' => json_encode(['type' => 'Polygon', 'coordinates' => [[[100.4, 5.4], [100.401, 5.4]]]]),
            'strings' => json_encode(['type' => 'Polygon', 'coordinates' => [[['a', 'b'], ['c', 'd'], ['e', 'f'], ['a', 'b']]]]),
        ];
        foreach ($bad as $name => $geojson) {
            try {
                $this->post(route('bangunan.store'), $this->payload($geojson))->assertSessionHasErrors(['site.boundary' => 'Sempadan tidak sah.']);
            } catch (AssertionFailedError $e) {
                $this->fail($name.': '.$e->getMessage());
            }
        }
        $this->assertSame(0, Application::query()->count());
    }

    public function test_oversized_boundary_rejected(): void
    {
        $ring = [];
        for ($i = 0; $i < 10001; $i++) {
            $ring[] = [100.40 + $i * 1e-8, 5.40 + ($i % 2) * 1e-5];
        }
        $ring[] = $ring[0];
        $geojson = json_encode(['type' => 'Polygon', 'coordinates' => [$ring]]);
        $this->post(route('bangunan.store'), $this->payload($geojson))->assertSessionHasErrors(['site.boundary' => 'Sempadan tidak sah.']);
        $this->post(route('bangunan.store'), $this->payload(str_replace('"coordinates"', str_repeat(' ', 1048577).'"coordinates"', $this->square())))->assertSessionHasErrors(['site.boundary' => 'Sempadan tidak sah.']);
    }

    public function test_boundary_outside_mbsp_rejected(): void
    {
        $this->post(route('bangunan.store'), $this->payload($this->square(101.5, 3.1)))
            ->assertSessionHasErrors(['site.boundary' => 'Lokasi berada di luar kawasan MBSP.']);
        $this->assertSame(0, Application::query()->count());
    }

    public function test_existing_application_boundary_set_with_version_and_audit(): void
    {
        $application = $this->registered();
        $version = $application->row_version;

        $this->post(route('gis.applications.boundary', $application), ['boundary' => $this->square(), 'expected_version' => $version])
            ->assertSessionHasNoErrors()->assertRedirect();

        $this->assertSame($version + 1, $application->refresh()->row_version);
        $this->assertSame('ST_MultiPolygon', DB::selectOne('SELECT ST_GeometryType(boundary) AS type FROM sites')->type);
        $audit = DB::table('audit_logs')->where('auditable_id', $application->id)->where('event', 'site_boundary_set')->first();
        $this->assertNotNull($audit);
        $this->assertSame($this->officer->id, $audit->actor_id);
        $this->assertNull(json_decode($audit->before_values, true)['area_m2']);
        $this->assertGreaterThan(1000, json_decode($audit->after_values, true)['area_m2']);

        // A second save with the stale version is refused; with the fresh one it records the previous area.
        $this->post(route('gis.applications.boundary', $application), ['boundary' => $this->square(100.41), 'expected_version' => $version])
            ->assertSessionHasErrors(['boundary' => 'Rekod telah berubah. Muat semula sebelum meneruskan.']);
        $this->post(route('gis.applications.boundary', $application), ['boundary' => $this->square(100.41), 'expected_version' => $version + 1])->assertSessionHasNoErrors();
        $second = DB::table('audit_logs')->where('event', 'site_boundary_set')->orderByDesc('id')->first();
        $this->assertGreaterThan(1000, json_decode($second->before_values, true)['area_m2']);
    }

    public function test_invalid_boundary_on_existing_application_changes_nothing(): void
    {
        $application = $this->registered();
        $this->post(route('gis.applications.boundary', $application), ['boundary' => $this->bowTie(), 'expected_version' => $application->row_version])
            ->assertSessionHasErrors(['boundary' => 'Sempadan bertindih sendiri.']);
        $this->assertSame($application->row_version, $application->refresh()->row_version);
        $this->assertNull(DB::selectOne('SELECT boundary FROM sites')->boundary);
    }

    public function test_officer_outside_scope_cannot_set_boundary(): void
    {
        $application = $this->registered();
        $outsider = User::factory()->create(['department_id' => 'JBANG', 'role' => 'building_officer', 'district' => 'Seberang Perai Utara']);
        $this->actingAs($outsider)->post(route('gis.applications.boundary', $application), ['boundary' => $this->square(), 'expected_version' => $application->row_version])
            ->assertForbidden();
        $planner = User::factory()->create(['department_id' => 'JPBD', 'role' => 'planning_officer']);
        $this->actingAs($planner)->post(route('gis.applications.boundary', $application), ['boundary' => $this->square(), 'expected_version' => $application->row_version])
            ->assertForbidden();
        $this->assertNull(DB::selectOne('SELECT boundary FROM sites')->boundary);
    }

    public function test_forms_and_show_page_render_the_boundary_field(): void
    {
        $this->get(route('bangunan.create'))->assertOk()->assertSee('data-boundary-field', false)->assertSee('name="site[boundary]"', false);
        $application = $this->registered();
        $this->get(route('bangunan.show', $application))->assertOk()
            ->assertSee(route('gis.applications.boundary', $application), false)->assertSee('Simpan sempadan');
    }

    public function test_tiles_are_available_to_registering_officers(): void
    {
        // Reaches the controller (no active layer yet), so 404 rather than 403.
        $this->get('/gis/tiles/lots/16/1/1.pbf')->assertNotFound();
    }

    public function test_standalone_item_boundary_stored(): void
    {
        $secretary = User::factory()->create(['role' => 'osc_secretary', 'department_id' => 'JPBD']);
        $chair = User::factory()->create(['role' => 'senior_building_officer', 'department_id' => 'JBANG']);
        $meeting = app(MeetingService::class)->create([
            'number' => 'Bil. 21/2026', 'scheduled_at' => now()->addWeek(), 'venue' => 'Bilik OSC',
            'proposal_cutoff_at' => now()->addDays(2), 'paper_cutoff_at' => now()->addDays(4),
            'chair_id' => $chair->id, 'secretary_id' => $secretary->id,
        ], $secretary);
        $data = ['purpose' => TablingPurpose::Permohonan->value, 'presenter_id' => $secretary->id, 'standalone_type' => 'Penamaan Jalan & Taman', 'title' => 'Penamaan jalan baharu', 'department_id' => 'JPBD'];

        $this->actingAs($secretary)->post(route('osc.items.store', $meeting), $data + ['boundary' => $this->square()])->assertSessionHasNoErrors();
        $item = AgendaItem::query()->firstOrFail();
        $this->assertSame('ST_MultiPolygon', DB::selectOne('SELECT ST_GeometryType(boundary) AS type FROM core.osc_agenda_items WHERE id = ?', [$item->id])->type);

        $this->post(route('osc.items.store', $meeting), $data + ['boundary' => $this->bowTie()])->assertSessionHasErrors(['boundary' => 'Sempadan bertindih sendiri.']);
        $this->assertSame(1, AgendaItem::query()->count());
    }
}
