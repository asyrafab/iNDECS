<?php

declare(strict_types=1);

namespace App\Airs\Ai;

use App\Airs\Exceptions\InvalidAiResponseException;
use App\Airs\Exceptions\MatrixTaskException;
use App\Airs\Models\AiRun;
use App\Airs\Models\ReviewVersion;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

final class AiTaskRunner
{
    public function run(ReviewVersion $version, string $task, string $schemaId, array $input, callable $providerCall, callable $validator, ?string $ruleId = null): array
    {
        $requestHash = hash('sha256', json_encode($input, JSON_UNESCAPED_UNICODE | JSON_THROW_ON_ERROR));
        $matrix = config('airs.provider') === 'matrix';
        $requestId = (string) Str::ulid();
        if ($matrix) {
            $context = app(MatrixReviewGate::class)->context($version, $requestId, false);
            $cached = AiRun::query()->where('review_version_id', $version->id)->where('task_type', $task)->where('rule_id', $ruleId)
                ->where('provider', 'matrix')->where('model', config('airs.matrix.model'))->where('schema_id', $schemaId)
                ->where('prompt_version', config('airs.prompt_version'))->where('request_hash', $requestHash)->where('status', 'SUCCEEDED')->latest()->first();
            if ($cached) {
                return $cached->parsed_response + ['_ai_run_id' => $cached->id];
            }
            $input['_matrix_context'] = app(MatrixReviewGate::class)->context($version, $requestId);
        }
        $attempt = AiRun::query()->where('review_version_id', $version->id)->where('task_type', $task)->where('rule_id', $ruleId)->count() + 1;
        $run = AiRun::create([
            'id' => $requestId, 'review_version_id' => $version->id, 'task_type' => $task, 'rule_id' => $ruleId,
            'provider' => config('airs.provider'), 'model' => $matrix ? config('airs.matrix.model') : config('airs.text_model'),
            'prompt_id' => $task, 'prompt_version' => config('airs.prompt_version'), 'schema_id' => $schemaId,
            'request_hash' => $requestHash, 'input_manifest' => ['file_ids' => collect($input['documents'] ?? $input['evidence'] ?? [])->pluck('file_id')->filter()->unique()->values()->all(), 'rule_id' => $ruleId, 'input_hash' => $requestHash],
            'status' => 'STARTED', 'attempt' => $attempt, 'started_at' => now(),
        ]);
        $meta = [];
        try {
            $response = $providerCall($input);
            $meta = is_array($response['_meta'] ?? null) ? $response['_meta'] : [];
            unset($response['_meta']);
            $this->recordMetadata($run, $meta);
            if ($matrix) {
                $current = app(MatrixReviewGate::class)->context($version, $requestId, false);
                if ($current['document_set_version'] !== $input['_matrix_context']['document_set_version']) {
                    throw new MatrixTaskException('MATRIX_STALE_SOURCE');
                }
            }
            $validated = $validator($response);
            $run->forceFill(['parsed_response' => $validated, 'status' => 'SUCCEEDED', 'completed_at' => now()])->save();

            return $validated + ['_ai_run_id' => $run->id];
        } catch (\Throwable $exception) {
            if ($exception instanceof MatrixTaskException && $exception->usageMetadata !== []) {
                $details = $exception->usageMetadata;
                $this->recordMetadata($run, ['raw_response' => $details['raw_response'] ?? '{}', 'usage' => $details['usage'] ?? null,
                    'estimated_cost' => $details['usage']['estimated_cost_usd'] ?? null, 'latency_ms' => $details['latency_ms'] ?? null,
                    'input_tokens' => $details['usage']['input_tokens'] ?? null, 'output_tokens' => $details['usage']['output_tokens'] ?? null]);
            }
            $run->forceFill(['status' => 'FAILED', 'error_code' => $exception instanceof InvalidAiResponseException ? 'AI_RESPONSE_INVALID' : 'AI_PROVIDER_ERROR',
                'error_message' => $exception instanceof MatrixTaskException ? $exception->getMessage() : 'The AI task failed; inspect the protected audit record.', 'completed_at' => now()])->save();
            throw $exception;
        }
    }

    private function recordMetadata(AiRun $run, array $meta): void
    {
        $rawPath = 'airs/'.ReviewVersion::findOrFail($run->review_version_id)->review_case_id.'/ai/'.$run->id.'.json';
        if (! Storage::disk('local')->put($rawPath, (string) ($meta['raw_response'] ?? '{}'))) {
            throw new \RuntimeException('AI audit storage unavailable.');
        }
        $run->forceFill([
            'provider_request_id' => $meta['provider_request_id'] ?? null, 'model' => $meta['model'] ?? $run->model,
            'raw_response_path' => $rawPath, 'latency_ms' => $meta['latency_ms'] ?? null,
            'input_tokens' => $meta['input_tokens'] ?? null, 'output_tokens' => $meta['output_tokens'] ?? null,
            'estimated_cost' => $meta['estimated_cost'] ?? null, 'usage_json' => $meta['usage'] ?? null, 'coverage_json' => $meta['coverage'] ?? null,
        ])->save();
    }
}
