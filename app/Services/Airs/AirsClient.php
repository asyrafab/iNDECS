<?php

declare(strict_types=1);

namespace App\Services\Airs;

use App\Models\AirsReview;
use App\Models\ApplicationDocument;
use Illuminate\Http\Client\PendingRequest;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Storage;
use RuntimeException;

final class AirsClient
{
    private function request(): PendingRequest
    {
        $url = rtrim((string) config('airs.url'), '/');
        $parts = parse_url($url);
        $loopback = is_array($parts) && in_array($parts['host'] ?? '', ['localhost', '127.0.0.1', '[::1]'], true);
        if (! config('airs.enabled') || ! is_array($parts) || ! isset($parts['host'])
            || isset($parts['user']) || isset($parts['pass']) || isset($parts['query']) || isset($parts['fragment'])
            || (($parts['scheme'] ?? '') !== 'https' && ! (($parts['scheme'] ?? '') === 'http' && $loopback && app()->environment(['local', 'testing'])))
            || strlen((string) config('airs.token')) < 32) {
            throw new RuntimeException('AIRS connection is not configured.');
        }

        return Http::baseUrl($url.'/api/v1')->withToken(config('airs.token'))->acceptJson()
            ->withoutRedirecting()->connectTimeout(5)->timeout(90);
    }

    public function capabilities(): array
    {
        return $this->request()->get('/capabilities')->throw()->json('data');
    }

    public function submit(AirsReview $review): array
    {
        $submission = $review->submission;
        $manifest = $submission->document_manifest;
        $client = $this->request()->asMultipart();
        $streams = [];
        try {
            foreach ($manifest as $index => $entry) {
                $file = ApplicationDocument::query()->where('application_id', $review->application_id)->findOrFail($entry['id']);
                $streams[] = $stream = Storage::disk('local')->readStream($file->storage_path);
                if (! is_resource($stream)) {
                    throw new RuntimeException('Document unavailable.');
                }
                $client->attach('files['.$index.']', $stream, $entry['name']);
            }

            return $client->post('/reviews', ['manifest' => json_encode([
                'request_id' => $review->id, 'application_id' => $review->application_id,
                'application_reference' => $review->application->application_reference,
                'submission_id' => $submission->id, 'document_set_hash' => $submission->document_set_hash,
                'module_code' => $review->module_code, 'lighting_types' => $review->lighting_types,
                'payment_clearance_reference' => $submission->payment_clearance_reference,
                'clearance_kind' => $submission->clearance_kind,
                'payment_cleared_at' => $submission->payment_cleared_at->toIso8601String(),
                'accepted_at' => $submission->accepted_at->toIso8601String(), 'documents' => $manifest,
            ], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_THROW_ON_ERROR)])->throw()->json('data');
        } finally {
            foreach ($streams as $stream) {
                if (is_resource($stream)) {
                    fclose($stream);
                }
            }
        }
    }

    public function status(AirsReview $review): array
    {
        return $this->request()->get('/reviews/'.$review->remote_id)->throw()->json('data');
    }

    public function feedback(AirsReview $review, array $payload): void
    {
        $this->request()->post('/reviews/'.$review->remote_id.'/findings/'.$payload['finding_id'].'/feedback', $payload)->throw();
    }
}
