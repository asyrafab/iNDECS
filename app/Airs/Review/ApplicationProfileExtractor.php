<?php

declare(strict_types=1);

namespace App\Airs\Review;

use App\Airs\Ai\AiResponseValidator;
use App\Airs\Ai\AiTaskRunner;
use App\Airs\Contracts\AiProvider;
use App\Airs\Exceptions\InvalidAiResponseException;
use App\Airs\Models\ApplicationProfile;
use App\Airs\Models\ReviewCase;
use App\Airs\Models\ReviewVersion;

final class ApplicationProfileExtractor
{
    public function __construct(
        private readonly AiProvider $provider,
        private readonly AiTaskRunner $runner,
        private readonly AiResponseValidator $validator,
        private readonly DeterministicProfileExtractor $deterministic,
    ) {}

    public function extract(ReviewCase $case, ReviewVersion $version): ApplicationProfile
    {
        $classifications = $version->classifications()->with('file.pages')->get();
        $input = ['documents' => $classifications->map(fn ($classification): array => [
            'file_id' => $classification->review_file_id,
            'filename' => $classification->file->original_name,
            'document_type' => $classification->primary_type,
            'pages' => $classification->file->pages->map(fn ($page): array => [
                'page_number' => $page->page_number,
                'content_type' => $page->content_type,
                'text' => mb_substr((string) $page->embedded_text, 0, 12000),
                'ocr_text' => mb_substr((string) $page->ocr_text, 0, 12000),
                'preview_path' => $page->preview_path,
            ])->all(),
        ])->all()];
        $deterministicResult = config('airs.provider') === 'matrix' ? ['profile' => [], 'confidence_summary' => [], 'conflicts' => []] : $this->deterministic->extract($input);

        try {
            $result = $this->runner->run(
                $version,
                'extract_application_profile',
                'application_profile_v1',
                $input,
                fn (array $payload): array => $this->provider->extractApplicationProfile($payload),
                fn (array $response): array => $this->validator->profile($response),
            );
        } catch (InvalidAiResponseException) {
            $result = [
                'profile' => ['extraction_status' => 'INVALID_AI_RESPONSE'],
                'confidence_summary' => [],
                'conflicts' => [['field' => '*', 'reason' => 'Application profile requires manual extraction.']],
            ];
            $warnings = $case->warnings ?? [];
            $warnings[] = 'Application profile extraction returned invalid structured output.';
            $case->forceFill(['warnings' => array_values(array_unique($warnings))])->save();
        }

        $result = $this->mergeDeterministicResult($result, $deterministicResult);
        $result = $this->applySubmittedLightingTypes($case, $result);

        return ApplicationProfile::updateOrCreate(
            ['review_version_id' => $version->id],
            [
                'profile_json' => $result['profile'],
                'confidence_summary' => $result['confidence_summary'],
                'conflicts_json' => $result['conflicts'],
            ],
        );
    }

    /**
     * @param  array{profile: array<string, mixed>, confidence_summary: array<string, mixed>, conflicts: array<int, mixed>}  $result
     * @param  array{profile: array<string, mixed>, confidence_summary: array<string, float>, conflicts: list<array<string, mixed>>}  $deterministic
     * @return array{profile: array<string, mixed>, confidence_summary: array<string, mixed>, conflicts: array<int, mixed>}
     */
    private function mergeDeterministicResult(array $result, array $deterministic): array
    {
        foreach ($deterministic['profile'] as $field => $value) {
            $current = $result['profile'][$field] ?? null;
            if ($current === null || $current === '' || $current === 'UNKNOWN' || $current === 'VISUAL_EXTRACTION_REQUIRED') {
                $result['profile'][$field] = $value;
            } elseif (is_string($current) && is_string($value)
                && mb_strtolower(trim($current)) !== mb_strtolower(trim($value))) {
                $result['conflicts'][] = [
                    'field' => $field,
                    'reason' => 'Nilai penyedia AI berbeza daripada teks berlabel dalam dokumen.',
                    'ai_value' => $current,
                    'document_value' => $value,
                ];
            }
        }

        $result['confidence_summary'] = array_replace(
            $result['confidence_summary'],
            $deterministic['confidence_summary'],
        );

        return $result;
    }

    /**
     * @param  array{profile: array<string, mixed>, confidence_summary: array<string, mixed>, conflicts: array<int, mixed>}  $result
     * @return array{profile: array<string, mixed>, confidence_summary: array<string, mixed>, conflicts: array<int, mixed>}
     */
    private function applySubmittedLightingTypes(ReviewCase $case, array $result): array
    {
        $types = $case->selected_lighting_types ?? [];
        if ($types === []) {
            return $result;
        }

        $labels = collect((array) config('airs.lighting_types', []))
            ->mapWithKeys(fn (array $meta, string $code): array => [$code => $meta['label'] ?? $code]);

        $result['profile']['selected_lighting_types'] = array_values($types);
        $result['profile']['selected_lighting_type_labels'] = collect($types)
            ->map(fn (string $type): string => (string) ($labels[$type] ?? $type))
            ->values()
            ->all();
        if (config('airs.provider') === 'matrix') {
            $observed = $result['profile']['observed_lighting_types'] ?? [];
            $declared = array_values($types);
            sort($observed);
            sort($declared);
            $result['profile']['lighting_type_conflict'] = $observed !== [] && $observed !== $declared;
            if ($result['profile']['lighting_type_conflict']) {
                $result['conflicts'][] = ['field' => 'lighting_system', 'reason' => 'Pilihan jenis lampu berbeza daripada pemerhatian dokumen.'];
            }

            return $result;
        }
        $lightingSystem = collect($result['profile']['selected_lighting_type_labels'])->implode(', ');
        if (in_array('KONKRIT', $types, true) && ! str_contains(mb_strtolower($lightingSystem), 'tnb')) {
            $lightingSystem .= ' / TNB';
        }
        $result['profile']['lighting_system'] = $lightingSystem;
        $result['confidence_summary']['selected_lighting_types'] = 1.0;
        $result['confidence_summary']['lighting_system'] = 1.0;

        return $result;
    }
}
