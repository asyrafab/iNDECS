<?php

declare(strict_types=1);

namespace App\Airs;

use Carbon\CarbonImmutable;
use InvalidArgumentException;

/** Proof that the applicant's check fee is cleared (paid) or formally waived. Supplied by iNDECS payment. */
final readonly class CheckClearance
{
    public function __construct(
        public string $kind,
        public string $reference,
        public CarbonImmutable $clearedAt,
    ) {
        if (! in_array($kind, ['paid', 'waived'], true)) {
            throw new InvalidArgumentException('Clearance kind must be paid or waived.');
        }
        if (trim($reference) === '') {
            throw new InvalidArgumentException('Clearance reference is required.');
        }
    }
}
