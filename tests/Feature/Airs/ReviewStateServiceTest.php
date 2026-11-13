<?php

declare(strict_types=1);

namespace Tests\Feature\Airs;

use App\Airs\Enums\ReviewCaseStatus;
use App\Airs\Models\ReviewCase;
use App\Airs\Review\ReviewStateService;
use DomainException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Support\AirsFixtures;
use Tests\TestCase;

final class ReviewStateServiceTest extends TestCase
{
    use AirsFixtures, RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed();
        \Illuminate\Support\Facades\Storage::fake('local');
    }

    public function test_valid_state_progress_never_moves_backwards(): void
    {
        $case = $this->reviewCase();
        $states = app(ReviewStateService::class);
        $progress = [];

        foreach ([
            ReviewCaseStatus::Inventory,
            ReviewCaseStatus::ExtractingText,
            ReviewCaseStatus::Rendering,
            ReviewCaseStatus::Classifying,
            ReviewCaseStatus::ExtractingProfile,
            ReviewCaseStatus::EvaluatingRules,
            ReviewCaseStatus::Consolidating,
            ReviewCaseStatus::GeneratingReport,
            ReviewCaseStatus::Completed,
        ] as $status) {
            $states->transition($case, $status);
            $case->refresh();
            $progress[] = $case->progress;
        }

        self::assertSame($progress, collect($progress)->sort()->values()->all());
        self::assertSame(100, $case->progress);
    }

    public function test_invalid_state_transition_is_rejected(): void
    {
        $this->expectException(DomainException::class);
        app(ReviewStateService::class)->transition($this->reviewCase(), ReviewCaseStatus::Completed);
    }

    private function reviewCase(): ReviewCase
    {
        return $this->airsCase(null, [
            'status' => ReviewCaseStatus::Uploaded,
            'total_original_bytes' => 1,
        ]);
    }
}
