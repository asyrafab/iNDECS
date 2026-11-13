<?php

declare(strict_types=1);

namespace App\Airs\Review;

use App\Airs\Models\AiRun;
use App\Airs\Models\ReviewVersion;

final class MatrixUsageReport
{
    public function forVersion(ReviewVersion $version): array
    {
        $runs = AiRun::query()->where('review_version_id', $version->id)->orderBy('created_at')->get();
        $rows = $runs->map(fn ($run): array => [
            'run_id' => $run->id, 'task' => $run->task_type, 'rule_id' => $run->rule_id, 'attempt' => $run->attempt,
            'status' => $run->status, 'model' => $run->model, 'input_tokens' => $run->input_tokens, 'output_tokens' => $run->output_tokens,
            'cache_read_input_tokens' => $run->usage_json['cache_read_input_tokens'] ?? null,
            'cache_creation_input_tokens' => $run->usage_json['cache_creation_input_tokens'] ?? null,
            'estimated_cost_usd' => $run->estimated_cost === null ? null : (float) $run->estimated_cost,
            'latency_ms' => $run->latency_ms, 'per_model' => $run->usage_json['per_model'] ?? [],
            'coverage' => $run->coverage_json,
        ])->all();
        $totals = [];
        foreach (['input_tokens', 'output_tokens', 'cache_read_input_tokens', 'cache_creation_input_tokens', 'estimated_cost_usd'] as $field) {
            $known = array_values(array_filter(array_column($rows, $field), fn ($value) => $value !== null));
            $totals[$field] = ['known_total' => array_sum($known), 'unknown_runs' => count($rows) - count($known)];
        }
        return ['cost_kind' => 'CLIENT_ESTIMATE_NOT_INVOICE', 'currency' => 'USD', 'review_version_id' => $version->id,
            'allocation' => 'Rule runs are direct costs. Classification/profile runs are shared overhead, counted once. Deterministic checks use no extra LLM call.',
            'totals' => $totals, 'runs' => $rows];
    }
}
