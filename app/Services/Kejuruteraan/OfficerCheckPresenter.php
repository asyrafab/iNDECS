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
        if ($completed && $feedback->isNotEmpty()) {
            $report = GeneratedReport::query()->where('review_version_id', $version->id)->latest()->first()?->report_json ?? [];
            $officerScore = data_get($this->scorer->apply($report, $feedback), 'application_score.score', $version->score);
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
            'shadow_outcome' => $version->shadow_outcome,
            'items' => $items,
            'usage' => $this->usage->forVersion($version),
        ];
    }

    /** @return \Illuminate\Support\Collection<string, \Illuminate\Support\Collection<int, ReviewFindingFeedback>> */
    private function feedbackFor(ReviewVersion $version): \Illuminate\Support\Collection
    {
        return ReviewFindingFeedback::query()->with('actor')
            ->whereHas('finding', fn ($query) => $query->where('review_version_id', $version->id))
            ->orderBy('created_at')->orderBy('id')->get()->groupBy('review_finding_id');
    }
}
