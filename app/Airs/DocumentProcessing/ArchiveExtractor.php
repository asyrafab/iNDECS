<?php

declare(strict_types=1);

namespace App\Airs\DocumentProcessing;

use App\Airs\Exceptions\InvalidDocumentException;
use App\Airs\Exceptions\NoRelevantDocumentException;
use App\Airs\Exceptions\UnsafeArchiveException;
use App\Airs\Models\ReviewCase;
use App\Airs\Models\ReviewFile;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use ZipArchive;

final class ArchiveExtractor
{
    public function __construct(private readonly FileValidator $validator)
    {
    }

    /** @return list<ReviewFile> */
    public function extract(ReviewCase $case, ReviewFile $archive): array
    {
        $zip = new ZipArchive;
        $archivePath = Storage::disk('local')->path($archive->safe_storage_path);
        if ($zip->open($archivePath) !== true) {
            throw new UnsafeArchiveException('The ZIP archive could not be opened.');
        }

        $inspection = $this->inspect($zip, max(1, $archive->size_bytes));
        $entries = $inspection['entries'];
        $warnings = array_merge($case->warnings ?? [], $inspection['warnings']);
        $directory = "airs/{$case->id}/".($archive->submission_number ?? 1)."/extracted/{$archive->id}";
        $created = [];

        try {
            Storage::disk('local')->makeDirectory($directory);
            foreach ($entries as $entry) {
                $extension = strtolower(pathinfo($entry['name'], PATHINFO_EXTENSION));
                $path = "{$directory}/".Str::ulid().".{$extension}";
                $source = $zip->getStream($entry['name']);
                if ($source === false) {
                    throw new UnsafeArchiveException('An archive entry could not be read safely.');
                }

                $destination = fopen(Storage::disk('local')->path($path), 'wb');
                if ($destination === false) {
                    fclose($source);
                    throw new UnsafeArchiveException('An extracted document could not be stored.');
                }

                stream_copy_to_stream($source, $destination);
                fclose($source);
                fclose($destination);

                $absolute = Storage::disk('local')->path($path);
                try {
                    $mime = $this->validator->assertStoredDocument($absolute, $extension);
                } catch (InvalidDocumentException $exception) {
                    Storage::disk('local')->delete($path);
                    $warnings[] = basename(str_replace('\\', '/', $entry['name'])).': diketepikan kerana kandungan fail tidak boleh digunakan.';
                    continue;
                }
                $sha256 = hash_file('sha256', $absolute);
                $duplicate = ReviewFile::query()
                    ->where('review_case_id', $case->id)
                    ->where('submission_number', $archive->submission_number ?? 1)
                    ->where('sha256', $sha256)
                    ->where('extension', '!=', 'zip')
                    ->first();

                $created[] = ReviewFile::create([
                    'review_case_id' => $case->id,
                    'parent_archive_file_id' => $archive->id,
                    'submission_number' => $archive->submission_number ?? 1,
                    'original_name' => basename(str_replace('\\', '/', $entry['name'])),
                    'relative_path' => $entry['name'],
                    'safe_storage_path' => $path,
                    'mime_type' => $mime,
                    'extension' => $extension,
                    'size_bytes' => filesize($absolute),
                    'sha256' => $sha256,
                    'is_duplicate' => $duplicate !== null,
                    'duplicate_of_file_id' => $duplicate?->id,
                    'processing_status' => 'PENDING',
                ]);
            }
        } catch (\Throwable $exception) {
            foreach ($created as $file) {
                $file->delete();
            }
            Storage::disk('local')->deleteDirectory($directory);
            throw $exception;
        } finally {
            $zip->close();
        }

        if ($created === []) {
            Storage::disk('local')->deleteDirectory($directory);
            throw new NoRelevantDocumentException('The ZIP archive contains no usable documents for plan review.');
        }

        $case->forceFill([
            'file_count' => count($created),
            'total_extracted_bytes' => array_sum(array_map(fn (ReviewFile $file): int => (int) $file->size_bytes, $created)),
            'warnings' => array_values(array_unique($warnings)),
        ])->save();
        $archive->forceFill(['processing_status' => 'EXTRACTED'])->save();

        return $created;
    }

    /** @return array{entries: list<array{name: string, size: int}>, warnings: list<string>} */
    private function inspect(ZipArchive $zip, int $archiveBytes): array
    {
        $maxFiles = (int) config('airs.limits.archive_files');
        $maxFileBytes = (int) config('airs.limits.file_bytes');
        $maxBytes = (int) config('airs.limits.extracted_bytes');
        $maxRatio = (int) config('airs.limits.compression_ratio');
        $seen = [];
        $entries = [];
        $warnings = [];
        $total = 0;

        for ($index = 0; $index < $zip->numFiles; $index++) {
            $stat = $zip->statIndex($index);
            if ($stat === false) {
                throw new UnsafeArchiveException('The ZIP directory is invalid.');
            }

            $name = str_replace('\\', '/', (string) $stat['name']);
            if (str_ends_with($name, '/')) {
                continue;
            }
            $this->assertSafePath($name);

            $extension = strtolower(pathinfo($name, PATHINFO_EXTENSION));
            if (in_array($extension, ['zip', 'rar', '7z', 'tar', 'gz'], true)) {
                throw new UnsafeArchiveException('Nested archives are not accepted.');
            }
            if (! in_array($extension, config('airs.allowed_extensions'), true)) {
                $warnings[] = basename($name).': diketepikan kerana format fail tidak disokong.';
                continue;
            }

            $key = strtolower($name);
            if (isset($seen[$key])) {
                throw new UnsafeArchiveException("Duplicate archive path: {$name}.");
            }
            $seen[$key] = true;
            $size = (int) ($stat['size'] ?? 0);
            if ($size < 1 || $size > $maxFileBytes) {
                $warnings[] = basename($name).': diketepikan kerana fail kosong atau melebihi had saiz.';
                continue;
            }
            $total += $size;
            $entries[] = ['name' => (string) $stat['name'], 'size' => $size];

            if (count($entries) > $maxFiles || $total > $maxBytes || ($total / $archiveBytes) > $maxRatio) {
                throw new UnsafeArchiveException('The ZIP archive exceeds safe extraction limits.');
            }
        }

        if ($entries === []) {
            throw new NoRelevantDocumentException('The ZIP archive contains no usable documents for plan review.');
        }

        return ['entries' => $entries, 'warnings' => $warnings];
    }

    private function assertSafePath(string $path): void
    {
        if ($path === '' || str_contains($path, "\0") || str_starts_with($path, '/') || preg_match('/^[A-Za-z]:\//', $path)) {
            throw new UnsafeArchiveException('The ZIP archive contains an unsafe absolute path.');
        }

        foreach (explode('/', $path) as $segment) {
            if ($segment === '..' || $segment === '') {
                throw new UnsafeArchiveException('The ZIP archive contains an unsafe traversal path.');
            }
        }
    }
}
