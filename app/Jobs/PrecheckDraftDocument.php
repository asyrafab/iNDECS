<?php

declare(strict_types=1);

namespace App\Jobs;

use App\Contracts\DocumentScanner;
use App\Contracts\TextExtractor;
use App\Models\ApplicantDraft;
use App\Models\ApplicantDraftDocument;
use App\Models\DocumentRequirement;
use App\Services\Precheck\DocumentSorter;
use App\Services\Precheck\DraftChecklist;
use App\Services\Precheck\DraftDocumentPlacer;
use App\Services\Precheck\DraftParticularsReader;
use App\Services\Precheck\DraftPrefiller;
use App\Services\Precheck\ExtractedText;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Queue\Middleware\WithoutOverlapping;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\ValidationException;
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
        if ($document === null || $document->precheck_status !== 'pending'
            || ! ApplicantDraft::query()->whereKey($document->draft_id)->exists()) {
            return;
        }
        if ($document->item_code !== null) {
            if ($document->superseded_at !== null) {
                // Superseded by a newer upload: the newer file gets its own check.
                $document->forceFill(['precheck_status' => 'done', 'readable' => null])->save();

                return;
            }
        }
        if ($document->scan_pending) {
            try {
                app(DocumentScanner::class)->assertClean(Storage::disk('local')->path($document->path));
            } catch (ValidationException $exception) {
                self::reject($document, (string) collect($exception->errors())->flatten()->first());

                return;
            }
            $document->forceFill(['scan_pending' => false])->save();
        }
        // Beyond the draft's reading budget a dropped file is sorted by its name only and left for the applicant to confirm.
        $result = $this->read
            ? $extractor->extract(Storage::disk('local')->path($document->path), $document->mime)
            : new ExtractedText('', ocrUnavailable: true);
        $text = mb_strtoupper((string) preg_replace('/\s+/u', ' ', $result->text));
        if ($document->item_code === null) {
            $items = app(DraftChecklist::class)->catalogueItems((string) ApplicantDraft::query()->whereKey($document->draft_id)->value('application_type_id'));
            app(DraftDocumentPlacer::class)->autoPlace($document, app(DocumentSorter::class)->rank($document->original_name, $text, $items));
            $document->refresh();
        }
        if ($this->read && $text !== '') {
            // Upload-first part 2: fill the draft's still-empty details; the text itself is never stored.
            app(DraftPrefiller::class)->apply($document, app(DraftParticularsReader::class)->read($text));
        }
        $keywords = $document->document_type_id === null ? [] : array_values(DocumentRequirement::query()->where('document_type_id', $document->document_type_id)
            ->where('is_active', true)->first()?->precheck_keywords ?? []);
        $matched = array_values(array_filter($keywords, fn (string $word) => str_contains($text, (string) preg_replace('/\s+/u', ' ', mb_strtoupper($word)))));
        $document->forceFill([
            'precheck_status' => 'done',
            // Only a damaged file is unreadable; little text (photos, stamps) is left for the applicant to confirm.
            'readable' => ! $result->corrupt,
            'text_chars' => $this->read ? mb_strlen(trim($text)) : null,
            'matched_keywords' => $matched,
            'ocr_used' => $result->ocrUsed,
            'ocr_unavailable' => $result->ocrUnavailable,
        ])->save();
    }

    /** A killed or timed-out check must never leave the applicant on "Sedang disemak". */
    public function failed(?Throwable $exception = null): void
    {
        $unscanned = ApplicantDraftDocument::query()->whereKey($this->documentId)->where('scan_pending', true)->first();
        if ($unscanned !== null) {
            // Fail closed: a file that was never scanned is never kept.
            self::reject($unscanned, 'Imbasan keselamatan tidak dapat diselesaikan. Fail dibuang; sila muat naik semula.');

            return;
        }
        ApplicantDraftDocument::query()->whereKey($this->documentId)->where('precheck_status', 'pending')
            ->update(['precheck_status' => 'done', 'readable' => null, 'ocr_unavailable' => true, 'updated_at' => now()]);
    }

    /** A dropped file the scanner refused: its bytes are deleted and the row stays in the tray with the reason until removed. */
    private static function reject(ApplicantDraftDocument $document, string $reason): void
    {
        Storage::disk('local')->delete($document->path);
        $document->forceFill(['item_code' => null, 'document_type_id' => null, 'sequence' => 0, 'scan_pending' => false,
            'rejected_reason' => mb_substr($reason !== '' ? $reason : 'Dokumen ditolak oleh imbasan keselamatan.', 0, 200),
            'precheck_status' => 'done', 'readable' => false, 'matched_keywords' => []])->save();
    }
}
