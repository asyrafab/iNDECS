<?php

declare(strict_types=1);

namespace Tests\Feature\Airs;

use App\Airs\Contracts\AiProvider;
use App\Airs\Enums\EvaluationMethod;
use App\Airs\Exceptions\MatrixTaskException;
use App\Airs\Models\ApplicationProfile;
use App\Airs\Models\ReviewFinding;
use App\Airs\Review\ReadinessScorer;
use App\Airs\Review\ReportGenerator;
use App\Airs\Review\RuleApplicabilityService;
use App\Airs\Review\RuleEvaluationService;
use App\Airs\Review\RuleRepository;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Storage;
use Tests\Support\AirsFixtures;
use Tests\TestCase;

final class ScoringTest extends TestCase
{
    use AirsFixtures, RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed();
        Storage::fake('local');
    }

    private function profileFor($version): ApplicationProfile
    {
        return ApplicationProfile::create(['review_version_id' => $version->id, 'conflicts_json' => [],
            'profile_json' => ['selected_lighting_types' => ['BESI'], 'lighting_system' => 'Tiang besi galvani', 'lighting_type_conflict' => false]]);
    }

    public function test_shadow_outcome_follows_configured_threshold(): void
    {
        config(['airs.score_threshold' => 50]);
        $this->assertSame('BELOW_THRESHOLD', ReadinessScorer::shadowOutcome(49));
        $this->assertSame('MEETS_THRESHOLD', ReadinessScorer::shadowOutcome(50));
    }

    public function test_shadow_outcome_is_recorded_but_never_changes_application(): void
    {
        config(['airs.score_threshold' => 101]);
        $case = $this->airsCase();
        $version = $this->airsVersion($case);
        $application = $case->application;
        [$stage, $status] = [$application->current_stage, $application->current_status];
        $rules = app(RuleRepository::class)->syncPack('ljup', 'v2');
        app(RuleEvaluationService::class)->evaluate($version, $this->profileFor($version), $rules);

        app(ReportGenerator::class)->generate($case->fresh(), $version->fresh());

        $version->refresh();
        $this->assertSame('BELOW_THRESHOLD', $version->shadow_outcome);
        $this->assertNotNull($version->score);
        $this->assertSame($stage, $application->fresh()->current_stage);
        $this->assertSame($status, $application->fresh()->current_status);
    }

    public function test_budget_exhausted_rule_becomes_manual_review(): void
    {
        $case = $this->airsCase();
        $version = $this->airsVersion($case);
        $profile = $this->profileFor($version);
        $rules = app(RuleRepository::class)->syncPack('ljup', 'v2');
        $aiRule = $rules->first(fn ($rule) => in_array($rule->evaluation_method, [EvaluationMethod::AiText, EvaluationMethod::AiVision], true)
            && app(RuleApplicabilityService::class)->evaluate($rule, $profile->profile_json) === true);
        $this->assertNotNull($aiRule, 'Expected an applicable AI rule in the LJUP v2 pack.');
        $fake = app(AiProvider::class);
        $this->app->instance(AiProvider::class, new class($fake) implements AiProvider
        {
            public function __construct(private AiProvider $inner) {}

            public function classifyDocument(array $input): array { return $this->inner->classifyDocument($input); }

            public function extractApplicationProfile(array $input): array { return $this->inner->extractApplicationProfile($input); }

            public function evaluateRule(array $input): array { throw new MatrixTaskException('MATRIX_REVIEW_BUDGET'); }

            public function generateReport(array $input): array { return $this->inner->generateReport($input); }
        });

        app(RuleEvaluationService::class)->evaluate($version, $profile, collect([$aiRule]));

        $finding = ReviewFinding::query()->where('review_version_id', $version->id)->where('rule_id', $aiRule->rule_id)->sole();
        $this->assertSame('PERLU_SEMAKAN_MANUAL', $finding->status->value);
        $this->assertStringContainsString('had kos/masa MATRIX', (string) $finding->manual_review_reason);
    }

    public function test_ditunaikan_without_evidence_is_never_stored(): void
    {
        $case = $this->airsCase();
        $version = $this->airsVersion($case);
        $rules = app(RuleRepository::class)->syncPack('ljup', 'v2');
        app(RuleEvaluationService::class)->evaluate($version, $this->profileFor($version), $rules);

        $complied = ReviewFinding::query()->where('review_version_id', $version->id)->where('status', 'DITUNAIKAN')->withCount('evidence')->get();
        $this->assertTrue($complied->every(fn ($finding) => $finding->evidence_count > 0));
    }
}
