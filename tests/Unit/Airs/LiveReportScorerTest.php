<?php

declare(strict_types=1);

namespace Tests\Unit\Airs;

use App\Airs\Enums\FindingStatus;
use App\Airs\Review\LiveReportScorer;
use Illuminate\Support\Collection;
use Tests\TestCase;

final class LiveReportScorerTest extends TestCase
{
    private function report(): array
    {
        return ['sections' => [['title' => 'LJUP Umum', 'findings' => [
            ['id' => 'f1', 'status' => 'PERLU_SEMAKAN_MANUAL'],
            ['id' => 'f2', 'status' => 'DITUNAIKAN'],
        ]]], 'application_score' => ['score' => 83]];
    }

    private function entry(string $status): object
    {
        return (object) ['status' => FindingStatus::from($status), 'created_at' => now()];
    }

    public function test_latest_officer_status_replaces_status_and_keeps_ai_status(): void
    {
        $out = app(LiveReportScorer::class)->apply($this->report(), new Collection([
            'f1' => collect([$this->entry('BELUM_DITUNAIKAN'), $this->entry('DITUNAIKAN')]),
        ]));
        $f1 = $out['sections'][0]['findings'][0];
        $this->assertSame('PERLU_SEMAKAN_MANUAL', $f1['ai_status']);
        $this->assertSame('DITUNAIKAN', $f1['status']);
        $this->assertTrue($f1['officer_confirmed']);
        $this->assertFalse($out['sections'][0]['findings'][1]['officer_confirmed']);
        $this->assertSame(100, $out['application_score']['score']);
    }

    public function test_never_emits_rejection(): void
    {
        $out = app(LiveReportScorer::class)->apply($this->report(), new Collection(['f1' => collect([$this->entry('BELUM_DITUNAIKAN')]), 'f2' => collect([$this->entry('BELUM_DITUNAIKAN')])]));
        $this->assertSame(0, $out['application_score']['score']);
        $this->assertArrayNotHasKey('rejected', $out);
        $this->assertArrayNotHasKey('rejected', $out['application_score']);
        $this->assertArrayNotHasKey('rejected', $out['section_checklist'][0]);
    }
}
