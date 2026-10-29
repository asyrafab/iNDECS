<?php

declare(strict_types=1);

namespace Tests\Feature\Osc;

use App\Enums\Osc\MeetingRole;
use App\Enums\Osc\MeetingStatus;
use App\Models\User;
use App\Services\Osc\MeetingService;
use App\Services\Osc\OscAccess;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Validation\ValidationException;
use Symfony\Component\HttpKernel\Exception\HttpException;
use Tests\TestCase;

final class MeetingServiceTest extends TestCase
{
    use RefreshDatabase;

    private User $secretary;

    private User $chair;

    private User $steno;

    /** @var array<int, User> */
    private array $members;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed();
        $this->secretary = User::factory()->create(['role' => 'osc_secretary', 'department_id' => 'JPBD']);
        $this->chair = User::factory()->create(['role' => 'building_officer', 'department_id' => 'JBANG']);
        $this->steno = User::factory()->create(['role' => 'osc_steno', 'department_id' => 'JPBD']);
        $this->members = User::factory()->count(2)->create(['role' => 'building_officer', 'department_id' => 'JBANG'])->all();
    }

    /** @return array<string, mixed> */
    private function data(array $overrides = []): array
    {
        return array_merge([
            'number' => 'Bil. 21/2026',
            'scheduled_at' => now()->addWeek(),
            'venue' => 'Bilik OSC',
            'proposal_cutoff_at' => now()->addDays(2),
            'paper_cutoff_at' => now()->addDays(4),
            'chair_id' => $this->chair->id,
            'secretary_id' => $this->secretary->id,
            'steno_id' => $this->steno->id,
            'member_ids' => array_map(fn (User $u) => $u->id, $this->members),
        ], $overrides);
    }

    public function test_secretary_creates_meeting_with_chair_and_members(): void
    {
        $meeting = app(MeetingService::class)->create($this->data(), $this->secretary);

        $this->assertSame(MeetingStatus::Draft, $meeting->status);
        $this->assertSame('Bil. 21/2026', $meeting->number);
        $this->assertSame($this->secretary->id, $meeting->created_by);
        $this->assertSame($this->chair->id, $meeting->members()->where('role', MeetingRole::Chair)->sole()->user_id);
        $this->assertSame(5, $meeting->members()->count());
        $this->assertSame(2, $meeting->members()->where('role', MeetingRole::Member)->count());
        $this->assertDatabaseHas('audit_logs', [
            'event' => 'osc_meeting_created', 'actor_id' => $this->secretary->id, 'auditable_id' => $meeting->id,
        ]);
    }

    public function test_only_one_chair_per_meeting_and_chair_is_any_user(): void
    {
        $service = app(MeetingService::class);
        $meeting = $service->create($this->data(['chair_id' => $this->secretary->id, 'secretary_id' => $this->secretary->id]), $this->secretary);
        $this->assertSame($this->secretary->id, $meeting->members()->where('role', MeetingRole::Chair)->sole()->user_id);

        $meeting = $service->setMembers($meeting, $this->data(), 1, $this->secretary);

        $this->assertSame(2, $meeting->row_version);
        $this->assertSame($this->chair->id, $meeting->members()->where('role', MeetingRole::Chair)->sole()->user_id);
        $this->assertSame(1, $meeting->members()->where('role', MeetingRole::Chair)->count());
        $this->assertDatabaseHas('audit_logs', ['event' => 'osc_meeting_members_changed', 'auditable_id' => $meeting->id]);
    }

    public function test_officer_without_manage_permission_cannot_create(): void
    {
        try {
            app(MeetingService::class)->create($this->data(), $this->chair);
            $this->fail('Expected a 403.');
        } catch (HttpException $e) {
            $this->assertSame(403, $e->getStatusCode());
        }
        $this->assertDatabaseCount('core.osc_meetings', 0);
    }

    public function test_set_members_with_stale_version_is_refused(): void
    {
        $service = app(MeetingService::class);
        $meeting = $service->create($this->data(), $this->secretary);

        try {
            $service->setMembers($meeting, $this->data(), 0, $this->secretary);
            $this->fail('Expected ValidationException.');
        } catch (ValidationException $e) {
            $this->assertSame('Rekod telah berubah. Muat semula sebelum meneruskan.', collect($e->errors())->flatten()->first());
        }
        $this->assertSame(1, $meeting->fresh()->row_version);
    }

    public function test_set_members_refused_when_meeting_is_closed(): void
    {
        $service = app(MeetingService::class);
        $meeting = $service->create($this->data(), $this->secretary);
        $meeting->update(['status' => MeetingStatus::Closed]);

        $this->expectException(ValidationException::class);
        $service->setMembers($meeting, $this->data(), 1, $this->secretary);
    }

    public function test_member_cannot_view_draft_but_can_view_published(): void
    {
        $meeting = app(MeetingService::class)->create($this->data(), $this->secretary);
        $member = $this->members[0];
        $outsider = User::factory()->create(['role' => 'building_officer', 'department_id' => 'JBANG']);

        $this->assertFalse(OscAccess::canViewMeeting($member, $meeting));
        $this->assertTrue(OscAccess::canViewMeeting($this->secretary, $meeting));
        $this->assertTrue(OscAccess::isMember($member, $meeting));
        $this->assertFalse(OscAccess::isMember($outsider, $meeting));

        $meeting->update(['status' => MeetingStatus::AgendaPublished]);

        $this->assertTrue(OscAccess::canViewMeeting($member, $meeting));
        $this->assertFalse(OscAccess::canViewMeeting($outsider, $meeting));
    }

    public function test_presenter_of_an_item_can_view_the_meeting(): void
    {
        $meeting = app(MeetingService::class)->create($this->data(), $this->secretary);
        $presenter = User::factory()->create(['role' => 'building_officer', 'department_id' => 'JBANG']);
        $meeting->items()->create([
            'department_id' => 'JBANG', 'standalone_type' => 'lain-lain', 'title' => 'Perkara', 'purpose' => 'lain_lain',
            'presenter_id' => $presenter->id, 'proposed_by' => $presenter->id,
        ]);

        $this->assertTrue(OscAccess::canViewMeeting($presenter, $meeting));
    }
}
