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
    private const MIMES = ['pdf' => 'application/pdf', 'jpg' => 'image/jpeg', 'jpeg' => 'image/jpeg', 'png' => 'image/png'];

    public function __construct(private readonly DraftChecklist $checklist, private readonly DocumentScanner $scanner) {}

    public function upload(User $owner, ApplicantDraft $draft, string $itemCode, UploadedFile $file): ApplicantDraftDocument
    {
        $this->authorize($owner, $draft);
        $item = collect($this->checklist->for($draft)['items'])->firstWhere('code', $itemCode);
        if ($item === null) {
            throw ValidationException::withMessages(['file' => 'Dokumen ini tidak diperlukan untuk projek anda.']);
        }
        $type = DocumentType::query()->where('code', \App\Services\DocumentCatalogueService::documentTypeCode($draft->application_type_id, $itemCode))->firstOrFail();
        $extensions = array_values(array_intersect($type->allowedExtensions(), array_keys(self::MIMES)));
        $mimes = array_values(array_unique(array_map(fn ($ext) => self::MIMES[$ext], $extensions)));
        Validator::make(['file' => $file], ['file' => ['required', 'file', 'mimes:'.implode(',', $extensions),
            'mimetypes:'.implode(',', $mimes), 'max:'.(int) $type->max_file_size_kb]], [], ['file' => 'fail dokumen'])->validate();
        // Read the bytes ourselves: a renamed file (e.g. HTML saved as .pdf) must never be stored.
        $sniffed = (string) (new \finfo(FILEINFO_MIME_TYPE))->file($file->getRealPath());
        if (! in_array($sniffed, $mimes, true)) {
            throw ValidationException::withMessages(['file' => 'Kandungan fail dokumen tidak sepadan dengan jenis fail yang dibenarkan: '.implode(', ', $extensions).'.']);
        }

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
            $path = "drafts/{$draft->id}/{$itemCode}/{$sequence}.{$extension}";
            if (! Storage::disk('local')->putFileAs(dirname($path), $file, basename($path))) {
                throw ValidationException::withMessages(['file' => 'Fail tidak dapat disimpan. Cuba lagi.']);
            }
            try {
                $document = new ApplicantDraftDocument;
                $document->forceFill(['draft_id' => $draft->id, 'item_code' => $itemCode, 'document_type_id' => $type->id,
                    'sequence' => $sequence, 'original_name' => mb_substr($file->getClientOriginalName(), 0, 255), 'path' => $path,
                    'mime' => $sniffed, 'size_bytes' => $file->getSize(), 'sha256' => $sha,
                    'uploaded_by' => $owner->id, 'precheck_status' => 'pending'])->save();

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

    private function authorize(User $owner, ApplicantDraft $draft): void
    {
        abort_unless($draft->user_id === $owner->id, 404);
        abort_unless($owner->email_verified_at !== null, 403, 'Sahkan emel anda melalui halaman Akaun saya sebelum menyediakan draf.');
        // After payment, documents change only inside the single amendment window.
        abort_unless(\App\Services\PaidCheck\PaidCheckJourneyService::draftEditable($draft), 403, 'Draf dikunci semasa semakan AIRS.');
    }
}
