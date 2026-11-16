<?php

declare(strict_types=1);

namespace App\Services\Kejuruteraan;

use App\Airs\Models\GeneratedReport;
use App\Airs\Models\ReviewCase;
use App\Airs\Models\ReviewFindingFeedback;
use App\Airs\Review\LiveReportScorer;
use App\Airs\Models\ReviewVersion;
use App\Airs\Review\MatrixUsageReport;
use App\Models\Application;
use App\Models\PaidCheckJourney;
use App\Services\PaidCheck\ApplicantCheckPresenter;

/**
 * Officer view of an LJUP application's AIRS checks: everything the applicant view hides (AI rationale,
 * confidence, manual-review reasons, warnings, shadow outcome, usage). Read-only; feedback is separate.
 */
final class OfficerCheckPresenter
{
    public function __construct(
        private readonly ApplicantCheckPresenter $applicantView,
        private readonly MatrixUsageReport $usage,
        private readonly LiveReportScorer $scorer,
        private readonly \App\Airs\Review\MatrixProfileProjection $projection,
    ) {}

    public function for(Application $application): array
    {
        $journey = PaidCheckJourney::query()->where('application_id', $application->id)->first();
        $case = ReviewCase::query()->where('application_id', $application->id)->first();
        $versions = $case === null ? collect() : $case->versions()->orderBy('check_number')->get();
        $final = $journey?->final_check_number;

        return [
            'journey' => $journey,
            'final_check_number' => $final,
            'checks' => $versions->map(fn (ReviewVersion $version) => $this->check($case, $version))->values()->all(),
            'missing_required' => $journey !== null && $final !== null ? $this->applicantView->missingRequired($journey, $final) : [],
        ];
    }

    public function itemsFor(ReviewVersion $version): array
    {
        $report = GeneratedReport::query()->where('review_version_id', $version->id)->latest()->first()?->report_json ?? [];
        $items = [];
        foreach ($report['sections'] ?? [] as $section) {
            foreach ($section['findings'] ?? [] as $finding) {
                $items[] = [
                    'finding_id' => (string) ($finding['id'] ?? ''),
                    'rule_id' => (string) ($finding['rule_id'] ?? ''),
                    'section' => (string) ($section['title'] ?? ''),
                    'title' => (string) ($finding['title'] ?? ''),
                    'requirement' => (string) ($finding['requirement'] ?? ''),
                    'ai_status' => (string) ($finding['status'] ?? ''),
                    'confidence' => isset($finding['confidence']) ? (float) $finding['confidence'] : null,
                    'finding' => (string) ($finding['finding'] ?? ''),
                    'manual_review_reason' => $finding['manual_review_reason'] ?? null,
                    'standard_comment' => $finding['standard_comment'] ?? null,
                    'warnings' => array_values($finding['warnings'] ?? []),
                    'evidence' => collect($finding['evidence'] ?? [])->map(fn ($evidence) => [
                        'document_id' => $evidence['source_document_id'] ?? null, 'document' => (string) ($evidence['document'] ?? ''),
                        'page' => $evidence['page'] ?? null, 'quote' => (string) ($evidence['quote_or_value'] ?? ''),
                    ])->values()->all(),
                ];
            }
        }

        return $items;
    }

