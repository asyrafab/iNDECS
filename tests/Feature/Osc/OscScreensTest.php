<?php

declare(strict_types=1);

namespace Tests\Feature\Osc;

use App\Contracts\DocumentScanner;
use App\Enums\ApplicationStage;
use App\Enums\ApplicationStatus;
use App\Enums\Osc\AgendaItemStatus;
use App\Enums\Osc\MeetingStatus;
use App\Enums\Osc\TablingPurpose;
use App\Models\Application;
use App\Models\Osc\AgendaItem;
use App\Models\Osc\Meeting;
use App\Models\Site;
use App\Models\User;
use App\Services\Osc\AgendaService;
use App\Services\Osc\MeetingService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

final class OscScreensTest extends TestCase
{
    use RefreshDatabase;

    private User $secretary;

    private User $chair;

    private User $officer;

    private User $member;

    private User $outsider;

    private Meeting $meeting;

    protected function setUp(): void
    {
        parent::setUp();
        Storage::fake('local');
        $this->seed();
        $this->secretary = User::factory()->create(['role' => 'osc_secretary', 'department_id' => 'JPBD']);
        $this->chair = User::factory()->create(['role' => 'senior_building_officer', 'department_id' => 'JBANG']);
        $this->officer = User::factory()->create(['role' => 'building_officer', 'department_id' => 'JBANG']);
        $this->member = User::factory()->create(['role' => 'planning_officer', 'department_id' => 'JPBD']);
        $this->outsider = User::factory()->create(['role' => 'planning_officer', 'department_id' => 'JPBD']);
        $this->meeting = app(MeetingService::class)->create([
            'number' => 'Bil. 21/2026', 'scheduled_at' => now()->addWeek(), 'venue' => 'Bilik OSC',
            'proposal_cutoff_at' => now()->addDays(2), 'paper_cutoff_at' => now()->addDays(4),
            'chair_id' => $this->chair->id, 'secretary_id' => $this->secretary->id,
            'member_ids' => [$this->member->id],
        ], $this->secretary);
        $this->app->instance(DocumentScanner::class, new class implements DocumentScanner
        {
            public function assertClean(string $path): void {}
        });
    }

    private function propose(string $title, ?User $presenter = null, string $department = 'JBANG'): AgendaItem
    {
        return app(AgendaService::class)->proposeStandalone($this->meeting, [
            'standalone_type' => 'Penamaan Jalan & Taman', 'title' => $title, 'reference' => null,
            'purpose' => TablingPurpose::Permohonan, 'presenter_id' => ($presenter ?? $this->officer)->id,
            'department_id' => $department,
        ], $this->secretary);
    }

    private function application(string $department = 'JBANG'): Application
    {
        $application = Application::query()->forceCreate([
            'application_type_id' => 'PB', 'application_reference' => 'PB/2026/00001', 'title' => 'Cadangan bangunan pejabat',
            'submission_date' => '2026-10-01', 'submission_year' => 2026, 'responsible_department_id' => $department,
            'current_stage' => ApplicationStage::Registration, 'current_status' => ApplicationStatus::Submitted,
        ]);
        $site = Site::query()->create(['development_address' => 'Jalan Ujian', 'district' => 'SPT', 'mukim' => 'Mukim 1']);
        $application->sites()->attach($site->id, ['is_primary' => true]);

        return $application;
    }

    private function payload(array $overrides = []): array
    {
        return array_merge([
            'number' => 'Bil. 22/2026', 'scheduled_at' => now()->addWeeks(2)->format('Y-m-d\TH:i'), 'venue' => 'Bilik OSC',
            'proposal_cutoff_at' => now()->addWeek()->format('Y-m-d\TH:i'), 'paper_cutoff_at' => now()->addDays(10)->format('Y-m-d\TH:i'),
            'chair_id' => $this->chair->id, 'secretary_id' => $this->secretary->id, 'member_ids' => [$this->member->id],
        ], $overrides);
    }

