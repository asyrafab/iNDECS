<?php

declare(strict_types=1);

namespace App\Airs\DocumentProcessing;

use App\Airs\Exceptions\NoRelevantDocumentException;
use App\Airs\Exceptions\UnsafeArchiveException;
use ZipArchive;

/**
 * ZIP inspection shared by the AIRS check and the applicant drop (upload-first drafts): no absolute or
 * traversal paths, no nested archives, no duplicate paths, and entry-count / uncompressed-size / ratio caps.
 */
final class ZipSafety
{
    /**
     * @return array{entries: list<array{name: string, size: int}>, warnings: list<string>, skipped: list<array{name: string, reason: string}>}
     *
     * @throws UnsafeArchiveException when the archive as a whole is unsafe
     * @throws NoRelevantDocumentException when no entry is usable
     */
    public static function inspect(ZipArchive $zip, int $archiveBytes): array
    {
        $maxFiles = (int) config('airs.limits.archive_files');
        $maxFileBytes = (int) config('airs.limits.file_bytes');
        $maxBytes = (int) config('airs.limits.extracted_bytes');
        $maxRatio = (int) config('airs.limits.compression_ratio');
        $archiveBytes = max(1, $archiveBytes);
        $seen = [];
        $entries = [];
        $warnings = [];
        $skipped = [];
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
            self::assertSafePath($name);

            $extension = strtolower(pathinfo($name, PATHINFO_EXTENSION));
            if (in_array($extension, ['zip', 'rar', '7z', 'tar', 'gz'], true)) {
                throw new UnsafeArchiveException('Nested archives are not accepted.');
            }
            if (! in_array($extension, config('airs.allowed_extensions'), true)) {
                $warnings[] = basename($name).': diketepikan kerana format fail tidak disokong.';
                $skipped[] = ['name' => basename($name), 'reason' => 'Jenis fail tidak dibenarkan.'];
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
                $skipped[] = ['name' => basename($name), 'reason' => 'Fail kosong atau melebihi had saiz.'];
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

        return ['entries' => $entries, 'warnings' => $warnings, 'skipped' => $skipped];
    }

    public static function assertSafePath(string $path): void
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
