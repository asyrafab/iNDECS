<?php

declare(strict_types=1);

namespace App\Airs\DocumentProcessing;

use App\Airs\Contracts\DocumentProcessor;
use App\Airs\Exceptions\InvalidDocumentException;
use App\Airs\Models\PageRegion;
use App\Airs\Models\ReviewCase;
use App\Airs\Models\ReviewPage;
use Illuminate\Support\Facades\Storage;

final class DocumentInventoryService
{
    public function __construct(private readonly DocumentProcessor $processor) {}

    public function inventory(ReviewCase $case): void
    {
        $totalPages = 0;
        $warnings = $case->warnings ?? [];
        $files = $case->activeFiles()->where('extension', '!=', 'zip')->where('is_duplicate', false)->get();

        foreach ($files as $file) {
            $outputPath = "airs/{$case->id}/".($file->submission_number ?? 1)."/derived/{$file->id}";
            Storage::disk('local')->makeDirectory($outputPath);
            try {
                $result = $this->processor->process(
                    Storage::disk('local')->path($file->safe_storage_path),
                    Storage::disk('local')->path($outputPath),
                );
            } catch (\RuntimeException|\JsonException $exception) {
                $file->forceFill([
                    'processing_status' => 'FAILED',
                    'rejection_reason' => 'DOCUMENT_UNREADABLE',
                ])->save();
                $warnings[] = "{$file->original_name}: diketepikan kerana dokumen tidak dapat dibaca.";

                continue;
            }

            if (($result['is_encrypted'] ?? false) === true) {
                $file->forceFill(['is_encrypted' => true, 'processing_status' => 'FAILED', 'rejection_reason' => 'PDF_ENCRYPTED'])->save();
                $warnings[] = "{$file->original_name}: password-protected PDF requires manual review.";

                continue;
            }

            $pageCount = (int) $result['page_count'];
            if ($pageCount < 1) {
                $file->forceFill([
                    'page_count' => 0,
                    'processing_status' => 'FAILED',
                    'rejection_reason' => 'NO_READABLE_PAGES',
                ])->save();
                $warnings[] = "{$file->original_name}: diketepikan kerana tiada halaman yang boleh dibaca.";

                continue;
            }
            $totalPages += $pageCount;
            if ($totalPages > (int) config('airs.limits.pages_per_case')) {
                throw new InvalidDocumentException('The review exceeds the configured page limit.');
            }

            foreach ($result['pages'] as $pageData) {
                $text = $this->readDerivedText($pageData['embedded_text_path'] ?? null, $outputPath);
                $page = ReviewPage::updateOrCreate(
                    ['review_file_id' => $file->id, 'page_number' => (int) $pageData['page']],
                    [
                        'width_points' => $pageData['width'] ?? null,
                        'height_points' => $pageData['height'] ?? null,
                        'rotation' => (int) ($pageData['rotation'] ?? 0),
                        'content_type' => (string) ($pageData['content_type'] ?? 'UNKNOWN'),
                        'embedded_text' => $text,
                        'text_length' => mb_strlen($text),
                        'thumbnail_path' => $this->relativeDerivedPath($pageData['thumbnail_path'] ?? null, $outputPath),
                        'preview_path' => $this->relativeDerivedPath($pageData['preview_path'] ?? null, $outputPath),
                        'high_res_path' => $this->relativeDerivedPath($pageData['high_res_path'] ?? null, $outputPath),
                        'page_hash' => $pageData['page_hash'] ?? null,
                        'processing_metadata' => ['worker' => 'pymupdf-v1'],
                    ],
                );

                PageRegion::query()->where('review_page_id', $page->id)->delete();
                foreach (($pageData['regions'] ?? []) as $region) {
                    PageRegion::create([
                        'review_page_id' => $page->id,
                        'region_type' => $region['region_type'],
                        'label' => $region['label'] ?? null,
                        'bbox_normalised' => $region['bbox_normalised'],
                        'image_path' => $this->relativeDerivedPath($region['image_path'], $outputPath),
                        'text' => $region['text'] ?? null,
                        'confidence' => $region['confidence'] ?? null,
                    ]);
                }
            }

            $file->forceFill([
                'page_count' => $pageCount,
                'page_width_max' => collect($result['pages'])->max('width'),
                'page_height_max' => collect($result['pages'])->max('height'),
                'processing_status' => 'PROCESSED',
            ])->save();
            foreach ($result['warnings'] as $warning) {
                $warnings[] = "{$file->original_name}: {$warning}";
            }
        }

        $case->forceFill([
            'page_count' => $case->activeFiles()->where('extension', '!=', 'zip')->sum('page_count'),
            'warnings' => array_values(array_unique($warnings)),
        ])->save();
    }

    private function readDerivedText(?string $absolutePath, string $expectedDirectory): string
    {
        if ($absolutePath === null) {
            return '';
        }
        $this->assertWithinOutput($absolutePath, $expectedDirectory);

        return is_file($absolutePath) ? (string) file_get_contents($absolutePath) : '';
    }

    private function relativeDerivedPath(?string $absolutePath, string $expectedDirectory): ?string
    {
        if ($absolutePath === null) {
            return null;
        }
        $this->assertWithinOutput($absolutePath, $expectedDirectory);
        $root = rtrim(Storage::disk('local')->path(''), '\\/');

        return str_replace('\\', '/', substr($absolutePath, strlen($root) + 1));
    }

    private function assertWithinOutput(string $path, string $expectedDirectory): void
    {
        $output = realpath(Storage::disk('local')->path($expectedDirectory));
        $parent = realpath(dirname($path));
        $outputLower = $output === false ? '' : strtolower(rtrim($output, '\\/'));
        $parentLower = $parent === false ? '' : strtolower(rtrim($parent, '\\/'));
        if (
            $output === false
            || $parent === false
            || ($parentLower !== $outputLower && ! str_starts_with($parentLower, $outputLower.DIRECTORY_SEPARATOR))
        ) {
            throw new InvalidDocumentException('The document worker returned an unsafe output path.');
        }
    }
}
