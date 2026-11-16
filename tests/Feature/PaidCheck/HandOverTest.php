<?php

declare(strict_types=1);

namespace Tests\Feature\PaidCheck;

use App\Airs\Contracts\DocumentProcessor;
use App\Airs\Models\ReviewVersion;
use App\Enums\ApplicationStage;
use App\Enums\ApplicationStatus;
use App\Enums\PaidCheckState;
use App\Models\AcceptedSubmission;
use App\Models\ApplicantDraft;
use App\Models\PaidCheckJourney;
use App\Models\User;
use App\Services\PaidCheck\CheckPaymentService;
use App\Services\PaidCheck\PaidCheckJourneyService;
use Carbon\CarbonImmutable;
use Illuminate\Console\Scheduling\Schedule;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Storage;
use Tests\Support\FakeAirsDocumentProcessor;
use Tests\TestCase;

final class HandOverTest extends TestCase
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

    private function paid(?ApplicantDraft $draft = null): array
    {
        $draft ??= $this->readyLjupDraft();
        $owner = User::query()->findOrFail($draft->user_id);
        $payment = app(CheckPaymentService::class)->start($owner, $draft, true);
        app(CheckPaymentService::class)->confirm($payment->reference, 'evt-'.$draft->id, CarbonImmutable::now()->subMinute());

        return [$draft->fresh(), $owner, PaidCheckJourney::query()->where('draft_id', $draft->id)->sole()];
    }

    public function test_handover_writes_accepted_submission_and_moves_application(): void
    {
        [$draft, $owner, $journey] = $this->paid();
        $this->actingAs($owner)->post(route('portal.drafts.check.submit', $draft));

        $submission = AcceptedSubmission::query()->sole();
        $version = ReviewVersion::query()->where('check_number', 1)->sole();
        $this->assertSame($journey->application_id, $submission->application_id);
        $this->assertSame($version->document_set_hash, $submission->document_set_hash);
        $this->assertSame('paid', $submission->clearance_kind);
        $this->assertSame($journey->payment->reference, $submission->payment_clearance_reference);
        $this->assertCount(2, $submission->document_manifest);
        $this->assertSame([], $submission->missing_required_documents);
        $application = $journey->application->fresh();
        $this->assertSame(ApplicationStage::TechnicalCirculation, $application->current_stage);
        $this->assertSame(ApplicationStatus::Submitted, $application->current_status);
    }

    public function test_handover_flags_missing_required_documents(): void
    {
        $owner = $this->applicant();
        $draft = $this->ljupDraft($owner);
        $this->ljupUpload($draft, 'pelan_lampu');
        [$draft, $owner, $journey] = $this->paid($draft);
        $this->actingAs($owner)->post(route('portal.drafts.check.submit', $draft));

        $this->assertSame(['Laporan Fotometrik'], AcceptedSubmission::query()->sole()->missing_required_documents);
        $this->assertSame(ApplicationStatus::Submitted, $journey->application->fresh()->current_status);
    }

    public function test_handover_is_idempotent(): void
    {
        [, , $journey] = $this->paid();
        app(PaidCheckJourneyService::class)->handOver($journey, 1);
        app(PaidCheckJourneyService::class)->handOver($journey->fresh(), 1);
        $this->assertSame(1, AcceptedSubmission::query()->count());
    }

    public function test_expired_window_hands_over_check_one(): void
    {
        [, , $journey] = $this->paid();
        $this->travelTo($journey->amendment_due_at->addMinute());

        $this->artisan('paid-check:hand-over-expired')->assertSuccessful();

        $journey->refresh();
        $this->assertSame(PaidCheckState::HandedOver, $journey->state);
        $this->assertSame(1, $journey->final_check_number);
        $this->assertSame(1, AcceptedSubmission::query()->count());
    }

    public function test_window_not_yet_expired_is_left_alone(): void
    {
        [, , $journey] = $this->paid();
        $this->artisan('paid-check:hand-over-expired')->assertSuccessful();
        $this->assertSame(PaidCheckState::AwaitingApplicant, $journey->fresh()->state);
    }

    public function test_amend_after_deadline_is_refused(): void
    {
        [$draft, $owner, $journey] = $this->paid();
        $this->travelTo($journey->amendment_due_at->addMinute());
        $this->actingAs($owner)->post(route('portal.drafts.check.amend', $draft), ['confirm_amendment' => '1'])->assertSessionHasErrors('check');
        $this->actingAs($owner)->post(route('portal.drafts.check.extend', $draft))->assertSessionHasErrors('check');
        $this->assertSame(0, ReviewVersion::query()->where('check_number', 2)->count());
    }

    public function test_check_two_completion_hands_over_automatically(): void
    {
        [$draft, $owner, $journey] = $this->paid();
        $this->ljupUpload($draft, 'pelan_lampu');
        $this->actingAs($owner)->post(route('portal.drafts.check.amend', $draft), ['confirm_amendment' => '1']);

        $submission = AcceptedSubmission::query()->sole();
        $this->assertSame(ReviewVersion::query()->where('check_number', 2)->sole()->document_set_hash, $submission->document_set_hash);
        $this->assertSame(2, $journey->fresh()->final_check_number);
    }

    public function test_handover_never_rejects_or_closes_application(): void
    {
        config(['airs.score_threshold' => 101]);
        $owner = $this->applicant();
        $draft = $this->ljupDraft($owner);
        $this->ljupUpload($draft, 'pelan_lampu');
        [, , $journey] = $this->paid($draft);
        $this->travelTo($journey->amendment_due_at->addMinute());
        $this->artisan('paid-check:hand-over-expired');

        $this->assertSame('BELOW_THRESHOLD', ReviewVersion::query()->sole()->shadow_outcome);
        $this->assertNotContains($journey->application->fresh()->current_status, [ApplicationStatus::Rejected, ApplicationStatus::Closed]);
    }

    public function test_expiry_command_is_scheduled(): void
    {
        $event = collect(app(Schedule::class)->events())->first(fn ($event) => str_contains((string) $event->command, 'paid-check:hand-over-expired'));
        $this->assertNotNull($event);
        $this->assertSame('*/5 * * * *', $event->expression);
    }
}
