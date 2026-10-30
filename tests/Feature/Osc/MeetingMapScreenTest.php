<?php

declare(strict_types=1);

namespace Tests\Feature\Osc;

use App\Enums\Osc\TablingPurpose;
use App\Models\Gis\GisLayer;
use App\Models\Gis\GisLayerSnapshot;
use App\Models\Osc\Meeting;
use App\Models\User;
use App\Services\Osc\AgendaService;
use App\Services\Osc\MeetingService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Str;
use Tests\TestCase;

final class MeetingMapScreenTest extends TestCase
{
    use RefreshDatabase;

    private User $secretary;

    private User $member;

    private Meeting $meeting;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed();
        Notification::fake();
        $this->secretary = User::factory()->create(['role' => 'osc_secretary', 'department_id' => 'JPBD']);
        $chair = User::factory()->create(['role' => 'senior_building_officer', 'department_id' => 'JBANG']);
        $this->member = User::factory()->create(['role' => 'intake_officer', 'department_id' => 'JBANG']);
        $this->meeting = app(MeetingService::class)->create([
            'number' => 'Bil. 21/2026', 'scheduled_at' => now()->addWeek(), 'venue' => 'Bilik OSC',
            'proposal_cutoff_at' => now()->addDays(2), 'paper_cutoff_at' => now()->addDays(4),
            'chair_id' => $chair->id, 'secretary_id' => $this->secretary->id,
        ], $this->secretary);
        app(MeetingService::class)->setMembers($this->meeting, [
            'chair_id' => $chair->id, 'secretary_id' => $this->secretary->id, 'member_ids' => [$this->member->id],
        ], $this->meeting->row_version, $this->secretary);
        $this->meeting->refresh();
        $item = app(AgendaService::class)->proposeStandalone($this->meeting, [
            'title' => 'Tajuk Rahsia Perkara', 'reference' => 'REF-RAHSIA', 'purpose' => TablingPurpose::Permohonan,
            'presenter_id' => $this->secretary->id, 'department_id' => 'JBANG', 'standalone_type' => 'Penamaan Jalan & Taman',
        ], $this->secretary);
        app(AgendaService::class)->accept($item->fresh(), $item->fresh()->row_version, $this->secretary);
        app(AgendaService::class)->publish($this->meeting, $this->meeting->fresh()->row_version, $this->secretary);
    }

    /** @return array<string, mixed> */
    private function config(string $html): array
    {
        $this->assertSame(1, preg_match('#<script type="application/json" id="osc-map-config">(.*?)</script>#s', $html, $m));

        return json_decode(html_entity_decode($m[1]), true, 512, JSON_THROW_ON_ERROR);
    }

    public function test_map_page_renders_config_without_item_data(): void
    {
        $layer = GisLayer::query()->findOrFail('lots');
        $snapshot = GisLayerSnapshot::query()->create(['layer_id' => 'lots', 'status' => 'active', 'feature_count' => 0]);
        $layer->update(['active_snapshot_id' => $snapshot->id]);

        $response = $this->actingAs($this->member)->get(route('osc.meetings.map', $this->meeting))->assertOk();
        $html = $response->getContent();
        $config = $this->config($html);

        $this->assertSame(route('osc.meetings.map-data', $this->meeting), $config['mapDataUrl']);
        $this->assertCount(1, $config['layers']);
        $this->assertSame('lots', $config['layers'][0]['id']);
        $this->assertStringContainsString('{z}/{x}/{y}', $config['layers'][0]['tileUrlTemplate']);
        $this->assertStringContainsString('/gis/tiles/lots/', $config['layers'][0]['tileUrlTemplate']);
        $this->assertSame(config('gis.region_bbox'), $config['regionBbox']);
        $this->assertNull($config['focusItemId']);
        $this->assertSame('Peta', $config['labels']['panel']);
        $this->assertSame('Imej satelit tidak tersedia. Lapisan tempatan masih dipaparkan.', $config['labels']['imageryUnavailable']);
        $this->assertSame('Lihat pelan susun atur', $config['labels']['showLayout']);
        $this->assertSame('Sembunyi pelan susun atur', $config['labels']['hideLayout']);
        $this->assertSame('Peta asas', $config['labels']['basemap']);
        $this->assertSame('Naikkan', $config['labels']['moveUp']);
        $this->assertSame('Turunkan', $config['labels']['moveDown']);
        $this->assertStringNotContainsString('Tajuk Rahsia Perkara', $html);
        $this->assertStringNotContainsString('REF-RAHSIA', $html);
    }

    public function test_focus_item_is_passed_through_only_when_a_ulid(): void
    {
        $id = (string) Str::ulid();
        $config = $this->config($this->actingAs($this->member)->get(route('osc.meetings.map', [$this->meeting, 'item' => $id]))->getContent());
        $this->assertSame($id, $config['focusItemId']);

        $config = $this->config($this->actingAs($this->member)->get(route('osc.meetings.map', [$this->meeting, 'item' => '<script>']))->getContent());
        $this->assertNull($config['focusItemId']);
    }

    public function test_orthophoto_only_when_configured(): void
    {
        config(['gis.imagery.orthophoto.url' => '']);
        $keys = array_column($this->config($this->actingAs($this->member)->get(route('osc.meetings.map', $this->meeting))->getContent())['imagery'], 'key');
        $this->assertSame(['imagery', 'street'], $keys);

        config(['gis.imagery.orthophoto.url' => 'https://tiles.example.test/{z}/{x}/{y}.png']);
        $keys = array_column($this->config($this->actingAs($this->member)->get(route('osc.meetings.map', $this->meeting))->getContent())['imagery'], 'key');
        $this->assertSame(['imagery', 'street', 'orthophoto'], $keys);
    }

    public function test_non_viewer_gets_403(): void
    {
        $other = User::factory()->create(['role' => 'planning_officer', 'department_id' => 'JPBD']);
        $this->actingAs($other)->get(route('osc.meetings.map', $this->meeting))->assertForbidden();
    }

    public function test_bottom_sheet_markup_is_an_accessible_dialog(): void
    {
        $html = $this->actingAs($this->member)->get(route('osc.meetings.map', $this->meeting))->assertOk()->getContent();

        $this->assertMatchesRegularExpression('/<aside[^>]*id="osc-map-panel"[^>]*role="dialog"[^>]*aria-labelledby="osc-map-panel-title"/', $html);
        $this->assertMatchesRegularExpression('/<h2[^>]*id="osc-map-panel-title"[^>]*tabindex="-1"/', $html);
        $this->assertMatchesRegularExpression('/<button[^>]*data-map-sheet-toggle[^>]*aria-expanded="false"[^>]*aria-controls="osc-map-panel"/', $html);
    }

    public function test_peta_button_on_meeting_page(): void
    {
        $this->actingAs($this->member)->get(route('osc.meetings.show', $this->meeting))
            ->assertOk()->assertSee(route('osc.meetings.map', $this->meeting), false)->assertSee('Peta');
        $this->actingAs($this->secretary)->get(route('osc.meetings.show', $this->meeting))
            ->assertOk()->assertSee(route('osc.meetings.map', $this->meeting), false);
    }
}
