<?php

declare(strict_types=1);

namespace App\Airs\Console;

use App\Airs\Jobs\RunAirsCheck;
use App\Airs\Models\ReviewVersion;
use Illuminate\Console\Command;

/** Re-dispatches checks whose initial queue delivery was lost (still UPLOADED, no task started, older than 2 minutes). */
final class RecoverAirsChecks extends Command
{
    protected $signature = 'airs:recover-checks';

    protected $description = 'Re-queue AIRS checks that were accepted but never started';

    public function handle(): int
    {
        $versions = ReviewVersion::query()->where('status', 'UPLOADED')->where('created_at', '<', now()->subMinutes(2))
            ->whereDoesntHave('processingTasks')->orderBy('created_at')->limit(20)->get();
        foreach ($versions as $version) {
            RunAirsCheck::dispatch($version->id)->onQueue((string) config('airs.queue'));
        }
        $this->info("Re-queued {$versions->count()} AIRS check(s).");

        return self::SUCCESS;
    }
}
