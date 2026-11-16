<?php

declare(strict_types=1);

namespace App\Services\Precheck;

use App\Contracts\DocumentScanner;
use App\Enums\PrecheckLabel;
use App\Jobs\PrecheckDraftDocument;
use App\Models\ApplicantDraft;
use App\Models\ApplicantDraftDocument;
use App\Models\DocumentType;
use App\Models\User;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\ValidationException;
use Throwable;

/**
 * Applicant uploads for the free pre-check (ADR-363): owner-only, validated against the
 * item's document type, virus-scanned before storage (fail closed), de-duplicated by
 * SHA-256, then queued for a local text check. Nothing here creates an application.
 */
final class DraftDocumentService
{
    public function __construct(private readonly DraftChecklist $checklist, private readonly DocumentScanner $scanner) {}

    public function upload(User $owner, ApplicantDraft $draft, string $itemCode, UploadedFile $file): ApplicantDraftDocument
    {
        $this->authorize($owner, $draft);
        $item = collect($this->checklist->for($draft)['items'])->firstWhere('code', $itemCode);
        if ($item === null) {
            throw ValidationException::withMessages(['file' => 'Dokumen ini tidak diperlukan untuk projek anda.']);
        }
        $rules = DraftFileRules::forItem($draft, $itemCode);
        $type = $rules['document_type'];
        $extensions = $rules['extensions'];
        $sniffed = DraftFileRules::assertFits($file, $rules);

        $sha = hash_file('sha256', $file->getRealPath());
        $current = ApplicantDraftDocument::query()->where('draft_id', $draft->id)->where('item_code', $itemCode)->orderByDesc('sequence')->first();
        if ($current !== null && hash_equals($current->sha256, $sha)) {
            return $current;
        }
        if (ApplicantDraftDocument::query()->where('draft_id', $draft->id)->count() >= (int) config('kaunter.precheck.max_documents_per_draft', 60)) {
            throw ValidationException::withMessages(['file' => 'Had muat naik bagi draf ini telah dicapai.']);
        }
        try {
            $this->scanner->assertClean($file->getRealPath());
        } catch (ValidationException $e) {
            throw ValidationException::withMessages(['file' => array_merge(...array_values($e->errors()))]);
        }

        $extension = in_array(strtolower((string) $file->guessExtension()), $extensions, true) ? strtolower((string) $file->guessExtension()) : $extensions[0];
        $document = DB::transaction(function () use ($owner, $draft, $itemCode, $file, $type, $sha, $extension, $sniffed): ApplicantDraftDocument {
            ApplicantDraft::query()->whereKey($draft->id)->lockForUpdate()->firstOrFail();
            $sequence = (int) ApplicantDraftDocument::query()->where('draft_id', $draft->id)->where('item_code', $itemCode)->max('sequence') + 1;
            // A unique name: a file that left this item for the tray keeps its old path, so a sequence-based name could overwrite it.
            $path = "drafts/{$draft->id}/{$itemCode}/{$sequence}-".\Illuminate\Support\Str::ulid().".{$extension}";
            if (! Storage::disk('local')->putFileAs(dirname($path), $file, basename($path))) {
                throw ValidationException::withMessages(['file' => 'Fail tidak dapat disimpan. Cuba lagi.']);
            }
            try {
                $document = new ApplicantDraftDocument;
                $document->forceFill(['draft_id' => $draft->id, 'item_code' => $itemCode, 'document_type_id' => $type->id,
                    'sequence' => $sequence, 'original_name' => mb_substr($file->getClientOriginalName(), 0, 255), 'path' => $path,
                    'mime' => $sniffed, 'size_bytes' => $file->getSize(), 'sha256' => $sha,
                    'uploaded_by' => $owner->id, 'precheck_status' => 'pending', 'sort_source' => 'manual'])->save();

                return $document;
            } catch (Throwable $e) {
                Storage::disk('local')->delete($path);
                throw $e;
            }
        });
        PrecheckDraftDocument::dispatch($document->id)->afterCommit();

        return $document;
    }

