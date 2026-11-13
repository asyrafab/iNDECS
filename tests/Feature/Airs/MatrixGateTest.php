<?php

declare(strict_types=1);

namespace Tests\Feature\Airs;

use App\Airs\Ai\MatrixReviewGate;
use App\Airs\Exceptions\MatrixTaskException;
use App\Airs\Models\AiRun;
use App\Airs\Models\ReviewCase;
use App\Airs\Models\ReviewVersion;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Storage;
use Tests\Support\AirsFixtures;
use Tests\TestCase;

final class MatrixGateTest extends TestCase
{
    use AirsFixtures, RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed();
        Storage::fake('local');
        config(['airs.matrix.enabled' => true]);
    }

    private function matrixCheck(array $caseAttributes = [], int $checkNumber = 1): array
    {
        $case = $this->airsCase(null, $caseAttributes);
        $version = $this->airsVersion($case, $checkNumber, ['ai_provider' => 'matrix']);

        return [$case, $version];
    }

    private function assertDenied(string $code, ReviewVersion $version): void
    {
        try {
            app(MatrixReviewGate::class)->context($version, 'synthetic-request');
            $this->fail("Expected $code");
        } catch (MatrixTaskException $exception) {
            $this->assertSame($code, $exception->getMessage());
        }
    }

    public function test_matrix_refused_without_payment_clearance(): void
    {
        [, $version] = $this->matrixCheck(['clearance_kind' => null, 'payment_clearance_reference' => null, 'payment_cleared_at' => null]);
        $this->assertDenied('MATRIX_CLEARANCE_REQUIRED', $version);
    }

    public function test_matrix_refused_for_non_street_lighting_module(): void
    {
        [, $version] = $this->matrixCheck(['review_module_code' => 'BUILDING_PLAN']);
        $this->assertDenied('MATRIX_SCOPE_DENIED', $version);
    }

    public function test_matrix_refused_when_disabled(): void
    {
        config(['airs.matrix.enabled' => false]);
        [, $version] = $this->matrixCheck();
        $this->assertDenied('MATRIX_SCOPE_DENIED', $version);
    }

    public function test_matrix_refused_for_superseded_check(): void
    {
        [$case, $one] = $this->matrixCheck();
        $this->airsVersion($case, 2, ['ai_provider' => 'matrix']);
        $this->assertDenied('MATRIX_STALE_REVIEW', $one);
    }

    public function test_matrix_budget_exceeded_is_refused(): void
    {
        [, $version] = $this->matrixCheck();
        $this->aiRun($version, ['status' => 'SUCCEEDED', 'estimated_cost' => 4.90]);
        $this->assertDenied('MATRIX_REVIEW_BUDGET', $version);
    }

    public function test_unknown_failed_attempt_cost_requires_reconciliation(): void
    {
        [, $version] = $this->matrixCheck();
        $this->aiRun($version, ['status' => 'FAILED', 'estimated_cost' => null]);
        $this->assertDenied('MATRIX_USAGE_RECONCILIATION_REQUIRED', $version);
    }

    public function test_context_reports_paid_formal_review_and_check_round(): void
    {
        [$case, $one] = $this->matrixCheck();
        $first = app(MatrixReviewGate::class)->context($one, 'r1');
        $this->assertSame('FIRST', $first['submission_round']);
        $this->assertTrue($first['payment_cleared']);
        $this->assertSame('FORMAL_REVIEW', $first['phase']);
        $this->assertSame('PRE_SUBMISSION_PAID_CHECK', $first['stage']);
        $this->assertSame($case->application_id, $first['application_id']);
        $this->assertSame(1, $first['check_number']);

        $two = $this->airsVersion($case, 2, ['ai_provider' => 'matrix']);
        $this->assertSame('SECOND_OR_LATER', app(MatrixReviewGate::class)->context($two, 'r2')['submission_round']);
    }

    private function aiRun(ReviewVersion $version, array $attributes): AiRun
    {
        return AiRun::create($attributes + ['review_version_id' => $version->id, 'task_type' => 'classify_document', 'provider' => 'matrix',
            'model' => 'claude-sonnet-5-5', 'prompt_id' => 'synthetic', 'prompt_version' => 'v1', 'schema_id' => 'v1',
            'request_hash' => str_repeat('0', 64), 'input_manifest' => [], 'attempt' => 1, 'started_at' => now(), 'completed_at' => now()]);
    }
}
