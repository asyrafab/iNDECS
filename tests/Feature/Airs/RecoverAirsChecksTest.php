<?php

declare(strict_types=1);

namespace Tests\Feature\Airs;

use App\Airs\Jobs\RunAirsCheck;
use App\Airs\Models\ProcessingTask;
use Illuminate\Console\Scheduling\Schedule;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Queue;
use Illuminate\Support\Facades\Storage;
use Tests\Support\AirsFixtures;
use Tests\TestCase;

final class RecoverAirsChecksTest extends TestCase
{
    use AirsFixtures, RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed();
        Storage::fake('local');
        Queue::fake();
    }

    public function test_only_stale_unstarted_checks_are_redispatched(): void
    {
        $stale = $this->airsVersion($this->airsCase(), 1, ['status' => 'UPLOADED']);
        $stale->forceFill(['created_at' => now()->subMinutes(3)])->save();
        $fresh = $this->airsVersion($this->airsCase(), 1, ['status' => 'UPLOADED']);
        $started = $this->airsVersion($this->airsCase(), 1, ['status' => 'UPLOADED']);
        $started->forceFill(['created_at' => now()->subMinutes(3)])->save();
        ProcessingTask::create(['review_version_id' => $started->id, 'task_name' => 'INVENTORY_DOCUMENTS', 'status' => 'STARTED', 'started_at' => now()]);

        $this->artisan('airs:recover-checks')->assertSuccessful();

        Queue::assertPushedOn('airs', RunAirsCheck::class, fn ($job) => $job->reviewVersionId === $stale->id);
        Queue::assertPushed(RunAirsCheck::class, 1);
        $this->assertNotSame($fresh->id, $stale->id);
    }

    public function test_recovery_is_scheduled_every_minute(): void
    {
        $event = collect(app(Schedule::class)->events())->first(fn ($event) => str_contains((string) $event->command, 'airs:recover-checks'));
        $this->assertNotNull($event);
        $this->assertSame('* * * * *', $event->expression);
    }
}
