<?php

declare(strict_types=1);

namespace Tests\Feature\Airs;

use App\Airs\Enums\EvaluationMethod;
use App\Airs\Enums\FindingStatus;
use App\Airs\Enums\ReviewCaseStatus;
use App\Airs\Models\ReviewFinding;
use App\Airs\Models\ReviewRule;
use App\Airs\Models\ReviewVersion;
use App\Airs\Review\FindingAggregator;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Storage;
use Tests\Support\AirsFixtures;
use Tests\TestCase;

final class FindingAggregatorTest extends TestCase
{
    use AirsFixtures, RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed();
        Storage::fake('local');
    }

    public function test_material_issue_controls_summary_without_approval_language(): void
    {
        [$version, $rule] = $this->versionAndRule();
        foreach ([FindingStatus::Complied, FindingStatus::NotComplied, FindingStatus::OfficerManualReview] as $status) {
            ReviewFinding::create([
                'review_version_id' => $version->id,
                'review_rule_id' => $rule->id,
                'rule_id' => $rule->rule_id,
                'applicable' => true,
                'status' => $status,
                'confidence' => 0.8,
                'finding' => 'Test finding',
                'extracted_values' => [],
                'warnings' => [],
                'evaluation_method' => EvaluationMethod::Deterministic,
            ]);
        }

        $summary = app(FindingAggregator::class)->aggregate($version);
        self::assertSame('APPLICATION_INCOMPLETE', $summary['overall_state']);
        self::assertSame(1, $summary['counts']['BELUM_DITUNAIKAN']);
        self::assertStringNotContainsString('APPROVED', $summary['overall_state']);
    }

    private function versionAndRule(): array
    {
        $case = $this->airsCase(null, [
            'status' => ReviewCaseStatus::Uploaded,
            'total_original_bytes' => 1,
        ]);
        $version = ReviewVersion::create([
            'review_case_id' => $case->id,
            'check_number' => 1,
            'rule_pack' => 'ljup',
            'rule_version' => 'v1',
            'prompt_version' => 'v1',
            'report_version' => 'v1',
            'ai_provider' => 'fake',
        ]);
        $rule = ReviewRule::create([
            'rule_pack' => 'ljup', 'rule_version' => 'v1', 'rule_id' => 'TEST-001', 'title' => 'Test',
            'section' => 'Test', 'source_document' => 'Test', 'stage' => 'SUB', 'requirement' => 'Test',
            'applies_when' => [], 'evaluation_method' => EvaluationMethod::Deterministic,
            'required_evidence_types' => [], 'severity' => 'LOW', 'manual_confirmation_required' => false,
            'active' => true, 'rule_hash' => str_repeat('a', 64),
        ]);

        return [$version, $rule];
    }
}
