<?php

declare(strict_types=1);

namespace App\Airs\Ai;

use App\Airs\Exceptions\MatrixTaskException;
use App\Airs\Models\AiRun;
use App\Airs\Models\ReviewVersion;

/**
 * MATRIX (paid Claude) may only run for the current check of a payment-cleared
 * STREET_LIGHTING case, within the per-request and per-check budgets.
 */
final class MatrixReviewGate
{
    public function context(ReviewVersion $version, string $requestId, bool $enforceBudget = true): array
    {
        $case = $version->reviewCase->fresh();
        if (! config('airs.matrix.enabled') || $version->ai_provider !== 'matrix' || $case->review_module_code !== 'STREET_LIGHTING') {
            throw new MatrixTaskException('MATRIX_SCOPE_DENIED');
        }
        if ($case->latestVersion()->first()?->id !== $version->id) {
            throw new MatrixTaskException('MATRIX_STALE_REVIEW');
        }
        if (! in_array($case->clearance_kind, ['paid', 'waived'], true) || blank($case->payment_clearance_reference) || $case->payment_cleared_at === null) {
            throw new MatrixTaskException('MATRIX_CLEARANCE_REQUIRED');
        }
        if ($enforceBudget) {
            $runs = AiRun::query()->where('review_version_id', $version->id)->where('provider', 'matrix')->where('id', '!=', $requestId)->get();
            if ($runs->contains(fn ($run) => $run->status === 'STARTED' || (in_array($run->status, ['SUCCEEDED', 'FAILED'], true) && $run->estimated_cost === null))) {
                throw new MatrixTaskException('MATRIX_USAGE_RECONCILIATION_REQUIRED');
            }
            if ((float) $runs->sum('estimated_cost') + (float) config('airs.matrix.request_budget_usd') > (float) config('airs.matrix.review_budget_usd')) {
                throw new MatrixTaskException('MATRIX_REVIEW_BUDGET');
            }
        }

        $checkNumber = (int) $version->check_number;

        // The MATRIX runtime requires accepted=true for formal work; `stage` records that this is the
        // paid pre-submission check (formal acceptance in iNDECS follows the final check).
        return [
            'phase' => 'FORMAL_REVIEW', 'stage' => 'PRE_SUBMISSION_PAID_CHECK', 'module' => 'STREET_LIGHTING',
            'accepted' => true, 'payment_cleared' => true,
            'case_id' => (string) $case->id, 'review_version_id' => (string) $version->id,
            'application_id' => (string) $case->application_id, 'check_number' => $checkNumber,
            'document_set_version' => (string) $case->document_set_version, 'document_set_hash' => (string) $version->document_set_hash,
            'request_id' => $requestId, 'declared_lighting_types' => $case->selected_lighting_types ?? [],
            'submission_round' => $checkNumber === 2 ? 'SECOND_OR_LATER' : 'FIRST',
        ];
    }
}
