<?php

namespace App\Services;

use App\Enums\ApplicationStage;
use App\Enums\ApplicationStatus;
use App\Enums\DocumentVerificationStatus;
use App\Models\Application;
use App\Models\ApplicationDocument;
use App\Models\AuditLog;
use App\Models\DocumentType;
use App\Models\User;
use DomainException;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Throwable;

class PkmDocumentService
{
    public function upload(
        Application $application,
        DocumentType $documentType,
        UploadedFile $file,
        User $actor,
    ): ApplicationDocument {
        $this->assertUploadAllowed($application, $documentType, $file);

        $extension = strtolower($file->getClientOriginalExtension());
        $checksum = hash_file('sha256', $file->getRealPath());
        $storagePath = sprintf(
            'pkm/%s/%s/%s.%s',
            $application->id,
            $documentType->code,
            Str::ulid(),
            $extension,
        );

        if (! Storage::disk('local')->put($storagePath, $file->getContent())) {
            throw new DomainException('Dokumen tidak dapat disimpan. Sila cuba lagi.');
        }

        try {
            return DB::transaction(function () use ($application, $documentType, $file, $actor, $checksum, $storagePath): ApplicationDocument {
                $locked = Application::query()->lockForUpdate()->findOrFail($application->id);
                $current = $locked->documents()
                    ->where('document_type_id', $documentType->id)
                    ->where('is_current', true)
                    ->lockForUpdate()
                    ->first();
                $revision = (int) $locked->documents()
                    ->where('document_type_id', $documentType->id)
                    ->max('revision_number') + 1;

                $current?->update(['is_current' => false]);

                $document = $locked->documents()->create([
                    'document_type_id' => $documentType->id,
                    'revision_number' => $revision,
                    'original_name' => $file->getClientOriginalName(),
                    'storage_path' => $storagePath,
                    'mime_type' => $file->getMimeType() ?: 'application/octet-stream',
                    'size_bytes' => $file->getSize(),
                    'checksum_sha256' => $checksum,
                    'uploaded_by' => $actor->id,
                    'uploaded_at' => now(),
                    'verification_status' => DocumentVerificationStatus::Pending,
                    'is_current' => true,
                    'supersedes_id' => $current?->id,
                ]);

                AuditLog::query()->create([
                    'auditable_type' => ApplicationDocument::class,
                    'auditable_id' => $document->id,
                    'event' => 'document_uploaded',
                    'actor_id' => $actor->id,
                    'after_values' => [
                        'application_id' => $locked->id,
                        'document_type' => $documentType->code,
                        'revision_number' => $revision,
                        'checksum_sha256' => $checksum,
                    ],
                    'ip_address' => request()?->ip(),
                ]);

                return $document;
            });
        } catch (Throwable $exception) {
            Storage::disk('local')->delete($storagePath);

            throw $exception;
        }
    }

    private function assertUploadAllowed(
        Application $application,
        DocumentType $documentType,
        UploadedFile $file,
    ): void {
        if ($application->application_type_id !== 'PKM') {
            throw new DomainException('Dokumen ini hanya boleh dimuat naik untuk permohonan PKM.');
        }

        if ($application->current_stage !== ApplicationStage::CompletenessCheck
            || ! in_array($application->current_status, [ApplicationStatus::InReview, ApplicationStatus::ReturnedForAmendment], true)) {
            throw new DomainException('Muat naik dokumen tidak tersedia pada peringkat semasa.');
        }

        $review = $application->latestCompletenessReview()->with('items')->first();

        if (! $review?->items->contains('document_type_id', $documentType->id)) {
            throw new DomainException('Jenis dokumen ini tidak termasuk dalam senarai semak permohonan.');
        }

        $extension = strtolower($file->getClientOriginalExtension());

        if (! in_array($extension, $documentType->allowedExtensions(), true)) {
            throw new DomainException('Format fail tidak dibenarkan untuk jenis dokumen ini.');
        }

        if ($file->getSize() > ($documentType->max_file_size_kb * 1024)) {
            throw new DomainException('Saiz fail melebihi had yang dibenarkan.');
        }
    }
}
