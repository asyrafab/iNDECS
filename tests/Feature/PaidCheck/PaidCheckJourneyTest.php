<?php

declare(strict_types=1);

namespace Tests\Feature\PaidCheck;

use App\Airs\Contracts\DocumentProcessor;
use App\Airs\Models\ReviewVersion;
use App\Enums\ApplicationStage;
use App\Enums\ApplicationStatus;
use App\Enums\PaidCheckState;
use App\Models\Application;
use App\Models\ApplicationDocument;
use App\Models\PaidCheckJourney;
use App\Models\User;
use App\Services\PaidCheck\CheckPaymentService;
use App\Services\PaidCheck\PaidCheckJourneyService;
use App\Services\WorkingCalendar;
use Carbon\CarbonImmutable;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Storage;
use Tests\Support\FakeAirsDocumentProcessor;
use Tests\TestCase;

final class PaidCheckJourneyTest extends TestCase
{
    use BuildsLjupJourney, RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed();
        Storage::fake('local');
        $this->app->instance(DocumentProcessor::class, new FakeAirsDocumentProcessor);
        $this->seedLjupCatalogue();
        config(['airs.fee_sen' => 15000]);
    }

    private function pay(\App\Models\ApplicantDraft $draft, string $event = 'evt-1'): \App\Models\CheckPayment
    {
        $owner = User::query()->findOrFail($draft->user_id);
        $payment = app(CheckPaymentService::class)->start($owner, $draft, true);

        return app(CheckPaymentService::class)->confirm($payment->reference, $event, CarbonImmutable::now()->subMinute());
    }

    public function test_payment_creates_jkej_application_with_copied_documents(): void
    {
        $draft = $this->readyLjupDraft();
        $this->pay($draft);

        $application = Application::query()->sole();
        $this->assertSame('LJUP', $application->application_type_id);
        $this->assertSame('JKEJ', $application->responsible_department_id);
        $this->assertSame('LJUP/'.now()->format('Y').'/00001', $application->application_reference);
        $this->assertSame($draft->title, $application->title);
        $documents = ApplicationDocument::query()->where('application_id', $application->id)->get();
        $this->assertCount(2, $documents);
        foreach ($draft->documents as $draftDocument) {
            $copy = $documents->firstWhere('checksum_sha256', $draftDocument->sha256);
            $this->assertNotNull($copy);
            $this->assertSame($draftDocument->sha256, hash('sha256', (string) Storage::disk('local')->get($copy->storage_path)));
            $this->assertSame('clean', $copy->scan_status);
        }
    }

    public function test_check_one_runs_and_journey_awaits_applicant(): void
    {
        $draft = $this->readyLjupDraft();
        $this->pay($draft);

        $journey = PaidCheckJourney::query()->sole();
        $this->assertSame(PaidCheckState::AwaitingApplicant, $journey->state);
        $this->assertSame(['BESI'], $journey->lighting_types);
        $version = ReviewVersion::query()->sole();
        $this->assertSame('COMPLETED', $version->status);
        $this->assertSame(1, $version->check_number);
        $expected = app(WorkingCalendar::class)->addWorkingDays($version->completed_at->toImmutable(), 5);
        $this->assertTrue($expected->equalTo($journey->amendment_due_at));
        $this->assertSame(ApplicationStage::TechnicalCheck, $journey->application->current_stage);
        $this->assertSame(ApplicationStatus::InReview, $journey->application->current_status);
    }

    public function test_duplicate_confirmation_creates_one_application_and_one_check(): void
    {
        $draft = $this->readyLjupDraft();
        $payment = $this->pay($draft);
        app(CheckPaymentService::class)->confirm($payment->reference, 'evt-1', CarbonImmutable::now());
        app(CheckPaymentService::class)->confirm($payment->reference, 'evt-2', CarbonImmutable::now());
        app(PaidCheckJourneyService::class)->paid($payment->fresh());

        $this->assertSame(1, Application::query()->count());
        $this->assertSame(1, PaidCheckJourney::query()->count());
        $this->assertSame(1, ReviewVersion::query()->count());
    }

    public function test_documents_for_items_that_no_longer_apply_are_not_copied(): void
    {
        $owner = $this->applicant();
        $draft = $this->ljupDraft($owner);
        $this->ljupUpload($draft, 'pelan_lampu');
        $this->ljupUpload($draft, 'laporan_fotometrik');
        $stale = $this->ljupUpload($draft, 'gambar_tapak');
        // JKEJ republishes the checklist without the photo item: the old upload no longer applies.
        app(\App\Services\DocumentCatalogueService::class)->publish('LJUP', ['version' => 1, 'source_reference' => 'Ujian v2', 'items' => [
            ['code' => 'pelan_lampu', 'name' => 'Pelan Lampu Jalan', 'is_required' => true],
            ['code' => 'laporan_fotometrik', 'name' => 'Laporan Fotometrik', 'is_required' => true],
        ]], User::query()->where('role', 'senior_engineering_officer')->firstOrFail());
        $this->pay($draft);

        $this->assertNull(ApplicationDocument::query()->where('checksum_sha256', $stale->sha256)->first());
        $this->assertSame(2, ApplicationDocument::query()->count());
    }

    public function test_only_the_latest_upload_per_item_is_copied(): void
    {
        $owner = $this->applicant();
        $draft = $this->ljupDraft($owner);
        $old = $this->ljupUpload($draft, 'pelan_lampu');
        $new = $this->ljupUpload($draft, 'pelan_lampu');
        $this->pay($draft);

        $this->assertNull(ApplicationDocument::query()->where('checksum_sha256', $old->sha256)->first());
        $this->assertNotNull(ApplicationDocument::query()->where('checksum_sha256', $new->sha256)->first());
    }
}
