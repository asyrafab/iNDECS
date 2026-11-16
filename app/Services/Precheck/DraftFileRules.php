<?php

declare(strict_types=1);

namespace App\Services\Precheck;

use App\Models\ApplicantDraft;
use App\Models\DocumentRequirement;
use App\Models\DocumentType;
use App\Services\DocumentCatalogueService;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\ValidationException;

/**
 * File type and size rules for applicant draft uploads (ADR-363). A placed file follows its
 * checklist item's document type; an unsorted file follows the loosest rules of the type's catalogue.
 */
final class DraftFileRules
{
    public const MIMES = ['pdf' => 'application/pdf', 'jpg' => 'image/jpeg', 'jpeg' => 'image/jpeg', 'png' => 'image/png'];

    /** @return array{extensions: list<string>, mimes: list<string>, max_kb: int, document_type: DocumentType} */
    public static function forItem(ApplicantDraft $draft, string $itemCode): array
    {
        $type = DocumentType::query()->where('code', DocumentCatalogueService::documentTypeCode($draft->application_type_id, $itemCode))->firstOrFail();

        return self::shape($type->allowedExtensions(), (int) $type->max_file_size_kb) + ['document_type' => $type];
    }

    /** @return array{extensions: list<string>, mimes: list<string>, max_kb: int} */
    public static function loosest(string $applicationTypeId): array
    {
        $types = DocumentRequirement::query()->with('documentType')->where('application_type_id', $applicationTypeId)
            ->where('is_active', true)->get()->pluck('documentType')->filter();
        $extensions = $types->flatMap(fn (DocumentType $type) => $type->allowedExtensions())->unique()->values()->all();

        return self::shape($extensions === [] ? array_keys(self::MIMES) : $extensions, (int) ($types->max('max_file_size_kb') ?? 20480));
    }

    /**
     * Validates extension, declared and sniffed type, and size. Returns the sniffed MIME.
     *
     * @param  array{extensions: list<string>, mimes: list<string>, max_kb: int}  $rules
     */
    public static function assertFits(UploadedFile|string $file, array $rules, string $field = 'file'): string
    {
        $path = $file instanceof UploadedFile ? (string) $file->getRealPath() : $file;
        if ($file instanceof UploadedFile) {
            Validator::make([$field => $file], [$field => ['required', 'file', 'mimes:'.implode(',', $rules['extensions']),
                'mimetypes:'.implode(',', $rules['mimes']), 'max:'.$rules['max_kb']]], [], [$field => 'fail dokumen'])->validate();
        } else {
            $extension = strtolower(pathinfo($path, PATHINFO_EXTENSION));
            if (! in_array($extension, $rules['extensions'], true)) {
                throw ValidationException::withMessages([$field => 'Jenis fail tidak dibenarkan untuk dokumen ini: '.implode(', ', $rules['extensions']).'.']);
            }
            if ((int) ceil(filesize($path) / 1024) > $rules['max_kb']) {
                throw ValidationException::withMessages([$field => 'Saiz fail melebihi had '.number_format($rules['max_kb'] / 1024).' MB untuk dokumen ini.']);
            }
        }
        // Read the bytes ourselves: a renamed file (e.g. HTML saved as .pdf) must never be stored.
        $sniffed = (string) (new \finfo(FILEINFO_MIME_TYPE))->file($path);
        if (! in_array($sniffed, $rules['mimes'], true)) {
            throw ValidationException::withMessages([$field => 'Kandungan fail dokumen tidak sepadan dengan jenis fail yang dibenarkan: '.implode(', ', $rules['extensions']).'.']);
        }

        return $sniffed;
    }

    /** @param list<string> $extensions */
    private static function shape(array $extensions, int $maxKb): array
    {
        $extensions = array_values(array_intersect($extensions, array_keys(self::MIMES)));

        return ['extensions' => $extensions, 'mimes' => array_values(array_unique(array_map(fn ($ext) => self::MIMES[$ext], $extensions))), 'max_kb' => $maxKb];
    }
}
