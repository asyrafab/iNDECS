<?php

declare(strict_types=1);

namespace App\Airs;

use App\Models\Application;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\ValidationException;

final class DocumentManifest
{
    public function forApplication(Application $application, bool $verifyBytes = false): array
    {
        $documents = $application->currentDocuments()->orderBy('id')->get();
        if ($documents->isEmpty() || $documents->count() > 50 || $documents->sum('size_bytes') > 104857600) {
            throw ValidationException::withMessages(['airs' => 'Pakej dokumen tidak tersedia atau melebihi had 50 fail.']);
        }

        return $documents->map(function ($file) use ($verifyBytes): array {
            if ($file->scan_status !== 'clean' || ! in_array($file->mime_type, ['application/pdf', 'image/png', 'image/jpeg'], true)) {
                throw ValidationException::withMessages(['airs' => 'Setiap dokumen mesti selamat dan berformat PDF/imej.']);
            }
            if ($verifyBytes && (! Storage::disk('local')->exists($file->storage_path)
                || hash_file('sha256', Storage::disk('local')->path($file->storage_path)) !== $file->checksum_sha256
                || Storage::disk('local')->size($file->storage_path) !== $file->size_bytes)) {
                throw ValidationException::withMessages(['airs' => 'Integriti dokumen tidak dapat disahkan.']);
            }

            return ['id' => $file->id, 'revision' => $file->revision_number, 'sha256' => $file->checksum_sha256,
                'size_bytes' => $file->size_bytes, 'name' => $file->original_name, 'mime_type' => $file->mime_type];
        })->all();
    }

    public static function hash(array $manifest): string
    {
        usort($manifest, fn ($a, $b) => strcmp($a['id'], $b['id']));
        $manifest = array_map(fn ($entry) => ['id' => $entry['id'], 'revision' => (int) $entry['revision'], 'sha256' => $entry['sha256'],
            'size_bytes' => (int) $entry['size_bytes'], 'name' => $entry['name'], 'mime_type' => $entry['mime_type']], $manifest);

        return hash('sha256', json_encode($manifest, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_THROW_ON_ERROR));
    }
}
