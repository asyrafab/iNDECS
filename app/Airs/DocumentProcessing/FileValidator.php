<?php

declare(strict_types=1);

namespace App\Airs\DocumentProcessing;

use App\Airs\Exceptions\InvalidDocumentException;
use Illuminate\Http\UploadedFile;

final class FileValidator
{
    private const ARCHIVE_MIMES = ['application/zip', 'application/x-zip-compressed', 'application/octet-stream'];

    /**
     * @param list<UploadedFile> $files
     */
    public function validateUploadSet(?UploadedFile $archive, array $files): void
    {
        if (($archive === null && $files === []) || ($archive !== null && $files !== [])) {
            throw new InvalidDocumentException('Upload either one ZIP archive or one or more PDF/image files.');
        }

        $uploads = $archive !== null ? [$archive] : $files;
        $totalBytes = 0;

        if ($archive === null && count($files) > (int) config('airs.limits.direct_files')) {
            throw new InvalidDocumentException('The number of uploaded files exceeds the configured limit.');
        }

        foreach ($uploads as $file) {
            if (! $file instanceof UploadedFile || ! $file->isValid()) {
                throw new InvalidDocumentException('One of the uploaded files could not be received.');
            }

            $size = (int) $file->getSize();
            $totalBytes += $size;
            $maxFileBytes = $archive !== null
                ? (int) config('airs.limits.upload_bytes')
                : (int) config('airs.limits.file_bytes');
            if ($size < 1 || $size > $maxFileBytes) {
                throw new InvalidDocumentException('An uploaded file exceeds the configured file-size limit.');
            }

            $extension = strtolower($file->getClientOriginalExtension());
            $mime = (string) $file->getMimeType();

            if ($archive !== null) {
                if ($extension !== 'zip' || ! in_array($mime, self::ARCHIVE_MIMES, true)) {
                    throw new InvalidDocumentException('The archive must be a valid ZIP file.');
                }
            } elseif (! in_array($extension, config('airs.allowed_extensions'), true)
                || ! in_array($mime, config('airs.allowed_mime_types'), true)) {
                throw new InvalidDocumentException("Unsupported file type: {$file->getClientOriginalName()}.");
            }
        }

        if ($totalBytes > (int) config('airs.limits.upload_bytes')) {
            throw new InvalidDocumentException('The combined upload exceeds the configured size limit.');
        }
    }

    public function assertStoredDocument(string $absolutePath, string $extension): string
    {
        if (! is_file($absolutePath) || filesize($absolutePath) < 1) {
            throw new InvalidDocumentException('An extracted document is empty or missing.');
        }

        if (filesize($absolutePath) > (int) config('airs.limits.file_bytes')) {
            throw new InvalidDocumentException('An extracted document exceeds the configured file-size limit.');
        }

        $extension = strtolower($extension);
        if (! in_array($extension, config('airs.allowed_extensions'), true)) {
            throw new InvalidDocumentException("Unsupported file extension in archive: .{$extension}.");
        }

        $mime = (new \finfo(FILEINFO_MIME_TYPE))->file($absolutePath) ?: 'application/octet-stream';
        if (! in_array($mime, config('airs.allowed_mime_types'), true)) {
            throw new InvalidDocumentException('An archived file has content that does not match an allowed document type.');
        }

        return $mime;
    }
}
