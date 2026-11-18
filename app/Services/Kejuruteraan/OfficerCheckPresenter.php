<?php

declare(strict_types=1);

namespace App\Services\Kejuruteraan;

use App\Airs\Models\GeneratedReport;
use App\Airs\Models\ProfileFieldReview;
use App\Airs\Models\ReviewCase;
use App\Airs\Models\ReviewFinding;
use App\Airs\Models\ReviewFindingFeedback;
use App\Airs\Models\ReviewRule;
use App\Airs\Models\ReviewVersion;
use App\Airs\Models\SystemConfigSnapshot;
use App\Airs\Review\LiveReportScorer;
use App\Airs\Review\MatrixProfileProjection;
use App\Airs\Review\MatrixUsageReport;
use App\Models\Application;
use App\Models\ApplicationDocument;
use App\Models\PaidCheckJourney;
use App\Models\User;
use App\Services\PaidCheck\ApplicantCheckPresenter;
use Illuminate\Support\Collection;

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
        private readonly MatrixProfileProjection $projection,
    ) {}

    public function for(Application $application): array
    {
        $journey = PaidCheckJourney::query()->where('application_id', $application->id)->first();
        $case = ReviewCase::query()->where('application_id', $application->id)->first();
        $versions = $case === null ? collect() : $case->versions()->orderBy('check_number')->get();
        $final = $journey?->final_check_number;

        return [
            'background' => $this->background($application, $journey),
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

    /** Report order, scoped actual findings, and saved officer feedback determine the next task. */
    public function nextUnreviewed(ReviewVersion $version, string $currentId): ?string
    {
        $ordered = array_values(array_unique(array_column($this->itemsFor($version), 'finding_id')));
        $valid = ReviewFinding::query()->where('review_version_id', $version->id)->whereIn('id', $ordered)->pluck('id')->all();
        $ordered = array_values(array_intersect($ordered, $valid));
        $reviewed = ReviewFindingFeedback::query()->whereIn('review_finding_id', $valid)->pluck('review_finding_id')->all();
        $index = array_search($currentId, $ordered, true);
        $index = $index === false ? -1 : $index;
        foreach (array_merge(array_slice($ordered, $index + 1), array_slice($ordered, 0, $index + 1)) as $id) {
            if ($id !== $currentId && ! in_array($id, $reviewed, true)) {
                return $id;
            }
        }

        return null;
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
            'documents' => $completed ? $this->documentsFor($case, $version) : [],
            'fields' => $completed ? $this->fieldsFor($version) : [],
            'usage' => $this->usage->forVersion($version),
        ];
    }

    private function background(Application $application, ?PaidCheckJourney $journey): array
    {
        $application->loadMissing(['applicationType', 'responsibleDepartment', 'assignee', 'sites', 'lots', 'applicationParties.party']);
        $draft = $journey?->draft;

        return [
            'type' => $application->applicationType?->name,
            'department' => $application->responsibleDepartment?->name,
            'assignee' => $application->assignee?->name,
            'submitter' => $draft ? User::query()->whereKey($draft->user_id)->value('name') : null,
            'sites' => $application->sites->map(fn ($site) => implode(', ', array_filter([$site->development_address, $site->mukim, $site->district])))->all(),
            'lots' => $application->lots->map(fn ($lot) => implode(', ', array_filter([$lot->lot_number, $lot->mukim, $lot->district])))->all(),
            'parties' => $application->applicationParties->map(fn ($party) => [
                'role' => match ($party->role) {
                    'applicant' => 'Pemohon', 'developer' => 'Pemaju', 'consultant', 'psp' => 'Perunding', 'owner' => 'Pemilik', default => 'Pihak berkaitan'
                },
                'name' => $party->submitted_snapshot['name'] ?? $party->party?->name,
            ])->filter(fn ($party) => filled($party['name']))->values()->all(),
            'lighting_types' => array_map(fn ($type) => config('airs.lighting_types.'.$type.'.label', $type), $journey?->lighting_types ?? []),
        ];
    }

    /** Only preview supported, clean application documents pinned to this check's manifest. */
    private function documentsFor(ReviewCase $case, ReviewVersion $version): array
    {
        $manifest = SystemConfigSnapshot::query()->where('review_version_id', $version->id)->first()?->config_json['document_manifest'] ?? [];
        $documents = ApplicationDocument::query()->where('application_id', $case->application_id)
            ->whereIn('id', array_column($manifest, 'id'))->where('scan_status', 'clean')
            ->whereIn('mime_type', ['application/pdf', 'image/png', 'image/jpeg', 'image/webp'])->get()->keyBy('id');

        return collect($manifest)->filter(fn ($entry) => isset($entry['id'], $entry['sha256'])
            && $documents->has($entry['id']) && hash_equals($entry['sha256'], $documents[$entry['id']]->checksum_sha256))
            ->map(fn ($entry) => [
                'id' => $entry['id'], 'name' => $documents[$entry['id']]->original_name,
                'revision' => $documents[$entry['id']]->revision_number,
                'url' => route('kejuruteraan.documents.show', [$case->application_id, $entry['id']]),
            ])->unique('id')->values()->all();
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
        ReviewRule::query()->where('rule_pack', $version->rule_pack)->where('rule_version', $version->rule_version)
            ->where('active', true)->pluck('applies_when')->each(fn ($when) => $collect($when));

        return array_intersect($changed, array_keys($used)) !== [];
    }

    /** MATRIX-extracted profile fields with the latest officer disposition and the next expected revision. */
    private function fieldsFor(ReviewVersion $version): array
    {
        $profile = $version->profile()->first()?->profile_json ?? [];
        $reviews = ProfileFieldReview::query()->with('actor')->where('review_version_id', $version->id)->orderBy('revision')->get()->groupBy('field');

        return array_map(function (string $field) use ($profile, $reviews): array {
            $latest = $reviews->get($field)?->last();
            $evidence = collect($profile['field_evidence'] ?? [])->firstWhere('field', $field);

            return ['field' => $field, 'extracted' => is_scalar($profile[$field] ?? null) ? (string) $profile[$field] : null,
                'page' => $evidence['page'] ?? null, 'disposition' => $latest?->disposition, 'value' => $latest?->value,
                'reason' => $latest?->reason, 'officer' => $latest?->actor?->name, 'revision' => (int) ($latest?->revision ?? 0)];
        }, MatrixProfileProjection::FIELDS);
    }

    /** @return Collection<string, Collection<int, ReviewFindingFeedback>> */
    private function feedbackFor(ReviewVersion $version): Collection
    {
        return ReviewFindingFeedback::query()->with('actor')
            ->whereHas('finding', fn ($query) => $query->where('review_version_id', $version->id))
            ->orderBy('created_at')->orderBy('id')->get()->groupBy('review_finding_id');
    }
}
