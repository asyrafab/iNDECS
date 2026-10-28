<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Contracts\DocumentScanner;
use App\Enums\ApplicationStage;
use App\Enums\ApplicationStatus;
use App\Enums\DocumentVerificationStatus;
use App\Models\Application;
use App\Models\ApplicationDocument;
use App\Models\DocumentChecklist;
use App\Models\DocumentChecklistItem;
use App\Models\User;
use App\Services\BangunanRegistrationService;
use App\Services\ClamAvDocumentScanner;
use Database\Seeders\BangunanDocumentWorkflowSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Process;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\ValidationException;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\Support\TestDocumentCatalogues;
use Tests\TestCase;

final class BangunanDocumentVerificationTest extends TestCase
{
    use RefreshDatabase;

    private User $officer;

    private User $senior;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed();
        Storage::fake('local');
        $this->officer = User::factory()->create(['department_id' => 'JBANG', 'role' => 'building_officer']);
        $this->senior = User::factory()->create(['department_id' => 'JBANG', 'role' => 'senior_building_officer']);
        $this->actingAs($this->officer);
        $this->app->instance(DocumentScanner::class, new class implements DocumentScanner
        {
            public function assertClean(string $path): void {}
        });
    }

    public function test_start_cannot_bypass_an_unconfirmed_checklist(): void
    {
        $record = $this->registerCase();
        $this->step($record, 'start_document_verification')->assertSessionHasErrors('workflow');
        $this->assertSame(ApplicationStage::Registration, $record->fresh()->current_stage);
        $this->assertDatabaseCount('core.document_checklists', 0);
        $this->assertDatabaseCount('stage_histories', 1);
    }

    public function test_only_authorised_seniors_can_publish_a_family_catalogue_with_a_source(): void
    {
        $this->post(route('bangunan.document-catalogue.publish', 'TEL'), $this->catalogue())->assertForbidden();
        $this->actingAs($this->senior)->get(route('bangunan.document-catalogue.edit', 'TEL'))->assertOk();
        $this->post(route('bangunan.document-catalogue.publish', 'TEL'), $this->catalogue())->assertSessionHasNoErrors()->assertRedirect();
        $this->assertDatabaseHas('core.document_catalogues', ['application_type_id' => 'TEL', 'source_reference' => 'Rujukan jabatan untuk ujian', 'version' => 1, 'confirmed_by' => $this->senior->id]);
        $this->assertDatabaseHas('document_types', ['code' => 'bina_tel_pelan', 'name' => 'Pelan ujian']);
        $this->assertDatabaseHas('audit_logs', ['event' => 'document_catalogue_published', 'actor_id' => $this->senior->id]);
        $this->post(route('bangunan.document-catalogue.publish', 'TEL'), $this->catalogue())->assertSessionHasErrors('version');
        $data = $this->catalogue();
        $data['version'] = 1;
        $data['source_reference'] = '';
        $this->post(route('bangunan.document-catalogue.publish', 'TEL'), $data)->assertSessionHasErrors('source_reference');
        $data['source_reference'] = 'Sumber';
        $data['items'][0]['is_required'] = false;
        $this->post(route('bangunan.document-catalogue.publish', 'TEL'), $data)->assertSessionHasErrors('items');
    }

    public function test_start_snapshots_requirements_and_later_catalogue_edits_do_not_change_the_case(): void
    {
        $this->actingAs($this->senior)->post(route('bangunan.document-catalogue.publish', 'TEL'), $this->catalogue())->assertSessionHasNoErrors();
        $record = $this->registerCase();
        $this->step($record, 'start_document_verification')->assertSessionHasNoErrors();
        $checklist = DocumentChecklist::query()->firstOrFail();
        $this->assertSame('Pelan ujian', $checklist->items->first()->document_name);
        $data = $this->catalogue();
        $data['version'] = 1;
        $data['items'][0]['name'] = 'Pelan versi baharu';
        $this->post(route('bangunan.document-catalogue.publish', 'TEL'), $data)->assertSessionHasNoErrors();
        $this->assertSame('Pelan ujian', $checklist->fresh()->items->first()->document_name);
        $this->assertSame(1, $checklist->catalogue_version);
        $this->get(route('bangunan.documents.index', $record))->assertOk()->assertSee('Pelan ujian');
    }

    public function test_clean_uploads_are_private_and_versioned_and_download_checks_ownership(): void
    {
        $record = $this->openCase();
        $item = $this->requiredItem($record);
        $this->upload($record, $item)->assertSessionHasNoErrors();
        $first = ApplicationDocument::query()->firstOrFail();
        $this->assertSame('clean', $first->scan_status);
        $this->assertNotNull($first->scanned_at);
        $this->assertSame(hash('sha256', $this->pdf()), $first->checksum_sha256);
        Storage::disk('local')->assertExists($first->storage_path);
        $this->get(route('bangunan.documents.download', [$record, $first]))->assertOk()->assertDownload('pelan.pdf');
        $this->upload($record, $item)->assertSessionHasNoErrors();
        $this->assertFalse($first->fresh()->is_current);
        $second = $record->currentDocuments()->firstOrFail();
        $this->assertSame(2, $second->revision_number);
        $this->assertSame($first->id, $second->supersedes_id);
        $this->get(route('bangunan.documents.download', [$record, $first]))->assertDownload('pelan.pdf');
        $other = $this->registerCase();
        $this->get(route('bangunan.documents.download', [$other, $first]))->assertNotFound();
        $this->get(route('bangunan.documents.index', $record))->assertOk()->assertSee('Sejarah revisi');
        if (getenv('INDECS_CAPTURE_DOCUMENT_VIEWS') === '1') {
            $directory = base_path('.tools/bangunan-document-ui');
            if (! is_dir($directory)) {
                mkdir($directory, 0755, true);
            }
            file_put_contents($directory.'/documents.html', $this->get(route('bangunan.documents.index', $record))->assertOk()->getContent());
            file_put_contents($directory.'/application.html', $this->get(route('bangunan.show', $record))->assertOk()->getContent());
            $this->actingAs($this->senior);
            file_put_contents($directory.'/catalogue.html', $this->get(route('bangunan.document-catalogue.edit', 'TEL'))->assertOk()->getContent());
            $this->actingAs($this->officer);
        }
    }

    public function test_required_documents_must_have_current_accepted_revisions_before_fee_assessment(): void
    {
        $record = $this->openCase();
        $item = $this->requiredItem($record);
        $this->step($record, 'complete_document_verification')->assertSessionHasErrors('workflow');
        $this->review($record, $item)->assertSessionHasErrors('document');
        $this->upload($record, $item)->assertSessionHasNoErrors();
        $this->step($record, 'complete_document_verification')->assertSessionHasErrors('workflow');
        $this->review($record, $item)->assertSessionHasNoErrors();
        $this->step($record, 'complete_document_verification')->assertSessionHasNoErrors();
        $this->assertSame(ApplicationStage::FeeAssessment, $record->fresh()->current_stage);
        $this->assertSame(ApplicationStatus::AwaitingPayment, $record->fresh()->current_status);
        $this->assertDatabaseHas('core.document_checklists', ['application_id' => $record->id, 'status' => 'completed']);
        $this->assertDatabaseHas('audit_logs', ['auditable_id' => $record->id, 'event' => 'document_verified', 'actor_id' => $this->officer->id]);
        $this->assertDatabaseHas('stage_histories', ['application_id' => $record->id, 'action' => 'complete_document_verification']);
        $this->upload($record, $item)->assertSessionHasErrors('document');
    }

    public function test_replacement_resets_acceptance_and_stale_revision_review_is_denied(): void
    {
        $record = $this->openCase();
        $item = $this->requiredItem($record);
        $this->upload($record, $item)->assertSessionHasNoErrors();
        $first = $record->currentDocuments()->firstOrFail();
        $this->review($record, $item)->assertSessionHasNoErrors();
        $this->upload($record, $item)->assertSessionHasNoErrors();
        $this->assertSame('pending', $item->fresh()->status);
        $this->assertNull($item->fresh()->document_id);
        $this->assertSame(DocumentVerificationStatus::Accepted, $first->fresh()->verification_status);
        $this->post(route('bangunan.documents.verify', [$record, $item]), ['row_version' => $record->fresh()->row_version, 'document_id' => $first->id, 'status' => 'accepted'])->assertSessionHasErrors('document');
        $this->step($record, 'complete_document_verification')->assertSessionHasErrors('workflow');
        $this->review($record, $item)->assertSessionHasNoErrors();
        $this->step($record, 'complete_document_verification')->assertSessionHasNoErrors();
    }

    public function test_return_and_resubmission_require_reasons_replacements_and_a_new_review(): void
    {
        $record = $this->openCase();
        $item = $this->requiredItem($record);
        $this->upload($record, $item)->assertSessionHasNoErrors();
        $this->review($record, $item, 'rejected')->assertSessionHasErrors('remarks');
        $this->review($record, $item, 'rejected', 'Pelan tidak jelas')->assertSessionHasNoErrors();
        $this->step($record, 'return_document_verification')->assertSessionHasErrors('remarks');
        $this->step($record, 'return_document_verification', 'Sila kemukakan pelan jelas')->assertSessionHasNoErrors();
        $this->assertSame(ApplicationStatus::ReturnedForAmendment, $record->fresh()->current_status);
        $this->review($record, $item)->assertSessionHasErrors('document');
        $this->step($record, 'resubmit_documents')->assertSessionHasErrors('workflow');
        $this->upload($record, $item)->assertSessionHasNoErrors();
        $this->step($record, 'resubmit_documents')->assertSessionHasNoErrors();
        $this->step($record, 'complete_document_verification')->assertSessionHasErrors('workflow');
        $this->review($record, $item)->assertSessionHasNoErrors();
        $this->step($record, 'complete_document_verification')->assertSessionHasNoErrors();
    }

    public function test_required_documents_cannot_be_waived_and_supplied_optional_documents_need_review(): void
    {
        $record = $this->openCase();
        $required = $this->requiredItem($record);
        $optional = DocumentChecklistItem::query()->where('is_required', false)->firstOrFail();
        $this->review($record, $required, 'not_applicable', 'Tidak sesuai')->assertSessionHasErrors('document');
        $this->upload($record, $required)->assertSessionHasNoErrors();
        $this->review($record, $required)->assertSessionHasNoErrors();
        $this->upload($record, $optional)->assertSessionHasNoErrors();
        $this->step($record, 'complete_document_verification')->assertSessionHasErrors('workflow');
        $this->review($record, $optional, 'not_applicable')->assertSessionHasErrors('remarks');
        $this->review($record, $optional, 'not_applicable', 'Tidak berkaitan dengan cadangan ini')->assertSessionHasNoErrors();
        $this->step($record, 'complete_document_verification')->assertSessionHasNoErrors();
    }

    public function test_scan_failure_preserves_the_old_revision_and_creates_no_blob_or_audit(): void
    {
        $record = $this->openCase();
        $item = $this->requiredItem($record);
        $this->upload($record, $item)->assertSessionHasNoErrors();
        $first = $record->currentDocuments()->firstOrFail();
        $auditCount = DB::table('audit_logs')->count();
        $this->app->instance(DocumentScanner::class, new class implements DocumentScanner
        {
            public function assertClean(string $path): void
            {
                throw ValidationException::withMessages(['document' => 'Scanner refused file']);
            }
        });
        $this->upload($record, $item)->assertSessionHasErrors('document');
        $this->assertTrue($first->fresh()->is_current);
        $this->assertDatabaseCount('application_documents', 1);
        $this->assertDatabaseCount('audit_logs', $auditCount);
        $this->assertCount(1, Storage::disk('local')->allFiles());
    }

    public function test_file_validation_and_stale_uploads_do_not_change_the_case(): void
    {
        $record = $this->openCase();
        $item = $this->requiredItem($record);
        $version = $record->fresh()->row_version;
        $this->post(route('bangunan.documents.store', [$record, $item]), ['row_version' => $version, 'document' => UploadedFile::fake()->createWithContent('spoof.pdf', '<script>bad()</script>')])->assertSessionHasErrors('document');
        $this->post(route('bangunan.documents.store', [$record, $item]), ['row_version' => $version - 1, 'document' => $this->file()])->assertSessionHasErrors('document');
        $this->assertDatabaseCount('application_documents', 0);
        $this->assertCount(0, Storage::disk('local')->allFiles());
        $this->assertSame($version, $record->fresh()->row_version);
    }

    public function test_document_routes_enforce_department_district_type_and_item_ownership(): void
    {
        $record = $this->openCase();
        $item = $this->requiredItem($record);
        $this->upload($record, $item)->assertSessionHasNoErrors();
        $document = $record->currentDocuments()->firstOrFail();
        $restricted = User::factory()->create(['department_id' => 'JBANG', 'role' => 'building_officer', 'district' => 'Seberang Perai Utara']);
        $this->actingAs($restricted)->get(route('bangunan.documents.index', $record))->assertForbidden();
        $this->get(route('bangunan.documents.download', [$record, $document]))->assertForbidden();
        $this->upload($record, $item)->assertForbidden();
        $this->review($record, $item)->assertForbidden();
        $planner = User::query()->where('email', 'pegawai@indecs.test')->firstOrFail();
        $this->actingAs($planner)->get(route('bangunan.documents.index', $record))->assertForbidden();
        $this->actingAs($this->officer);
        $other = $this->registerCase();
        $this->step($other, 'start_document_verification')->assertSessionHasNoErrors();
        $this->upload($other, $item)->assertNotFound();
        $this->review($other, $item)->assertNotFound();
        DB::table('core.role_permissions')->where('role_id', 'building_officer')->where('permission_name', 'bangunan.application.access')->update(['application_type_id' => 'PB']);
        $this->get(route('bangunan.documents.download', [$record, $document]))->assertForbidden();
    }

    public function test_upload_and_verify_permissions_are_independent_of_module_access(): void
    {
        $record = $this->openCase();
        $item = $this->requiredItem($record);
        DB::table('core.role_permissions')->where('role_id', 'building_officer')->where('permission_name', 'bangunan.application.documents.upload')->delete();
        $this->get(route('bangunan.documents.index', $record))->assertOk();
        $this->upload($record, $item)->assertForbidden();
        $this->actingAs($this->senior);
        $this->upload($record, $item)->assertSessionHasNoErrors();
        DB::table('core.role_permissions')->where('role_id', 'building_officer')->where('permission_name', 'bangunan.application.documents.verify')->delete();
        $this->actingAs($this->officer);
        $this->review($record, $item)->assertForbidden();
        $this->step($record, 'complete_document_verification')->assertForbidden();
    }

    public function test_tampered_or_missing_private_files_cannot_be_accepted_or_completed(): void
    {
        $record = $this->openCase();
        $item = $this->requiredItem($record);
        $this->upload($record, $item)->assertSessionHasNoErrors();
        $document = $record->currentDocuments()->firstOrFail();
        Storage::disk('local')->put($document->storage_path, 'changed contents');
        $this->get(route('bangunan.documents.download', [$record, $document]))->assertStatus(409);
        $this->review($record, $item)->assertSessionHasErrors('document');
        Storage::disk('local')->put($document->storage_path, $this->pdf());
        $this->review($record, $item)->assertSessionHasNoErrors();
        Storage::disk('local')->delete($document->storage_path);
        $this->step($record, 'complete_document_verification')->assertSessionHasErrors('workflow');
        $this->assertSame(ApplicationStage::DocumentVerification, $record->fresh()->current_stage);
    }

    public function test_existing_verification_cases_require_explicit_checklist_initialisation(): void
    {
        TestDocumentCatalogues::seed($this->senior);
        $record = $this->registerCase();
        $record->update(['current_stage' => ApplicationStage::DocumentVerification, 'current_status' => ApplicationStatus::InReview]);
        $this->step($record, 'complete_document_verification')->assertSessionHasErrors('document');
        $this->post(route('bangunan.documents.initialise', $record), ['row_version' => $record->fresh()->row_version])->assertSessionHasNoErrors();
        $this->assertDatabaseHas('audit_logs', ['auditable_id' => $record->id, 'event' => 'document_checklist_initialised']);
        $this->post(route('bangunan.documents.initialise', $record), ['row_version' => $record->fresh()->row_version])->assertSessionHasErrors('workflow');
        $this->assertDatabaseCount('core.document_checklists', 1);
    }

    public static function scannerExitCodes(): array
    {
        return [[0], [1], [2], [127]];
    }

    #[DataProvider('scannerExitCodes')]
    public function test_real_scanner_adapter_accepts_only_successful_scan_completion(int $exitCode): void
    {
        Process::fake(['*' => Process::result(exitCode: $exitCode)]);
        if ($exitCode !== 0) {
            $this->expectException(ValidationException::class);
        }
        app(ClamAvDocumentScanner::class)->assertClean('/private/server-generated-file.pdf');
        Process::assertRanTimes(fn ($process) => true, 1);
    }

    public function test_scanner_exception_is_a_closed_upload_failure(): void
    {
        Process::fake(fn () => throw new \RuntimeException('scanner unavailable'));
        $this->expectException(ValidationException::class);
        app(ClamAvDocumentScanner::class)->assertClean('/private/server-generated-file.pdf');
    }

    public function test_repeated_document_workflow_seeding_does_not_duplicate_transitions_or_grants(): void
    {
        $count = DB::table('core.role_permissions')->count();
        $this->seed(BangunanDocumentWorkflowSeeder::class);
        $this->seed(BangunanDocumentWorkflowSeeder::class);
        $this->assertDatabaseCount('core.workflow_transitions', 20);
        $this->assertDatabaseCount('core.role_permissions', $count);
        $this->assertDatabaseCount('core.document_catalogues', 0);
    }

    public function test_metadata_failure_rolls_back_the_revision_and_removes_the_new_private_blob(): void
    {
        $record = $this->openCase();
        $item = $this->requiredItem($record);
        $this->upload($record, $item)->assertSessionHasNoErrors();
        $first = $record->currentDocuments()->firstOrFail();
        $version = $record->fresh()->row_version;
        Event::listen('eloquent.creating: App\\Models\\ApplicationDocument', fn () => throw new \RuntimeException('Forced metadata failure'));
        $this->upload($record, $item)->assertStatus(500);
        $this->assertTrue($first->fresh()->is_current);
        $this->assertDatabaseCount('application_documents', 1);
        $this->assertCount(1, Storage::disk('local')->allFiles());
        $this->assertSame($version, $record->fresh()->row_version);
    }

    public function test_stale_verification_cannot_accept_a_document_and_other_module_routes_are_rejected(): void
    {
        $record = $this->openCase();
        $item = $this->requiredItem($record);
        $stale = $record->fresh()->row_version;
        $this->upload($record, $item)->assertSessionHasNoErrors();
        $document = $record->currentDocuments()->firstOrFail();
        $this->post(route('bangunan.documents.verify', [$record, $item]), ['row_version' => $stale, 'document_id' => $document->id, 'status' => 'accepted'])->assertSessionHasErrors('document');
        $this->assertSame(DocumentVerificationStatus::Pending, $document->fresh()->verification_status);
        $pkm = Application::query()->create(['application_type_id' => 'PKM', 'application_reference' => 'PKM/2026/00001',
            'title' => 'PKM ujian', 'submission_date' => '2026-10-01', 'submission_year' => 2026,
            'responsible_department_id' => 'JPBD', 'current_stage' => ApplicationStage::Registration, 'current_status' => ApplicationStatus::Submitted]);
        $this->get(route('bangunan.documents.index', $pkm))->assertNotFound();
        $this->get(route('bangunan.documents.download', [$pkm, $document]))->assertNotFound();
    }

    private function registerCase(): Application
    {
        return app(BangunanRegistrationService::class)->register([
            'application_type_id' => 'TEL', 'application_title' => 'Struktur telekomunikasi ujian', 'submission_date' => '2026-10-01', 'priority' => 'normal',
            'applicant' => ['party_type' => 'organisation', 'name' => 'Pemohon ujian'],
            'site' => ['development_address' => 'Jalan ujian', 'district' => 'Seberang Perai Tengah', 'mukim' => 'Mukim 11'],
            'lots' => [['lot_number' => '101']], 'bangunan' => ['building_category' => 'Struktur', 'building_use' => 'Telekomunikasi'],
        ], $this->officer);
    }

    private function openCase(): Application
    {
        TestDocumentCatalogues::seed($this->senior);
        $record = $this->registerCase();
        $this->step($record, 'start_document_verification')->assertSessionHasNoErrors();

        return $record->fresh();
    }

    private function requiredItem(Application $record): DocumentChecklistItem
    {
        return DocumentChecklist::query()->where('application_id', $record->id)->firstOrFail()->items()->where('is_required', true)->firstOrFail();
    }

    private function step(Application $record, string $action, ?string $remarks = null)
    {
        return $this->post(route('bangunan.transition', $record), ['action' => $action, 'row_version' => $record->fresh()->row_version, 'remarks' => $remarks]);
    }

    private function upload(Application $record, DocumentChecklistItem $item)
    {
        return $this->post(route('bangunan.documents.store', [$record, $item]), ['row_version' => $record->fresh()->row_version, 'document' => $this->file()]);
    }

    private function review(Application $record, DocumentChecklistItem $item, string $status = 'accepted', ?string $remarks = null)
    {
        $document = $record->currentDocuments()->where('document_type_id', $item->document_type_id)->first();

        return $this->post(route('bangunan.documents.verify', [$record, $item]), ['row_version' => $record->fresh()->row_version, 'document_id' => $document?->id, 'status' => $status, 'remarks' => $remarks]);
    }

    private function file(): UploadedFile
    {
        return UploadedFile::fake()->createWithContent('pelan.pdf', $this->pdf());
    }

    private function pdf(): string
    {
        return "%PDF-1.4\n1 0 obj\n<< /Type /Catalog >>\nendobj\n%%EOF\n";
    }

    private function catalogue(): array
    {
        return ['version' => 0, 'source_reference' => 'Rujukan jabatan untuk ujian', 'items' => [['code' => 'pelan', 'name' => 'Pelan ujian', 'is_required' => true]]];
    }
}
