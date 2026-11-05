<?php

declare(strict_types=1);

namespace App\Jobs;

use App\Contracts\TextExtractor;
use App\Models\ApplicantDraftDocument;
use App\Models\DocumentRequirement;
use Illuminate\Contracts\Queue\ShouldBeUnique;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Support\Facades\Storage;

/**
 * Free pre-check of one uploaded draft file (ADR-363): embedded text or local OCR only,
 * keyword matching, no AI and no network. One active check per draft; never stores the
 * extracted text.
 */
final class PrecheckDraftDocument implements ShouldBeUnique, ShouldQueue
{
    use Queueable;

    public int $tries = 1;

    public int $timeout = 300;

    public int $uniqueFor = 600;

    public function __construct(public readonly string $documentId)
    {
        $this->onQueue('precheck');
    }

    public function uniqueId(): string
    {
        return (string) (ApplicantDraftDocument::query()->whereKey($this->documentId)->value('draft_id') ?? $this->documentId);
    }

    public function handle(TextExtractor $extractor): void
    {
        $document = ApplicantDraftDocument::query()->find($this->documentId);
        if ($document === null || $document->precheck_status !== 'pending') {
            return;
        }
        $currentSequence = (int) ApplicantDraftDocument::query()->where('draft_id', $document->draft_id)
            ->where('item_code', $document->item_code)->max('sequence');
        if ($document->sequence !== $currentSequence) {
            // Superseded by a newer upload: the newer file gets its own check.
            $document->forceFill(['precheck_status' => 'done', 'readable' => null])->save();

            return;
        }
        $result = $extractor->extract(Storage::disk('local')->path($document->path), $document->mime);
        $text = mb_strtoupper((string) preg_replace('/\s+/u', ' ', $result->text));
        $chars = mb_strlen(trim($text));
        $keywords = array_values(DocumentRequirement::query()->where('document_type_id', $document->document_type_id)
            ->where('is_active', true)->first()?->precheck_keywords ?? []);
        $matched = array_values(array_filter($keywords, fn (string $word) => str_contains($text, (string) preg_replace('/\s+/u', ' ', mb_strtoupper($word)))));
        $document->forceFill([
            'precheck_status' => 'done',
            'readable' => ! $result->corrupt && ($result->ocrUnavailable || $chars >= (int) config('kaunter.precheck.min_text_chars', 30)),
            'text_chars' => $chars,
            'matched_keywords' => $matched,
            'ocr_used' => $result->ocrUsed,
            'ocr_unavailable' => $result->ocrUnavailable,
        ])->save();
    }
}
