<?php

declare(strict_types=1);

namespace Tests\Feature\PaidCheck;

use App\Models\AcceptedSubmission;
use App\Models\AuditLog;
use App\Models\CheckPayment;
use App\Models\PaidCheckJourney;
use App\Models\User;
use App\Services\PaidCheck\CheckPaymentService;
use App\Services\PaidCheck\PaidCheckJourneyService;
use Carbon\CarbonImmutable;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Validation\ValidationException;
use Symfony\Component\HttpKernel\Exception\HttpException;
use Tests\Feature\Kejuruteraan\BuildsHandedOverLjup;
use Tests\TestCase;

final class FeeWaiverTest extends TestCase
{
    use BuildsHandedOverLjup, RefreshDatabase;

    private const REASON = 'Pengecualian fi di bawah dasar MBSP/JKEJ/2026/01 (projek awam).';

    protected function setUp(): void
    {
        parent::setUp();
        $this->setUpLjupOfficerWorld();
    }

    public function test_senior_engineer_waives_fee_and_check_runs(): void
    {
        $draft = $this->readyLjupDraft();
        $senior = $this->engineer('senior_engineering_officer');

        $this->artisan('paid-check:waive', ['draft' => $draft->id, '--actor' => $senior->email, '--reason' => self::REASON])->assertSuccessful();

        $payment = CheckPayment::query()->sole();
        $this->assertSame('waived', $payment->clearance_kind);
        $this->assertSame(0, $payment->amount_sen);
        $this->assertSame($senior->id, $payment->waived_by);
        $journey = PaidCheckJourney::query()->sole();
        app(PaidCheckJourneyService::class)->submitAsIs(User::query()->findOrFail($draft->user_id), $journey);
        $submission = AcceptedSubmission::query()->sole();
        $this->assertSame('waived', $submission->clearance_kind);
        $this->assertSame($senior->id, $submission->cleared_by);
        $this->assertSame(1, AuditLog::query()->where('event', 'check_payment_waived')->count());
    }

    public function test_engineering_officer_without_permission_is_refused(): void
    {
        $draft = $this->readyLjupDraft();
        $this->expectException(HttpException::class);
        app(CheckPaymentService::class)->waive($this->engineer('engineering_officer'), $draft, self::REASON);
    }

    public function test_waiver_refused_when_already_paid(): void
    {
        [, $journey] = $this->paidLjup(null, false);
        $draft = $journey->draft;
        try {
            app(CheckPaymentService::class)->waive($this->engineer('senior_engineering_officer'), $draft, self::REASON);
            $this->fail('Expected refusal');
        } catch (ValidationException $exception) {
            $this->assertArrayHasKey('payment', $exception->errors());
        }
        $this->assertSame(1, PaidCheckJourney::query()->count());
        $this->assertSame(1, CheckPayment::query()->count());
    }

    public function test_waiver_cancels_pending_payment(): void
    {
        $draft = $this->readyLjupDraft();
        $pending = app(CheckPaymentService::class)->start(User::query()->findOrFail($draft->user_id), $draft, true);
        app(CheckPaymentService::class)->waive($this->engineer('senior_engineering_officer'), $draft, self::REASON);
        $this->assertSame('cancelled', $pending->fresh()->status);
        $this->assertSame(1, CheckPayment::query()->where('status', 'paid')->count());
        // A late gateway confirmation of the cancelled payment changes nothing.
        app(CheckPaymentService::class)->confirm($pending->reference, 'late-evt', CarbonImmutable::now());
        $this->assertSame('cancelled', $pending->fresh()->status);
        $this->assertSame(1, PaidCheckJourney::query()->count());
    }

    public function test_waiver_reason_required(): void
    {
        $draft = $this->readyLjupDraft();
        $this->expectException(ValidationException::class);
        app(CheckPaymentService::class)->waive($this->engineer('senior_engineering_officer'), $draft, 'ok');
    }
}
