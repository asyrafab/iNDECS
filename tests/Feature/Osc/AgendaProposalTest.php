<?php

declare(strict_types=1);

namespace Tests\Feature\Osc;

use App\Enums\ApplicationStage;
use App\Enums\ApplicationStatus;
use App\Enums\Osc\AgendaItemStatus;
use App\Enums\Osc\MeetingStatus;
use App\Enums\Osc\TablingPurpose;
use App\Models\Application;
use App\Models\Osc\DepartmentSetting;
use App\Models\Osc\Meeting;
use App\Models\Site;
use App\Models\User;
use App\Services\Osc\AgendaService;
use App\Services\Osc\MeetingService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Validation\ValidationException;
use PHPUnit\Framework\Attributes\DataProvider;
use Symfony\Component\HttpKernel\Exception\HttpException;
use Tests\TestCase;

final class AgendaProposalTest extends TestCase
{
    use RefreshDatabase;

    private User $secretary;

    private User $officer;

    private User $seniorOfficer;

    private User $planner;

    private Meeting $meeting;

    private int $sequence = 0;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed();
        $this->secretary = User::factory()->create(['role' => 'osc_secretary', 'department_id' => 'JPBD']);
        $this->officer = User::factory()->create(['role' => 'building_officer', 'department_id' => 'JBANG']);
        $this->seniorOfficer = User::factory()->create(['role' => 'senior_building_officer', 'department_id' => 'JBANG']);
        $this->planner = User::factory()->create(['role' => 'planning_officer', 'department_id' => 'JPBD']);
        $this->meeting = app(MeetingService::class)->create([
            'number' => 'Bil. 21/2026',
            'scheduled_at' => now()->addWeek(),
            'venue' => 'Bilik OSC',
            'proposal_cutoff_at' => now()->addDays(2),
            'paper_cutoff_at' => now()->addDays(4),
            'chair_id' => $this->seniorOfficer->id,
            'secretary_id' => $this->secretary->id,
        ], $this->secretary);
    }

    private function application(string $type = 'PB', string $department = 'JBANG', string $district = 'SPT'): Application
    {
        $application = Application::query()->forceCreate([
            'application_type_id' => $type,
            'application_reference' => $type.'/2026/'.str_pad((string) ++$this->sequence, 5, '0', STR_PAD_LEFT),
            'title' => 'Cadangan bangunan pejabat',
            'submission_date' => '2026-10-01',
            'submission_year' => 2026,
            'responsible_department_id' => $department,
            'current_stage' => ApplicationStage::Registration,
            'current_status' => ApplicationStatus::Submitted,
        ]);
        $site = Site::query()->create(['development_address' => 'Jalan Ujian', 'district' => $district, 'mukim' => 'Mukim 1']);
        $application->sites()->attach($site->id, ['is_primary' => true]);

        return $application;
    }

    private function service(): AgendaService
    {
        return app(AgendaService::class);
    }

    private function assertForbidden(callable $action): void
    {
        try {
            $action();
            $this->fail('Expected a 403.');
        } catch (HttpException $e) {
            $this->assertSame(403, $e->getStatusCode());
        }
    }

    private function assertRefused(string $message, callable $action, string $key = ''): void
    {
        try {
            $action();
            $this->fail('Expected ValidationException.');
        } catch (ValidationException $e) {
            $this->assertSame($message, $key === '' ? collect($e->errors())->flatten()->first() : $e->errors()[$key][0]);
        }
    }

    /** @return array<string, mixed> */
    private function standalone(array $overrides = []): array
    {
        return array_merge([
            'standalone_type' => 'Penamaan Jalan & Taman', 'title' => 'Penamaan jalan baharu', 'reference' => null,
            'purpose' => TablingPurpose::Permohonan, 'presenter_id' => $this->planner->id,
        ], $overrides);
    }

    public function test_building_officer_tables_own_application_linked(): void
    {
        $application = $this->application();

        $item = $this->service()->proposeLinked($this->meeting, $application, TablingPurpose::Permohonan, $this->officer, $this->officer);

        $this->assertSame(AgendaItemStatus::Proposed, $item->status);
        $this->assertSame($application->title, $item->title);
        $this->assertSame($application->application_reference, $item->reference);
        $this->assertSame($application->id, $item->application_id);
        $this->assertSame('PB', $item->application_type_id);
        $this->assertSame('JBANG', $item->department_id);
        $this->assertSame(TablingPurpose::Permohonan, $item->purpose);
        $this->assertSame($this->officer->id, $item->proposed_by);
        $this->assertNull($item->previous_item_id);
        $this->assertDatabaseHas('audit_logs', ['event' => 'osc_item_proposed', 'actor_id' => $this->officer->id, 'auditable_id' => $item->id]);
    }

    public function test_officer_cannot_table_application_outside_scope(): void
    {
        $jbang = $this->application();
        $this->assertForbidden(fn () => $this->service()->proposeLinked($this->meeting, $jbang, TablingPurpose::Permohonan, $this->planner, $this->planner));

        $districtOfficer = User::factory()->create(['role' => 'building_officer', 'department_id' => 'JBANG', 'district' => 'SPU']);
        $this->assertForbidden(fn () => $this->service()->proposeLinked($this->meeting, $this->application(district: 'SPS'), TablingPurpose::Permohonan, $districtOfficer, $districtOfficer));

        $this->assertDatabaseCount('core.osc_agenda_items', 0);
    }

    public function test_officer_without_propose_permission_is_refused(): void
    {
        $outsider = User::factory()->create(['role' => 'public_applicant', 'department_id' => null]);

        $this->assertForbidden(fn () => $this->service()->proposeStandalone($this->meeting, $this->standalone(), $outsider));
        $this->assertDatabaseCount('core.osc_agenda_items', 0);
    }

    public function test_standalone_item_for_type_without_module(): void
    {
        $item = $this->service()->proposeStandalone($this->meeting, $this->standalone(), $this->planner);

        $this->assertNull($item->application_id);
        $this->assertSame('Penamaan Jalan & Taman', $item->standalone_type);
        $this->assertSame('JPBD', $item->department_id);
        $this->assertSame(AgendaItemStatus::Proposed, $item->status);
        $this->assertSame($this->planner->id, $item->proposed_by);
        $this->assertDatabaseHas('audit_logs', ['event' => 'osc_item_proposed', 'auditable_id' => $item->id]);
    }

    public function test_repeat_tabling_links_previous_item(): void
    {
        $application = $this->application();

        $first = $this->service()->proposeLinked($this->meeting, $application, TablingPurpose::Permohonan, $this->officer, $this->officer);
        $second = $this->service()->proposeLinked($this->meeting, $application, TablingPurpose::Rayuan, $this->officer, $this->officer);

        $this->assertSame(2, $this->meeting->items()->count());
        $this->assertSame($first->id, $second->previous_item_id);
    }

    public function test_cutoff_blocks_officer_but_not_secretary(): void
    {
        $this->travelTo(now()->addDays(3));
        $application = $this->application();

        $this->assertRefused('Tarikh tutup cadangan agenda telah berlalu.', fn () => $this->service()->proposeLinked($this->meeting, $application, TablingPurpose::Permohonan, $this->officer, $this->officer));

        $item = $this->service()->proposeStandalone($this->meeting, $this->standalone(['department_id' => 'JPBD']), $this->secretary);
        $this->assertSame(AgendaItemStatus::Proposed, $item->status);
    }

    #[DataProvider('closedStatuses')]
    public function test_closed_meeting_refuses_proposals(MeetingStatus $status): void
    {
        $this->meeting->update(['status' => $status]);

        $this->assertRefused('Mesyuarat ini tidak lagi menerima cadangan.', fn () => $this->service()->proposeStandalone($this->meeting, $this->standalone(), $this->planner));
        $this->assertDatabaseCount('core.osc_agenda_items', 0);
    }

    /** @return array<string, array{MeetingStatus}> */
    public static function closedStatuses(): array
    {
        return [
            'in_progress' => [MeetingStatus::InProgress],
            'closed' => [MeetingStatus::Closed],
            'minutes_confirmed' => [MeetingStatus::MinutesConfirmed],
        ];
    }

    public function test_presenter_must_be_active_staff(): void
    {
        $inactive = User::factory()->create(['role' => 'planning_officer', 'department_id' => 'JPBD', 'is_active' => false]);
        $public = User::factory()->create(['role' => 'public_applicant', 'department_id' => null, 'account_type' => 'public']);

        foreach ([$inactive->id, $public->id, 999999] as $presenterId) {
            $this->assertRefused('Penyampai mesti kakitangan yang aktif.', fn () => $this->service()->proposeStandalone($this->meeting, $this->standalone(['presenter_id' => $presenterId]), $this->planner), 'presenter_id');
        }
        $this->assertDatabaseCount('core.osc_agenda_items', 0);
    }

    public function test_endorsement_required_when_department_setting_on(): void
    {
        $this->assertFalse($this->service()->requiresEndorsement('JBANG'));
        DepartmentSetting::query()->create(['department_id' => 'JBANG', 'requires_endorsement' => true]);
        $this->assertTrue($this->service()->requiresEndorsement('JBANG'));

        $item = $this->service()->proposeLinked($this->meeting, $this->application(), TablingPurpose::Permohonan, $this->officer, $this->officer);

        $this->assertForbidden(fn () => $this->service()->endorse($item, 1, $this->officer));
        $this->assertSame(AgendaItemStatus::Proposed, $item->fresh()->status);

        $endorsed = $this->service()->endorse($item, 1, $this->seniorOfficer);

        $this->assertSame(AgendaItemStatus::Endorsed, $endorsed->status);
        $this->assertSame(2, $endorsed->row_version);
        $this->assertDatabaseHas('audit_logs', ['event' => 'osc_item_endorsed', 'actor_id' => $this->seniorOfficer->id, 'auditable_id' => $item->id]);
    }

    public function test_endorsement_is_scoped_to_item_department_and_version_checked(): void
    {
        $item = $this->service()->proposeStandalone($this->meeting, $this->standalone(), $this->planner);

        $this->assertForbidden(fn () => $this->service()->endorse($item, 1, $this->seniorOfficer));
        $seniorPlanner = User::factory()->create(['role' => 'senior_planning_officer', 'department_id' => 'JPBD']);
        $this->assertRefused('Rekod telah berubah. Muat semula sebelum meneruskan.', fn () => $this->service()->endorse($item, 0, $seniorPlanner));
    }

    public function test_secretary_chooses_department_for_standalone_item(): void
    {
        $item = $this->service()->proposeStandalone($this->meeting, $this->standalone(['department_id' => 'JBANG']), $this->secretary);
        $this->assertSame('JBANG', $item->department_id);

        foreach ([[], ['department_id' => 'XXXX']] as $extra) {
            $this->assertRefused('Jabatan mesti dipilih.', fn () => $this->service()->proposeStandalone($this->meeting, $this->standalone($extra), $this->secretary), 'department_id');
        }
        $this->assertSame(1, $this->meeting->items()->count());
    }

    public function test_officer_standalone_ignores_department_id(): void
    {
        $item = $this->service()->proposeStandalone($this->meeting, $this->standalone(['department_id' => 'JBANG']), $this->planner);

        $this->assertSame('JPBD', $item->department_id);
    }

    public function test_secretary_tables_linked_application_on_behalf_of_department(): void
    {
        $item = $this->service()->proposeLinked($this->meeting, $this->application(), TablingPurpose::Permohonan, $this->officer, $this->secretary);

        $this->assertSame('JBANG', $item->department_id);
        $this->assertSame($this->secretary->id, $item->proposed_by);
    }

    public function test_secretary_tables_linked_application_after_cutoff(): void
    {
        $this->travelTo(now()->addDays(3));

        $item = $this->service()->proposeLinked($this->meeting, $this->application(), TablingPurpose::Permohonan, $this->officer, $this->secretary);

        $this->assertSame(AgendaItemStatus::Proposed, $item->status);
    }
}
