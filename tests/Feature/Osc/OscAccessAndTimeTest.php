<?php

declare(strict_types=1);

namespace Tests\Feature\Osc;

use App\Enums\Osc\AgendaItemStatus;
use App\Enums\Osc\TablingPurpose;
use App\Models\Osc\AgendaItem;
use App\Models\Osc\DepartmentSetting;
use App\Models\Osc\Meeting;
use App\Models\User;
use App\Services\Osc\AgendaService;
use App\Services\Osc\MeetingService;
use Carbon\CarbonImmutable;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Notification;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;

final class OscAccessAndTimeTest extends TestCase
{
    use RefreshDatabase;

    private User $secretary;

    private User $chair;

    private User $officer;

    private Meeting $meeting;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed();
        Notification::fake();
        $this->secretary = User::factory()->create(['role' => 'osc_secretary', 'department_id' => 'JPBD', 'email' => 'setiausaha@indecs.test']);
        $this->chair = User::factory()->create(['role' => 'senior_building_officer', 'department_id' => 'JBANG']);
        $this->officer = User::factory()->create(['role' => 'building_officer', 'department_id' => 'JBANG']);
        $this->meeting = app(MeetingService::class)->create([
            'number' => 'Bil. 21/2026', 'scheduled_at' => now()->addWeek(), 'venue' => 'Bilik OSC',
            'proposal_cutoff_at' => now()->addDays(2), 'paper_cutoff_at' => now()->addDays(4),
            'chair_id' => $this->chair->id, 'secretary_id' => $this->secretary->id,
        ], $this->secretary);
    }

    private function login(string $email)
    {
        return $this->post(route('login.store'), ['email' => $email, 'password' => 'password']);
    }

    private function propose(string $title, User $actor, string $department = 'JBANG', ?User $presenter = null): AgendaItem
    {
        return app(AgendaService::class)->proposeStandalone($this->meeting, [
            'standalone_type' => 'Penamaan Jalan & Taman', 'title' => $title, 'reference' => null,
            'purpose' => TablingPurpose::Permohonan, 'presenter_id' => ($presenter ?? $actor)->id, 'department_id' => $department,
        ], $actor);
    }

    public function test_osc_secretary_signs_in_and_lands_on_osc(): void
    {
        $this->login('setiausaha@indecs.test')->assertRedirect(route('osc.meetings.index'));
        $this->assertAuthenticatedAs($this->secretary);

        $this->get('/')->assertRedirect(route('osc.meetings.index'));
        $this->get(route('osc.meetings.index'))->assertOk()->assertSee('Bil. 21/2026');
    }

    public function test_member_without_osc_grant_signs_in_and_opens_published_agenda(): void
    {
        $member = User::factory()->create(['role' => 'intake_officer', 'department_id' => 'JBANG', 'email' => 'ahli@indecs.test']);
        $this->assertFalse($member->canAccessPkm());
        $this->assertFalse($member->canPerformOsc('osc.meeting.view'));
        app(MeetingService::class)->setMembers($this->meeting, [
            'chair_id' => $this->chair->id, 'secretary_id' => $this->secretary->id, 'member_ids' => [$member->id],
        ], $this->meeting->row_version, $this->secretary);
        $item = $this->propose('Penamaan Jalan Pertama', $this->secretary);
        app(AgendaService::class)->accept($item, $item->row_version, $this->secretary);
        app(AgendaService::class)->publish($this->meeting, $this->meeting->fresh()->row_version, $this->secretary);

        $this->login('ahli@indecs.test')->assertRedirect(route('osc.meetings.index'));
        $this->assertAuthenticatedAs($member);
        $this->get('/')->assertRedirect(route('osc.meetings.index'));
        $this->get(route('osc.meetings.index'))->assertOk()->assertSee('Bil. 21/2026');
        $this->get(route('osc.meetings.show', $this->meeting))->assertOk()->assertSee('Penamaan Jalan Pertama');
    }

    public function test_unrelated_staff_without_grants_cannot_sign_in_or_open_osc(): void
    {
        $stranger = User::factory()->create(['role' => 'intake_officer', 'department_id' => 'JBANG', 'email' => 'luar@indecs.test']);

        $this->login('luar@indecs.test')->assertSessionHasErrors('email');
        $this->assertGuest();

        $this->actingAs($stranger)->get(route('osc.meetings.index'))->assertForbidden();
        $this->actingAs($stranger)->get(route('osc.meetings.show', $this->meeting))->assertForbidden();
    }

    public function test_inactive_member_is_not_admitted(): void
    {
        $member = User::factory()->create(['role' => 'intake_officer', 'department_id' => 'JBANG', 'is_active' => false]);
        app(MeetingService::class)->setMembers($this->meeting, [
            'chair_id' => $this->chair->id, 'secretary_id' => $this->secretary->id, 'member_ids' => [$member->id],
        ], $this->meeting->row_version, $this->secretary);

        $this->assertFalse($member->canAccessOsc());
        $this->assertFalse($member->canAccessStaffWorkspace());
    }

    public function test_index_lists_meetings_for_proposers_and_endorsers(): void
    {
        $other = app(MeetingService::class)->create([
            'number' => 'Bil. 30/2026', 'scheduled_at' => now()->addWeeks(3), 'venue' => 'Bilik OSC',
            'proposal_cutoff_at' => now()->addWeeks(2), 'paper_cutoff_at' => now()->addWeeks(2),
            'chair_id' => $this->chair->id, 'secretary_id' => $this->secretary->id,
        ], $this->secretary);
        DepartmentSetting::query()->updateOrCreate(['department_id' => 'JBANG'], ['requires_endorsement' => true]);
        $presenter = User::factory()->create(['role' => 'building_officer', 'department_id' => 'JBANG']);
        $this->propose('Dicadang pegawai', $this->officer, 'JBANG', $presenter);

        $this->actingAs($this->officer)->get(route('osc.meetings.index'))->assertOk()
            ->assertSee('Bil. 21/2026')->assertDontSee('Bil. 30/2026');

        $head = User::factory()->create(['role' => 'senior_building_officer', 'department_id' => 'JBANG']);
        $this->actingAs($head)->get(route('osc.meetings.index'))->assertOk()
            ->assertSee('Bil. 21/2026')->assertDontSee($other->number)->assertSee('1 menunggu sokongan anda');

        $planner = User::factory()->create(['role' => 'planning_officer', 'department_id' => 'JPBD']);
        $this->actingAs($planner)->get(route('osc.meetings.index'))->assertOk()->assertDontSee('Bil. 21/2026');
    }

    public function test_officer_posting_manager_actions_gets_403_and_nothing_changes(): void
    {
        $item = $this->propose('Cadangan pegawai', $this->officer);
        $version = $this->meeting->fresh()->row_version;

        $this->actingAs($this->officer)->post(route('osc.items.accept', $item), ['expected_version' => $item->row_version])->assertForbidden();
        $this->actingAs($this->officer)->post(route('osc.meetings.reorder', $this->meeting), ['expected_version' => $version, 'order' => [$item->id]])->assertForbidden();
        $this->actingAs($this->officer)->post(route('osc.meetings.publish', $this->meeting), ['expected_version' => $version])->assertForbidden();

        $this->assertSame(AgendaItemStatus::Proposed, $item->fresh()->status);
        $this->assertNull($item->fresh()->position);
        $this->assertSame($version, $this->meeting->fresh()->row_version);
        $this->assertSame(0, $this->meeting->fresh()->agenda_version);
        $this->assertDatabaseCount('core.osc_agenda_versions', 0);
        $this->assertDatabaseMissing('audit_logs', ['event' => 'osc_item_accepted']);
    }

    /** @return array<string, array{0: string}> */
    public static function databaseTimezones(): array
    {
        return ['database in UTC' => ['UTC'], 'database in Kuala Lumpur' => ['Asia/Kuala_Lumpur']];
    }

    #[DataProvider('databaseTimezones')]
    public function test_meeting_times_are_entered_and_shown_in_malaysian_time(string $databaseTimezone): void
    {
        DB::statement("SET LOCAL TIME ZONE '".$databaseTimezone."'");
        $this->travelTo(CarbonImmutable::parse('2026-11-01 01:00:00', 'UTC'));
        $this->actingAs($this->secretary)->post(route('osc.meetings.store'), [
            'number' => 'Bil. 40/2026', 'scheduled_at' => '2026-11-10T09:00', 'venue' => 'Bilik OSC',
            'proposal_cutoff_at' => '2026-11-05T17:00', 'paper_cutoff_at' => '2026-11-07T12:00',
            'chair_id' => $this->chair->id, 'secretary_id' => $this->secretary->id, 'member_ids' => [],
        ])->assertSessionHasNoErrors();
        $meeting = Meeting::query()->where('number', 'Bil. 40/2026')->firstOrFail();
        $this->assertSame('2026-11-05 09:00:00', $meeting->proposal_cutoff_at->utc()->format('Y-m-d H:i:s'));
        $this->assertSame('2026-11-10 01:00:00', $meeting->scheduled_at->utc()->format('Y-m-d H:i:s'));

        $this->actingAs($this->secretary)->get(route('osc.meetings.show', $meeting))->assertOk()
            ->assertSee('10/11/2026 09:00')->assertSee('Tutup cadangan 05/11/2026 17:00');

        $body = fn (string $title): array => [
            'purpose' => 'permohonan', 'standalone_type' => 'Penamaan Jalan & Taman', 'title' => $title, 'presenter_id' => $this->officer->id,
        ];
        $this->travelTo(CarbonImmutable::parse('2026-11-05 08:59:00', 'UTC'));
        $this->actingAs($this->officer)->post(route('osc.items.store', $meeting), $body('Sebelum tutup'))->assertSessionHasNoErrors();

        $this->travelTo(CarbonImmutable::parse('2026-11-05 09:01:00', 'UTC'));
        $this->actingAs($this->officer)->post(route('osc.items.store', $meeting), $body('Selepas tutup'))
            ->assertSessionHasErrors(['meeting' => 'Tarikh tutup cadangan agenda telah berlalu.']);
        $this->assertSame(['Sebelum tutup'], AgendaItem::query()->where('meeting_id', $meeting->id)->pluck('title')->all());
        $this->assertSame('2026-11-05 09:00:00', DB::scalar("SELECT to_char(proposal_cutoff_at AT TIME ZONE 'UTC', 'YYYY-MM-DD HH24:MI:SS') FROM core.osc_meetings WHERE id = ?", [$meeting->id]));
    }

    public function test_meeting_in_the_past_local_time_is_refused(): void
    {
        $this->travelTo(CarbonImmutable::parse('2026-11-01 02:00:00', 'UTC')); // 10:00 in Kuala Lumpur
        $this->actingAs($this->secretary)->post(route('osc.meetings.store'), [
            'number' => 'Bil. 41/2026', 'scheduled_at' => '2026-11-01T09:30', 'venue' => 'Bilik OSC',
            'proposal_cutoff_at' => '2026-11-01T08:00', 'paper_cutoff_at' => '2026-11-01T08:00',
            'chair_id' => $this->chair->id, 'secretary_id' => $this->secretary->id,
        ])->assertSessionHasErrors(['scheduled_at' => 'Tarikh mesyuarat mesti pada masa hadapan.']);
    }
}
