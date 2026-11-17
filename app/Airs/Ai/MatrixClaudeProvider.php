<?php

declare(strict_types=1);

namespace App\Airs\Ai;

use App\Airs\Contracts\AiProvider;
use App\Airs\Exceptions\MatrixTaskException;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Storage;
use Symfony\Component\Process\Process;

/** Local stdin/stdout delegation. No source records or authentication tokens move to MATRIX. */
final class MatrixClaudeProvider implements AiProvider
{
    public function classifyDocument(array $input): array
    {
        return $this->request('classify_document', $input);
    }

    public function extractApplicationProfile(array $input): array
    {
        return $this->request('extract_application_profile', $input);
    }

    public function evaluateRule(array $input): array
    {
        return $this->request('evaluate_rule', $input);
    }

    public function generateReport(array $input): array
    {
        return ['narrative' => 'Laporan dijana daripada dapatan tersimpan.', '_meta' => ['raw_response' => '{}', 'model' => 'deterministic', 'input_tokens' => 0, 'output_tokens' => 0, 'estimated_cost' => 0]];
    }

    private function request(string $task, array $input): array
    {
        if (! config('airs.matrix.enabled')) {
            throw new MatrixTaskException('MATRIX_DISABLED');
        }
        $context = $input['_matrix_context'] ?? null;
        unset($input['_matrix_context']);
        if (! is_array($context) || ($context['phase'] ?? '') !== 'FORMAL_REVIEW') {
            throw new MatrixTaskException('MATRIX_FORMAL_REVIEW_REQUIRED');
        }
        [$images, $coverage] = $this->images($task, $input, (string) $context['case_id']);
        $payload = [
            'contract_version' => 'matrix.airs.v1', 'task' => $task, 'context' => $context,
            'input' => $this->withoutPaths($input), 'images' => $images, 'coverage' => $coverage,
            'options' => ['model' => config('airs.matrix.model'), 'budget_usd' => config('airs.matrix.request_budget_usd'), 'timeout_ms' => config('airs.matrix.timeout_seconds') * 1000],
        ];

        // Serialize native account use across workers. Do not share credentials with another service identity.
        return Cache::lock('matrix-native-claude-account', (int) config('airs.matrix.timeout_seconds') + 30)->block(15, function () use ($payload): array {
            $process = new Process([(string) config('airs.matrix.node'), (string) config('airs.matrix.worker')], base_path());
            $process->setTimeout((int) config('airs.matrix.timeout_seconds') + 15);
            $process->setInput(json_encode($payload, JSON_UNESCAPED_UNICODE | JSON_THROW_ON_ERROR));
            $process->run();
            try {
                $response = json_decode($process->getOutput(), true, flags: JSON_THROW_ON_ERROR);
            } catch (\Throwable) {
                throw new MatrixTaskException('MATRIX_INVALID_WORKER_RESPONSE');
            }
            if (! $process->isSuccessful() || isset($response['error'])) {
                $code = (string) ($response['error']['code'] ?? 'MATRIX_WORKER_FAILED');
                throw new MatrixTaskException(preg_match('/^[A-Z_]+$/', $code) ? $code : 'MATRIX_WORKER_FAILED', $response['error']['meta'] ?? []);
            }
            if (($response['contract_version'] ?? '') !== 'matrix.airs.v1' || ($response['request_id'] ?? '') !== $payload['context']['request_id'] || ! is_array($response['result'] ?? null)) {
                throw new MatrixTaskException('MATRIX_CORRELATION_MISMATCH');
            }
            $meta = $response['meta'];
            $usage = $meta['usage'] ?? [];

            return $response['result'] + ['_meta' => [
                'provider_request_id' => $response['request_id'], 'model' => $meta['model'], 'latency_ms' => $meta['latency_ms'],
                'raw_response' => $meta['raw_response'], 'input_tokens' => $usage['input_tokens'] ?? null, 'output_tokens' => $usage['output_tokens'] ?? null,
                'estimated_cost' => $usage['estimated_cost_usd'] ?? null, 'usage' => $usage, 'coverage' => $meta['coverage'] ?? null,
            ]];
        });
    }

    private function images(string $task, array $input, string $caseId): array
    {
        $candidates = [];
        if ($task === 'classify_document') {
            foreach ($input['pages'] ?? [] as $page) {
                $candidates[] = $page + ['file_id' => $input['file_id']];
            }
        }
        if ($task === 'extract_application_profile') {
            foreach ($input['documents'] ?? [] as $document) {
                foreach ($document['pages'] ?? [] as $page) {
                    $candidates[] = $page + ['file_id' => $document['file_id']];
                }
            }
        }
        if ($task === 'evaluate_rule') {
            $candidates = $input['evidence'] ?? [];
        }
        $images = [];
        $omitted = [];
        $seen = [];
        foreach ($candidates as $page) {
            $reference = ['file_id' => $page['file_id'], 'page_number' => $page['page_number']];
            $key = $reference['file_id'].':'.$reference['page_number'];
            if (isset($seen[$key])) {
                continue;
            }
            $seen[$key] = true;
            $path = $page['preview_path'] ?? $page['high_res_path'] ?? null;
            if (! is_string($path) || $path === '') {
                $omitted[] = $reference + ['reason' => 'NO_PREVIEW'];

                continue;
            }
            $path = str_replace('\\', '/', $path);
            if (! str_starts_with($path, 'airs/'.$caseId.'/') || str_contains($path, '..')) {
                throw new MatrixTaskException('MATRIX_IMAGE_SCOPE_DENIED');
            }
            $disk = Storage::disk('local');
            if (! $disk->exists($path)) {
                $omitted[] = $reference + ['reason' => 'PREVIEW_UNAVAILABLE'];

                continue;
            }
            if (count($images) >= config('airs.matrix.max_images') || $disk->size($path) > config('airs.matrix.max_image_bytes')) {
                $omitted[] = $reference + ['reason' => 'IMAGE_BUDGET'];

                continue;
            }
            $binary = $disk->get($path);
            $imageInfo = @getimagesizefromstring($binary);
            $mime = $imageInfo['mime'] ?? null;
            if (! in_array($mime, ['image/jpeg', 'image/png'], true) || $imageInfo[0] > 6000 || $imageInfo[1] > 6000 || $imageInfo[0] * $imageInfo[1] > 20000000) {
                throw new MatrixTaskException('MATRIX_INVALID_IMAGE');
            }
            $images[] = $reference + ['media_type' => $mime, 'data' => base64_encode($binary)];
        }

        return [$images, ['supplied_images' => count($images), 'omitted_images' => $omitted, 'image_coverage_complete' => $omitted === []]];
    }

    private function withoutPaths(array $input): array
    {
        foreach ($input as $key => $value) {
            if (in_array($key, ['preview_path', 'high_res_path', 'safe_storage_path'], true)) {
                unset($input[$key]);
            } elseif (is_array($value)) {
                $input[$key] = $this->withoutPaths($value);
            }
        }

        return $input;
    }
}
