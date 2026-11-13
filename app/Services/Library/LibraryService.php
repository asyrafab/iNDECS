<?php

declare(strict_types=1);

namespace App\Services\Library;

use App\Jobs\IndexLibraryVersion;
use App\Models\AuditLog;
use App\Models\LibraryDocument;
use App\Models\LibraryDocumentVersion;
use App\Models\User;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;
use Throwable;

/**
 * ADR-362: the only writer of department libraries. A Penolong Pengarah changes
 * their own department's documents; every change is audited and versions are
 * append-only. Files are private PDFs on the local disk.
 */
final class LibraryService
{
    public const MAX_KB = 51200;

    private const ATTRIBUTES = ['title' => 'tajuk', 'category' => 'kategori', 'description' => 'keterangan',
        'visibility' => 'paparan', 'application_type_ids' => 'jenis permohonan', 'application_type_ids.*' => 'jenis permohonan',
        'file' => 'fail dokumen', 'notes' => 'catatan versi'];

    /** @return array<string, list<mixed>> */
    public static function fileRules(): array
    {
        return ['file' => ['required', 'file', 'mimes:pdf', 'mimetypes:application/pdf', 'max:'.self::MAX_KB]];
    }

    /** @return array<string, list<mixed>> */
    public static function rules(): array
    {
        return [
            'title' => ['required', 'string', 'max:255'],
            'category' => ['required', Rule::in(array_keys(LibraryDocument::CATEGORIES))],
            'description' => ['nullable', 'string', 'max:2000'],
            'visibility' => ['required', Rule::in(array_keys(LibraryDocument::VISIBILITIES))],
            'application_type_ids' => ['nullable', 'array', 'max:20'],
            'application_type_ids.*' => ['string', 'distinct', Rule::exists('application_types', 'id')],
        ] + self::fileRules();
    }

    public function create(User $actor, array $data, UploadedFile $file): LibraryDocument
    {
        $department = (string) $actor->department_id;
        abort_unless($actor->canManageLibrary($department), 403);
        $data = $this->validate($data + ['file' => $file], self::rules());

        $id = (string) Str::ulid();
        $path = "library/{$department}/{$id}/v1.pdf";
        $this->put($file, $path);
        try {
            return DB::transaction(function () use ($actor, $data, $file, $department, $id, $path): LibraryDocument {
                $document = new LibraryDocument;
                $document->forceFill(['id' => $id, 'department_id' => $department, 'title' => $data['title'], 'category' => $data['category'],
                    'description' => $data['description'] ?? null, 'visibility' => $data['visibility'], 'status' => 'active',
                    'current_version' => 1, 'created_by' => $actor->id])->save();
                $document->applicationTypes()->sync($data['application_type_ids'] ?? []);
                $this->recordVersion($document, 1, $file, $path, null, $actor);
                $this->audit($actor, $document, 'library_document_created', null,
                    $document->only(['title', 'category', 'visibility', 'current_version']) + ['application_type_ids' => $data['application_type_ids'] ?? []]);

                return $document;
            });
        } catch (Throwable $e) {
            Storage::disk('local')->delete($path);
            throw $e;
        }
    }

    public function addVersion(User $actor, LibraryDocument $document, UploadedFile $file, ?string $notes): LibraryDocumentVersion
    {
        abort_unless($actor->canManageLibrary($document->department_id), 403);
        $this->validate(['file' => $file, 'notes' => $notes], self::fileRules() + ['notes' => ['nullable', 'string', 'max:1000']]);

        return DB::transaction(function () use ($actor, $document, $file, $notes): LibraryDocumentVersion {
            $locked = LibraryDocument::query()->lockForUpdate()->findOrFail($document->id);
            if ($locked->status !== 'active') {
                throw ValidationException::withMessages(['file' => 'Dokumen ini telah dibatalkan dan tidak boleh menerima versi baharu.']);
            }
            $version = $locked->current_version + 1;
            $path = "library/{$locked->department_id}/{$locked->id}/v{$version}.pdf";
            $this->put($file, $path);
            try {
                $record = $this->recordVersion($locked, $version, $file, $path, $notes, $actor);
                $before = $locked->current_version;
                $locked->forceFill(['current_version' => $version])->save();
                $this->audit($actor, $locked, 'library_version_added', ['current_version' => $before], ['current_version' => $version, 'notes' => $notes]);
            } catch (Throwable $e) {
                Storage::disk('local')->delete($path);
                throw $e;
            }
            $document->setRawAttributes($locked->getAttributes(), true);

            return $record;
        });
    }

    public function retire(User $actor, LibraryDocument $document): void
    {
        abort_unless($actor->canManageLibrary($document->department_id), 403);
        DB::transaction(function () use ($actor, $document): void {
            $locked = LibraryDocument::query()->lockForUpdate()->findOrFail($document->id);
            if ($locked->status === 'retired') {
                return;
            }
            $locked->forceFill(['status' => 'retired', 'retired_by' => $actor->id, 'retired_at' => now()])->save();
            $this->audit($actor, $locked, 'library_document_retired', ['status' => 'active'], ['status' => 'retired']);
        });
    }

    private function validate(array $data, array $rules): array
    {
        return Validator::make($data, $rules, [], self::ATTRIBUTES)->validate();
    }

    private function put(UploadedFile $file, string $path): void
    {
        if (! Storage::disk('local')->putFileAs(dirname($path), $file, basename($path))) {
            throw ValidationException::withMessages(['file' => 'Fail tidak dapat disimpan. Cuba lagi.']);
        }
    }

    private function recordVersion(LibraryDocument $document, int $version, UploadedFile $file, string $path, ?string $notes, User $actor): LibraryDocumentVersion
    {
        $record = new LibraryDocumentVersion;
        $record->forceFill(['document_id' => $document->id, 'version' => $version,
            'original_name' => mb_substr($file->getClientOriginalName(), 0, 255), 'path' => $path,
            'size_bytes' => $file->getSize(), 'sha256' => hash_file('sha256', $file->getRealPath()),
            'notes' => $notes, 'uploaded_by' => $actor->id, 'created_at' => now()])->save();
        // ADR-369: make the new version searchable for AMIRA once the upload is committed.
        IndexLibraryVersion::dispatch($record->id)->afterCommit();

        return $record;
    }

    private function audit(User $actor, LibraryDocument $document, string $event, ?array $before, array $after): void
    {
        AuditLog::query()->create(['auditable_type' => LibraryDocument::class, 'auditable_id' => $document->id,
            'event' => $event, 'actor_id' => $actor->id, 'before_values' => $before, 'after_values' => $after,
            'ip_address' => request()?->ip()]);
    }
}
