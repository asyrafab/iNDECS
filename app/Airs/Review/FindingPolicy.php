<?php

declare(strict_types=1);

namespace App\Airs\Review;

use App\Airs\Enums\FindingStatus;
use App\Airs\Models\ReviewRule;

final class FindingPolicy
{
    public function enforce(ReviewRule $rule, array $result, array $availableEvidence): array
    {
        $status = FindingStatus::from($result['status']);
        $validFileIds = array_column($availableEvidence, 'file_id');
        $result['evidence'] = array_values(array_filter(
            $result['evidence'] ?? [],
            fn (array $item): bool => in_array($item['file_id'] ?? null, $validFileIds, true),
        ));

        if ($status === FindingStatus::Complied && ($result['evidence'] === [] || $rule->manual_confirmation_required)) {
            $officerReview = $this->requiresOfficerManualReview($rule);
            $result['status'] = $officerReview
                ? FindingStatus::OfficerManualReview->value
                : FindingStatus::ManualReview->value;
            $result['manual_review_reason'] = $officerReview
                ? 'The requirement includes an officer-only official-status or authenticity decision.'
                : 'A positive compliance claim was withheld because no valid direct evidence was supplied.';
            $result['warnings'] = array_values(array_unique([...($result['warnings'] ?? []), 'POSITIVE_EVIDENCE_POLICY_APPLIED']));
        }

        return $result;
    }

    private function requiresOfficerManualReview(ReviewRule $rule): bool
    {
        return in_array($rule->rule_id, [
            'LJUP-SUB-002',
            'LJUP-SUB-003',
            'LJUP-SUB-004',
            'LJUP-SUB-005',
            'LJUP-SUB-008',
            'LJUP-SUB-009',
            'LJUP-SUB-010',
            'LJUP-XDOC-007',
        ], true);
    }
}
