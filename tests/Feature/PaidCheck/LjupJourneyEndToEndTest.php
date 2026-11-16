<?php

declare(strict_types=1);

namespace Tests\Feature\PaidCheck;

use App\Airs\Contracts\DocumentProcessor;
use App\Airs\Models\ReviewVersion;
use App\Contracts\DocumentScanner;
use App\Enums\ApplicationStage;
use App\Enums\ApplicationStatus;
use App\Enums\PaidCheckState;
use App\Jobs\PrecheckDraftDocument;
use App\Models\AcceptedSubmission;
use App\Models\ApplicantDraft;
use App\Models\CheckPayment;
use App\Models\PaidCheckJourney;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Queue;
use Illuminate\Support\Facades\Storage;
use Tests\Support\FakeAirsDocumentProcessor;
use Tests\TestCase;

/** One PSP takes an LJUP draft from precheck through payment, check 1, amendment, check 2 and handover. */
final class LjupJourneyEndToEndTest extends TestCase
{
    use BuildsLjupJourney, RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed();
        Storage::fake('local');
        Queue::fake([PrecheckDraftDocument::class]);
        $this->app->instance(DocumentProcessor::class, new FakeAirsDocumentProcessor);
        $this->app->instance(DocumentScanner::class, new class implements DocumentScanner
        {
            public function assertClean(string $path): void {}
        });
        $this->seedLjupCatalogue();
        config(['airs.fee_sen' => 15000, 'airs.payment_driver' => 'staging']);
    }

    public function test_full_ljup_paid_check_journey(): void
    {
        $owner = $this->applicant();

        // Draft with lighting types, then the free precheck uploads.
        $this->actingAs($owner)->post(route('portal.drafts.store'), ['application_type_id' => 'LJUP', 'title' => 'Lampu jalan Taman Uji',
            'site_address' => 'Lot 101, Mukim 6', 'lighting_types' => ['BESI']])->assertRedirect();
        $draft = ApplicantDraft::query()->sole();
        $this->ljupUpload($draft, 'pelan_lampu');
        $this->ljupUpload($draft, 'laporan_fotometrik');
        $this->actingAs($owner)->get(route('portal.drafts.edit', $draft))->assertOk()->assertSee('Bayar semakan AIRS (RM150.00)');

        // Pay through the staging gateway: check 1 runs (sync) and the result is shown.
        $this->actingAs($owner)->post(route('portal.drafts.payment.start', $draft))->assertRedirect();
        $payment = CheckPayment::query()->sole();
        $this->actingAs($owner)->post(route('portal.payments.staging.confirm', $payment))->assertRedirect(route('portal.drafts.edit', $draft));
        $journey = PaidCheckJourney::query()->sole();
        $this->assertSame(PaidCheckState::AwaitingApplicant, $journey->state);
        $this->actingAs($owner)->get(route('portal.drafts.edit', $draft))->assertOk()
            ->assertSee('Skor kesediaan dokumen')->assertSee('Semakan 1')->assertSee('Tarikh akhir pindaan')->assertDontSee('Bayar semakan AIRS (RM');

        // One amendment: replace the lighting plan and add concrete poles; check 2 runs and hands over.
        $this->ljupUpload($draft, 'pelan_lampu');
        $this->actingAs($owner)->put(route('portal.drafts.update', $draft), ['application_type_id' => 'LJUP', 'title' => 'Lampu jalan Taman Uji',
            'site_address' => 'Lot 101, Mukim 6', 'lighting_types' => ['BESI', 'KONKRIT'], 'version' => $draft->fresh()->version])->assertRedirect();
        $this->actingAs($owner)->post(route('portal.drafts.check.amend', $draft), ['confirm_amendment' => '1'])->assertRedirect();

        $journey->refresh();
        $this->assertSame(PaidCheckState::HandedOver, $journey->state);
        $this->assertSame(2, $journey->final_check_number);
        $this->assertSame(['BESI', 'KONKRIT'], $journey->lighting_types);
        $this->assertSame(2, ReviewVersion::query()->count());
        $submission = AcceptedSubmission::query()->sole();
        $this->assertSame(ReviewVersion::query()->where('check_number', 2)->sole()->document_set_hash, $submission->document_set_hash);
        $application = $journey->application->fresh();
        $this->assertSame(ApplicationStage::TechnicalCirculation, $application->current_stage);
        $this->assertSame(ApplicationStatus::Submitted, $application->current_status);
        $this->assertSame('JKEJ', $application->responsible_department_id);

        // Result is read-only afterwards: no amendment, no edits.
        $this->actingAs($owner)->get(route('portal.drafts.check', $draft))->assertOk()->assertSee('Semakan 2')
            ->assertSee('Diserahkan kepada Jabatan Kejuruteraan')->assertDontSee('Hantar pindaan');
        $this->actingAs($owner)->post(route('portal.drafts.check.amend', $draft), ['confirm_amendment' => '1'])->assertSessionHasErrors('check');
        $this->actingAs($owner)->put(route('portal.drafts.update', $draft), ['application_type_id' => 'LJUP', 'title' => 'X',
            'lighting_types' => ['BESI'], 'version' => $draft->fresh()->version])->assertForbidden();
    }
}
