<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Enums\ApplicationStage;
use App\Enums\ApplicationStatus;
use App\Models\Application;
use App\Models\BangunanApplication;
use App\Models\User;
use App\Services\BangunanRegistrationService;
use Database\Seeders\BangunanWorkflowSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use PHPUnit\Framework\Attributes\DataProvider;
use RuntimeException;
use Tests\Support\TestDocumentCatalogues;
use Tests\TestCase;

final class BangunanRegistrationTest extends TestCase
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

    public static function familyCodes(): array
    {
        return array_map(fn ($type) => [$type], BangunanApplication::TYPES);
    }

    #[DataProvider('familyCodes')]
    public function test_registration_persists_each_family_in_core_and_department_tables(string $type): void
    {
        $this->post(route('bangunan.store'), $this->payload($type))->assertSessionHasNoErrors()->assertRedirect();
        $application = Application::query()->firstOrFail();
        $this->assertSame($type.'/2026/00001', $application->application_reference);
        $this->assertSame('JBANG', $application->responsible_department_id);
        $this->assertSame(ApplicationStage::Registration, $application->current_stage);
        $this->assertSame(ApplicationStatus::Submitted, $application->current_status);
        $this->assertNotNull($application->workflow_template_id);
        $this->assertSame('Pejabat', $application->bangunan->building_use);
        $this->assertSame('100.50', $application->bangunan->gross_floor_area);
        $this->assertSame('Pemohon Ujian', $application->applicationParties()->first()->submitted_snapshot['name']);
        $this->assertDatabaseCount('application_parties', 2);
        $this->assertDatabaseCount('application_lots', 2);
        $this->assertDatabaseCount('application_sites', 1);
        $this->assertDatabaseHas('stage_histories', ['application_id' => $application->id, 'actor_id' => $this->officer->id, 'action' => 'application_registered']);
        $this->assertDatabaseHas('audit_logs', ['auditable_id' => $application->id, 'actor_id' => $this->officer->id, 'event' => 'created']);
        $this->get(route('bangunan.show', $application))->assertOk()->assertSee($application->application_reference)
            ->assertSee('Pemohon Ujian')->assertSee('Mulakan pengesahan dokumen');
        $this->get(route('bangunan.index'))->assertOk()->assertSee($application->application_reference);
        if ($type === 'TEL' && getenv('INDECS_CAPTURE_VIEWS') === '1') {
            $directory = base_path('.tools/bangunan-ui');
            if (! is_dir($directory)) {
                mkdir($directory, 0755, true);
            }
            foreach (['index' => route('bangunan.index'), 'create' => route('bangunan.create'), 'show' => route('bangunan.show', $application)] as $name => $url) {
                file_put_contents($directory.'/'.$name.'.html', $this->get($url)->assertOk()->getContent());
            }
        }
    }

    public function test_reference_sequences_are_independent_by_family_and_year(): void
    {
        foreach (['PB', 'PB', 'TEL'] as $type) {
            $this->post(route('bangunan.store'), $this->payload($type))->assertSessionHasNoErrors();
        }
        $data = $this->payload('PB');
        $data['submission_date'] = '2025-10-01';
        $this->post(route('bangunan.store'), $data)->assertSessionHasNoErrors();
        $this->assertSame(['PB/2025/00001', 'PB/2026/00001', 'PB/2026/00002', 'TEL/2026/00001'], Application::query()->orderBy('application_reference')->pluck('application_reference')->all());
    }

    public function test_invalid_registration_cannot_write_or_consume_a_reference(): void
    {
        $data = $this->payload('PB');
        $data['external_reference'] = '';
        $data['bangunan']['number_of_storeys'] = -1;
        $data['lots'][1]['lot_number'] = '101';
        $this->post(route('bangunan.store'), $data)->assertSessionHasErrors(['external_reference', 'bangunan.number_of_storeys', 'lots.0.lot_number']);
        $this->assertDatabaseCount('applications', 0);
        $this->assertDatabaseCount('reference_sequences', 0);
        $this->assertDatabaseCount('audit_logs', 0);
    }

    public function test_failure_rolls_back_core_extension_parties_history_and_reference_together(): void
    {
        try {
            DB::transaction(function (): void {
                app(BangunanRegistrationService::class)->register($this->payload(), $this->officer);
                throw new RuntimeException('Abort whole operation');
            });
            $this->fail('Expected rollback.');
        } catch (RuntimeException $exception) {
            $this->assertSame('Abort whole operation', $exception->getMessage());
        }
        foreach (['applications', 'bangunan.app_pb_applications', 'parties', 'sites', 'lots', 'stage_histories', 'audit_logs', 'reference_sequences'] as $table) {
            $this->assertDatabaseCount($table, 0);
        }
        $this->post(route('bangunan.store'), $this->payload())->assertSessionHasNoErrors();
        $this->assertSame('TEL/2026/00001', Application::query()->firstOrFail()->application_reference);
    }

    public function test_guests_planners_public_and_inactive_accounts_cannot_register(): void
    {
        auth()->logout();
        $this->post(route('bangunan.store'), $this->payload())->assertRedirect(route('login'));
        $planner = User::query()->where('email', 'pegawai@indecs.test')->firstOrFail();
        $public = User::factory()->create(['department_id' => 'JBANG', 'role' => 'building_officer']);
        $public->forceFill(['account_type' => 'public'])->save();
        $inactive = User::factory()->create(['department_id' => 'JBANG', 'role' => 'building_officer', 'is_active' => false]);
        $wrongDepartment = User::factory()->create(['department_id' => 'JPBD', 'role' => 'building_officer']);
        foreach ([$planner, $public, $inactive, $wrongDepartment] as $user) {
            $this->actingAs($user)->get(route('bangunan.index'))->assertForbidden();
            $this->post(route('bangunan.store'), $this->payload())->assertForbidden();
        }
        $this->assertDatabaseCount('applications', 0);
    }

    public function test_district_scope_applies_to_registration_search_metrics_details_and_transitions(): void
    {
        $spt = app(BangunanRegistrationService::class)->register($this->payload(), $this->officer);
        $scoped = User::factory()->create(['department_id' => 'JBANG', 'role' => 'building_officer', 'district' => 'Seberang Perai Utara']);
        $this->actingAs($scoped)->get(route('bangunan.index'))
            ->assertOk()->assertDontSee($spt->application_reference)->assertViewHas('metrics', fn ($metrics) => $metrics['total'] === 0);
        $this->get(route('bangunan.show', $spt))->assertForbidden();
        $this->post(route('bangunan.transition', $spt), $this->transitionPayload())->assertForbidden();
        $this->post(route('bangunan.store'), $this->payload())->assertSessionHasErrors('site.district');
        $data = $this->payload();
        $data['site']['district'] = 'Seberang Perai Utara';
        $this->post(route('bangunan.store'), $data)->assertSessionHasNoErrors();
        $this->get(route('bangunan.index', ['search' => $spt->application_reference]))->assertViewHas('applications', fn ($applications) => $applications->isEmpty());
        $this->assertDatabaseCount('applications', 2);
        $this->assertSame(1, $spt->fresh()->row_version);
    }

    public function test_type_scoped_permission_never_exposes_other_families(): void
    {
        $pb = app(BangunanRegistrationService::class)->register($this->payload('PB'), $this->officer);
        $tel = app(BangunanRegistrationService::class)->register($this->payload(), $this->officer);
        DB::table('core.role_permissions')->where('role_id', 'building_officer')->where('permission_name', 'like', 'bangunan.application.%')->update(['application_type_id' => 'TEL']);
        $this->get(route('bangunan.index'))->assertOk()->assertSee($tel->application_reference)->assertDontSee($pb->application_reference)
            ->assertViewHas('metrics', fn ($metrics) => $metrics['total'] === 1);
        $this->get(route('bangunan.create'))->assertOk()->assertDontSee('value="PB"', false);
        $this->get(route('bangunan.show', $pb))->assertForbidden();
        $this->post(route('bangunan.store'), $this->payload('PB'))->assertForbidden();
        $this->post(route('bangunan.transition', $pb), $this->transitionPayload())->assertForbidden();
    }

    public function test_document_verification_transition_appends_actor_history_and_audit_once(): void
    {
        $record = app(BangunanRegistrationService::class)->register($this->payload(), $this->officer);
        $this->post(route('bangunan.transition', $record), $this->transitionPayload())->assertSessionHasNoErrors()->assertRedirect(route('bangunan.show', $record));
        $record->refresh();
        $this->assertSame(ApplicationStage::DocumentVerification, $record->current_stage);
        $this->assertSame(ApplicationStatus::InReview, $record->current_status);
        $this->assertSame(2, $record->row_version);
        $this->assertDatabaseHas('stage_histories', ['application_id' => $record->id, 'actor_id' => $this->officer->id, 'previous_stage' => 'registration', 'new_stage' => 'document_verification', 'remarks' => 'Semakan dimulakan']);
        $this->assertDatabaseCount('stage_histories', 2);
        $this->assertDatabaseCount('audit_logs', 2);
        $this->post(route('bangunan.transition', $record), $this->transitionPayload())->assertSessionHasErrors('workflow');
        $this->post(route('bangunan.transition', $record), $this->transitionPayload() + ['ignored' => true])->assertSessionHasErrors('workflow');
        $this->assertDatabaseCount('stage_histories', 2);
        $this->assertDatabaseCount('audit_logs', 2);
    }

    public function test_stale_invalid_and_unauthorised_transitions_leave_no_side_effects(): void
    {
        $record = app(BangunanRegistrationService::class)->register($this->payload(), $this->officer);
        $this->post(route('bangunan.transition', $record), array_replace($this->transitionPayload(), ['row_version' => 3]))->assertSessionHasErrors('workflow');
        $this->post(route('bangunan.transition', $record), array_replace($this->transitionPayload(), ['action' => 'approve']))->assertSessionHasErrors('workflow');
        DB::table('core.role_permissions')->where('role_id', 'building_officer')->where('permission_name', 'bangunan.application.review')->delete();
        $this->post(route('bangunan.transition', $record), $this->transitionPayload())->assertForbidden();
        $this->get(route('bangunan.show', $record))->assertOk()->assertDontSee('name="action"', false);
        $this->assertSame(ApplicationStage::Registration, $record->fresh()->current_stage);
        $this->assertSame(1, $record->fresh()->row_version);
        $this->assertDatabaseCount('stage_histories', 1);
        $this->assertDatabaseCount('audit_logs', 1);
    }

    public function test_read_only_access_cannot_create_or_advance_applications(): void
    {
        $record = app(BangunanRegistrationService::class)->register($this->payload(), $this->officer);
        DB::table('core.role_permissions')->where('role_id', 'building_officer')->whereIn('permission_name', ['bangunan.application.register', 'bangunan.application.review'])->delete();
        $this->get(route('bangunan.index'))->assertOk()->assertDontSee('Daftar permohonan');
        $this->get(route('bangunan.create'))->assertForbidden();
        $this->post(route('bangunan.store'), $this->payload())->assertForbidden();
        $this->post(route('bangunan.transition', $record), $this->transitionPayload())->assertForbidden();
    }

    public function test_disabled_types_templates_and_tampered_extension_fields_are_rejected(): void
    {
        $data = $this->payload();
        $data['bangunan']['application_id'] = 'injected';
        $this->post(route('bangunan.store'), $data)->assertSessionHasErrors('bangunan');
        DB::table('application_types')->where('id', 'TEL')->update(['is_active' => false]);
        $this->post(route('bangunan.store'), $this->payload())->assertSessionHasErrors('application_type_id');
        DB::table('application_types')->where('id', 'TEL')->update(['is_active' => true]);
        DB::table('core.workflow_templates')->where('application_type_id', 'TEL')->update(['is_active' => false]);
        $this->post(route('bangunan.store'), $this->payload())->assertStatus(409);
        $this->assertDatabaseCount('applications', 0);
        $this->assertDatabaseCount('reference_sequences', 0);
    }

    public function test_building_officer_home_and_login_route_to_operational_module(): void
    {
        $this->get(route('dashboard'))->assertRedirect(route('bangunan.index'));
        $this->get(route('login'))->assertRedirect(route('bangunan.index'));
    }

    public function test_workflow_seeder_can_be_repeated_without_duplicates_or_rewriting_versions(): void
    {
        $before = DB::table('core.role_permissions')->count();
        $this->seed(BangunanWorkflowSeeder::class);
        $this->seed(BangunanWorkflowSeeder::class);
        $this->assertDatabaseCount('core.workflow_templates', 5);
        $this->assertDatabaseCount('core.workflow_transitions', 20);
        $this->assertDatabaseCount('core.role_permissions', $before);
    }

    public function test_department_routes_reject_other_modules_even_for_a_system_administrator(): void
    {
        $record = app(BangunanRegistrationService::class)->register($this->payload(), $this->officer);
        $admin = User::factory()->create(['department_id' => 'JBANG', 'role' => 'system_admin']);
        $this->actingAs($admin);
        $this->get(route('perancang.show', $record))->assertNotFound();
        $this->post(route('perancang.start-review', $record))->assertNotFound();
        $this->post(route('perancang.completeness.complete', $record))->assertNotFound();
        $pkm = Application::query()->create([
            'application_type_id' => 'PKM', 'application_reference' => 'PKM/2026/00001',
            'title' => 'Permohonan Perancang', 'submission_date' => '2026-10-01', 'submission_year' => 2026,
            'responsible_department_id' => 'JPBD', 'current_stage' => ApplicationStage::Registration,
            'current_status' => ApplicationStatus::Submitted,
        ]);
        $this->get(route('bangunan.show', $pkm))->assertNotFound();
        $this->post(route('bangunan.transition', $pkm), $this->transitionPayload())->assertNotFound();
        $this->assertDatabaseCount('stage_histories', 1);
        $this->assertDatabaseCount('audit_logs', 1);
    }

    private function transitionPayload(): array
    {
        return ['action' => 'start_document_verification', 'row_version' => 1, 'remarks' => 'Semakan dimulakan'];
    }

    private function payload(string $type = 'TEL'): array
    {
        return [
            'application_type_id' => $type, 'application_title' => 'Cadangan bangunan pejabat',
            'submission_date' => '2026-10-01', 'external_reference' => 'OSC/2026/001', 'priority' => 'normal',
            'applicant' => ['party_type' => 'organisation', 'name' => 'Pemohon Ujian', 'registration_number' => 'TEST-001'],
            'consultant' => ['party_type' => 'individual', 'name' => 'Perunding Ujian'],
            'site' => ['development_address' => 'Jalan Ujian', 'district' => 'Seberang Perai Tengah', 'mukim' => 'Mukim 11'],
            'lots' => [['lot_number' => '101'], ['lot_number' => '102']],
            'bangunan' => ['building_category' => 'Komersial', 'building_use' => 'Pejabat', 'number_of_storeys' => 2, 'gross_floor_area' => 100.50],
        ];
    }
}
