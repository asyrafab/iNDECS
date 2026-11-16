<?php

declare(strict_types=1);

namespace App\Services\Precheck;

use App\Models\ApplicantDraftDocument;

/** Outcome of one dropped file or ZIP: the files kept (unsorted) and the ones turned away, with the reason. */
final readonly class DropResult
{
    /**
     * @param  list<ApplicantDraftDocument>  $accepted
     * @param  list<array{name: string, reason: string}>  $rejected
     */
    public function __construct(public array $accepted = [], public array $rejected = []) {}

    public function merge(self $other): self
    {
        return new self([...$this->accepted, ...$other->accepted], [...$this->rejected, ...$other->rejected]);
    }
}
