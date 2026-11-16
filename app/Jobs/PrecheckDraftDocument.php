<?php

declare(strict_types=1);

namespace App\Jobs;

use App\Contracts\TextExtractor;
use App\Models\ApplicantDraftDocument;
use App\Models\DocumentRequirement;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Queue\Middleware\WithoutOverlapping;
use Illuminate\Support\Facades\Storage;
use Throwable;

/**
 * Free pre-check of one uploaded draft file (ADR-363): embedded text or local OCR only,
 * keyword matching, no AI and no network. One active check per draft: a second job for
 * the same draft waits (WithoutOverlapping) and is never discarded. If the job dies, the
 * file falls back to "Perlu pengesahan pemohon" so the applicant is never stuck.
 * Never stores the extracted text.
 */
final class PrecheckDraftDocument implements ShouldQueue
{
    use Queueable;

    /** Releases while another check of the same draft runs count as attempts; keep generous. */
    public int $tries = 40;

    public int $timeout = 300;

    public readonly string $draftId;

    /** @param bool $read false = sort by file name only (beyond the draft's reading budget). */
    public function __construct(public readonly string $documentId, public readonly bool $read = true)
    {
        $this->draftId = (string) (ApplicantDraftDocument::query()->whereKey($documentId)->value('draft_id') ?? $documentId);
        $this->onQueue('precheck');
    }

    /** @return list<object> */
    public function middleware(): array
    {
        return [(new WithoutOverlapping($this->draftId))->releaseAfter(15)->expireAfter($this->timeout + 60)];
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
        $keywords = array_values(DocumentRequirement::query()->where('document_type_id', $document->document_type_id)
            ->where('is_active', true)->first()?->precheck_keywords ?? []);
        $matched = array_values(array_filter($keywords, fn (string $word) => str_contains($text, (string) preg_replace('/\s+/u', ' ', mb_strtoupper($word)))));
        $document->forceFill([
            'precheck_status' => 'done',
            // Only a damaged file is unreadable; little text (photos, stamps) is left for the applicant to confirm.
            'readable' => ! $result->corrupt,
            'text_chars' => mb_strlen(trim($text)),
            'matched_keywords' => $matched,
            'ocr_used' => $result->ocrUsed,
            'ocr_unavailable' => $result->ocrUnavailable,
        ])->save();
    }

    /** A killed or timed-out check must never leave the applicant on "Sedang disemak". */
    public function failed(?Throwable $exception = null): void
    {
        ApplicantDraftDocument::query()->whereKey($this->documentId)->where('precheck_status', 'pending')
            ->update(['precheck_status' => 'done', 'readable' => null, 'ocr_unavailable' => true, 'updated_at' => now()]);
    }
}
