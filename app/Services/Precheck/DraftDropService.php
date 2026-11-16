<?php

declare(strict_types=1);

namespace App\Services\Precheck;

use App\Airs\DocumentProcessing\ZipSafety;
use App\Airs\Exceptions\NoRelevantDocumentException;
use App\Airs\Exceptions\UnsafeArchiveException;
use App\Contracts\DocumentScanner;
use App\Jobs\PrecheckDraftDocument;
use App\Models\ApplicantDraft;
use App\Models\ApplicantDraftDocument;
use App\Models\User;
use App\Services\PaidCheck\PaidCheckJourneyService;
use Illuminate\Http\File;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\File as Files;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use Throwable;
use ZipArchive;

/**
 * Upload-first drafts: loose files and ZIP entries dropped by the applicant are stored as unsorted
 * draft documents and queued for the free local pre-check, which sorts them (ADR-363, no AI).
 * Each file passes the same checks as a per-item upload; one bad file never blocks the others.
 */
final class DraftDropService
{
    public function __construct(private readonly DocumentScanner $scanner) {}

    public function createDraft(User $owner, string $applicationTypeId): ApplicantDraft
    {
        abort_unless($owner->email_verified_at !== null, 403, 'Sahkan emel anda melalui halaman Akaun saya sebelum menyediakan draf.');

        return DB::transaction(function () use ($owner, $applicationTypeId): ApplicantDraft {
            // Serialize per-owner creation so concurrent requests cannot exceed the quota.
            User::query()->whereKey($owner->id)->lockForUpdate()->firstOrFail();
            if (ApplicantDraft::query()->ownedBy($owner)->count() >= 100) {
                throw ValidationException::withMessages(['files' => 'Had 100 draf telah dicapai. Sila sambung draf sedia ada.']);
            }
            $draft = new ApplicantDraft(['application_type_id' => $applicationTypeId, 'title' => 'Draf baharu — '.now()->format('d/m/Y')]);
            $draft->user_id = $owner->id;
            $draft->version = 1;
            $draft->title_provisional = true;
            $draft->save();
            DB::table('core.applicant_draft_revisions')->insert([
                'draft_id' => $draft->id, 'actor_id' => $owner->id, 'version' => 1,
                'snapshot' => json_encode($draft->only(['application_type_id', 'title', 'site_address', 'notes', ...ApplicantDraft::PARTICULARS, 'lighting_types']), JSON_THROW_ON_ERROR),
                'created_at' => now(),
            ]);

            return $draft;
        });
    }

    /** Accepts one dropped file; a ZIP is opened and each safe entry is treated as a dropped file. */
    public function accept(User $owner, ApplicantDraft $draft, UploadedFile $file): DropResult
    {
        abort_unless($draft->user_id === $owner->id, 404);
        abort_unless($owner->email_verified_at !== null, 403, 'Sahkan emel anda melalui halaman Akaun saya sebelum menyediakan draf.');
        abort_unless(PaidCheckJourneyService::draftEditable($draft), 403, 'Draf dikunci semasa semakan AIRS.');

        $name = mb_substr($file->getClientOriginalName(), 0, 255);
        $sniffed = (string) (new \finfo(FILEINFO_MIME_TYPE))->file((string) $file->getRealPath());
        if (strtolower($file->getClientOriginalExtension()) === 'zip' || $sniffed === 'application/zip') {
            return $this->archive($owner, $draft, $file, $name);
        }

        return $this->store($owner, $draft, $file, $name, strtolower($file->getClientOriginalExtension()));
    }

