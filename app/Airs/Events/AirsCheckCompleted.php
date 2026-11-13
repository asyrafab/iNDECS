<?php

declare(strict_types=1);

namespace App\Airs\Events;

use Illuminate\Foundation\Events\Dispatchable;

/** An AIRS check reached COMPLETED (dispatched once per completed run). */
final class AirsCheckCompleted
{
    use Dispatchable;

    public function __construct(
        public readonly string $reviewVersionId,
        public readonly string $applicationId,
        public readonly int $checkNumber,
    ) {
    }
}
