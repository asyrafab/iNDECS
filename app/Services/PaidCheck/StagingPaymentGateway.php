<?php

declare(strict_types=1);

namespace App\Services\PaidCheck;

use App\Models\CheckPayment;

/** Test-only gateway: a confirmation page instead of a real payment. Refused in production. */
final class StagingPaymentGateway implements PaymentGateway
{
    public static function available(): bool
    {
        return ! app()->isProduction() && config('airs.payment_driver') === 'staging';
    }

    public function name(): string
    {
        return 'staging';
    }

    public function checkoutUrl(CheckPayment $payment): string
    {
        return route('portal.payments.staging', $payment);
    }
}
