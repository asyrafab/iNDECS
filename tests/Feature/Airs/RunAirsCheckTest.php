<?php

declare(strict_types=1);

namespace Tests\Feature\Airs;

use App\Airs\CheckClearance;
use App\Airs\CheckService;
use App\Airs\Contracts\AiProvider;
use App\Airs\Contracts\DocumentProcessor;
use App\Airs\Enums\ReviewCaseStatus;
use App\Airs\Models\AiRun;
use App\Airs\Models\ProcessingTask;
use App\Airs\Models\ReviewFinding;
use App\Airs\Models\ReviewVersion;
use App\Models\Application;
use Carbon\CarbonImmutable;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Storage;
use Tests\Support\AirsFixtures;
use Tests\Support\FakeAirsDocumentProcessor;
use Tests\TestCase;

final class RunAirsCheckTest extends TestCase
{
    use AirsFixtures, RefreshDatabase;

    private Application $application;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed();
        Storage::fake('local');
        $this->app->instance(DocumentProcessor::class, new FakeAirsDocumentProcessor);
        $this->application = $this->ljupApplication();
        $this->ljupDocument($this->application, "%PDF-1.4\n1 0 obj\n<< /Type /Catalog >>\nendobj\n%%EOF", 'pelan-lampu-jalan.pdf');
    }

    private function runCheck(): ReviewVersion
    {
        // QUEUE_CONNECTION=sync in tests: the job runs inside start().
        return app(CheckService::class)->start($this->application, new CheckClearance('paid', 'TEST-PAY-1', CarbonImmutable::now()->subMinute()),
            ['BESI'], $this->application->currentDocuments()->get())->fresh();
    }

    public function test_check_runs_to_completed_report_without_touching_application(): void
    {
        [$stage, $status] = [$this->application->current_stage, $this->application->current_status];

        $version = $this->runCheck();
        $case = $version->reviewCase;

        $this->assertSame('COMPLETED', $version->status);
        $this->assertSame(ReviewCaseStatus::Completed, $case->status);
        $this->assertSame(100, (int) $case->progress);
        $this->assertSame(1, (int) $case->page_count);
        $this->assertSame(0, ProcessingTask::query()->where('review_version_id', $version->id)->where('status', '!=', 'COMPLETED')->count());
        $report = $version->reports()->sole()->report_json;
        foreach (['application_score', 'section_checklist', 'applicability_matrix', 'document_inventory'] as $key) {
            $this->assertNotEmpty($report[$key], $key);
        }
        $this->assertSame(['LJUP Umum', 'LJUP Khusus'], array_column($report['sections'], 'title'));
        $this->assertSame($this->application->currentDocuments()->value('id'), $report['document_inventory'][0]['source_document_id']);
        $this->assertIsInt($version->score);
        $this->assertContains($version->shadow_outcome, ['MEETS_THRESHOLD', 'BELOW_THRESHOLD']);
        $this->assertGreaterThanOrEqual(30, $version->findings()->where('is_superseded', false)->count());
        $this->assertSame($stage, $this->application->fresh()->current_stage);
        $this->assertSame($status, $this->application->fresh()->current_status);
    }

    public function test_invalid_ai_output_is_audited_and_never_stored_as_a_conclusion(): void
    {
        $fake = app(AiProvider::class);
        $this->app->instance(AiProvider::class, new class($fake) implements AiProvider
        {
            public function __construct(private AiProvider $inner) {}

            public function classifyDocument(array $input): array { return $this->inner->classifyDocument($input); }

            public function extractApplicationProfile(array $input): array { return $this->inner->extractApplicationProfile($input); }

            public function evaluateRule(array $input): array { return ['status' => 'LULUS', 'confidence' => 'very']; }

            public function generateReport(array $input): array { return $this->inner->generateReport($input); }
        });

        $version = $this->runCheck();

        $this->assertSame('COMPLETED', $version->status);
        $this->assertGreaterThan(0, AiRun::query()->where('review_version_id', $version->id)->where('task_type', 'evaluate_rule')->where('status', 'FAILED')->count());
        $this->assertSame(0, ReviewFinding::query()->where('review_version_id', $version->id)->whereNotIn('status',
            ['DITUNAIKAN', 'BELUM_DITUNAIKAN', 'TIDAK_BERKAITAN', 'TIDAK_DAPAT_DIKESAN', 'PERLU_SEMAKAN_MANUAL', 'PERLU_SEMAKAN_MANUAL_PEGAWAI'])->count());
        $this->assertGreaterThan(0, ReviewFinding::query()->where('review_version_id', $version->id)->whereJsonContains('warnings', 'AI_RESPONSE_INVALID')->count());
    }

    public function test_unreadable_upload_fails_the_check_without_consuming_it(): void
    {
        $this->app->instance(DocumentProcessor::class, new class implements DocumentProcessor
        {
            public function process(string $filePath, string $outputDirectory): array
            {
                return ['page_count' => 0, 'is_encrypted' => false, 'warnings' => [], 'pages' => []];
            }
        });

        try {
            $this->runCheck();
        } catch (\Throwable) {
            // the sync queue rethrows the job failure
        }

        $version = ReviewVersion::query()->sole();
        $this->assertSame('FAILED', $version->status);
        $this->assertSame(ReviewCaseStatus::Failed, $version->reviewCase->status);
        $this->assertSame(0, app(CheckService::class)->completedChecks($this->application));
    }
}
