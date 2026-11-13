<?php

declare(strict_types=1);

namespace Tests\Feature\Airs;

use App\Airs\Enums\FindingStatus;
use App\Airs\Enums\ReviewCaseStatus;
use App\Airs\Models\ApplicationProfile;
use App\Airs\Models\ReviewCase;
use App\Airs\Models\ReviewFile;
use App\Airs\Models\ReviewVersion;
use App\Airs\Review\FindingPolicy;
use App\Airs\Review\OfficerReferenceCalibrationService;
use App\Airs\Review\RuleApplicabilityService;
use App\Airs\Review\RuleRepository;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Support\AirsFixtures;
use Tests\TestCase;

final class OfficerReferenceCalibrationTest extends TestCase
{
    use AirsFixtures, RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed();
        \Illuminate\Support\Facades\Storage::fake('local');
    }

    public function test_exact_officer_review_reference_keeps_final_complied_status_with_evidence(): void
    {
        [$version, $profile, $file] = $this->pilotCase();
        $rule = app(RuleRepository::class)->syncConfiguredPack()->firstWhere('rule_id', 'LJUP-SPC-C-007');
        self::assertNotNull($rule);

        $available = [[
            'file_id' => $file->id,
            'page_number' => 1,
            'document_type' => 'LJUP_PLAN',
            'quote_or_value' => '',
            'evidence_type' => 'TEXT',
            'confidence' => 0.9,
        ]];
        $calibrated = app(OfficerReferenceCalibrationService::class)->forRule($version, $profile, $rule, $available);

        self::assertNotNull($calibrated);
        self::assertSame(FindingStatus::Complied->value, $calibrated['result']['status']);
        self::assertSame(FindingStatus::NotComplied->value, data_get($calibrated, 'result.extracted_values.review_history.first_officer_status'));
        self::assertSame(FindingStatus::Complied->value, data_get($calibrated, 'result.extracted_values.review_history.final_officer_status'));

        $enforced = app(FindingPolicy::class)->enforce($rule, $calibrated['result'], $available);
        self::assertSame(FindingStatus::Complied->value, $enforced['status']);
        self::assertCount(1, $enforced['evidence']);
        self::assertSame($file->id, $enforced['evidence'][0]['file_id']);
    }

    public function test_calibration_does_not_apply_when_source_file_hash_differs(): void
    {
        [$version, $profile, $file] = $this->pilotCase();
        $file->forceFill(['sha256' => str_repeat('0', 64)])->save();
        $rule = app(RuleRepository::class)->syncConfiguredPack()->firstWhere('rule_id', 'LJUP-SPC-C-007');

        $result = app(OfficerReferenceCalibrationService::class)->forRule($version, $profile, $rule, [[
            'file_id' => $file->id,
            'page_number' => 1,
            'document_type' => 'LJUP_PLAN',
        ]]);

        self::assertNull($result);
    }

    public function test_rule_pack_v2_applies_tnb_rules_and_excludes_galvanised_rules(): void
    {
        $rules = app(RuleRepository::class)->syncConfiguredPack();
        $applicability = app(RuleApplicabilityService::class);

        $konkrit = ['selected_lighting_types' => ['KONKRIT']];
        self::assertTrue($applicability->evaluate($rules->firstWhere('rule_id', 'LJUP-SPC-D-TNB-002'), $konkrit));
        self::assertFalse($applicability->evaluate($rules->firstWhere('rule_id', 'LJUP-SPC-D-GAL-001'), $konkrit));
        self::assertFalse($applicability->evaluate($rules->firstWhere('rule_id', 'LJUP-SPC-C-009'), $konkrit));

        $besi = ['selected_lighting_types' => ['BESI']];
        self::assertFalse($applicability->evaluate($rules->firstWhere('rule_id', 'LJUP-SPC-D-TNB-002'), $besi));
        self::assertTrue($applicability->evaluate($rules->firstWhere('rule_id', 'LJUP-SPC-D-GAL-001'), $besi));
        self::assertTrue($applicability->evaluate($rules->firstWhere('rule_id', 'LJUP-SPC-C-009'), $besi));

        $solar = ['selected_lighting_types' => ['SOLAR']];
        self::assertFalse($applicability->evaluate($rules->firstWhere('rule_id', 'LJUP-SPC-D-TNB-002'), $solar));
        self::assertTrue($applicability->evaluate($rules->firstWhere('rule_id', 'LJUP-SPC-D-GAL-001'), $solar));

        $mixed = ['selected_lighting_types' => ['BESI', 'KONKRIT', 'SOLAR']];
        self::assertTrue($applicability->evaluate($rules->firstWhere('rule_id', 'LJUP-SPC-D-TNB-002'), $mixed));
        self::assertTrue($applicability->evaluate($rules->firstWhere('rule_id', 'LJUP-SPC-D-GAL-001'), $mixed));
    }

    public function test_rule_pack_v2_keeps_legacy_lighting_system_fallback(): void
    {
        $rules = app(RuleRepository::class)->syncConfiguredPack();
        $applicability = app(RuleApplicabilityService::class);
        $profile = ['lighting_system' => 'Tiang konkrit TNB'];

        self::assertTrue($applicability->evaluate($rules->firstWhere('rule_id', 'LJUP-SPC-D-TNB-002'), $profile));
        self::assertFalse($applicability->evaluate($rules->firstWhere('rule_id', 'LJUP-SPC-D-GAL-001'), $profile));
    }

    private function pilotCase(): array
    {
        config()->set('airs.rule_version', 'v2');
        $case = $this->airsCase(null, [
            'status' => ReviewCaseStatus::Completed,
            'total_original_bytes' => 1,
        ]);
        $version = ReviewVersion::create([
            'review_case_id' => $case->id,
            'check_number' => 1,
            'rule_pack' => 'ljup',
            'rule_version' => 'v2',
            'prompt_version' => 'v1',
            'report_version' => 'v1',
            'ai_provider' => 'fake',
        ]);
        $file = ReviewFile::create([
            'review_case_id' => $case->id,
            'original_name' => 'PELAN_LAMPU_JALAN_BillionSite.pdf',
            'safe_storage_path' => 'reviews/test/originals/plan.pdf',
            'mime_type' => 'application/pdf',
            'extension' => 'pdf',
            'size_bytes' => 1,
            'sha256' => '317e46553958b465d7596882b289c6c4b03c5ab8e2456588fe58d41f639cc1ea',
            'page_count' => 1,
            'processing_status' => 'READY',
        ]);
        $profile = ApplicationProfile::create([
            'review_version_id' => $version->id,
            'profile_json' => [
                'developer' => 'BILLION SITE SDN. BHD.',
                'lighting_system' => 'Tiang konkrit TNB',
            ],
            'confidence_summary' => [],
            'conflicts_json' => [],
        ]);

        return [$version, $profile, $file];
    }
}
