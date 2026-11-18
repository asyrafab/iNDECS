<?php

declare(strict_types=1);

namespace App\Services;

use App\Contracts\RecommendationSource;
use App\Models\ApplicationLetterDraft;
use App\Models\ApplicationRecommendation;
use App\Models\AuditLog;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\Rule;

/** Internal preparation only: never transitions an application or publishes a letter. */
final class ApplicationRecommendationService
{
    public function context(User $actor, string $applicationId, RecommendationSource $source): array
    {
        return DB::transaction(function () use ($actor, $applicationId, $source): array {
            $snapshot = $source->lock($actor, $applicationId);

            return ['snapshot' => $snapshot, 'source_hash' => $this->hash($snapshot),
                'revision' => (int) ApplicationRecommendation::query()->where('application_id', $applicationId)->max('revision')];
        });
    }

    public function record(User $actor, string $applicationId, RecommendationSource $source, array $input): ApplicationRecommendation
    {
        return DB::transaction(function () use ($actor, $applicationId, $source, $input): ApplicationRecommendation {
            $snapshot = $source->lock($actor, $applicationId);
            $data = Validator::make($input, [
                'expected_revision' => ['required', 'integer', 'min:0'], 'source_hash' => ['required', 'string', 'size:64'],
                'outcome' => ['required', Rule::in(array_keys($source->outcomes()))],
                'reasons' => ['required', 'string', 'max:6000'], 'conditions' => ['nullable', 'string', 'max:6000'],
                'evidence_references' => ['required', 'string', 'max:4000'],
                'missing_document_explanation' => [Rule::requiredIf($snapshot['missing_required_documents'] !== []), 'nullable', 'string', 'max:4000'],
                'recipient' => ['required', 'string', 'max:2000'], 'subject' => ['required', 'string', 'max:500'],
                'letter_body' => ['nullable', 'string', 'max:12000'],
            ])->validate();
            $last = (int) ApplicationRecommendation::query()->where('application_id', $applicationId)->max('revision');
            abort_unless($last === (int) $data['expected_revision'] && hash_equals($this->hash($snapshot), $data['source_hash']), 409, 'Syor atau bukti telah berubah. Muat semula sebelum menyimpan.');
            $recommendation = new ApplicationRecommendation;
            $recommendation->forceFill(collect($data)->only(['outcome', 'reasons', 'conditions', 'evidence_references', 'missing_document_explanation'])->all() + [
                'application_id' => $applicationId, 'revision' => $last + 1, 'actor_id' => $actor->id,
                'source_snapshot' => $snapshot, 'source_hash' => $data['source_hash'],
            ])->save();
            $content = ['application_reference' => $snapshot['application_reference'], 'title' => $snapshot['title'],
                'recipient' => $data['recipient'], 'subject' => $data['subject'], 'outcome_label' => $source->outcomes()[$data['outcome']],
                'body' => filled($data['letter_body'] ?? null) ? $data['letter_body'] : $data['reasons'],
                'conditions' => $data['conditions'] ?? null, 'prepared_by' => $actor->name, 'revision' => $last + 1];
            $letter = new ApplicationLetterDraft;
            $letter->forceFill(['recommendation_id' => $recommendation->id, 'template_key' => 'internal-recommendation',
                'template_version' => 1, 'content_snapshot' => $content, 'content_hash' => $this->hash($content)])->save();
            AuditLog::query()->create(['auditable_type' => ApplicationRecommendation::class, 'auditable_id' => $recommendation->id,
                'actor_id' => $actor->id, 'event' => 'application_recommendation_drafted',
                'after_values' => ['application_id' => $applicationId, 'revision' => $last + 1, 'source_hash' => $data['source_hash'], 'letter_draft_id' => $letter->id],
                'ip_address' => request()?->ip()]);

            return $recommendation;
        });
    }

    private function hash(array $value): string
    {
        return hash('sha256', json_encode($this->canonical($value), JSON_THROW_ON_ERROR | JSON_UNESCAPED_UNICODE));
    }

    private function canonical(array $value): array
    {
        if (! array_is_list($value)) {
            ksort($value);
        }
        foreach ($value as &$item) {
            if (is_array($item)) {
                $item = $this->canonical($item);
            }
        }

        return $value;
    }
}
