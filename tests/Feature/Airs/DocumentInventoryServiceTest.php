<?php

declare(strict_types=1);

namespace Tests\Feature\Airs;

use App\Airs\Contracts\DocumentProcessor;
use App\Airs\DocumentProcessing\DocumentInventoryService;
use App\Airs\Enums\ReviewCaseStatus;
use App\Airs\Models\ReviewCase;
use App\Airs\Models\ReviewFile;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Storage;
use RuntimeException;
use Tests\Support\AirsFixtures;
use Tests\TestCase;

final class DocumentInventoryServiceTest extends TestCase
{
    use AirsFixtures, RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed();
        Storage::fake('local');
    }

    public function test_one_unreadable_file_does_not_stop_another_usable_document(): void
    {
        Storage::fake('local');
        $case = $this->airsCase(null, [
            'status' => ReviewCaseStatus::Uploaded,
            'total_original_bytes' => 2,
        ]);
        $bad = $this->file($case, 'rosak.pdf');
        $good = $this->file($case, 'pelan-lampu.pdf');

        $processor = new class implements DocumentProcessor
        {
            public function process(string $filePath, string $outputDirectory): array
            {
                if (str_contains($filePath, 'rosak.pdf')) {
                    throw new RuntimeException('unreadable test document');
                }

                return [
                    'is_encrypted' => false,
                    'page_count' => 1,
                    'pages' => [[
                        'page' => 1,
                        'width' => 595,
                        'height' => 842,
                        'rotation' => 0,
                        'content_type' => 'TEXT',
                        'embedded_text_path' => null,
                        'thumbnail_path' => null,
                        'preview_path' => null,
                        'high_res_path' => null,
                        'page_hash' => hash('sha256', $filePath),
                        'regions' => [],
                    ]],
                    'warnings' => [],
                ];
            }
        };

        (new DocumentInventoryService($processor))->inventory($case);

        self::assertSame('FAILED', $bad->fresh()->processing_status);
        self::assertSame('DOCUMENT_UNREADABLE', $bad->fresh()->rejection_reason);
        self::assertSame('PROCESSED', $good->fresh()->processing_status);
        self::assertSame(1, $case->fresh()->page_count);
        self::assertStringContainsString('rosak.pdf: diketepikan', implode(' ', $case->fresh()->warnings ?? []));
    }

    private function file(ReviewCase $case, string $name): ReviewFile
    {
        $path = "reviews/{$case->id}/extracted/{$name}";
        Storage::disk('local')->put($path, '%PDF-1.4 test');

        return ReviewFile::create([
            'review_case_id' => $case->id,
            'original_name' => $name,
            'safe_storage_path' => $path,
            'mime_type' => 'application/pdf',
            'extension' => 'pdf',
            'size_bytes' => Storage::disk('local')->size($path),
            'sha256' => hash('sha256', $name),
            'processing_status' => 'PENDING',
        ]);
    }
}
