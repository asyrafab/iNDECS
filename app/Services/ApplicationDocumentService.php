<?php

declare(strict_types=1);

namespace App\Services;

use App\Contracts\DocumentScanner;
use App\Enums\DocumentVerificationStatus;
use App\Models\Application;
use App\Models\ApplicationDocument;
use App\Models\DocumentChecklistItem;
use App\Models\User;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use Throwable;

final class ApplicationDocumentService
{
    public function __construct(private readonly DocumentChecklistService $checklists, private readonly DocumentScanner $scanner) {}

    public function upload(Application $application, DocumentChecklistItem $item, UploadedFile $file, int $expected, User $actor): ApplicationDocument
    {
        $path = null;
        try {
            return DB::transaction(function () use ($application, $item, $file, $expected, $actor, &$path): ApplicationDocument {
                $record = Application::query()->lockForUpdate()->findOrFail($application->id);
                $this->checklists->assertPermission($record, $actor, 'upload');
                $this->checklists->assertVersion($record, $expected);
                $this->checklists->assertStage($record, true);
                $checklist = $this->checklists->checklist($record);
                $item = DocumentChecklistItem::query()->where('checklist_id', $checklist->id)->findOrFail($item->id);
                $extension = strtolower($file->getClientOriginalExtension());
                $mime = (new \finfo(FILEINFO_MIME_TYPE))->file($file->getRealPath());
                $mimes = ['pdf' => 'application/pdf', 'png' => 'image/png', 'jpg' => 'image/jpeg', 'jpeg' => 'image/jpeg'];
                if (! $file->isValid() || ! in_array($extension, explode(',', $item->allowed_extensions), true)
                    || ! isset($mimes[$extension]) || $mime !== $mimes[$extension] || $file->getSize() > $item->max_file_size_kb * 1024
                    || $file->getSize() === 0 || strlen($file->getClientOriginalName()) > 255) {
                    throw ValidationException::withMessages(['document' => 'Format, kandungan atau saiz dokumen tidak dibenarkan.']);
                }
                $this->scanner->assertClean($file->getRealPath());
                $checksum = hash_file('sha256', $file->getRealPath());
                $path = 'applications/'.$record->id.'/'.Str::ulid().'.'.$extension;
                if (! Storage::disk('local')->put($path, $file->getContent())) {
                    throw ValidationException::withMessages(['document' => 'Dokumen tidak dapat disimpan.']);
                }
                $current = $record->documents()->where('document_type_id', $item->document_type_id)->where('is_current', true)->first();
                $revision = ((int) $record->documents()->where('document_type_id', $item->document_type_id)->max('revision_number')) + 1;
                $current?->update(['is_current' => false]);
                $document = $record->documents()->create([
                    'document_type_id' => $item->document_type_id, 'revision_number' => $revision,
                    'original_name' => $file->getClientOriginalName(), 'storage_path' => $path,
                    'mime_type' => $mime, 'size_bytes' => $file->getSize(), 'checksum_sha256' => $checksum,
                    'uploaded_by' => $actor->id, 'uploaded_at' => now(), 'scan_status' => 'clean', 'scanned_at' => now(),
                    'verification_status' => DocumentVerificationStatus::Pending, 'is_current' => true, 'supersedes_id' => $current?->id,
                ]);
                $item->update(['status' => 'pending', 'document_id' => null, 'remarks' => null, 'checked_by' => null, 'checked_at' => null]);
                $record->increment('row_version');
                $this->checklists->audit($record, $actor, 'document_uploaded', [], ['document_id' => $document->id,
                    'document_type_id' => $item->document_type_id, 'revision_number' => $revision, 'checksum_sha256' => $checksum, 'scan_status' => 'clean']);

                return $document;
            });
        } catch (Throwable $error) {
            if ($path !== null) {
                Storage::disk('local')->delete($path);
            }
            throw $error;
        }
    }
}