    public function confirm(User $owner, ApplicantDraftDocument $document): void
    {
        $draft = $document->draft;
        $this->authorize($owner, $draft);
        $item = collect($this->checklist->for($draft)['items'])->firstWhere('code', $document->item_code);
        if ($item === null || $item['document']?->id !== $document->id) {
            throw ValidationException::withMessages(['file' => 'Dokumen ini bukan versi semasa.']);
        }
        if ($item['label'] === PrecheckLabel::Unreadable) {
            throw ValidationException::withMessages(['file' => 'Fail tidak dapat dibaca. Sila muat naik semula.']);
        }
        if ($item['label'] !== PrecheckLabel::NeedsConfirmation) {
            throw ValidationException::withMessages(['file' => 'Dokumen ini tidak memerlukan pengesahan.']);
        }
        $document->forceFill(['confirmed_at' => now()])->save();
    }

    /** The applicant places a tray file into an applicable checklist item; the item's file rules apply. */
    public function assign(User $owner, ApplicantDraftDocument $document, string $itemCode): void
    {
        $draft = $document->draft;
        $this->authorize($owner, $draft);
        $checklist = $this->checklist->for($draft);
        if (! collect($checklist['unsorted'])->contains(fn (ApplicantDraftDocument $tray) => $tray->id === $document->id)) {
            throw ValidationException::withMessages(['file' => 'Fail ini sudah berada dalam senarai semak.']);
        }
        if ($document->scan_pending) {
            throw ValidationException::withMessages(['file' => 'Tunggu imbasan keselamatan fail ini selesai.']);
        }
        if ($document->rejected_reason !== null) {
            throw ValidationException::withMessages(['file' => 'Fail ini ditolak oleh imbasan keselamatan dan perlu dibuang.']);
        }
        if (collect($checklist['items'])->firstWhere('code', $itemCode) === null) {
            throw ValidationException::withMessages(['file' => 'Dokumen ini tidak diperlukan untuk projek anda.']);
        }
        DraftFileRules::assertFits(Storage::disk('local')->path($document->path), DraftFileRules::forItem($draft, $itemCode));
        $staleItem = $document->item_code;
        app(DraftDocumentPlacer::class)->place($document, $itemCode, 'manual');
        $document->forceFill(['confirmed_at' => null])->save();
        $this->clearStaleHistory($draft, $staleItem, $checklist);
    }

    /** Removes a tray file (never a file placed in the checklist). */
    public function remove(User $owner, ApplicantDraftDocument $document): void
    {
        $draft = $document->draft;
        $this->authorize($owner, $draft);
        if (! collect($this->checklist->for($draft)['unsorted'])->contains(fn (ApplicantDraftDocument $tray) => $tray->id === $document->id)) {
            throw ValidationException::withMessages(['file' => 'Hanya fail yang belum dipadankan boleh dibuang. Gantikan dokumen senarai semak dengan muat naik semula.']);
        }
        $path = $document->path;
        $staleItem = $document->item_code;
        $document->delete();
        Storage::disk('local')->delete($path);
        $this->clearStaleHistory($draft, $staleItem, $this->checklist->for($draft));
    }

    /**
     * Older versions of an item that no longer applies would otherwise surface in the tray one by one
     * after the applicant deals with the current one; they are history of a dropped item and are removed together.
     */
    private function clearStaleHistory(ApplicantDraft $draft, ?string $itemCode, array $checklist): void
    {
        if ($itemCode === null || collect($checklist['items'])->contains(fn (array $item) => $item['code'] === $itemCode)) {
            return;
        }
        ApplicantDraftDocument::query()->where('draft_id', $draft->id)->where('item_code', $itemCode)->get()
            ->each(function (ApplicantDraftDocument $old): void {
                $path = $old->path;
                $old->delete();
                Storage::disk('local')->delete($path);
            });
    }

    private function authorize(User $owner, ApplicantDraft $draft): void
    {
        abort_unless($draft->user_id === $owner->id, 404);
        abort_unless($owner->email_verified_at !== null, 403, 'Sahkan emel anda melalui halaman Akaun saya sebelum menyediakan draf.');
        // After payment, documents change only inside the single amendment window.
        abort_unless(\App\Services\PaidCheck\PaidCheckJourneyService::draftEditable($draft), 403, 'Draf dikunci semasa semakan AIRS.');
    }
}
