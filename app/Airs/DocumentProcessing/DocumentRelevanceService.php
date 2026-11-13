<?php

declare(strict_types=1);

namespace App\Airs\DocumentProcessing;

use App\Airs\Exceptions\NoRelevantDocumentException;
use App\Airs\Models\ReviewCase;
use App\Airs\Models\ReviewVersion;

final class DocumentRelevanceService
{
    private const IRRELEVANT_TYPE = 'IRRELEVANT_TO_SELECTED_REVIEW';

    /**
     * Accept the submission when at least one processed document is relevant,
     * or when relevance remains uncertain and therefore requires officer review.
     * Reject only when every usable document is explicitly classified as unrelated.
     *
     * @return array{accepted: int, ignored: int}
     */
    public function assertAtLeastOneRelevantDocument(ReviewCase $case, ReviewVersion $version): array
    {
        $classifications = $version->classifications()
            ->with('file')
            ->get()
            ->filter(fn ($classification): bool => $classification->file !== null
                && $classification->file->processing_status === 'PROCESSED'
                && (int) $classification->file->page_count > 0);

        $irrelevant = $classifications->filter(fn ($classification): bool =>
            $classification->primary_type === self::IRRELEVANT_TYPE
            && (float) $classification->confidence >= 0.8
        );
        $accepted = $classifications->reject(fn ($classification): bool => $irrelevant->contains('id', $classification->id));

        if ($accepted->isEmpty()) {
            throw new NoRelevantDocumentException('No uploaded document matches the selected plan review.');
        }

        if ($irrelevant->isNotEmpty()) {
            $warnings = $case->warnings ?? [];
            foreach ($irrelevant as $classification) {
                $warnings[] = "{$classification->file->original_name}: diketepikan kerana tidak berkaitan dengan semakan yang dipilih.";
            }
            $case->forceFill(['warnings' => array_values(array_unique($warnings))])->save();
        }

        return ['accepted' => $accepted->count(), 'ignored' => $irrelevant->count()];
    }
}
