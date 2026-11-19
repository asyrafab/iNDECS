<?php

declare(strict_types=1);

namespace Tests\Feature\Tasks;

use App\Models\Application;
use App\Models\ApplicationDocument;
use App\Models\AuditLog;
use App\Models\DocumentType;
use App\Models\OfficerTask;
use App\Models\Site;
use App\Models\User;
use App\Services\Tasks\OfficerTaskAccess;
use App\Services\Tasks\OfficerTaskService;
use Carbon\CarbonImmutable;
use Illuminate\Database\QueryException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;

final class OfficerWorkQueueTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed();
        Storage::fake('local');
    }

    public static function departments(): array
    {
        return [
            ['PKM', 'JPBD', 'planning_officer', 'senior_planning_officer'],
            ['PB', 'JBANG', 'building_officer', 'senior_building_officer'],
            ['PL', 'JLAND', 'landscape_officer', 'senior_landscape_officer'],
            ['LJUP', 'JKEJ', 'engineering_officer', 'senior_engineering_officer'],
        ];
    }

    private function world(string $type = 'PKM', string $department = 'JPBD', string $role = 'planning_officer', string $senior = 'senior_planning_officer'): array
    {
        $application = Application::query()->create(['application_type_id' => $type, 'application_reference' => $type.'/'.Str::ulid(),
            'title' => 'Rekod ujian '.$type, 'submission_date' => today()->toDateString(), 'submission_year' => 2026,
            'responsible_department_id' => $department, 'current_stage' => 'registration', 'current_status' => 'submitted']);
        $officer = User::factory()->create(['department_id' => $department, 'role' => $role, 'district' => null]);
        $supervisor = User::factory()->create(['department_id' => $department, 'role' => $senior, 'district' => null]);

        return [$application, $officer, $supervisor];
    }

    private function createTask(Application $application, User $officer, User $supervisor): OfficerTask
    {
        return app(OfficerTaskService::class)->create($supervisor, $application->id,
            ['title' => 'Semak bukti tapak', 'assignee_id' => $officer->id, 'due_date' => today()->subDay()->toDateString()]);
    }

    private function document(Application $application, User $owner, bool $current = true): ApplicationDocument
    {
        $path = 'tasks/'.Str::ulid().'.pdf';
        $bytes = '%PDF-1.4 bukti ujian '.Str::ulid();
        Storage::disk('local')->put($path, $bytes);

        return ApplicationDocument::query()->create(['application_id' => $application->id, 'document_type_id' => DocumentType::query()->firstOrFail()->id,
            'revision_number' => 1, 'original_name' => 'bukti-tapak.pdf', 'storage_path' => $path, 'mime_type' => 'application/pdf',
            'size_bytes' => strlen($bytes), 'checksum_sha256' => hash('sha256', $bytes), 'uploaded_by' => $owner->id,
            'uploaded_at' => now(), 'scan_status' => 'clean', 'scanned_at' => now(), 'is_current' => $current, 'verification_status' => 'pending']);
    }

    #[DataProvider('departments')]
    public function test_each_department_can_assign_start_and_complete_without_changing_application(string $type, string $department, string $role, string $senior): void
    {
        [$application, $officer, $supervisor] = $this->world($type, $department, $role, $senior);
        $before = $application->fresh()->getAttributes();
        $this->actingAs($supervisor)->post(route('tasks.store'), ['application_id' => $application->id, 'title' => 'Semak bukti tapak',
            'assignee_id' => $officer->id, 'due_date' => today()->subDay()->toDateString()])->assertSessionHasNoErrors()->assertRedirect();
        $task = OfficerTask::query()->sole();
        $this->actingAs($officer)->get(route('tasks.index', ['overdue' => 1]))->assertOk()->assertSee('Semak bukti tapak')->assertSee('Tarikh sasaran manual')->assertDontSee('Tugasan pasukan');
        $this->get(route('tasks.show', $task))->assertOk()->assertSee('Mulakan tugasan')->assertDontSee('Simpan agihan');
        $this->post(route('tasks.act', $task), ['revision' => 1, 'action' => 'start'])->assertSessionHasNoErrors()->assertRedirect();
        $this->post(route('tasks.act', $task), ['revision' => 2, 'action' => 'complete', 'note' => 'Bukti disemak.'])->assertSessionHasNoErrors()->assertRedirect();
        $this->assertSame('completed', $task->fresh()->status);
        $this->assertSame($before, $application->fresh()->getAttributes());
        $this->assertDatabaseCount('core.officer_task_actions', 3);
        $this->assertSame(3, AuditLog::query()->where('auditable_type', OfficerTask::class)->count());
    }

    public function test_only_assignee_or_authorised_supervisor_sees_task(): void
    {
        [$application, $officer, $supervisor] = $this->world();
        $task = $this->createTask($application, $officer, $supervisor);
        $peer = User::factory()->create(['department_id' => 'JPBD', 'role' => 'planning_officer']);
        $foreign = User::factory()->create(['department_id' => 'JBANG', 'role' => 'senior_building_officer']);
        foreach ([$peer, $foreign] as $actor) {
            $this->actingAs($actor)->get(route('tasks.index', ['scope' => 'team']))->assertOk()->assertDontSee($task->title);
            $this->get(route('tasks.show', $task))->assertNotFound();
        }
        $this->actingAs($supervisor)->get(route('tasks.index', ['scope' => 'team']))->assertOk()->assertSee($task->title)->assertSee('Tugasan pasukan');
        $this->post(route('tasks.act', $task), ['action' => 'start', 'revision' => 1])->assertForbidden();
    }

    public function test_officer_cannot_self_assign_and_supervisor_cannot_complete_others_work(): void
    {
        [$application, $officer, $supervisor] = $this->world();
        $task = $this->createTask($application, $officer, $supervisor);
        $this->actingAs($officer)->post(route('tasks.store'), ['application_id' => $application->id, 'title' => 'Ujian', 'assignee_id' => $officer->id])->assertNotFound();
        $this->post(route('tasks.act', $task), ['action' => 'assign', 'revision' => 1, 'assignee_id' => $officer->id, 'note' => 'Cuba agih.'])->assertForbidden();
        $this->actingAs($supervisor)->post(route('tasks.act', $task), ['action' => 'complete', 'revision' => 1, 'note' => 'Cuba selesai.'])->assertForbidden();
        $this->assertSame(1, $task->fresh()->revision);
    }

    public function test_district_scope_blocks_assignment_and_record_discovery(): void
    {
        [$application, $officer, $supervisor] = $this->world();
        $site = Site::query()->create(['development_address' => 'Lot Ujian', 'district' => 'Seberang Perai Selatan', 'mukim' => 'Mukim 1', 'state' => 'Pulau Pinang']);
        $application->sites()->attach($site);
        $officer->update(['district' => 'Seberang Perai Utara']);
        $this->actingAs($supervisor)->post(route('tasks.store'), ['application_id' => $application->id, 'title' => 'Ujian', 'assignee_id' => $officer->id])->assertSessionHasErrors('assignee_id');
        $supervisor->update(['district' => 'Seberang Perai Utara']);
        $this->get(route('tasks.create', ['application' => $application->id]))->assertNotFound();
    }

    public function test_ljup_unlocated_scope_is_preserved_but_located_foreign_district_is_denied(): void
    {
        [$application, $officer, $supervisor] = $this->world('LJUP', 'JKEJ', 'engineering_officer', 'senior_engineering_officer');
        $officer->update(['district' => 'Seberang Perai Utara']);
        $task = $this->createTask($application, $officer, $supervisor);
        $this->actingAs($officer)->get(route('tasks.show', $task))->assertOk();
        $site = Site::query()->create(['development_address' => 'Lot Ujian', 'district' => 'Seberang Perai Selatan', 'mukim' => 'Mukim 1']);
        $application->sites()->attach($site);
        $this->get(route('tasks.show', $task))->assertNotFound();
    }

    public function test_reassignment_keeps_history_and_revokes_previous_assignee_visibility(): void
    {
        [$application, $officer, $supervisor] = $this->world();
        $task = $this->createTask($application, $officer, $supervisor);
        $replacement = User::factory()->create(['department_id' => 'JPBD', 'role' => 'planning_officer']);
        $this->actingAs($officer)->post(route('tasks.act', $task), ['action' => 'start', 'revision' => 1])->assertRedirect();
        $this->actingAs($supervisor)->post(route('tasks.act', $task), ['action' => 'assign', 'revision' => 2, 'assignee_id' => $replacement->id,
            'due_date' => '2026-12-01', 'note' => 'Agih semula untuk kesinambungan kerja.'])->assertSessionHasNoErrors()->assertRedirect();
        $this->assertSame('open', $task->fresh()->status);
        $this->assertSame($officer->id, $task->actions()->first()->before_values['assignee_id']);
        $this->actingAs($officer)->get(route('tasks.show', $task))->assertNotFound();
        $this->actingAs($replacement)->get(route('tasks.show', $task))->assertOk();
    }

    public function test_stale_revision_does_not_append_history_and_closed_tasks_cannot_change(): void
    {
        [$application, $officer, $supervisor] = $this->world();
        $task = $this->createTask($application, $officer, $supervisor);
        $this->actingAs($officer)->post(route('tasks.act', $task), ['action' => 'start', 'revision' => 1])->assertRedirect();
        $this->post(route('tasks.act', $task), ['action' => 'complete', 'revision' => 1, 'note' => 'Ancien.'])->assertConflict();
        $this->assertDatabaseCount('core.officer_task_actions', 2);
        $this->actingAs($supervisor)->post(route('tasks.act', $task), ['action' => 'cancel', 'revision' => 2, 'note' => 'Kerja tidak lagi diperlukan.'])->assertSessionHasNoErrors();
        $this->post(route('tasks.act', $task), ['action' => 'assign', 'revision' => 3, 'note' => 'Cuba buka.', 'assignee_id' => $officer->id])->assertSessionHasErrors('task');
        $this->assertSame('cancelled', $task->fresh()->status);
    }

    public function test_completion_pins_historical_document_and_protects_download_bytes_and_scope(): void
    {
        [$application, $officer, $supervisor] = $this->world();
        $task = $this->createTask($application, $officer, $supervisor);
        $document = $this->document($application, $officer);
        $this->actingAs($officer)->post(route('tasks.act', $task), ['action' => 'start', 'revision' => 1]);
        $this->post(route('tasks.act', $task), ['action' => 'complete', 'revision' => 2, 'note' => 'Rujuk bukti.', 'document_ids' => [$document->id]])->assertSessionHasNoErrors();
        $action = $task->actions()->first();
        $this->assertSame($document->checksum_sha256, $action->evidence[0]['checksum_sha256']);
        $document->update(['is_current' => false]);
        $url = route('tasks.evidence', [$task, $action, $document]);
        $this->get($url)->assertOk()->assertHeader('X-Content-Type-Options', 'nosniff');
        Storage::disk('local')->put($document->storage_path, 'tampered');
        $this->get($url)->assertNotFound();
        $this->actingAs(User::factory()->create(['account_type' => 'public', 'department_id' => null, 'role' => 'public_applicant']))->get($url)->assertNotFound();
    }

    public function test_foreign_unscanned_and_tampered_evidence_cannot_complete_task(): void
    {
        [$application, $officer, $supervisor] = $this->world();
        $task = $this->createTask($application, $officer, $supervisor);
        [$foreign] = $this->world('PB', 'JBANG', 'building_officer', 'senior_building_officer');
        $foreignDocument = $this->document($foreign, $officer);
        $document = $this->document($application, $officer);
        $document->update(['scan_status' => 'pending']);
        $this->actingAs($officer)->post(route('tasks.act', $task), ['action' => 'start', 'revision' => 1]);
        $data = ['action' => 'complete', 'revision' => 2, 'note' => 'Bukti.'];
        $this->post(route('tasks.act', $task), $data + ['document_ids' => [$foreignDocument->id]])->assertNotFound();
        $this->post(route('tasks.act', $task), $data + ['document_ids' => [$document->id]])->assertSessionHasErrors('document_ids');
        $document->update(['scan_status' => 'clean']);
        Storage::disk('local')->put($document->storage_path, 'changed');
        $this->post(route('tasks.act', $task), $data + ['document_ids' => [$document->id]])->assertSessionHasErrors('document_ids');
        $this->assertSame(2, $task->fresh()->revision);
    }

    public function test_audit_failure_rolls_back_task_and_action(): void
    {
        [$application, $officer, $supervisor] = $this->world();
        $task = $this->createTask($application, $officer, $supervisor);
        AuditLog::creating(function (AuditLog $audit): void {
            if ($audit->event === 'officer_task_start') {
                throw new \RuntimeException('Audit unavailable');
            }
        });
        try {
            app(OfficerTaskService::class)->act($officer, $task->id, ['action' => 'start', 'revision' => 1]);
            $this->fail('Audit failure should abort the action.');
        } catch (\RuntimeException $exception) {
            $this->assertSame('Audit unavailable', $exception->getMessage());
        } finally {
            AuditLog::flushEventListeners();
            AuditLog::clearBootedModels();
        }
        $this->assertSame('open', $task->fresh()->status);
        $this->assertDatabaseCount('core.officer_task_actions', 1);
    }

    public function test_pinned_evidence_prevents_document_deletion(): void
    {
        [$application, $officer, $supervisor] = $this->world();
        $task = $this->createTask($application, $officer, $supervisor);
        $document = $this->document($application, $officer);
        app(OfficerTaskService::class)->act($officer, $task->id, ['action' => 'start', 'revision' => 1]);
        app(OfficerTaskService::class)->act($officer, $task->id, ['action' => 'complete', 'revision' => 2, 'note' => 'Bukti dipinkan.', 'document_ids' => [$document->id]]);
        $this->expectException(QueryException::class);
        DB::table('core.application_documents')->where('id', $document->id)->delete();
    }

    public function test_module_access_does_not_bypass_a_revoked_task_permission(): void
    {
        [$application, $officer, $supervisor] = $this->world();
        $task = $this->createTask($application, $officer, $supervisor);
        DB::table('core.role_permissions')->where('role_id', 'planning_officer')->where('permission_name', 'core.tasks.work')->delete();
        $this->actingAs($officer)->get(route('tasks.show', $task))->assertOk()->assertDontSee('Mulakan tugasan');
        $this->post(route('tasks.act', $task), ['action' => 'start', 'revision' => 1])->assertForbidden();
        DB::table('core.role_permissions')->where('role_id', 'planning_officer')->where('permission_name', 'core.tasks.view')->delete();
        $this->get(route('tasks.show', $task))->assertNotFound();
        $this->assertTrue($officer->canAccessPkm());
    }

    public function test_manual_overdue_dates_follow_the_malaysian_calendar(): void
    {
        config(['app.display_timezone' => 'Asia/Kuala_Lumpur']);
        $this->travelTo(CarbonImmutable::parse('2026-11-18 17:00:00', 'UTC'));
        try {
            [$application, $officer, $supervisor] = $this->world();
            app(OfficerTaskService::class)->create($supervisor, $application->id, ['title' => 'Tarikh Malaysia',
                'assignee_id' => $officer->id, 'due_date' => '2026-11-18']);
            $this->actingAs($officer)->get(route('tasks.index', ['overdue' => 1]))->assertOk()->assertSee('Tarikh Malaysia')->assertSee('Melepasi tarikh sasaran');
        } finally {
            $this->travelBack();
        }
    }

    public function test_supervisor_forms_show_authorised_assignees_and_escape_task_content(): void
    {
        [$application, $officer, $supervisor] = $this->world();
        $this->actingAs($supervisor)->get(route('tasks.create', ['application' => $application->id]))->assertOk()
            ->assertSee($officer->name)->assertSee('Cipta dan agih tugasan');
        $task = app(OfficerTaskService::class)->create($supervisor, $application->id, ['title' => '<script>unsafe()</script>', 'assignee_id' => $officer->id]);
        $this->get(route('tasks.show', $task))->assertOk()->assertSee('&lt;script&gt;unsafe()&lt;/script&gt;', false)->assertDontSee('<script>unsafe()</script>', false);
        $foreign = User::factory()->create(['department_id' => 'JBANG', 'role' => 'building_officer']);
        $this->post(route('tasks.act', $task), ['action' => 'assign', 'revision' => 1, 'note' => 'Agihan salah jabatan.', 'assignee_id' => $foreign->id])->assertSessionHasErrors('assignee_id');
    }

    public function test_action_history_is_database_immutable(): void
    {
        [$application, $officer, $supervisor] = $this->world();
        $task = $this->createTask($application, $officer, $supervisor);
        $this->expectException(QueryException::class);
        DB::table('core.officer_task_actions')->where('task_id', $task->id)->update(['note' => 'Rewrite']);
    }

    public function test_public_inactive_and_library_only_accounts_do_not_gain_task_power(): void
    {
        foreach ([['account_type' => 'public', 'role' => 'public_applicant'], ['is_active' => false, 'role' => 'planning_officer'], ['role' => 'penolong_pengarah']] as $attributes) {
            $user = User::factory()->create($attributes + ['department_id' => 'JPBD']);
            $this->actingAs($user)->get(route('tasks.index'))->assertForbidden();
            $this->assertSame([], app(OfficerTaskAccess::class)->types($user, 'assign'));
        }
    }
}
