<?php

declare(strict_types=1);

namespace Tests\Feature\PaidCheck;

use App\Airs\Jobs\RunAirsCheck;
use App\Events\CheckPaymentConfirmed;
use App\Models\ApplicantDraft;
use App\Models\AuditLog;
use App\Models\CheckPayment;
use App\Models\User;
use App\Services\PaidCheck\CheckPaymentService;
use App\Services\PaidCheck\PaidCheckJourneyService;
use Carbon\CarbonImmutable;
use Illuminate\Foundation\Http\Middleware\PreventRequestForgery;
use Illuminate\Foundation\Http\Middleware\ValidateCsrfToken;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Queue;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

final class CheckPaymentTest extends TestCase
{
    use BuildsLjupJourney, RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed();
        Storage::fake('local');
        Event::fake([CheckPaymentConfirmed::class]);
        $this->seedLjupCatalogue();
        config(['airs.fee_sen' => 15000, 'airs.payment_driver' => 'staging']);
    }

    public function test_pay_button_disabled_when_fee_not_set(): void
    {
        config(['airs.fee_sen' => null]);
        $draft = $this->readyLjupDraft();
        $this->actingAs($draft->user_id === null ? null : User::find($draft->user_id))
            ->get(route('portal.drafts.edit', $draft))->assertOk()->assertSee('Fi semakan belum ditetapkan');
    }

    public function test_banner_reflects_whether_the_paid_check_is_open(): void
    {
        $draft = $this->readyLjupDraft();
        $owner = User::find($draft->user_id);
        $this->actingAs($owner)->get(route('portal.drafts.edit', $draft))->assertOk()
            ->assertDontSee('Fi, bayaran dan penghantaran rasmi belum dibuka')->assertSee('Semakan AIRS berbayar dibuka')
            ->assertDontSee('Kejuruteraan dan Landskap akan menyusul')->assertSee('Landskap akan menyusul');
        config(['airs.fee_sen' => null]);
        $this->actingAs($owner)->get(route('portal.drafts.edit', $draft))->assertOk()
            ->assertSee('Fi, bayaran dan penghantaran rasmi belum dibuka');
    }

    public function test_pay_button_shows_fee_when_set(): void
    {
        $draft = $this->readyLjupDraft();
        $this->actingAs(User::find($draft->user_id))->get(route('portal.drafts.edit', $draft))->assertOk()
            ->assertSee('Bayar semakan AIRS (RM150.00)');
    }

    public function test_owner_starts_payment_and_is_sent_to_staging_checkout(): void
    {
        $draft = $this->readyLjupDraft();
        $owner = User::find($draft->user_id);

        $response = $this->actingAs($owner)->post(route('portal.drafts.payment.start', $draft));

        $payment = CheckPayment::query()->sole();
        $this->assertSame('pending', $payment->status);
        $this->assertSame(15000, $payment->amount_sen);
        $this->assertStringStartsWith('PAY-LJUP-', $payment->reference);
        $response->assertRedirect(route('portal.payments.staging', $payment));
        $this->actingAs($owner)->get(route('portal.payments.staging', $payment))->assertOk()->assertSee('Sahkan bayaran ujian');
    }

    public function test_starting_twice_reuses_the_pending_payment(): void
    {
        $draft = $this->readyLjupDraft();
        $owner = User::find($draft->user_id);
        $this->actingAs($owner)->post(route('portal.drafts.payment.start', $draft));
        $this->actingAs($owner)->post(route('portal.drafts.payment.start', $draft));
        $this->assertSame(1, CheckPayment::query()->count());
    }

    public function test_payment_with_missing_required_needs_acknowledgement(): void
    {
        $owner = $this->applicant();
        $draft = $this->ljupDraft($owner);
        $this->ljupUpload($draft, 'pelan_lampu');

        $this->actingAs($owner)->post(route('portal.drafts.payment.start', $draft))->assertSessionHasErrors('acknowledge_missing');
        $this->assertSame(0, CheckPayment::query()->count());

        $this->actingAs($owner)->post(route('portal.drafts.payment.start', $draft), ['acknowledge_missing' => '1'])->assertRedirect();
        $this->assertSame(1, CheckPayment::query()->count());
    }

    public function test_payment_refused_while_a_document_is_unreadable_or_pending(): void
    {
        $owner = $this->applicant();
        $draft = $this->ljupDraft($owner);
        $this->ljupUpload($draft, 'pelan_lampu', null, ['readable' => false]);
        $this->ljupUpload($draft, 'laporan_fotometrik', null, ['precheck_status' => 'pending']);

        $this->actingAs($owner)->post(route('portal.drafts.payment.start', $draft), ['acknowledge_missing' => '1'])->assertSessionHasErrors('payment');
        $this->assertSame(0, CheckPayment::query()->count());
    }

    public function test_payment_refused_without_any_document(): void
    {
        $owner = $this->applicant();
        $draft = $this->ljupDraft($owner);
        $this->actingAs($owner)->post(route('portal.drafts.payment.start', $draft), ['acknowledge_missing' => '1'])->assertSessionHasErrors('payment');
    }

    public function test_other_user_cannot_start_or_confirm_payment(): void
    {
        $draft = $this->readyLjupDraft();
        $owner = User::find($draft->user_id);
        $stranger = $this->applicant('stranger@example.test');
        $this->actingAs($stranger)->post(route('portal.drafts.payment.start', $draft))->assertNotFound();
        $this->actingAs($owner)->post(route('portal.drafts.payment.start', $draft));
        $payment = CheckPayment::query()->sole();
        $this->actingAs($stranger)->get(route('portal.payments.staging', $payment))->assertNotFound();
        $this->actingAs($stranger)->post(route('portal.payments.staging.confirm', $payment))->assertNotFound();
        $this->assertSame('pending', $payment->fresh()->status);
    }

    public function test_staging_confirmation_marks_paid_and_dispatches_event(): void
    {
        $draft = $this->readyLjupDraft();
        $owner = User::find($draft->user_id);
        $this->actingAs($owner)->post(route('portal.drafts.payment.start', $draft));
        $payment = CheckPayment::query()->sole();

        $this->actingAs($owner)->post(route('portal.payments.staging.confirm', $payment))->assertRedirect(route('portal.drafts.edit', $draft));

        $this->assertSame('paid', $payment->fresh()->status);
        $this->assertNotNull($payment->fresh()->paid_at);
        Event::assertDispatchedTimes(CheckPaymentConfirmed::class, 1);
    }

    public function test_confirm_is_idempotent_for_same_event(): void
    {
        $draft = $this->readyLjupDraft();
        $payment = app(CheckPaymentService::class)->start(User::find($draft->user_id), $draft, false);
        $service = app(CheckPaymentService::class);

        $service->confirm($payment->reference, 'evt-1', CarbonImmutable::now());
        $service->confirm($payment->reference, 'evt-1', CarbonImmutable::now());
        $service->confirm($payment->reference, 'evt-2', CarbonImmutable::now());

        $this->assertSame('evt-1', $payment->fresh()->gateway_event_id);
        $this->assertSame(1, AuditLog::query()->where('event', 'check_payment_confirmed')->count());
        $this->assertSame(1, AuditLog::query()->where('event', 'check_payment_duplicate_event')->count());
        // The listener is faked, so no journey exists: each retry re-dispatches so a lost journey can be
        // recovered (the journey service is idempotent; see JourneyReviewFixesTest).
        Event::assertDispatchedTimes(CheckPaymentConfirmed::class, 3);
    }

    public function test_confirm_does_not_redispatch_once_the_journey_exists(): void
    {
        Event::fake([CheckPaymentConfirmed::class]);
        $draft = $this->readyLjupDraft();
        $payment = app(CheckPaymentService::class)->start(User::find($draft->user_id), $draft, false);
        $service = app(CheckPaymentService::class);
        $service->confirm($payment->reference, 'evt-1', CarbonImmutable::now());
        Event::fake([CheckPaymentConfirmed::class]);
        Queue::fake([RunAirsCheck::class]);
        app(PaidCheckJourneyService::class)->paid($payment->fresh());

        $service->confirm($payment->reference, 'evt-1', CarbonImmutable::now());

        Event::assertNotDispatched(CheckPaymentConfirmed::class);
    }

    public function test_paid_draft_cannot_start_another_payment(): void
    {
        $draft = $this->readyLjupDraft();
        $owner = User::find($draft->user_id);
        $payment = app(CheckPaymentService::class)->start($owner, $draft, false);
        app(CheckPaymentService::class)->confirm($payment->reference, 'evt-1', CarbonImmutable::now());
        $this->actingAs($owner)->post(route('portal.drafts.payment.start', $draft))->assertSessionHasErrors('payment');
        $this->assertSame(1, CheckPayment::query()->count());
    }

    public function test_staging_gateway_refused_in_production(): void
    {
        $draft = $this->readyLjupDraft();
        $owner = User::find($draft->user_id);
        $payment = app(CheckPaymentService::class)->start($owner, $draft, false);
        $this->app->detectEnvironment(fn () => 'production');
        $this->withoutMiddleware([PreventRequestForgery::class, ValidateCsrfToken::class]); // CSRF is only bypassed in 'testing'
        $this->actingAs($owner)->get(route('portal.payments.staging', $payment))->assertNotFound();
        $this->actingAs($owner)->post(route('portal.payments.staging.confirm', $payment))->assertNotFound();
        $this->assertSame('pending', $payment->fresh()->status);
    }

    public function test_non_ljup_draft_cannot_pay(): void
    {
        $owner = $this->applicant();
        $draft = new ApplicantDraft(['application_type_id' => 'PKM', 'title' => 'Rumah']);
        $draft->user_id = $owner->id;
        $draft->version = 1;
        $draft->save();
        $this->actingAs($owner)->post(route('portal.drafts.payment.start', $draft), ['acknowledge_missing' => '1'])->assertSessionHasErrors('payment');
    }
}
