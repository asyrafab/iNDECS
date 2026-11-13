<?php

declare(strict_types=1);

namespace Tests\Feature\Airs;

use App\Airs\Enums\ReviewCaseStatus;
use App\Airs\Exceptions\NoRelevantDocumentException;
use App\Airs\Models\DocumentClassification;
use App\Airs\Models\ReviewCase;
use App\Airs\Models\ReviewFile;
use App\Airs\Models\ReviewVersion;
use App\Airs\DocumentProcessing\DocumentRelevanceService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Support\AirsFixtures;
use Tests\TestCase;

final class DocumentRelevanceServiceTest extends TestCase
{
    use AirsFixtures, RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed();
        \Illuminate\Support\Facades\Storage::fake('local');
    }

    public function test_one_relevant_document_accepts_case_and_ignores_unrelated_file(): void
    {
        [$case, $version] = $this->caseAndVersion();
        $this->classification($case, $version, 'pelan-lampu.pdf', 'LJUP_PLAN', 0.92);
        $this->classification($case, $version, 'resume.pdf', 'IRRELEVANT_TO_SELECTED_REVIEW', 0.95);

        $result = app(DocumentRelevanceService::class)->assertAtLeastOneRelevantDocument($case, $version);

        self::assertSame(['accepted' => 1, 'ignored' => 1], $result);
        self::assertStringContainsString('resume.pdf: diketepikan', implode(' ', $case->fresh()->warnings ?? []));
    }

    public function test_case_is_rejected_only_when_every_usable_document_is_explicitly_unrelated(): void
    {
        [$case, $version] = $this->caseAndVersion();
        $this->classification($case, $version, 'resume.pdf', 'IRRELEVANT_TO_SELECTED_REVIEW', 0.95);
        $this->expectException(NoRelevantDocumentException::class);

        app(DocumentRelevanceService::class)->assertAtLeastOneRelevantDocument($case, $version);
    }

    /** @return array{ReviewCase, ReviewVersion} */
    private function caseAndVersion(): array
    {
        $case = $this->airsCase(null, [
            'status' => ReviewCaseStatus::Uploaded,
            'total_original_bytes' => 1,
        ]);
        $version = ReviewVersion::create([
            'review_case_id' => $case->id,
            'check_number' => 1,
            'rule_pack' => 'ljup',
            'rule_version' => 'v1',
            'prompt_version' => 'v1',
            'report_version' => 'v1',
            'ai_provider' => 'fake',
        ]);

        return [$case, $version];
    }

    private function classification(
        ReviewCase $case,
        ReviewVersion $version,
        string $name,
        string $type,
        float $confidence,
    ): void {
        $file = ReviewFile::create([
            'review_case_id' => $case->id,
            'original_name' => $name,
            'safe_storage_path' => 'reviews/test/'.$name,
            'mime_type' => 'application/pdf',
            'extension' => 'pdf',
            'size_bytes' => 1,
            'sha256' => hash('sha256', $name),
            'page_count' => 1,
            'processing_status' => 'PROCESSED',
        ]);

        DocumentClassification::create([
            'review_version_id' => $version->id,
            'review_file_id' => $file->id,
            'primary_type' => $type,
            'secondary_tags' => [],
            'confidence' => $confidence,
            'reason' => 'test',
            'evidence_page_numbers' => [1],
            'warnings' => [],
        ]);
    }
}
