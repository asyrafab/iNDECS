<?php

declare(strict_types=1);

namespace Tests\Concurrency;

use App\Airs\Models\ReviewVersion;
use App\Models\ApplicationRecommendation;
use App\Services\ApplicationRecommendationService;
use App\Services\Kejuruteraan\LjupRecommendationSource;
use Illuminate\Support\Facades\DB;
use Symfony\Component\Process\Process;
use Tests\Feature\Kejuruteraan\BuildsHandedOverLjup;
use Tests\TestCase;

final class RecommendationConcurrencyTest extends TestCase
{
    use BuildsHandedOverLjup;

    protected function setUp(): void
    {
        parent::setUp();
        // TestCase verifies both configuration and actual indecs_test before any rebuild.
        $this->artisan('migrate:fresh', ['--force' => true])->assertExitCode(0);
        $this->setUpLjupOfficerWorld();
    }

    protected function tearDown(): void
    {
        $this->artisan('migrate:fresh', ['--force' => true]);
        parent::tearDown();
    }

    public function test_two_independent_editors_cannot_save_the_same_revision(): void
    {
        [$application] = $this->paidLjup();
        $actor = $this->engineer();
        $context = app(ApplicationRecommendationService::class)->context($actor, $application->id, app(LjupRecommendationSource::class));
        $input = json_encode(['application' => $application->id, 'actor' => $actor->id, 'database' => DB::connection()->getConfig(),
            'data' => ['expected_revision' => 0, 'source_hash' => $context['source_hash'], 'outcome' => 'amend',
                'reasons' => 'Cadangan pindaan pegawai.', 'evidence_references' => 'Pelan halaman 1',
                'missing_document_explanation' => 'Dapatkan dokumen tambahan.', 'recipient' => 'Perunding ujian', 'subject' => 'Draf dalaman']], JSON_THROW_ON_ERROR);
        $processes = [];
        DB::beginTransaction();
        ReviewVersion::query()->whereKey($context['snapshot']['review_version_id'])->lockForUpdate()->firstOrFail();
        try {
            for ($i = 0; $i < 2; $i++) {
                $process = new Process([PHP_BINARY, base_path('tests/Support/record-recommendation.php')], base_path(), ['APP_ENV' => 'testing'], $input, 30);
                $process->start();
                $processes[] = $process;
            }
            $waiting = 0;
            for ($i = 0; $i < 80; $i++) {
                DB::select('select pg_stat_clear_snapshot()');
                $waiting = DB::table('pg_stat_activity')->where('application_name', 'recommendation-race-'.$application->id)->where('wait_event_type', 'Lock')->count();
                if ($waiting === 2) {
                    break;
                }
                usleep(100000);
            }
        } finally {
            DB::commit();
        }
        $results = [];
        foreach ($processes as $process) {
            $process->wait();
            $this->assertTrue($process->isSuccessful(), $process->getErrorOutput().$process->getOutput());
            $results[] = trim($process->getOutput());
        }
        sort($results);
        $this->assertSame(2, $waiting, 'Both editors must contend on the locked review version.');
        $this->assertSame(['conflict', 'saved'], $results);
        $this->assertSame(1, ApplicationRecommendation::query()->sole()->revision);
        $this->assertDatabaseCount('core.application_letter_drafts', 1);
        $this->assertSame(1, DB::table('audit_logs')->where('event', 'application_recommendation_drafted')->count());
    }
}