    public function test_secretary_full_flow_through_http(): void
    {
        Notification::fake();
        $this->actingAs($this->secretary)->post(route('osc.meetings.store'), $this->payload())->assertSessionHasNoErrors();
        $created = Meeting::query()->where('number', 'Bil. 22/2026')->firstOrFail();

        $this->actingAs($this->officer)->post(route('osc.items.store', $created), [
            'purpose' => 'permohonan', 'standalone_type' => 'Penamaan Jalan & Taman', 'title' => 'Penamaan Jalan Pertama',
            'presenter_id' => $this->officer->id,
        ])->assertSessionHasNoErrors();
        $this->actingAs($this->officer)->post(route('osc.items.store', $created), [
            'purpose' => 'rayuan', 'standalone_type' => 'Penamaan Jalan & Taman', 'title' => 'Penamaan Jalan Kedua',
            'presenter_id' => $this->officer->id,
        ])->assertSessionHasNoErrors();
        [$first, $second] = AgendaItem::query()->where('meeting_id', $created->id)->orderBy('created_at')->get()->all();
        $this->assertSame('JBANG', $first->department_id);

        foreach ([$first, $second] as $item) {
            $this->actingAs($this->secretary)->post(route('osc.items.accept', $item), ['expected_version' => $item->fresh()->row_version])
                ->assertSessionHasNoErrors()->assertSessionHas('status');
        }
        $this->actingAs($this->secretary)->post(route('osc.meetings.reorder', $created), [
            'expected_version' => $created->fresh()->row_version, 'order' => [$second->id, $first->id],
        ])->assertSessionHasNoErrors()->assertSessionHas('status');
        $this->assertSame(1, $second->fresh()->position);

        $this->actingAs($this->secretary)->post(route('osc.meetings.publish', $created), ['expected_version' => $created->fresh()->row_version])
            ->assertSessionHasNoErrors()->assertSessionHas('status');

        $page = $this->actingAs($this->member)->get(route('osc.meetings.show', $created))->assertOk();
        $page->assertSee('Penamaan Jalan Pertama')->assertSee('Versi 1');
        $page->assertSeeInOrder(['Penamaan Jalan Kedua', 'Penamaan Jalan Pertama']);
    }

    public function test_non_member_staff_gets_403_on_meeting_and_paper(): void
    {
        $item = $this->propose('Perkara A');
        app(AgendaService::class)->accept($item, $item->row_version, $this->secretary);
        app(AgendaService::class)->publish($this->meeting, $this->meeting->fresh()->row_version, $this->secretary);
        $this->actingAs($this->officer)->post(route('osc.papers.store', $item), ['paper' => $this->pdf()])->assertSessionHasNoErrors();
        $paper = $item->papers()->firstOrFail();

        $this->actingAs($this->outsider)->get(route('osc.meetings.show', $this->meeting))->assertForbidden();
        $this->actingAs($this->outsider)->get(route('osc.papers.show', $paper))->assertForbidden();
        $this->actingAs($this->member)->get(route('osc.papers.show', $paper))->assertOk();
    }

    public function test_member_cannot_see_draft_agenda_or_proposals(): void
    {
        $kept = $this->propose('Perkara Diterima');
        $returned = $this->propose('Perkara Dikembalikan');
        $this->actingAs($this->member)->get(route('osc.meetings.show', $this->meeting))->assertForbidden();

        app(AgendaService::class)->accept($kept, $kept->row_version, $this->secretary);
        app(AgendaService::class)->returnToDepartment($returned, $returned->row_version, 'Tidak lengkap', $this->secretary);
        $this->propose('Perkara Baharu Dicadangkan');
        app(AgendaService::class)->publish($this->meeting, $this->meeting->fresh()->row_version, $this->secretary);

        $this->actingAs($this->member)->get(route('osc.meetings.show', $this->meeting))->assertOk()
            ->assertSee('Perkara Diterima')->assertDontSee('Perkara Dikembalikan')->assertDontSee('Perkara Baharu Dicadangkan')
            ->assertDontSee('Terbitkan agenda');
    }

    public function test_presenter_on_draft_meeting_sees_only_own_items(): void
    {
        $mine = $this->propose('Perkara Saya');
        $other = User::factory()->create(['role' => 'building_officer', 'department_id' => 'JBANG']);
        $this->propose('Perkara Jabatan Lain', $other, 'JPBD');
        $returned = $this->propose('Perkara Saya Dikembalikan');
        app(AgendaService::class)->returnToDepartment($returned, $returned->row_version, 'Sila lengkapkan butiran', $this->secretary);

        $this->actingAs($this->officer)->get(route('osc.meetings.show', $this->meeting))->assertOk()
            ->assertSee('Perkara Saya')->assertSee('Sila lengkapkan butiran')
            ->assertDontSee('Perkara Jabatan Lain')->assertDontSee('Terima')->assertDontSee('Terbitkan agenda');
        $this->assertNotNull($mine->id);
    }

