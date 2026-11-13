<?php

declare(strict_types=1);

namespace App\Events;

use Illuminate\Foundation\Events\Dispatchable;

/** A paid-check fee was confirmed by the payment gateway (dispatched once per payment). */
final class CheckPaymentConfirmed
{
    use Dispatchable;

    public function __construct(public readonly string $paymentId)
    {
    }
}
