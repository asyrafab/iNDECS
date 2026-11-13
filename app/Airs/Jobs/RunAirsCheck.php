<?php

declare(strict_types=1);

namespace App\Airs\Jobs;

use App\Airs\DocumentProcessing\ArchiveExtractor;
use App\Airs\DocumentProcessing\DocumentClassifier;
use App\Airs\DocumentProcessing\DocumentInventoryService;
use App\Airs\DocumentProcessing\DocumentRelevanceService;
use App\Airs\Enums\ReviewCaseStatus;
use App\Airs\Exceptions\InvalidAiResponseException;
use App\Airs\Exceptions\InvalidDocumentException;
use App\Airs\Exceptions\NoRelevantDocumentException;
use App\Airs\Exceptions\UnsafeArchiveException;
use App\Airs\Models\ProcessingTask;
use App\Airs\Models\ReviewVersion;
use App\Airs\Review\ApplicationProfileExtractor;
use App\Airs\Review\FindingAggregator;
use App\Airs\Review\ReportGenerator;
use App\Airs\Review\ReviewStateService;
use App\Airs\Review\RuleEvaluationService;
use App\Airs\Review\RuleRepository;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\Middleware\WithoutOverlapping;
use Illuminate\Queue\SerializesModels;
use Throwable;

/** Runs one AIRS check (pipeline ported from the archived AIRS ProcessReviewCase job). */
final class RunAirsCheck implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $tries = 3;

    public int $timeout = 900;

    public function __construct(public readonly string $reviewVersionId)
    {
    }

    public function middleware(): array
    {
        $caseId = (string) ReviewVersion::query()->whereKey($this->reviewVersionId)->value('review_case_id');

        return [(new WithoutOverlapping('airs-check-'.$caseId))->shared()->releaseAfter(30)->expireAfter(960)];
    }

    public function backoff(): array
    {
        return [30, 120, 300];
    }

    public function handle(
        ReviewStateService $states,
        ArchiveExtractor $archives,
        DocumentInventoryService $inventory,
        DocumentClassifier $classifier,
        DocumentRelevanceService $relevance,
        ApplicationProfileExtractor $profiles,
        RuleRepository $rules,
        RuleEvaluationService $evaluation,
        FindingAggregator $aggregator,
        ReportGenerator $reports,
    ): void {
        $version = ReviewVersion::query()->find($this->reviewVersionId);
        if ($version === null || $version->status === 'COMPLETED') {
            return;
        }
        $case = $version->reviewCase;
        if ($case->latestVersion()->first()?->id !== $version->id) {
            return; // superseded by a later check
        }
        $version->forceFill(['status' => 'PROCESSING', 'started_at' => $version->started_at ?? now()])->save();

        try {
            $archive = $case->activeFiles()->where('extension', 'zip')->first();
            if ($archive !== null && ! $case->activeFiles()->where('parent_archive_file_id', $archive->id)->exists()) {
                $states->transition($case, ReviewCaseStatus::Extracting);
                $this->task($version->id, 'EXTRACT_ARCHIVE', fn () => $archives->extract($case, $archive));
            }

            $states->transition($case, ReviewCaseStatus::Inventory);
            $this->task($version->id, 'INVENTORY_DOCUMENTS', function () use ($case): void {
                $case->forceFill(['file_count' => $case->activeFiles()->where('extension', '!=', 'zip')->count()])->save();
            });

            $states->transition($case, ReviewCaseStatus::ExtractingText);
            $this->task($version->id, 'PROCESS_DOCUMENTS', fn () => $inventory->inventory($case));

            $states->transition($case, ReviewCaseStatus::Rendering);
            $this->task($version->id, 'VERIFY_PAGE_OUTPUTS', function () use ($case): void {
                if ($case->fresh()->page_count < 1) {
                    throw new InvalidDocumentException('No readable pages were produced from the upload.');
                }
            });

            $states->transition($case, ReviewCaseStatus::Classifying);
            $this->task($version->id, 'CLASSIFY_DOCUMENTS', fn () => $classifier->classify($case, $version));
            $this->task($version->id, 'VERIFY_DOCUMENT_RELEVANCE', fn () => $relevance->assertAtLeastOneRelevantDocument($case, $version));

            $states->transition($case, ReviewCaseStatus::ExtractingProfile);
            $profile = $this->task($version->id, 'EXTRACT_APPLICATION_PROFILE', fn () => $profiles->extract($case, $version));

            $states->transition($case, ReviewCaseStatus::EvaluatingRules);
            $ruleCollection = $this->task($version->id, 'LOAD_RULE_PACK', fn () => $rules->syncPack($version->rule_pack, $version->rule_version));
            $this->task($version->id, 'EVALUATE_RULES', fn () => $evaluation->evaluate($version, $profile, $ruleCollection));

            $states->transition($case, ReviewCaseStatus::Consolidating);
            $this->task($version->id, 'CONSOLIDATE_FINDINGS', fn () => $aggregator->aggregate($version));

            $states->transition($case, ReviewCaseStatus::GeneratingReport);
            $this->task($version->id, 'GENERATE_REPORT', fn () => $reports->generate($case, $version));

            $version->forceFill(['status' => 'COMPLETED', 'completed_at' => now()])->save();
            $states->transition($case, ReviewCaseStatus::Completed);
        } catch (Throwable $exception) {
            $version->forceFill(['status' => 'FAILED'])->save();
            [$code, $message] = $this->safeFailure($exception);
            $states->fail($case, $code, $message);
            throw $exception;
        }
    }

    private function task(string $versionId, string $name, callable $callback): mixed
    {
        $attempt = ProcessingTask::query()->where('review_version_id', $versionId)->where('task_name', $name)->count() + 1;
        $task = ProcessingTask::create([
            'review_version_id' => $versionId, 'task_name' => $name, 'status' => 'STARTED',
            'progress' => 0, 'attempts' => $attempt, 'started_at' => now(),
        ]);

        try {
            $result = $callback();
            $task->forceFill(['status' => 'COMPLETED', 'progress' => 100, 'completed_at' => now()])->save();

            return $result;
        } catch (Throwable $exception) {
            $task->forceFill([
                'status' => 'FAILED', 'error_code' => $exception::class,
                'error_message' => mb_substr($exception->getMessage(), 0, 500), 'completed_at' => now(),
            ])->save();
            throw $exception;
        }
    }

    private function safeFailure(Throwable $exception): array
    {
        return match (true) {
            $exception instanceof NoRelevantDocumentException => ['NO_RELEVANT_DOCUMENTS', 'No uploaded document matches the selected plan review.'],
            $exception instanceof UnsafeArchiveException => ['ARCHIVE_EXTRACTION_FAILED', 'The archive did not pass safe extraction checks.'],
            $exception instanceof InvalidDocumentException => ['DOCUMENT_PROCESSING_FAILED', $exception->getMessage()],
            $exception instanceof InvalidAiResponseException => ['AI_RESPONSE_INVALID', 'An AI response failed schema validation and was not used.'],
            default => ['CASE_FAILED', 'Processing could not be completed. The check can be rerun without using another paid check.'],
        };
    }
}
