<?php

declare(strict_types=1);

namespace App\Airs\Review;

use App\Airs\Enums\FindingStatus;
use App\Airs\Models\ReviewVersion;

final class FindingAggregator
{
    public function aggregate(ReviewVersion $version): array
    {
        $findings = $version->findings()->where('is_superseded', false)->get();
        $counts = array_fill_keys(array_map(fn (FindingStatus $status): string => $status->value, FindingStatus::cases()), 0);
        foreach ($findings as $finding) {
            $counts[$finding->status->value]++;
        }

        $manual = $counts[FindingStatus::ManualReview->value] + $counts[FindingStatus::OfficerManualReview->value];
        $overall = match (true) {
            $counts[FindingStatus::NotComplied->value] > 0 => 'APPLICATION_INCOMPLETE',
            $manual > 0 => 'REVIEW_COMPLETED_PENDING_MANUAL_VERIFICATION',
            $counts[FindingStatus::NotDetected->value] > 0 => 'REVIEW_COMPLETED_WITH_COMMENTS',
            default => 'NO_MATERIAL_ISSUES_DETECTED_BY_AI',
        };

        $version->forceFill(['overall_state' => $overall])->save();

        return [
            'overall_state' => $overall,
            'counts' => $counts,
            'applicable_rules' => $findings->where('applicable', true)->count(),
            'manual_review_items' => $manual,
            'material_issues' => $counts[FindingStatus::NotComplied->value],
        ];
    }
}
