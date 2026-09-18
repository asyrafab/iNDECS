<?php

namespace Tests\Feature;

use App\Enums\ApplicationStage;
use App\Enums\ApplicationStatus;
use App\Enums\CompletenessItemStatus;
use App\Models\Application;
use App\Models\ApplicationDocument;
use App\Models\DocumentType;
use App\Models\PkmCompletenessItem;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class PkmCompletenessTest extends TestCase
{
    use RefreshDatabase;

    private User $officer;

    protected function setUp(): void
    {
        parent::setUp();

        Storage::fake('local');
        $this->seed();
        $this->officer = User::query()->firstOrFail();
        $this->actingAs($this->officer);
    }

    public function test_starting_review_snapshots_requirements_and_assigns_an_officer(): void
    {
        $application = $this->registerApplication();

        $this->post(route('perancang.start-review', $application))->assertSessionHas('success');

        $application->refresh();
        $this->assertSame(ApplicationStage::CompletenessCheck, $application->current_stage);
        $this->assertSame(ApplicationStatus::InReview, $application->current_status);
        $this->assertSame($this->officer->id, $application->assigned_to);
        $this->assertNotNull($application->due_date);
        $this->assertDatabaseCount('app_pkm_completeness_reviews', 1);
        $this->assertDatabaseCount('app_pkm_completeness_items', 6);
    }

    public function test_private_document_uploads_are_versioned(): void
    {
        $application = $this->startReview();
        $documentType = DocumentType::query()->where('code', 'pkm_form')->firstOrFail();

        $this->post(route('perancang.documents.store', [$application, $documentType]), [
            'document' => UploadedFile::fake()->create('borang-km.pdf', 120, 'application/pdf'),
        ])->assertSessionHas('success');

        $first = ApplicationDocument::query()->firstOrFail();
        Storage::disk('local')->assertExists($first->storage_path);
        $this->assertSame(1, $first->revision_number);
        $this->assertTrue($first->is_current);

        $this->post(route('perancang.documents.store', [$application, $documentType]), [
            'document' => UploadedFile::fake()->create('borang-km-pindaan.pdf', 140, 'application/pdf'),
        ])->assertSessionHas('success');

        $this->assertDatabaseCount('application_documents', 2);
        $this->assertSame(1, ApplicationDocument::query()->where('is_current', true)->count());
        $this->assertSame(2, ApplicationDocument::query()->where('is_current', true)->value('revision_number'));
    }

    public function test_a_required_item_cannot_be_completed_without_a_document(): void
    {
        $application = $this->startReview();
        $item = PkmCompletenessItem::query()->where('is_required', true)->firstOrFail();

        $this->post(route('perancang.completeness.items.update', [$application, $item]), [
            'status' => CompletenessItemStatus::Complete->value,
        ])->assertSessionHas('error');

        $this->assertSame(CompletenessItemStatus::Pending, $item->fresh()->status);
    }

    public function test_a_fully_verified_review_advances_to_fee_assessment(): void
    {
        $application = $this->startReview();
        $items = PkmCompletenessItem::query()->where('is_required', true)->with('documentType')->get();

        foreach ($items as $item) {
            $this->post(route('perancang.documents.store', [$application, $item->documentType]), [
                'document' => UploadedFile::fake()->create("{$item->documentType->code}.pdf", 100, 'application/pdf'),
            ])->assertSessionHas('success');

            $this->post(route('perancang.completeness.items.update', [$application, $item]), [
                'status' => CompletenessItemStatus::Complete->value,
                'remarks' => 'Disahkan.',
            ])->assertSessionHas('success');
        }

        $this->post(route('perancang.completeness.complete', $application), [
            'remarks' => 'Semua dokumen wajib lengkap.',
        ])->assertSessionHas('success');

        $application->refresh();
        $this->assertSame(ApplicationStage::FeeAssessment, $application->current_stage);
        $this->assertSame(ApplicationStatus::AwaitingPayment, $application->current_status);
        $this->assertDatabaseHas('app_pkm_completeness_reviews', ['status' => 'complete']);
    }

    public function test_an_incomplete_application_can_be_returned_and_resubmitted(): void
    {
        $application = $this->startReview();
        $item = PkmCompletenessItem::query()->where('is_required', true)->firstOrFail();

        $this->post(route('perancang.completeness.items.update', [$application, $item]), [
            'status' => CompletenessItemStatus::Missing->value,
            'remarks' => 'Salinan tidak jelas.',
        ])->assertSessionHas('success');

        $this->post(route('perancang.completeness.return', $application), [
            'remarks' => 'Muat naik salinan dokumen yang jelas.',
        ])->assertSessionHas('success');

        $this->assertSame(ApplicationStatus::ReturnedForAmendment, $application->fresh()->current_status);

        $this->post(route('perancang.resubmit', $application))->assertSessionHas('success');

        $application->refresh();
        $this->assertSame(ApplicationStatus::InReview, $application->current_status);
        $this->assertDatabaseCount('app_pkm_completeness_reviews', 2);
        $this->assertDatabaseHas('app_pkm_completeness_reviews', ['cycle_number' => 2, 'status' => 'in_progress']);
    }

    private function startReview(): Application
    {
        $application = $this->registerApplication();
        $this->post(route('perancang.start-review', $application))->assertSessionHas('success');

        return $application->fresh();
    }

    private function registerApplication(): Application
    {
        $this->post(route('perancang.store'), [
            'application_title' => 'Cadangan Perumahan Bersepadu',
            'application_description' => 'Cadangan pembangunan untuk ujian kelengkapan.',
            'external_reference' => 'OSC/2026/0200',
            'submission_date' => '2026-08-12',
            'priority' => 'normal',
            'applicant' => [
                'party_type' => 'organisation',
                'name' => 'Pemaju Seberang Sdn. Bhd.',
                'registration_number' => '202601234567',
                'email' => 'projek@example.test',
            ],
            'consultant' => [],
            'site' => [
                'development_address' => 'Lot 101, Mukim 6',
                'district' => 'Seberang Perai Tengah',
                'mukim' => 'Mukim 6',
                'state' => 'Pulau Pinang',
                'site_area' => '2.1000',
                'site_area_unit' => 'hectare',
            ],
            'lots' => [[
                'lot_number' => 'Lot 101',
                'lot_type' => 'Lot',
                'land_area' => '2.1000',
                'land_area_unit' => 'hectare',
            ]],
            'pkm' => [
                'permission_category' => 'Pembangunan Baharu',
                'permission_type' => 'Kebenaran Merancang Penuh',
                'existing_land_use' => 'Pertanian',
                'proposed_land_use' => 'Perumahan',
            ],
            'components' => [],
        ])->assertRedirect();

        return Application::query()->latest('created_at')->firstOrFail();
    }
}