    private function check(ReviewCase $case, ReviewVersion $version): array
    {
        $completed = $version->status === 'COMPLETED';
        $feedback = $completed ? $this->feedbackFor($version) : collect();
        $officerScore = $version->score;
        $profileStale = false;
        if ($completed) {
            $report = GeneratedReport::query()->where('review_version_id', $version->id)->latest()->first()?->report_json ?? [];
            // A corrected profile field defers the officer score only when a rule of this check depends on it.
            $profileStale = $this->correctionAffectsRules($version, $report);
            if ($profileStale) {
                $officerScore = null;
            } elseif ($feedback->isNotEmpty()) {
                $officerScore = data_get($this->scorer->apply($report, $feedback), 'application_score.score', $version->score);
            }
        }
        $items = $completed ? array_map(function (array $item) use ($feedback): array {
            $history = $feedback->get($item['finding_id']) ?? collect();
            $latest = $history->last();

            return $item + [
                'officer_status' => $latest?->status->value,
                'officer_comment' => $latest?->comment,
                'feedback_history' => $history->map(fn ($entry) => ['status' => $entry->status->value, 'comment' => $entry->comment,
                    'officer' => $entry->actor?->name, 'at' => $entry->created_at])->values()->all(),
            ];
        }, $this->itemsFor($version)) : [];

        return [
            'version' => $version,
            'number' => (int) $version->check_number,
            'status' => $version->status,
            'error_code' => $version->status === 'FAILED' ? $case->error_code : null,
            'score' => $version->score,
            'officer_score' => $officerScore,
            'profile_stale' => $profileStale,
            'shadow_outcome' => $version->shadow_outcome,
            'items' => $items,
            'fields' => $completed ? $this->fieldsFor($version) : [],
            'usage' => $this->usage->forVersion($version),
        ];
    }

    /** True when an officer correction changed a profile field that an active rule's applicability reads. */
    private function correctionAffectsRules(ReviewVersion $version, array $report): bool
    {
        $projected = $this->projection->apply($report);
        if (! ($projected['profile_dependencies_stale'] ?? false)) {
            return false;
        }
        $changed = [];
        foreach ($projected['profile_field_reviews'] ?? [] as $field => $review) {
            if (($projected['application_profile'][$field] ?? null) !== ($report['application_profile'][$field] ?? null)) {
                $changed[] = $field;
            }
        }
        $used = [];
        $collect = function (mixed $node) use (&$collect, &$used): void {
            if (! is_array($node)) {
                return;
            }
            foreach ($node as $key => $value) {
                if (is_string($key) && ! in_array($key, ['any_of', 'all_of', 'not', 'contains', 'contains_any', 'not_contains', 'equals', 'in', 'missing', 'known'], true)) {
                    $used[$key] = true;
                }
                $collect($value);
            }
        };
        \App\Airs\Models\ReviewRule::query()->where('rule_pack', $version->rule_pack)->where('rule_version', $version->rule_version)
            ->where('active', true)->pluck('applies_when')->each(fn ($when) => $collect($when));

        return array_intersect($changed, array_keys($used)) !== [];
    }

    /** MATRIX-extracted profile fields with the latest officer disposition and the next expected revision. */
    private function fieldsFor(ReviewVersion $version): array
    {
        $profile = $version->profile()->first()?->profile_json ?? [];
        $reviews = \App\Airs\Models\ProfileFieldReview::query()->with('actor')->where('review_version_id', $version->id)->orderBy('revision')->get()->groupBy('field');

        return array_map(function (string $field) use ($profile, $reviews): array {
            $latest = $reviews->get($field)?->last();
            $evidence = collect($profile['field_evidence'] ?? [])->firstWhere('field', $field);

            return ['field' => $field, 'extracted' => is_scalar($profile[$field] ?? null) ? (string) $profile[$field] : null,
                'page' => $evidence['page'] ?? null, 'disposition' => $latest?->disposition, 'value' => $latest?->value,
                'reason' => $latest?->reason, 'officer' => $latest?->actor?->name, 'revision' => (int) ($latest?->revision ?? 0)];
        }, \App\Airs\Review\MatrixProfileProjection::FIELDS);
    }

    /** @return \Illuminate\Support\Collection<string, \Illuminate\Support\Collection<int, ReviewFindingFeedback>> */
    private function feedbackFor(ReviewVersion $version): \Illuminate\Support\Collection
    {
        return ReviewFindingFeedback::query()->with('actor')
            ->whereHas('finding', fn ($query) => $query->where('review_version_id', $version->id))
            ->orderBy('created_at')->orderBy('id')->get()->groupBy('review_finding_id');
    }
}
