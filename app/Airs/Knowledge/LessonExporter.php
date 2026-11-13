<?php

declare(strict_types=1);

namespace App\Airs\Knowledge;

use App\Airs\Models\ApplicationProfile;
use App\Airs\Models\DocumentClassification;
use App\Airs\Models\ProfileFieldReview;
use App\Airs\Models\ReviewEvidence;
use App\Airs\Models\ReviewFindingFeedback;
use App\Airs\Models\ReviewVersion;
use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

/**
 * Exports officer corrections as de-identified candidate lessons (JSONL) for MATRIX's evaluation
 * process. Candidates are untrusted until evaluated; they never change rules or models by themselves.
 * No application reference, names, e-mails, addresses, file names, paths or bytes are written.
 */
final class LessonExporter
{
    /** @return array{file: ?string, count: int} */
    public function export(?CarbonImmutable $since = null): array
    {
        $lessons = [...$this->findingLessons($since), ...$this->fieldLessons($since)];
        if ($lessons === []) {
            return ['file' => null, 'count' => 0];
        }
        $directory = (string) config('airs.matrix.lessons_path');
        if (! is_dir($directory)) {
            mkdir($directory, 0770, true);
        }
        $name = 'lessons-'.now()->utc()->format('Ymd-His').'-'.Str::ulid().'.jsonl';
        $file = $directory.DIRECTORY_SEPARATOR.$name;
        DB::transaction(function () use ($lessons, $file, $name): void {
            $lines = array_map(fn (array $lesson) => json_encode($lesson['record'], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_THROW_ON_ERROR), $lessons);
            file_put_contents($file, implode("\n", $lines)."\n", LOCK_EX);
            foreach ($lessons as $lesson) {
                DB::table('airs.lesson_exports')->insert(['source_type' => $lesson['source_type'], 'source_id' => $lesson['source_id'], 'file' => $name, 'exported_at' => now()]);
            }
        });

        return ['file' => $file, 'count' => count($lessons)];
    }

    private function findingLessons(?CarbonImmutable $since): array
    {
        $lessons = [];
        $feedback = ReviewFindingFeedback::query()->with('finding.reviewVersion.reviewCase')
            ->whereNotIn('id', DB::table('airs.lesson_exports')->where('source_type', 'finding_feedback')->select('source_id'))
            ->when($since, fn ($query) => $query->where('created_at', '>=', $since))->orderBy('created_at')->get();
        foreach ($feedback as $entry) {
            $finding = $entry->finding;
            $aiStatus = $finding->status->value;
            if ($entry->status->value === $aiStatus && blank($entry->comment)) {
                continue; // a plain confirmation teaches nothing
            }
            $version = $finding->reviewVersion;
            $lessons[] = ['source_type' => 'finding_feedback', 'source_id' => $entry->id, 'record' => $this->base($version, 'finding_correction', $entry->created_at) + [
                'rule_id' => $finding->rule_id, 'ai_status' => $aiStatus, 'officer_status' => $entry->status->value, 'officer_comment' => $entry->comment,
                'field' => null, 'ai_value' => null, 'officer_value' => null, 'evidence' => $this->evidence($finding->id, $version->id),
            ]];
        }

        return $lessons;
    }

    private function fieldLessons(?CarbonImmutable $since): array
    {
        $lessons = [];
        $reviews = ProfileFieldReview::query()->whereIn('disposition', ['CORRECTED', 'REJECTED'])
            ->whereNotIn('id', DB::table('airs.lesson_exports')->where('source_type', 'field_review')->select('source_id'))
            ->when($since, fn ($query) => $query->where('created_at', '>=', $since))->orderBy('created_at')->get();
        foreach ($reviews as $review) {
            $version = ReviewVersion::query()->with('reviewCase')->findOrFail($review->review_version_id);
            $profile = ApplicationProfile::query()->where('review_version_id', $version->id)->value('profile_json') ?? [];
            $aiValue = $profile[$review->field] ?? null;
            $lessons[] = ['source_type' => 'field_review', 'source_id' => $review->id, 'record' => $this->base($version, 'field_correction', $review->created_at) + [
                'rule_id' => null, 'ai_status' => null, 'officer_status' => $review->disposition, 'officer_comment' => $review->reason,
                'field' => $review->field, 'ai_value' => is_scalar($aiValue) ? (string) $aiValue : null, 'officer_value' => $review->value,
                'evidence' => collect($profile['field_evidence'] ?? [])->where('field', $review->field)->map(fn ($item) => ['document_type' => null, 'page' => $item['page'] ?? null])->values()->all(),
            ]];
        }

        return $lessons;
    }

    private function base(ReviewVersion $version, string $kind, $recordedAt): array
    {
        return [
            'lesson_id' => (string) Str::ulid(), 'status' => 'CANDIDATE_UNEVALUATED', 'kind' => $kind,
            'case_key' => hash('sha256', config('app.key').'|'.$version->reviewCase->application_id),
            'module' => $version->reviewCase->review_module_code, 'rule_pack' => $version->rule_pack, 'rule_version' => $version->rule_version,
            'prompt_version' => $version->prompt_version, 'ai_provider' => $version->ai_provider,
            'model' => $version->ai_provider === 'matrix' ? config('airs.matrix.model') : $version->text_model,
            'check_number' => (int) $version->check_number,
            'recorded_at' => CarbonImmutable::instance($recordedAt)->utc()->toIso8601ZuluString(),
        ];
    }

    /** Evidence as document type + page only (no names, paths or quotes). */
    private function evidence(string $findingId, string $versionId): array
    {
        return ReviewEvidence::query()->where('review_finding_id', $findingId)->get()->map(fn (ReviewEvidence $evidence) => [
            'document_type' => DocumentClassification::query()->where('review_version_id', $versionId)->where('review_file_id', $evidence->review_file_id)->value('primary_type'),
            'page' => $evidence->page_number,
        ])->values()->all();
    }
}
