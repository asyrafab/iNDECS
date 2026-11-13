<?php

declare(strict_types=1);

namespace App\Airs\Review;

use Illuminate\Support\Collection;

/**
 * Re-scores a generated report_json with the latest officer feedback per finding, so officers see an
 * officer-adjusted readiness score beside the original. Advisory only: it never rejects anything.
 */
final class LiveReportScorer
{
    /**
     * @param  Collection<string, Collection<int, object>>  $feedbackByFinding  finding id => feedback entries, oldest first
     */
    public function apply(array $report, Collection $feedbackByFinding): array
    {
        $sectionsOut = [];
        $checklistOut = [];
        $allFindings = [];
        foreach ($report['sections'] ?? [] as $section) {
            $findingsOut = [];
            foreach ($section['findings'] ?? [] as $finding) {
                $latest = ($feedbackByFinding->get($finding['id'] ?? '') ?? collect())->last();
                $finding['ai_status'] = $finding['status'];
                $finding['officer_confirmed'] = $latest !== null;
                if ($latest !== null) {
                    $finding['status'] = $latest->status->value;
                    $finding['officer_confirmed_at'] = $latest->created_at;
                }
                $findingsOut[] = $finding;
                $allFindings[] = $finding;
            }
            $sectionScore = ReadinessScorer::sectionScore($findingsOut);
            $sectionsOut[] = ['title' => $section['title'], 'findings' => $findingsOut];
            $checklistOut[] = ['title' => $section['title'], 'total_items' => count($findingsOut), 'score' => $sectionScore['score'],
                'label' => $sectionScore['label'], 'suggestion' => $sectionScore['suggestion'], 'counts' => $sectionScore['counts']];
        }
        if ($sectionsOut === []) {
            return $report;
        }
        $report['sections'] = $sectionsOut;
        $report['section_checklist'] = $checklistOut;
        $report['application_score'] = ReadinessScorer::score($allFindings);

        return $report;
    }
}
