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
        $module = config('airs.modules.'.$draft->application_type_id);
        if (! is_array($module) || ($module['enabled'] ?? false) !== true) {
            throw ValidationException::withMessages(['payment' => 'Semakan AIRS berbayar belum dibuka untuk jenis permohonan ini.']);
        }
        $fee = self::feeSen();
        if ($fee === null) {
            throw ValidationException::withMessages(['payment' => 'Fi semakan belum ditetapkan.']);
        }
        $checklist = $this->checklists->for($draft);
        $withDocuments = array_filter($checklist['items'], fn (array $item) => $item['document'] !== null);
        if ($withDocuments === []) {
            throw ValidationException::withMessages(['payment' => 'Muat naik sekurang-kurangnya satu dokumen sebelum membayar.']);
        }
        if (collect($withDocuments)->contains(fn (array $item) => in_array($item['label'], [PrecheckLabel::Pending, PrecheckLabel::Unreadable], true))) {
            throw ValidationException::withMessages(['payment' => 'Gantikan dokumen yang tidak dapat dibaca dan tunggu semakan awal selesai sebelum membayar.']);
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

    public function checkoutUrl(CheckPayment $payment): string
    {
        return $this->gateway->checkoutUrl($payment);
    }

    /** Idempotent by gateway event id; dispatches CheckPaymentConfirmed once, after commit. */
    public function confirm(string $reference, string $gatewayEventId, CarbonImmutable $paidAt): CheckPayment
    {
        [$payment, $newlyPaid] = DB::transaction(function () use ($reference, $gatewayEventId, $paidAt): array {
            $payment = CheckPayment::query()->where('reference', $reference)->lockForUpdate()->firstOrFail();
            if ($payment->status === 'paid') {
                if ($payment->gateway_event_id !== $gatewayEventId) {
                    AuditLog::query()->create(['auditable_type' => CheckPayment::class, 'auditable_id' => $payment->id, 'event' => 'check_payment_duplicate_event',
                        'actor_id' => null, 'before_values' => null, 'after_values' => ['gateway_event_id' => $gatewayEventId], 'ip_address' => request()?->ip()]);
                }

                return [$payment, false];
            }
            $payment->forceFill(['status' => 'paid', 'gateway_event_id' => $gatewayEventId, 'paid_at' => $paidAt])->save();
            AuditLog::query()->create(['auditable_type' => CheckPayment::class, 'auditable_id' => $payment->id, 'event' => 'check_payment_confirmed',
                'actor_id' => null, 'before_values' => ['status' => 'pending'], 'after_values' => ['status' => 'paid', 'gateway_event_id' => $gatewayEventId],
                'ip_address' => request()?->ip()]);

            return [$payment, true];
        });
        if ($newlyPaid) {
            CheckPaymentConfirmed::dispatch($payment->id);
        }

        return $payment->fresh();
    }
}
