<?php

declare(strict_types=1);

namespace Tests\Feature\PaidCheck;

use App\Airs\Contracts\DocumentProcessor;
use App\Airs\Jobs\RunAirsCheck;
use App\Airs\Models\ReviewVersion;
use App\Enums\PaidCheckState;
use App\Models\AcceptedSubmission;
use App\Models\ApplicantDraft;
use App\Models\Application;
use App\Models\PaidCheckJourney;
use App\Models\User;
use App\Services\PaidCheck\CheckPaymentService;
use App\Services\PaidCheck\PaidCheckJourneyService;
use Carbon\CarbonImmutable;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Queue;
use Illuminate\Support\Facades\Storage;
use Tests\Support\FakeAirsDocumentProcessor;
use Tests\TestCase;

/** Final-review fixes for the paid-check journey state machine. */
final class JourneyReviewFixesTest extends TestCase
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

    public function test_check_one_handover_is_refused_once_check_two_has_started(): void
    {
        [, , $journey] = $this->paid();
        // An amendment won the race: the journey is already running check 2.
        $journey->forceFill(['state' => PaidCheckState::Check2Running, 'amended_at' => now()])->save();

        app(PaidCheckJourneyService::class)->handOver($journey, 1);

        $this->assertSame(PaidCheckState::Check2Running, $journey->fresh()->state);
        $this->assertSame(0, AcceptedSubmission::query()->count());
    }

    public function test_check_two_handover_requires_check_two_running(): void
    {
        [, , $journey] = $this->paid();
        app(PaidCheckJourneyService::class)->handOver($journey, 2);
        $this->assertSame(PaidCheckState::AwaitingApplicant, $journey->fresh()->state);
        $this->assertSame(0, AcceptedSubmission::query()->count());
    }

    public function test_expiry_rechecks_the_deadline_under_lock(): void
    {
        [, , $journey] = $this->paid();
        $this->travelTo($journey->amendment_due_at->addMinute());
        // Extended by staff/applicant between selection and handover: no longer expired.
        $journey->forceFill(['amendment_due_at' => now()->addDay()])->save();
        app(PaidCheckJourneyService::class)->handOverExpiredJourney($journey);
        $this->assertSame(PaidCheckState::AwaitingApplicant, $journey->fresh()->state);
    }

    public function test_listener_failure_after_payment_is_recovered_by_a_gateway_retry(): void
    {
        $draft = $this->readyLjupDraft();
        $owner = User::query()->findOrFail($draft->user_id);
        $payment = app(CheckPaymentService::class)->start($owner, $draft, true);
        $upload = $draft->documents()->firstOrFail();
        $bytes = Storage::disk('local')->get($upload->path);
        Storage::disk('local')->delete($upload->path);

        try {
            app(CheckPaymentService::class)->confirm($payment->reference, 'evt-1', CarbonImmutable::now()->subMinute());
        } catch (\Throwable) {
        }
        $this->assertSame('paid', $payment->fresh()->status);
        $this->assertSame(0, PaidCheckJourney::query()->count());

        Storage::disk('local')->put($upload->path, $bytes);
        app(CheckPaymentService::class)->confirm($payment->reference, 'evt-1', CarbonImmutable::now()->subMinute());

        $this->assertSame(1, PaidCheckJourney::query()->count());
        $this->assertSame(1, Application::query()->count());
    }

    public function test_draft_type_is_frozen_while_a_payment_is_pending(): void
    {
        $draft = $this->readyLjupDraft();
        $owner = User::query()->findOrFail($draft->user_id);
        app(CheckPaymentService::class)->start($owner, $draft, true);

        $this->actingAs($owner)->put(route('portal.drafts.update', $draft), ['application_type_id' => 'PKM', 'title' => 'Rumah', 'version' => $draft->version])
            ->assertSessionHasErrors('application_type_id');
        $this->assertSame('LJUP', $draft->fresh()->application_type_id);
    }

    public function test_paid_refuses_a_draft_whose_type_has_no_enabled_module(): void
    {
        $draft = $this->readyLjupDraft();
        $owner = User::query()->findOrFail($draft->user_id);
        $payment = app(CheckPaymentService::class)->start($owner, $draft, true);
        $draft->forceFill(['application_type_id' => 'PKM'])->save();

        try {
            app(CheckPaymentService::class)->confirm($payment->reference, 'evt-1', CarbonImmutable::now());
        } catch (\Throwable) {
        }

        $this->assertSame(0, Application::query()->count());
    }

    public function test_check_that_fails_to_start_shows_staff_contact_message(): void
    {
        [$draft, $owner, $journey] = $this->paid();
        // Amendment that withdraws every document: check 2 cannot start (empty document set).
        $draft->documents()->delete();
        $this->actingAs($owner)->post(route('portal.drafts.check.amend', $draft), ['confirm_amendment' => '1']);

        $this->assertSame(PaidCheckState::Check2Running, $journey->fresh()->state);
        $this->assertSame(0, ReviewVersion::query()->where('check_number', 2)->count());
        $this->actingAs($owner)->get(route('portal.drafts.check', $draft))->assertOk()
            ->assertSee('Pegawai JKEJ akan menghubungi anda')->assertDontSee('dikemas kini secara automatik');
    }

    public function test_stuck_journeys_are_listed_for_staff(): void
    {
        [$draft, $owner, $journey] = $this->paid();
        $draft->documents()->delete();
        $this->actingAs($owner)->post(route('portal.drafts.check.amend', $draft), ['confirm_amendment' => '1']);
        $this->travel(2)->hours();

        $this->artisan('paid-check:stuck')->expectsOutputToContain($journey->application->application_reference)->assertSuccessful();
    }

    public function test_running_result_uses_a_persistent_poller(): void
    {
        Queue::fake([RunAirsCheck::class]);
        [$draft, $owner] = $this->paid();
        $this->actingAs($owner)->get(route('portal.drafts.edit', $draft))->assertOk()
            ->assertSee('data-check-poll-url', false)->assertSee('setInterval', false)->assertSee('X-Airs-Partial', false);
        $this->actingAs($owner)->get(route('portal.drafts.check', $draft))->assertOk()->assertHeader('X-Airs-Partial', '1');
    }
}
