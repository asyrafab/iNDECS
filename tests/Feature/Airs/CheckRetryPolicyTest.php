<?php

declare(strict_types=1);

namespace Tests\Feature\Airs;

use App\Airs\Ai\MatrixReviewGate;
use App\Airs\Contracts\AiProvider;
use App\Airs\Enums\EvaluationMethod;
use App\Airs\Enums\ReviewCaseStatus;
use App\Airs\Exceptions\MatrixTaskException;
use App\Airs\Jobs\RunAirsCheck;
use App\Airs\Models\AiRun;
use App\Airs\Models\ApplicationProfile;
use App\Airs\Models\ProcessingTask;
use App\Airs\Models\ReviewVersion;
use App\Airs\Review\RuleApplicabilityService;
use App\Airs\Review\RuleEvaluationService;
use App\Airs\Review\RuleRepository;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Queue;
use Illuminate\Support\Facades\Storage;
use RuntimeException;
use Tests\Support\AirsFixtures;
use Tests\TestCase;

/** Final-review fixes: MATRIX cost accounting, job claiming, failure hook, retry policy. */
final class CheckRetryPolicyTest extends TestCase
{
    use AirsFixtures, RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed();
        Storage::fake('local');
        config(['airs.matrix.enabled' => true]);
    }

    private function aiRun(ReviewVersion $version, array $attributes): AiRun
    {
        return AiRun::create($attributes + ['review_version_id' => $version->id, 'task_type' => 'classify_document', 'provider' => 'matrix',
            'model' => 'claude-sonnet-5-5', 'prompt_id' => 'synthetic', 'prompt_version' => 'v1', 'schema_id' => 'v1',
            'request_hash' => str_repeat('0', 64), 'input_manifest' => [], 'attempt' => 1, 'started_at' => now(), 'completed_at' => now()]);
    }

    public function test_failed_run_with_unknown_cost_is_charged_at_request_budget_instead_of_blocking(): void
    {
        $version = $this->airsVersion($this->airsCase(), 1, ['ai_provider' => 'matrix', 'started_at' => now()->subMinute()]);
        $this->aiRun($version, ['status' => 'FAILED', 'estimated_cost' => null]);

        $context = app(MatrixReviewGate::class)->context($version, 'next-request');

        $this->assertSame('FIRST', $context['submission_round']);
    }

    public function test_unknown_cost_failures_still_count_toward_the_budget(): void
    {
        config(['airs.matrix.request_budget_usd' => 1.0, 'airs.matrix.review_budget_usd' => 2.0]);
        $version = $this->airsVersion($this->airsCase(), 1, ['ai_provider' => 'matrix', 'started_at' => now()->subMinute()]);
        $this->aiRun($version, ['status' => 'FAILED', 'estimated_cost' => null]);
        $this->aiRun($version, ['status' => 'FAILED', 'estimated_cost' => null]);

        $this->expectExceptionMessage('MATRIX_REVIEW_BUDGET');
        app(MatrixReviewGate::class)->context($version, 'next-request');
    }

    public function test_budget_counts_only_the_current_attempt(): void
    {
        $version = $this->airsVersion($this->airsCase(), 1, ['ai_provider' => 'matrix', 'started_at' => now()]);
        $old = $this->aiRun($version, ['status' => 'SUCCEEDED', 'estimated_cost' => 4.90]);
        $old->forceFill(['created_at' => now()->subHour()])->save();

        $this->assertSame(1, app(MatrixReviewGate::class)->context($version, 'next-request')['check_number']);
    }

    public function test_in_flight_run_still_requires_reconciliation(): void
    {
        $version = $this->airsVersion($this->airsCase(), 1, ['ai_provider' => 'matrix', 'started_at' => now()->subMinute()]);
        $this->aiRun($version, ['status' => 'STARTED', 'estimated_cost' => null, 'completed_at' => null]);

        $this->expectExceptionMessage('MATRIX_USAGE_RECONCILIATION_REQUIRED');
        app(MatrixReviewGate::class)->context($version, 'next-request');
    }

    public function test_worker_failure_fails_the_check_instead_of_downgrading_the_rule(): void
    {
        $case = $this->airsCase();
        $version = $this->airsVersion($case);
        $profile = ApplicationProfile::create(['review_version_id' => $version->id, 'conflicts_json' => [],
            'profile_json' => ['selected_lighting_types' => ['BESI'], 'lighting_system' => 'Tiang besi galvani', 'lighting_type_conflict' => false]]);
        $rules = app(RuleRepository::class)->syncPack('ljup', 'v2');
        $aiRule = $rules->first(fn ($rule) => in_array($rule->evaluation_method, [EvaluationMethod::AiText, EvaluationMethod::AiVision], true)
            && app(RuleApplicabilityService::class)->evaluate($rule, $profile->profile_json) === true);
        $fake = app(AiProvider::class);
        $this->app->instance(AiProvider::class, new class($fake) implements AiProvider
        {
            public function __construct(private AiProvider $inner) {}

            public function classifyDocument(array $input): array { return $this->inner->classifyDocument($input); }

            public function extractApplicationProfile(array $input): array { return $this->inner->extractApplicationProfile($input); }

            public function evaluateRule(array $input): array { throw new MatrixTaskException('MATRIX_INVALID_WORKER_RESPONSE'); }

            public function generateReport(array $input): array { return $this->inner->generateReport($input); }
        });

        $this->expectExceptionMessage('MATRIX_INVALID_WORKER_RESPONSE');
        app(RuleEvaluationService::class)->evaluate($version, $profile, collect([$aiRule]));
    }

    public function test_job_only_runs_a_version_waiting_in_uploaded(): void
    {
        $case = $this->airsCase();
        $failed = $this->airsVersion($case, 1, ['status' => 'FAILED']);

        app()->call([new RunAirsCheck($failed->id), 'handle']);

        $this->assertSame('FAILED', $failed->fresh()->status);
        $this->assertSame(0, ProcessingTask::query()->where('review_version_id', $failed->id)->count());

        $failed->forceFill(['status' => 'PROCESSING'])->save();
        app()->call([new RunAirsCheck($failed->id), 'handle']);
        $this->assertSame(0, ProcessingTask::query()->where('review_version_id', $failed->id)->count());
    }

    public function test_failed_hook_releases_a_stuck_check(): void
    {
        $case = $this->airsCase(null, ['status' => 'CLASSIFYING']);
        $version = $this->airsVersion($case, 1, ['status' => 'PROCESSING']);

        (new RunAirsCheck($version->id))->failed(new RuntimeException('worker killed'));

        $this->assertSame('FAILED', $version->fresh()->status);
        $this->assertSame(ReviewCaseStatus::Failed, $case->fresh()->status);
    }

    public function test_job_is_not_auto_retried_and_uses_the_airs_connection(): void
    {
        Queue::fake();
        $job = new RunAirsCheck('01JTESTVERSION0000000000000');
        $this->assertSame(1, $job->tries);
        $this->assertGreaterThan(900, (int) config('queue.connections.airs.retry_after'));

        $version = $this->airsVersion($this->airsCase());
        app(\App\Airs\CheckService::class)->rerun(tap($version)->forceFill(['status' => 'FAILED'])->save() ? $version->fresh() : $version);
        Queue::assertPushed(RunAirsCheck::class, fn ($pushed) => $pushed->connection === 'airs' && $pushed->queue === 'airs');
    }

    public function test_recovery_retries_transient_failures_up_to_three_attempts(): void
    {
        Queue::fake();
        $transient = $this->airsVersion($this->airsCase(null, ['error_code' => 'MATRIX_UNAVAILABLE']), 1, ['status' => 'FAILED', 'attempts' => 1]);
        $transient->forceFill(['updated_at' => now()->subMinutes(6)])->save();
        $exhausted = $this->airsVersion($this->airsCase(null, ['error_code' => 'MATRIX_UNAVAILABLE']), 1, ['status' => 'FAILED', 'attempts' => 3]);
        $exhausted->forceFill(['updated_at' => now()->subMinutes(6)])->save();
        $permanent = $this->airsVersion($this->airsCase(null, ['error_code' => 'NO_RELEVANT_DOCUMENTS']), 1, ['status' => 'FAILED', 'attempts' => 1]);
        $permanent->forceFill(['updated_at' => now()->subMinutes(6)])->save();

        $this->artisan('airs:recover-checks')->assertSuccessful();

        Queue::assertPushed(RunAirsCheck::class, 1);
        Queue::assertPushed(RunAirsCheck::class, fn ($job) => $job->reviewVersionId === $transient->id);
        $this->assertSame('UPLOADED', $transient->fresh()->status);
        $this->assertSame('FAILED', $exhausted->fresh()->status);
    }
}