    public function test_sidebar_and_application_button_visibility(): void
    {
        $application = $this->application();
        $this->actingAs($this->officer)->get(route('bangunan.show', $application))->assertOk()
            ->assertSee('eOSC+')->assertSee('Bentang ke mesyuarat OSC')
            ->assertSee(route('osc.meetings.index', ['application' => $application->id]), false);

        $plain = User::factory()->create(['role' => 'planning_officer', 'department_id' => 'JPBD']);
        $this->actingAs($plain)->get(route('osc.meetings.index'))->assertOk();
        $stranger = User::factory()->create(['role' => 'public_applicant', 'department_id' => null]);
        $this->actingAs($stranger)->get(route('osc.meetings.index'))->assertForbidden();

        $noOsc = User::factory()->create(['role' => 'building_officer', 'department_id' => 'JBANG']);
        DB::table('core.role_permissions')->where('permission_name', 'like', 'osc.%')->where('role_id', 'building_officer')->delete();
        $this->actingAs($noOsc)->get(route('bangunan.show', $application))->assertOk()
            ->assertDontSee('eOSC+')->assertDontSee('Bentang ke mesyuarat OSC');
    }

    public function test_pages_render_without_hard_coded_colours(): void
    {
        $item = $this->propose('Perkara A');
        app(AgendaService::class)->accept($item, $item->row_version, $this->secretary);
        $this->propose('Perkara B');
        $application = $this->application();
        $urls = [
            route('osc.meetings.index'), route('osc.meetings.index', ['application' => $application->id]),
            route('osc.meetings.create'), route('osc.meetings.show', $this->meeting),
            route('osc.items.create', $this->meeting), route('osc.items.create', [$this->meeting, 'application' => $application->id]),
        ];
        foreach ($urls as $url) {
            $html = $this->actingAs($this->secretary)->get($url)->assertOk()->getContent();
            $this->assertSame(0, preg_match('/style="[^"]*#[0-9a-fA-F]{3,6}/', $html), $url);
            $this->assertSame(0, preg_match('/<style/i', $html), $url);
        }
        app(AgendaService::class)->publish($this->meeting, $this->meeting->fresh()->row_version, $this->secretary);
        $html = $this->actingAs($this->member)->get(route('osc.meetings.show', $this->meeting))->assertOk()->getContent();
        $this->assertSame(0, preg_match('/style="[^"]*#[0-9a-fA-F]{3,6}/', $html));
    }

    public function test_meeting_people_must_be_active_staff_accounts(): void
    {
        $public = User::factory()->create(['role' => 'public_applicant', 'department_id' => null, 'account_type' => 'public']);
        $inactive = User::factory()->create(['role' => 'planning_officer', 'department_id' => 'JPBD', 'is_active' => false]);
        $before = Meeting::query()->count();

        foreach ([['chair_id', $public->id], ['secretary_id', $inactive->id], ['steno_id', $public->id], ['member_ids', [$inactive->id]]] as [$key, $value]) {
            $this->actingAs($this->secretary)->post(route('osc.meetings.store'), $this->payload([$key => $value]))
                ->assertSessionHasErrors($key === 'member_ids' ? 'member_ids.0' : $key);
        }
        $this->actingAs($this->secretary)->post(route('osc.meetings.store'), $this->payload(['number' => 'Bil. 21/2026']))
            ->assertSessionHasErrors('number');
        $this->assertSame($before, Meeting::query()->count());
        $this->actingAs($this->officer)->post(route('osc.meetings.store'), $this->payload())->assertForbidden();
        $this->actingAs($this->officer)->get(route('osc.meetings.create'))->assertForbidden();
    }

    public function test_only_managers_choose_a_department_for_standalone_items(): void
    {
        $this->actingAs($this->secretary)->get(route('osc.items.create', $this->meeting))->assertOk()->assertSee('name="department_id"', false);
        $this->actingAs($this->officer)->get(route('osc.items.create', $this->meeting))->assertOk()->assertDontSee('name="department_id"', false);

        $this->actingAs($this->secretary)->post(route('osc.items.store', $this->meeting), [
            'purpose' => 'permohonan', 'standalone_type' => 'Lain', 'title' => 'Item Pengurus', 'presenter_id' => $this->officer->id,
            'department_id' => 'JPBD',
        ])->assertSessionHasNoErrors();
        $this->assertSame('JPBD', AgendaItem::query()->where('title', 'Item Pengurus')->firstOrFail()->department_id);

        $this->actingAs($this->officer)->post(route('osc.items.store', $this->meeting), [
            'purpose' => 'permohonan', 'standalone_type' => 'Lain', 'title' => 'Item Pegawai', 'presenter_id' => $this->officer->id,
            'department_id' => 'JPBD',
        ])->assertSessionHasNoErrors();
        $this->assertSame('JBANG', AgendaItem::query()->where('title', 'Item Pegawai')->firstOrFail()->department_id);
    }

