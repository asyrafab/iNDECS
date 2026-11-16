<?php

declare(strict_types=1);

namespace App\Services\PaidCheck;

use App\Enums\PrecheckLabel;
use App\Events\CheckPaymentConfirmed;
use App\Models\ApplicantDraft;
use App\Models\AuditLog;
use App\Models\CheckPayment;
use App\Models\User;
use App\Services\Precheck\DraftChecklist;
use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

/**
 * The applicant's fee for the paid AIRS check. Payment never relies on a browser redirect:
 * only confirm(), called from a verified gateway callback, marks a payment paid.
 */
final class CheckPaymentService
{
    public function __construct(private readonly DraftChecklist $checklists, private readonly PaymentGateway $gateway) {}

    public static function feeSen(): ?int
    {
        $fee = config('airs.fee_sen');

        return $fee === null || $fee === '' ? null : (int) $fee;
    }

    public function start(User $owner, ApplicantDraft $draft, bool $acknowledgedMissing): CheckPayment
    {
        if ($draft->user_id !== $owner->id) {
            throw (new ModelNotFoundException)->setModel(ApplicantDraft::class, [$draft->id]);
        }
        $checklist = $this->assertReadyForCheck($draft);
        $fee = self::feeSen();
        if ($fee === null) {
            throw ValidationException::withMessages(['payment' => 'Fi semakan belum ditetapkan.']);
        }
        if (! $checklist['ready'] && ! $acknowledgedMissing) {
            throw ValidationException::withMessages(['acknowledge_missing' => 'Sahkan bahawa anda faham dokumen wajib belum lengkap sebelum membayar.']);
        }

        return DB::transaction(function () use ($owner, $draft, $fee): CheckPayment {
            ApplicantDraft::query()->whereKey($draft->id)->lockForUpdate()->firstOrFail();
            if (CheckPayment::query()->where('draft_id', $draft->id)->where('status', 'paid')->exists()) {
                throw ValidationException::withMessages(['payment' => 'Semakan AIRS bagi draf ini telah dibayar.']);
            }
            $pending = CheckPayment::query()->where('draft_id', $draft->id)->where('status', 'pending')->first();
            if ($pending !== null) {
                return $pending;
            }
            $payment = new CheckPayment;
            $payment->forceFill(['draft_id' => $draft->id, 'user_id' => $owner->id, 'amount_sen' => $fee,
                'reference' => 'PAY-'.$draft->application_type_id.'-'.Str::ulid(), 'status' => 'pending', 'gateway' => $this->gateway->name()])->save();
            AuditLog::query()->create(['auditable_type' => CheckPayment::class, 'auditable_id' => $payment->id, 'event' => 'check_payment_started',
                'actor_id' => $owner->id, 'before_values' => null, 'after_values' => ['draft_id' => $draft->id, 'amount_sen' => $fee], 'ip_address' => request()?->ip()]);

            return $payment;
        });
    }

    /**
     * A draft is ready for a paid (or waived) check: enabled AIRS module, lighting types declared, at least
     * one uploaded document and none still being prechecked or unreadable. Returns the draft checklist.
     */
    public const UNSORTED = 'Isih atau buang fail dalam "Belum diisih" sebelum membayar.';

    public function assertReadyForCheck(ApplicantDraft $draft): array
    {
        $module = config('airs.modules.'.$draft->application_type_id);
        if (! is_array($module) || ($module['enabled'] ?? false) !== true) {
            throw ValidationException::withMessages(['payment' => 'Semakan AIRS berbayar belum dibuka untuk jenis permohonan ini.']);
        }
        if ($draft->title_provisional) {
            throw ValidationException::withMessages(['payment' => 'Isi tajuk cadangan dan simpan draf sebelum membayar.']);
        }
        if ($draft->lightingTypes() === []) {
            throw ValidationException::withMessages(['payment' => 'Pilih jenis lampu sebelum semakan AIRS.']);
        }
        $checklist = $this->checklists->for($draft);
        // Only files in checklist items are handed over; a file left unsorted must never be silently dropped.
        if ($checklist['unsorted'] !== []) {
            throw ValidationException::withMessages(['payment' => self::UNSORTED]);
        }
        $withDocuments = array_filter($checklist['items'], fn (array $item) => $item['document'] !== null);
        if ($withDocuments === []) {
            throw ValidationException::withMessages(['payment' => 'Muat naik sekurang-kurangnya satu dokumen sebelum membayar.']);
        }
        if (collect($withDocuments)->contains(fn (array $item) => in_array($item['label'], [PrecheckLabel::Pending, PrecheckLabel::Unreadable], true))) {
            throw ValidationException::withMessages(['payment' => 'Gantikan dokumen yang tidak dapat dibaca dan tunggu semakan awal selesai sebelum membayar.']);
        }

        return $checklist;
    }

    public function checkoutUrl(CheckPayment $payment): string
    {
        return $this->gateway->checkoutUrl($payment);
    }

