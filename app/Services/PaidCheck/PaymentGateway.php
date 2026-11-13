<?php

declare(strict_types=1);

namespace App\Services\PaidCheck;

use App\Models\CheckPayment;

/**
 * Where the applicant pays the AIRS check fee. The gateway's verified callback must call
 * CheckPaymentService::confirm(); a browser redirect alone is never proof of payment.
 */
interface PaymentGateway
{
    public function name(): string;

    public function checkoutUrl(CheckPayment $payment): string;
}
