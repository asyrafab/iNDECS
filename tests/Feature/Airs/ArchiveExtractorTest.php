<?php

declare(strict_types=1);

namespace Tests\Feature\Airs;

use App\Airs\Enums\ReviewCaseStatus;
use App\Airs\Exceptions\NoRelevantDocumentException;
use App\Airs\Exceptions\UnsafeArchiveException;
use App\Airs\Models\ReviewCase;
use App\Airs\Models\ReviewFile;
use App\Airs\DocumentProcessing\ArchiveExtractor;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Storage;
use Tests\Support\AirsFixtures;
use Tests\TestCase;
use ZipArchive;

final class ArchiveExtractorTest extends TestCase
{
    use AirsFixtures, RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed();
        Storage::fake('local');
    }

    public function test_safe_archive_is_extracted_with_manifest_metadata(): void
    {
        [$case, $archive] = $this->archive(['docs/plan.pdf' => $this->pdf()]);
        $files = app(ArchiveExtractor::class)->extract($case, $archive);

        self::assertCount(1, $files);
        self::assertSame('docs/plan.pdf', $files[0]->relative_path);
        self::assertSame(64, strlen($files[0]->sha256));
        self::assertSame(1, $case->fresh()->file_count);
    }

    public function test_archive_with_one_usable_document_accepts_case_and_ignores_unsupported_entries(): void
    {
        [$case, $archive] = $this->archive([
            'docs/plan.pdf' => $this->pdf(),
            'notes/readme.txt' => 'not a plan-review document',
        ]);

        $files = app(ArchiveExtractor::class)->extract($case, $archive);

        self::assertCount(1, $files);
        self::assertSame('docs/plan.pdf', $files[0]->relative_path);
        self::assertStringContainsString('readme.txt: diketepikan', implode(' ', $case->fresh()->warnings ?? []));
    }

    public function test_archive_without_any_usable_document_is_rejected(): void
    {
        [$case, $archive] = $this->archive(['notes/readme.txt' => 'not a plan-review document']);
        $this->expectException(NoRelevantDocumentException::class);

        app(ArchiveExtractor::class)->extract($case, $archive);
    }

    public function test_parent_traversal_entry_is_rejected_before_extraction(): void
    {
        [$case, $archive] = $this->archive(['../evil.pdf' => $this->pdf()]);
        $this->expectException(UnsafeArchiveException::class);

        app(ArchiveExtractor::class)->extract($case, $archive);
    }

    public function test_nested_archive_is_rejected(): void
    {
        [$case, $archive] = $this->archive(['nested.zip' => 'not a real nested archive']);
        $this->expectException(UnsafeArchiveException::class);

        app(ArchiveExtractor::class)->extract($case, $archive);
    }

    public function test_decompression_ratio_limit_is_enforced(): void
    {
        config()->set('airs.limits.compression_ratio', 1);
        [$case, $archive] = $this->archive(['large.pdf' => "%PDF-1.4\n".str_repeat('0', 50000)]);
        $this->expectException(UnsafeArchiveException::class);

        app(ArchiveExtractor::class)->extract($case, $archive);
    }

    private function archive(array $entries): array
    {
        $case = $this->airsCase(null, [
            'status' => ReviewCaseStatus::Uploaded,
            'total_original_bytes' => 1,
        ]);
        $path = "reviews/{$case->id}/originals/package.zip";
        Storage::disk('local')->makeDirectory(dirname($path));
        $zip = new ZipArchive;
        $absolute = Storage::disk('local')->path($path);
        self::assertTrue($zip->open($absolute, ZipArchive::CREATE | ZipArchive::OVERWRITE));
        foreach ($entries as $name => $contents) {
            $zip->addFromString($name, $contents);
        }
        $zip->close();
        $archive = ReviewFile::create([
            'review_case_id' => $case->id,
            'original_name' => 'package.zip',
            'safe_storage_path' => $path,
            'mime_type' => 'application/zip',
            'extension' => 'zip',
            'size_bytes' => filesize($absolute),
            'sha256' => hash_file('sha256', $absolute),
            'processing_status' => 'ORIGINAL_ARCHIVE',
        ]);

        return [$case, $archive];
    }

    private function pdf(): string
    {
        return "%PDF-1.4\n1 0 obj\n<< /Type /Catalog >>\nendobj\n%%EOF";
    }
}
