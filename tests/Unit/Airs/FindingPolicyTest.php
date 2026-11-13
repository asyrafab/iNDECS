<?php

declare(strict_types=1);

namespace Tests\Unit\Airs;

use App\Airs\Models\ReviewRule;
use App\Airs\Review\FindingPolicy;
use PHPUnit\Framework\TestCase;

final class FindingPolicyTest extends TestCase
{
    public function test_positive_finding_without_evidence_is_downgraded(): void
    {
        $rule = new ReviewRule(['manual_confirmation_required' => false]);
        $result = (new FindingPolicy)->enforce($rule, [
            'status' => 'DITUNAIKAN',
            'evidence' => [],
            'warnings' => [],
            'manual_review_reason' => null,
        ], []);

        self::assertSame('PERLU_SEMAKAN_MANUAL', $result['status']);
        self::assertContains('POSITIVE_EVIDENCE_POLICY_APPLIED', $result['warnings']);
    }

    public function test_manual_confirmation_rule_without_official_authority_check_uses_normal_manual_review(): void
    {
        $rule = new ReviewRule([
            'rule_id' => 'LJUP-SPC-C-007',
            'manual_confirmation_required' => true,
        ]);
        $evidence = [['file_id' => 'file-1']];
        $result = (new FindingPolicy)->enforce($rule, [
            'status' => 'DITUNAIKAN',
            'evidence' => [['file_id' => 'file-1']],
            'warnings' => [],
            'manual_review_reason' => null,
        ], $evidence);

        self::assertSame('PERLU_SEMAKAN_MANUAL', $result['status']);
    }

    public function test_official_authority_check_uses_officer_manual_review(): void
    {
        $rule = new ReviewRule([
            'rule_id' => 'LJUP-SUB-005',
            'manual_confirmation_required' => true,
        ]);
        $evidence = [['file_id' => 'file-1']];
        $result = (new FindingPolicy)->enforce($rule, [
            'status' => 'DITUNAIKAN',
            'evidence' => [['file_id' => 'file-1']],
            'warnings' => [],
            'manual_review_reason' => null,
        ], $evidence);

        self::assertSame('PERLU_SEMAKAN_MANUAL_PEGAWAI', $result['status']);
    }
}
