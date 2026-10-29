<?php

declare(strict_types=1);

namespace Tests\Feature\Gis;

use App\Jobs\Gis\ReplicateLayers;
use App\Models\Gis\GisLayer;
use App\Models\Gis\GisLayerSnapshot;
use App\Models\User;
use App\Services\Osc\OscTime;
use Carbon\CarbonImmutable;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Queue;
use Tests\TestCase;

final class RefreshButtonTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed();
    }

    public function test_manager_dispatches_replication_with_actor(): void
    {
        Queue::fake();
        $secretary = User::factory()->create(['role' => 'osc_secretary', 'department_id' => 'JPBD']);

        $this->actingAs($secretary)->post(route('osc.gis.refresh'))
            ->assertRedirect()->assertSessionHas('status', 'Kemas kini lapisan dimulakan.');

        Queue::assertPushed(ReplicateLayers::class, fn (ReplicateLayers $job) => $job->actor?->is($secretary) === true);
    }

    public function test_non_manager_is_refused(): void
    {
        Queue::fake();
        $officer = User::factory()->create(['role' => 'building_officer', 'department_id' => 'JBANG']);

        $this->actingAs($officer)->post(route('osc.gis.refresh'))->assertForbidden();

        Queue::assertNothingPushed();
    }

    public function test_index_shows_last_snapshot_time_and_status(): void
    {
        $secretary = User::factory()->create(['role' => 'osc_secretary', 'department_id' => 'JPBD']);
        $layer = GisLayer::query()->findOrFail('lots');
        $finished = CarbonImmutable::parse('2026-10-29T06:30:00Z');
        GisLayerSnapshot::query()->create(['layer_id' => 'lots', 'status' => 'failed', 'finished_at' => '2026-10-01 01:00:00', 'error' => 'x']);
        GisLayerSnapshot::query()->create(['layer_id' => 'lots', 'status' => 'ready', 'feature_count' => 5, 'finished_at' => $finished]);

        $this->actingAs($secretary)->get(route('osc.meetings.index'))
            ->assertOk()->assertSee($layer->label)->assertSee(OscTime::format($finished))->assertSee('Siap')
            ->assertSee('Kemas kini lapisan');
    }

    public function test_officer_does_not_see_layer_panel(): void
    {
        $officer = User::factory()->create(['role' => 'building_officer', 'department_id' => 'JBANG']);

        $this->actingAs($officer)->get(route('osc.meetings.index'))->assertOk()->assertDontSee('Kemas kini lapisan');
    }
}
