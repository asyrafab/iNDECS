<?php

declare(strict_types=1);

namespace Tests\Concurrency;

use App\Models\Application;
use App\Models\OfficerTask;
use App\Models\User;
use App\Services\Tasks\OfficerTaskService;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Symfony\Component\Process\Process;
use Tests\TestCase;

final class OfficerTaskConcurrencyTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();
        $this->artisan('migrate:fresh', ['--force' => true])->assertExitCode(0);
        $this->seed();
    }

    protected function tearDown(): void
    {
        $this->artisan('migrate:fresh', ['--force' => true]);
        parent::tearDown();
    }

    public function test_two_writers_contend_on_one_task_revision_without_duplicate_history(): void
    {
        $application = Application::query()->create(['application_type_id' => 'PKM', 'application_reference' => 'PKM/'.Str::ulid(),
            'title' => 'Ujian serentak', 'submission_date' => '2026-11-18', 'submission_year' => 2026,
            'responsible_department_id' => 'JPBD', 'current_stage' => 'registration', 'current_status' => 'submitted']);
        $officer = User::factory()->create(['department_id' => 'JPBD', 'role' => 'planning_officer']);
        $supervisor = User::factory()->create(['department_id' => 'JPBD', 'role' => 'senior_planning_officer']);
        $task = app(OfficerTaskService::class)->create($supervisor, $application->id, ['title' => 'Ujian kunci', 'assignee_id' => $officer->id]);
        $input = json_encode(['task' => $task->id, 'actor' => $officer->id, 'database' => DB::connection()->getConfig()], JSON_THROW_ON_ERROR);
        $processes = [];
        $waiting = 0;
        DB::beginTransaction();
        OfficerTask::query()->whereKey($task->id)->lockForUpdate()->firstOrFail();
        try {
            for ($i = 0; $i < 2; $i++) {
                $process = new Process([PHP_BINARY, base_path('tests/Support/start-officer-task.php')], base_path(), ['APP_ENV' => 'testing'], $input, 30);
                $process->start();
                $processes[] = $process;
            }
            for ($i = 0; $i < 80; $i++) {
                DB::select('select pg_stat_clear_snapshot()');
                $waiting = DB::table('pg_stat_activity')->where('application_name', 'task-race-'.$task->id)->where('wait_event_type', 'Lock')->count();
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
        $this->assertSame(2, $waiting);
        $this->assertSame(['conflict', 'saved'], $results);
        $this->assertSame(2, $task->fresh()->revision);
        $this->assertDatabaseCount('core.officer_task_actions', 2);
        $this->assertSame(1, DB::table('core.audit_logs')->where('event', 'officer_task_start')->count());
    }
}