    private function archive(User $owner, ApplicantDraft $draft, UploadedFile $file, string $name): DropResult
    {
        if ((int) ceil($file->getSize() / 1024) > (int) config('kaunter.precheck.max_zip_kb', 122880)) {
            return new DropResult([], [['name' => $name, 'reason' => 'Fail ZIP melebihi had '.number_format((int) config('kaunter.precheck.max_zip_kb', 122880) / 1024).' MB. Muat naik fail secara berasingan.']]);
        }
        $zip = new ZipArchive;
        if ($zip->open((string) $file->getRealPath()) !== true) {
            return new DropResult([], [['name' => $name, 'reason' => 'Fail ZIP tidak dapat dibuka.']]);
        }
        $dir = storage_path('app/precheck-tmp/'.Str::ulid());
        Files::ensureDirectoryExists($dir);
        try {
            try {
                $inspection = ZipSafety::inspect($zip, (int) $file->getSize());
            } catch (UnsafeArchiveException) {
                // Refused whole, but reported like any other rejected file so the rest of the batch and the new draft stay visible.
                return new DropResult([], [['name' => $name, 'reason' => 'Fail ZIP tidak selamat atau melebihi had dan tidak diproses.']]);
            } catch (NoRelevantDocumentException) {
                return new DropResult([], [['name' => $name, 'reason' => 'Fail ZIP tidak mengandungi dokumen yang boleh digunakan.']]);
            }
            $result = new DropResult([], $inspection['skipped']);
            foreach ($inspection['entries'] as $entry) {
                $entryName = mb_substr(basename(str_replace('\\', '/', $entry['name'])), 0, 255);
                $extension = strtolower(pathinfo($entryName, PATHINFO_EXTENSION));
                $temp = $dir.DIRECTORY_SEPARATOR.Str::ulid().'.'.$extension;
                $source = $zip->getStream($entry['name']);
                $target = $source === false ? false : fopen($temp, 'wb');
                if ($source === false || $target === false) {
                    $result = $result->merge(new DropResult([], [['name' => $entryName, 'reason' => 'Fail dalam ZIP tidak dapat dibaca.']]));
                    continue;
                }
                // Never trust the declared size: stop copying past the per-file cap.
                $copied = stream_copy_to_stream($source, $target, (int) config('airs.limits.file_bytes') + 1);
                fclose($source);
                fclose($target);
                if ($copied === false || $copied > (int) config('airs.limits.file_bytes')) {
                    $result = $result->merge(new DropResult([], [['name' => $entryName, 'reason' => 'Fail kosong atau melebihi had saiz.']]));
                    continue;
                }
                $result = $result->merge($this->store($owner, $draft, $temp, $entryName, $extension));
            }

            return $result;
        } finally {
            $zip->close();
            Files::deleteDirectory($dir);
        }
    }

    private function store(User $owner, ApplicantDraft $draft, UploadedFile|string $file, string $name, string $extension): DropResult
    {
        $path = $file instanceof UploadedFile ? (string) $file->getRealPath() : $file;
        try {
            $rules = DraftFileRules::loosest($draft->application_type_id);
            $mime = DraftFileRules::assertFits($file, $rules, 'files');
            $sha = hash_file('sha256', $path);
            if (ApplicantDraftDocument::query()->where('draft_id', $draft->id)->where('sha256', $sha)->exists()) {
                throw ValidationException::withMessages(['files' => 'Fail yang sama telah dimuat naik.']);
            }
            if (ApplicantDraftDocument::query()->where('draft_id', $draft->id)->count() >= (int) config('kaunter.precheck.max_documents_per_draft', 60)) {
                throw ValidationException::withMessages(['files' => 'Had muat naik bagi draf ini telah dicapai.']);
            }
            $this->scanner->assertClean($path);
        } catch (ValidationException $e) {
            return new DropResult([], [['name' => $name, 'reason' => (string) collect($e->errors())->flatten()->first()]]);
        }
        $extension = in_array($extension, $rules['extensions'], true) ? $extension : $rules['extensions'][0];

        [$document, $read] = DB::transaction(function () use ($owner, $draft, $path, $name, $extension, $mime, $sha): array {
            ApplicantDraft::query()->whereKey($draft->id)->lockForUpdate()->firstOrFail();
            $inbox = "drafts/{$draft->id}/inbox";
            $read = ApplicantDraftDocument::query()->where('draft_id', $draft->id)->where('path', 'like', $inbox.'/%')->count()
                < (int) config('kaunter.precheck.read_files_per_draft', 20);
            $stored = $inbox.'/'.Str::ulid().'.'.$extension;
            if (! Storage::disk('local')->putFileAs($inbox, new File($path), basename($stored))) {
                throw ValidationException::withMessages(['files' => 'Fail tidak dapat disimpan. Cuba lagi.']);
            }
            try {
                $document = new ApplicantDraftDocument;
                $document->forceFill(['draft_id' => $draft->id, 'item_code' => null, 'document_type_id' => null, 'sequence' => 0,
                    'original_name' => $name, 'path' => $stored, 'mime' => $mime, 'size_bytes' => filesize($path), 'sha256' => $sha,
                    'uploaded_by' => $owner->id, 'precheck_status' => 'pending'])->save();

                return [$document, $read];
            } catch (Throwable $e) {
                Storage::disk('local')->delete($stored);
                throw $e;
            }
        });
        PrecheckDraftDocument::dispatch($document->id, $read)->afterCommit();

        return new DropResult([$document]);
    }
}
