<?php

declare(strict_types=1);

namespace App\Airs\Review;

use App\Airs\Enums\ReviewCaseStatus;
use App\Airs\Models\ReviewCase;
use DomainException;

final class ReviewStateService
{
    /** @var array<string, list<string>> */
    private const TRANSITIONS = [
        'UPLOADED' => ['EXTRACTING', 'INVENTORY', 'FAILED'],
        'EXTRACTING' => ['INVENTORY', 'FAILED'],
        'INVENTORY' => ['EXTRACTING_TEXT', 'FAILED'],
        'EXTRACTING_TEXT' => ['RENDERING', 'FAILED'],
        'RENDERING' => ['CLASSIFYING', 'FAILED'],
        'CLASSIFYING' => ['EXTRACTING_PROFILE', 'FAILED'],
        'EXTRACTING_PROFILE' => ['EVALUATING_RULES', 'FAILED'],
        'EVALUATING_RULES' => ['CONSOLIDATING', 'FAILED'],
        'CONSOLIDATING' => ['GENERATING_REPORT', 'FAILED'],
        'GENERATING_REPORT' => ['COMPLETED', 'FAILED'],
        'FAILED' => ['EXTRACTING', 'INVENTORY'],
        'COMPLETED' => [],
    ];

    public function transition(ReviewCase $case, ReviewCaseStatus $next, ?string $step = null): void
    {
        $current = $case->status->value;

        if ($current !== $next->value && ! in_array($next->value, self::TRANSITIONS[$current] ?? [], true)) {
            throw new DomainException("Invalid review transition from {$current} to {$next->value}.");
        }

        $progress = (int) config("airs.stage_progress.{$next->value}", $case->progress);
        if ($next !== ReviewCaseStatus::Failed) {
            $progress = max($case->progress, $progress);
        }

        $case->forceFill([
            'status' => $next,
            'progress' => $progress,
            'current_step' => $step ?? $this->label($next),
            'started_at' => $case->started_at ?? now(),
            'completed_at' => $next === ReviewCaseStatus::Completed ? now() : $case->completed_at,
        ])->save();
    }

    public function fail(ReviewCase $case, string $code, string $safeMessage): void
    {
        $case->forceFill([
            'status' => ReviewCaseStatus::Failed,
            'current_step' => 'Processing stopped',
            'error_code' => $code,
            'error_message' => $safeMessage,
        ])->save();
    }

    private function label(ReviewCaseStatus $status): string
    {
        return match ($status) {
            ReviewCaseStatus::Uploaded => 'Upload received',
            ReviewCaseStatus::Extracting => 'Safely extracting archive',
            ReviewCaseStatus::Inventory => 'Inventorying documents',
            ReviewCaseStatus::ExtractingText => 'Extracting document text',
            ReviewCaseStatus::Rendering => 'Preparing page previews',
            ReviewCaseStatus::Classifying => 'Classifying documents',
            ReviewCaseStatus::ExtractingProfile => 'Extracting application profile',
            ReviewCaseStatus::EvaluatingRules => 'Evaluating applicable LJUP rules',
            ReviewCaseStatus::Consolidating => 'Consolidating findings',
            ReviewCaseStatus::GeneratingReport => 'Generating review report',
            ReviewCaseStatus::Completed => 'Review completed',
            ReviewCaseStatus::Failed => 'Processing stopped',
        };
    }
}
