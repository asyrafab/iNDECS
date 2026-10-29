<?php

declare(strict_types=1);

namespace Tests\Feature\Osc;

use App\Enums\Osc\TablingPurpose;
use App\Models\Application;
use App\Models\Gis\GisLayer;
use App\Models\Gis\GisLayerSnapshot;
use App\Models\Lot;
use App\Models\Osc\AgendaItem;
use App\Models\Osc\AgendaItemPaper;
use App\Models\Osc\Meeting;
use App\Models\Site;
use App\Models\User;
use App\Services\Osc\AgendaService;
use App\Services\Osc\MeetingService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Notification;
use Tests\TestCase;

final class MeetingMapTest extends TestCase
{
    use RefreshDatabase;

    private User $secretary;

    private User $chair;

    private User $member;

    private Meeting $meeting;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed();
        Notification::fake();
        $this->secretary = User::factory()->create(['role' => 'osc_secretary', 'department_id' => 'JPBD']);
        $this->chair = User::factory()->create(['role' => 'senior_building_officer', 'department_id' => 'JBANG']);
        $this->member = User::factory()->create(['role' => 'intake_officer', 'department_id' => 'JBANG']);
        $this->meeting = app(MeetingService::class)->create([
            'number' => 'Bil. 21/2026', 'scheduled_at' => now()->addWeek(), 'venue' => 'Bilik OSC',
            'proposal_cutoff_at' => now()->addDays(2), 'paper_cutoff_at' => now()->addDays(4),
            'chair_id' => $this->chair->id, 'secretary_id' => $this->secretary->id,
        ], $this->secretary);
    }

    private function square(float $lon, float $lat, float $size = 0.001): string
    {
        return sprintf('MULTIPOLYGON(((%1$F %2$F,%3$F %2$F,%3$F %4$F,%1$F %4$F,%1$F %2$F)))', $lon, $lat, $lon + $size, $lat + $size);
    }

    private function propose(string $title, string $department = 'JBANG', ?string $applicationId = null): AgendaItem
    {
        $data = ['title' => $title, 'reference' => 'REF-'.$title, 'purpose' => TablingPurpose::Permohonan,
            'presenter_id' => $this->secretary->id, 'department_id' => $department];
        if ($applicationId === null) {
            return app(AgendaService::class)->proposeStandalone($this->meeting, $data + ['standalone_type' => 'Penamaan Jalan & Taman'], $this->secretary);
        }

        return AgendaItem::query()->create($data + [
            'meeting_id' => $this->meeting->id, 'application_id' => $applicationId, 'proposed_by' => $this->secretary->id,
            'status' => 'proposed',
        ]);
    }

    private function boundary(AgendaItem $item, string $wkt): void
    {
        DB::update('UPDATE core.osc_agenda_items SET boundary = ST_GeomFromText(?, 4326) WHERE id = ?', [$wkt, $item->id]);
    }

    private function accept(AgendaItem ...$items): void
    {
        foreach ($items as $item) {
            app(AgendaService::class)->accept($item->fresh(), $item->fresh()->row_version, $this->secretary);
        }
        app(AgendaService::class)->publish($this->meeting, $this->meeting->fresh()->row_version, $this->secretary);
    }

    private function application(string $ref = 'OSC/1'): Application
    {
        return Application::query()->create([
            'application_type_id' => 'PKM', 'application_reference' => $ref, 'title' => 'Permohonan '.$ref,
            'submission_date' => '2026-10-01', 'submission_year' => 2026, 'responsible_department_id' => 'JPBD',
            'current_stage' => 'registration', 'current_status' => 'submitted',
        ]);
    }

    private function members(): void
    {
        app(MeetingService::class)->setMembers($this->meeting, [
            'chair_id' => $this->chair->id, 'secretary_id' => $this->secretary->id, 'member_ids' => [$this->member->id],
        ], $this->meeting->row_version, $this->secretary);
        $this->meeting->refresh();
    }

    private function mapData(User $user)
    {
        return $this->actingAs($user)->getJson(route('osc.meetings.map-data', $this->meeting));
    }

    /** @return array<int, array<string, mixed>> */
    private function items(array $json): array
    {
        return array_values(array_filter($json['features'], fn ($f) => ($f['properties']['kind'] ?? null) === 'item'));
    }

    public function test_member_gets_only_published_accepted_items(): void
    {
        $this->members();
        $a = $this->propose('A');
        $b = $this->propose('B');
        $this->boundary($a, $this->square(100.40, 5.40));
        $this->boundary($b, $this->square(100.41, 5.41));
        $this->accept($a);
        app(AgendaService::class)->returnToDepartment($b->fresh(), $b->fresh()->row_version, 'Tidak lengkap', $this->secretary);

        AgendaItemPaper::query()->create([
            'agenda_item_id' => $a->id, 'version' => 1, 'original_name' => 'a.pdf', 'path' => 'x/a.pdf', 'mime_type' => 'application/pdf',
            'size_bytes' => 10, 'sha256' => str_repeat('a', 64), 'uploaded_by' => $this->secretary->id,
        ]);

        $json = $this->mapData($this->member)->assertOk()->json();
        $this->assertSame('FeatureCollection', $json['type']);
        $items = $this->items($json);
        $this->assertCount(1, $items);
        $props = $items[0]['properties'];
        $this->assertSame($a->id, $props['id']);
        $this->assertSame('A', $props['title']);
        $this->assertSame('REF-A', $props['reference']);
        $this->assertSame('Permohonan', $props['purpose']);
        $this->assertSame('JBANG', $props['department_id']);
        $this->assertSame('accepted', $props['status']);
        $this->assertSame(1, $props['position']);
        $this->assertSame(route('osc.papers.show', AgendaItemPaper::query()->first()), $props['paper_url']);
        $this->assertNull($props['layout_url']);
        $this->assertSame('item', $props['geometry_source']);
        $this->assertSame('MultiPolygon', $items[0]['geometry']['type']);
    }

    public function test_user_without_access_to_the_meeting_gets_403(): void
    {
        $this->propose('A');
        $other = User::factory()->create(['role' => 'planning_officer', 'department_id' => 'JPBD']);
        $this->mapData($other)->assertForbidden();
    }

    public function test_endorser_gets_only_own_department_items(): void
    {
        $own = $this->propose('Own', 'JBANG');
        $other = $this->propose('Other', 'JPBD');
        $this->boundary($own, $this->square(100.40, 5.40));
        $this->boundary($other, $this->square(100.41, 5.41));

        $items = $this->items($this->mapData($this->chair)->assertOk()->json());
        $this->assertSame([$own->id], array_column(array_column($items, 'properties'), 'id'));
    }

    public function test_geometry_falls_back_to_site_then_lots(): void
    {
        $layer = GisLayer::query()->updateOrCreate(['id' => 'lots'], [
            'label' => 'Lot', 'kind' => 'reference', 'source_url' => 'https://example.test/lots',
            'match_attributes' => ['lot_number' => 'lot', 'mukim' => 'mukim', 'district' => 'daerah'],
        ]);
        $snapshot = GisLayerSnapshot::query()->create(['layer_id' => 'lots', 'status' => 'active', 'feature_count' => 3]);
        $layer->update(['active_snapshot_id' => $snapshot->id]);
        foreach ([
            [100.50, 5.50, ['lot' => '3899', 'mukim' => 'MUKIM 4', 'daerah' => 'SEBERANG PERAI TENGAH']],
            [100.51, 5.50, ['lot' => '3899', 'mukim' => 'MUKIM 4', 'daerah' => 'SEBERANG PERAI UTARA']],
            [100.52, 5.50, ['lot' => '12', 'mukim' => 'MUKIM 4', 'daerah' => 'SEBERANG PERAI TENGAH']],
        ] as [$lon, $lat, $attrs]) {
            $wkt = $this->square($lon, $lat);
            DB::insert(
                'INSERT INTO core.gis_features (snapshot_id, attributes, geom, geom_web) VALUES (?, ?::jsonb, ST_GeomFromText(?, 4326), ST_Transform(ST_GeomFromText(?, 4326), 3857))',
                [$snapshot->id, json_encode($attrs), $wkt, $wkt],
            );
        }

        $withBoundary = $this->propose('Sempadan');
        $this->boundary($withBoundary, $this->square(100.40, 5.40));

        $siteApp = $this->application('OSC/2');
        $site = Site::query()->create(['development_address' => 'Jalan A', 'district' => 'SPT', 'mukim' => '4']);
        DB::update('UPDATE sites SET boundary = ST_GeomFromText(?, 4326) WHERE id = ?', [$this->square(100.45, 5.45), $site->id]);
        $siteApp->sites()->attach($site->id, ['is_primary' => true]);
        $viaSite = $this->propose('Tapak', 'JBANG', $siteApp->id);

        $lotApp = $this->application('OSC/3');
        $lot = Lot::query()->create(['lot_number' => 'LOT 03899', 'mukim' => 'Mukim 04', 'district' => 'Seberang Perai Tengah']);
        $lotApp->lots()->attach($lot->id);
        $viaLot = $this->propose('Lot', 'JBANG', $lotApp->id);

        $nothing = $this->propose('Tiada');

        $json = $this->mapData($this->secretary)->assertOk()->json();
        $byTitle = [];
        foreach ($this->items($json) as $feature) {
            $byTitle[$feature['properties']['title']] = $feature;
        }

        $this->assertSame('item', $byTitle['Sempadan']['properties']['geometry_source']);
        $this->assertSame('site', $byTitle['Tapak']['properties']['geometry_source']);
        $this->assertSame('MultiPolygon', $byTitle['Tapak']['geometry']['type']);
        $this->assertSame('lot', $byTitle['Lot']['properties']['geometry_source']);
        // Only the Seberang Perai Tengah lot 3899 matches: its square starts at lon 100.50.
        $coordinates = json_encode($byTitle['Lot']['geometry']['coordinates']);
        $this->assertStringContainsString('100.5', $coordinates);
        $this->assertStringNotContainsString('100.51', $coordinates);
        $this->assertStringNotContainsString('100.52', $coordinates);

        $this->assertArrayNotHasKey('Tiada', $byTitle);
        $unmapped = $json['properties']['unmapped'];
        $this->assertSame([$nothing->id], array_column($unmapped, 'id'));
        $this->assertNotContains($viaSite->id, array_column($unmapped, 'id'));
        $this->assertNotContains($viaLot->id, array_column($unmapped, 'id'));
    }

    public function test_earlier_tablings_are_listed_when_visible(): void
    {
        $app = $this->application('OSC/4');
        $first = $this->propose('Pertama', 'JBANG', $app->id);
        $this->boundary($first, $this->square(100.40, 5.40));
        $second = $this->propose('Kedua', 'JBANG', $app->id);
        $second->update(['previous_item_id' => $first->id]);
        $this->boundary($second, $this->square(100.40, 5.40));

        $features = $this->mapData($this->secretary)->assertOk()->json('features');
        $earlier = array_values(array_filter($features, fn ($f) => $f['properties']['kind'] === 'earlier'));
        $this->assertCount(1, $earlier);
        $this->assertSame($first->id, $earlier[0]['properties']['id']);
        $this->assertSame($second->id, $earlier[0]['properties']['item_id']);
    }

    private function legacy(int $id, float $lon, float $lat, string $status): void
    {
        DB::insert(
            "INSERT INTO core.osc_legacy_papers (id, title, status_code, year, imported_at, geom) VALUES (?, ?, ?, '2019', now(), ST_SetSRID(ST_MakePoint(?, ?), 4326))",
            [$id, 'Lama '.$id, $status, $lon, $lat],
        );
    }

    public function test_past_decisions_within_500m_only(): void
    {
        $a = $this->propose('A');
        $this->boundary($a, $this->square(100.40, 5.40));
        $this->legacy(1, 100.4005, 5.4005, '1');   // inside the boundary
        $this->legacy(2, 100.4030, 5.4005, '4');   // about 220 m from the east edge
        $this->legacy(3, 100.4200, 5.4005, '2');   // about 2 km away

        $features = $this->mapData($this->secretary)->assertOk()->json('features');
        $legacy = array_values(array_filter($features, fn ($f) => $f['properties']['kind'] === 'legacy'));
        $titles = array_column(array_column($legacy, 'properties'), 'title');
        sort($titles);
        $this->assertSame(['Lama 1', 'Lama 2'], $titles);
        $byTitle = array_column(array_column($legacy, 'properties'), null, 'title');
        $this->assertSame('Lulus Dgn Syarat Tambahan', $byTitle['Lama 1']['decision']);
        $this->assertSame('Tolak', $byTitle['Lama 2']['decision']);
        $this->assertSame('2019', $byTitle['Lama 1']['year']);
        $this->assertSame('Point', $legacy[0]['geometry']['type']);
    }

    public function test_past_decisions_hidden_from_participants_without_view_grant(): void
    {
        $this->members();
        $a = $this->propose('A');
        $this->boundary($a, $this->square(100.40, 5.40));
        $this->accept($a);
        $this->legacy(1, 100.4005, 5.4005, '1');

        $this->assertFalse($this->member->canPerformOsc('osc.meeting.view'));
        $features = $this->mapData($this->member)->assertOk()->json('features');
        $this->assertCount(0, array_filter($features, fn ($f) => $f['properties']['kind'] === 'legacy'));
        $this->assertCount(1, $this->items(['features' => $features]));

        $withGrant = $this->mapData($this->secretary)->json('features');
        $this->assertCount(1, array_filter($withGrant, fn ($f) => $f['properties']['kind'] === 'legacy'));
    }
}