    public function test_linked_item_is_prefilled_from_the_application_and_meeting_picker_lists_open_meetings(): void
    {
        $application = $this->application();
        $past = app(MeetingService::class)->create([
            'number' => 'Bil. 1/2026', 'scheduled_at' => now()->subDay(), 'venue' => 'Dewan Lama',
            'proposal_cutoff_at' => now()->subDays(5), 'paper_cutoff_at' => now()->subDays(3),
            'chair_id' => $this->chair->id, 'secretary_id' => $this->secretary->id,
        ], $this->secretary);

        $this->actingAs($this->officer)->get(route('osc.meetings.index', ['application' => $application->id]))->assertOk()
            ->assertSee('Pilih mesyuarat')->assertSee(route('osc.items.create', [$this->meeting, 'application' => $application->id]), false)
            ->assertDontSee(route('osc.items.create', [$past, 'application' => $application->id]), false);
        $this->actingAs($this->officer)->get(route('osc.items.create', [$this->meeting, 'application' => $application->id]))->assertOk()
            ->assertSee('PB/2026/00001')->assertSee('Cadangan bangunan pejabat')->assertDontSee('name="standalone_type"', false);

        $this->actingAs($this->officer)->post(route('osc.items.store', $this->meeting), [
            'purpose' => 'rayuan', 'application_id' => $application->id, 'presenter_id' => $this->officer->id,
        ])->assertSessionHasNoErrors();
        $item = AgendaItem::query()->where('application_id', $application->id)->firstOrFail();
        $this->assertSame(AgendaItemStatus::Proposed, $item->status);
        $this->assertSame('Cadangan bangunan pejabat', $item->title);
    }

    public function test_closed_meeting_hides_agenda_actions(): void
    {
        $this->propose('Perkara Menunggu');
        $this->actingAs($this->secretary)->get(route('osc.meetings.show', $this->meeting))->assertOk()
            ->assertSee('Terima')->assertSee('Kembalikan');
        $this->meeting->update(['status' => MeetingStatus::Closed]);
        $this->actingAs($this->secretary)->get(route('osc.meetings.show', $this->meeting))->assertOk()
            ->assertSee('Perkara Menunggu')->assertDontSee('Terima')->assertDontSee('Kembalikan')->assertDontSee('Terbitkan agenda');
    }

    public function test_return_requires_a_reason_and_stale_versions_are_reported(): void
    {
        $item = $this->propose('Perkara A');
        $this->actingAs($this->secretary)->post(route('osc.items.return', $item), ['expected_version' => $item->row_version, 'reason' => ''])
            ->assertSessionHasErrors('reason');
        $this->actingAs($this->secretary)->post(route('osc.items.accept', $item), ['expected_version' => 99])
            ->assertSessionHasErrors('item');
        $this->actingAs($this->secretary)->post(route('osc.items.return', $item), ['expected_version' => $item->row_version, 'reason' => 'Tidak lengkap'])
            ->assertSessionHasNoErrors();
        $this->assertSame(AgendaItemStatus::Returned, $item->fresh()->status);
    }

    public function test_paper_is_uploaded_and_served_inline(): void
    {
        $item = $this->propose('Perkara A');
        $this->actingAs($this->officer)->post(route('osc.papers.store', $item), ['paper' => $this->pdf()])
            ->assertSessionHasNoErrors()->assertSessionHas('status');
        $paper = $item->papers()->firstOrFail();

        $response = $this->actingAs($this->secretary)->get(route('osc.papers.show', $paper))->assertOk();
        $this->assertStringContainsString('inline', (string) $response->headers->get('Content-Disposition'));
        $this->assertSame('application/pdf', $response->headers->get('Content-Type'));
    }

    private function pdf(): UploadedFile
    {
        return UploadedFile::fake()->createWithContent('kertas.pdf', "%PDF-1.4\n1 0 obj<<>>endobj\n%x\ntrailer<<>>\n%%EOF\n");
    }
}