    /** Idempotent by gateway event id; dispatches CheckPaymentConfirmed once, after commit. */
    public function confirm(string $reference, string $gatewayEventId, CarbonImmutable $paidAt): CheckPayment
    {
        [$payment, $newlyPaid] = DB::transaction(function () use ($reference, $gatewayEventId, $paidAt): array {
            // Same lock order as start()/waive() (draft, then payments) so confirmations and waivers serialise.
            $draftId = CheckPayment::query()->where('reference', $reference)->value('draft_id') ?? abort(404);
            ApplicantDraft::query()->whereKey($draftId)->lockForUpdate()->firstOrFail();
            $payment = CheckPayment::query()->where('reference', $reference)->lockForUpdate()->firstOrFail();
            if ($payment->status === 'paid') {
                if ($payment->gateway_event_id !== $gatewayEventId) {
                    AuditLog::query()->create(['auditable_type' => CheckPayment::class, 'auditable_id' => $payment->id, 'event' => 'check_payment_duplicate_event',
                        'actor_id' => null, 'before_values' => null, 'after_values' => ['gateway_event_id' => $gatewayEventId], 'ip_address' => request()?->ip()]);
                }

                return [$payment, false];
            }
            if ($payment->status === 'cancelled') {
                // e.g. superseded by a waiver: a late gateway event is recorded but changes nothing (refund is a manual matter).
                AuditLog::query()->create(['auditable_type' => CheckPayment::class, 'auditable_id' => $payment->id, 'event' => 'check_payment_event_on_cancelled',
                    'actor_id' => null, 'before_values' => null, 'after_values' => ['gateway_event_id' => $gatewayEventId], 'ip_address' => request()?->ip()]);

                return [$payment, false];
            }
            $payment->forceFill(['status' => 'paid', 'gateway_event_id' => $gatewayEventId, 'paid_at' => $paidAt])->save();
            AuditLog::query()->create(['auditable_type' => CheckPayment::class, 'auditable_id' => $payment->id, 'event' => 'check_payment_confirmed',
                'actor_id' => null, 'before_values' => ['status' => 'pending'], 'after_values' => ['status' => 'paid', 'gateway_event_id' => $gatewayEventId],
                'ip_address' => request()?->ip()]);

            return [$payment, true];
        });
        // A retried callback re-dispatches when an earlier attempt was paid but its journey was never created
        // (the listener failed after the payment committed). The journey service is idempotent.
        if ($payment->status === 'paid' && ($newlyPaid || ! \App\Models\PaidCheckJourney::query()->where('draft_id', $payment->draft_id)->exists())) {
            CheckPaymentConfirmed::dispatch($payment->id);
        }

        return $payment->fresh();
    }

    /**
     * Audited fee waiver by an authorised JKEJ officer under an approved policy (cited in the reason).
     * Cancels any pending payment and clears the check exactly as a paid fee would.
     */
    public function waive(User $officer, ApplicantDraft $draft, string $reason): CheckPayment
    {
        abort_unless($officer->canPerformLjup('fee_waive'), 403, 'Kebenaran pengecualian fi diperlukan.');
        if (mb_strlen(trim($reason)) < 10) {
            throw ValidationException::withMessages(['reason' => 'Nyatakan sebab dan rujukan dasar pengecualian.']);
        }
        $this->assertReadyForCheck($draft);

        $payment = DB::transaction(function () use ($officer, $draft, $reason): CheckPayment {
            ApplicantDraft::query()->whereKey($draft->id)->lockForUpdate()->firstOrFail();
            // Lock every payment of the draft too, so a concurrent gateway confirmation is seen, not raced.
            CheckPayment::query()->where('draft_id', $draft->id)->lockForUpdate()->get();
            if (CheckPayment::query()->where('draft_id', $draft->id)->where('status', 'paid')->exists()
                || \App\Models\PaidCheckJourney::query()->where('draft_id', $draft->id)->exists()) {
                throw ValidationException::withMessages(['payment' => 'Semakan AIRS bagi draf ini telah dibayar atau dikecualikan.']);
            }
            CheckPayment::query()->where('draft_id', $draft->id)->where('status', 'pending')->update(['status' => 'cancelled', 'updated_at' => now()]);
            $payment = new CheckPayment;
            $payment->forceFill(['draft_id' => $draft->id, 'user_id' => $draft->user_id, 'amount_sen' => 0,
                'reference' => 'WAIVE-'.$draft->application_type_id.'-'.Str::ulid(), 'status' => 'paid', 'gateway' => 'waiver',
                'gateway_event_id' => 'waiver-'.Str::ulid(), 'paid_at' => now(), 'clearance_kind' => 'waived',
                'waived_by' => $officer->id, 'waiver_reason' => trim($reason)])->save();
            AuditLog::query()->create(['auditable_type' => CheckPayment::class, 'auditable_id' => $payment->id, 'event' => 'check_payment_waived',
                'actor_id' => $officer->id, 'before_values' => null, 'after_values' => ['draft_id' => $draft->id, 'reason' => trim($reason)], 'ip_address' => request()?->ip()]);

            return $payment;
        });
        CheckPaymentConfirmed::dispatch($payment->id);

        return $payment->fresh();
    }
}
