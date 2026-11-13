<?php

declare(strict_types=1);

namespace App\Airs\DocumentProcessing;

use App\Airs\Contracts\AiProvider;
use App\Airs\Exceptions\InvalidAiResponseException;
use App\Airs\Models\DocumentClassification;
use App\Airs\Models\ReviewCase;
use App\Airs\Models\ReviewVersion;
use App\Airs\Ai\AiResponseValidator;
use App\Airs\Ai\AiTaskRunner;

final class DocumentClassifier
{
    public function __construct(
        private readonly AiProvider $provider,
        private readonly AiTaskRunner $runner,
        private readonly AiResponseValidator $validator,
    ) {
    }

    public function classify(ReviewCase $case, ReviewVersion $version): void
    {
        $warnings = $case->warnings ?? [];
        $files = $case->activeFiles()
            ->where('extension', '!=', 'zip')
            ->where('processing_status', 'PROCESSED')
            ->get();

        foreach ($files as $file) {
            $input = [
                'file_id' => $file->id,
                'filename' => $file->original_name,
                'extension' => $file->extension,
                'selected_review_module' => $case->review_module_code,
                'page_count' => (int) $file->page_count,
                'pages' => $file->pages()->orderBy('page_number')->limit(3)->get()->map(fn ($page): array => [
                    'page_number' => $page->page_number,
                    'content_type' => $page->content_type,
                    'text' => mb_substr((string) $page->embedded_text, 0, 8000),
                    'preview_path' => $page->preview_path,
                ])->all(),
            ];

            try {
                $result = $this->runner->run(
                    $version,
                    'classify_document',
                    'document_classification_v1',
                    $input,
                    fn (array $payload): array => $this->provider->classifyDocument($payload),
                    fn (array $response): array => $this->validator->classification($response),
                );
            } catch (InvalidAiResponseException) {
                $result = [
                    'primary_type' => 'UNKNOWN',
                    'secondary_tags' => [],
                    'confidence' => 0,
                    'reason' => 'The classifier returned invalid structured output.',
                    'evidence_page_numbers' => [],
                    'warnings' => ['AI_RESPONSE_INVALID'],
                ];
                $warnings[] = "{$file->original_name}: classification requires manual review.";
            }

            DocumentClassification::updateOrCreate(
                ['review_version_id' => $version->id, 'review_file_id' => $file->id],
                [
                    'primary_type' => $result['primary_type'],
                    'secondary_tags' => $result['secondary_tags'],
                    'confidence' => $result['confidence'],
                    'reason' => $result['reason'],
                    'evidence_page_numbers' => $result['evidence_page_numbers'],
                    'warnings' => $result['warnings'],
                    'ai_run_id' => $result['_ai_run_id'] ?? null,
                ],
            );
        }

        $case->forceFill(['warnings' => array_values(array_unique($warnings))])->save();
    }
}
