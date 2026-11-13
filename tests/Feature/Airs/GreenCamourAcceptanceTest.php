<?php

declare(strict_types=1);

namespace Tests\Feature\Airs;

use App\Airs\CheckClearance;
use App\Airs\CheckService;
use App\Airs\Models\ReviewFinding;
use Carbon\CarbonImmutable;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Storage;
use Tests\Support\AirsFixtures;
use Tests\TestCase;

/**
 * First acceptance case (AGENTS.md): the real 12-PDF Green Camour LJUP package.
 * The package is not in git; set PLAN_REVIEW_GREEN_CAMOUR_PATH to its folder of PDFs or to the ZIP.
 * Needs AIRS_PYTHON_BINARY pointing at a Python with python_worker/requirements.txt installed.
 */
final class GreenCamourAcceptanceTest extends TestCase
{
    use AirsFixtures, RefreshDatabase;

    public function test_green_camour_pilot_package(): void
    {
        $fixture = (string) env('PLAN_REVIEW_GREEN_CAMOUR_PATH', '');
        if ($fixture === '' || ! file_exists($fixture)) {
            self::markTestSkipped('Set PLAN_REVIEW_GREEN_CAMOUR_PATH to run the real 12-PDF pilot acceptance test.');
        }

        $this->seed();
        Storage::fake('local');
        config()->set('airs.provider', 'fake');
        $this->app->forgetInstance(\App\Airs\Contracts\AiProvider::class);

        $application = $this->ljupApplication();
        $paths = is_dir($fixture) ? glob(rtrim($fixture, '/\\').'/*.pdf') : [$fixture];
        sort($paths);
        foreach ($paths as $path) {
            $zip = str_ends_with(strtolower($path), '.zip');
            $this->ljupDocument($application, (string) file_get_contents($path), basename($path), $zip ? 'application/zip' : 'application/pdf');
        }

        // QUEUE_CONNECTION=sync: the check runs inside start().
        $version = app(CheckService::class)->start($application, new CheckClearance('paid', 'TEST-GREEN-CAMOUR', CarbonImmutable::now()->subMinute()),
            ['BESI'], $application->currentDocuments()->get())->fresh(['classifications', 'profile', 'reviewCase']);
        $case = $version->reviewCase;

        self::assertSame('COMPLETED', $version->status);
        self::assertSame(12, (int) $case->file_count);
        self::assertSame(79, (int) $case->page_count);
        $types = $version->classifications->pluck('primary_type')->all();
        self::assertContains('LJUP_PLAN', $types);
        self::assertContains('PHOTOMETRIC_REPORT', $types);
        self::assertContains('PAYMENT_REGISTRATION_DEMAND', $types);
        self::assertFalse($version->classifications->contains(fn ($classification): bool => $classification->primary_type === 'UNKNOWN' && (float) $classification->confidence === 0.35));

        $schedules = collect($version->profile->profile_json['lamp_schedules'] ?? []);
        self::assertSame(['150' => 23, '180' => 7], $schedules->firstWhere('source_type', 'LJUP_PLAN')['quantities'] ?? null);
        self::assertSame(['150' => 23, '180' => 7], $schedules->firstWhere('source_type', 'PHOTOMETRIC_REPORT')['quantities'] ?? null);
        self::assertSame('UNVERIFIED', data_get($version->profile->profile_json, 'payments.0.paid_status'));

        $photoFinding = ReviewFinding::query()->where('review_version_id', $version->id)->where('rule_id', 'LJUP-SUB-007')->where('is_superseded', false)->firstOrFail();
        if (! $version->classifications->contains('primary_type', 'SITE_PHOTOGRAPHS')) {
            self::assertNotSame('DITUNAIKAN', $photoFinding->status->value);
        }
    }
}
