<?php

declare(strict_types=1);

namespace App\Airs\Console;

use App\Airs\Enums\ReviewCaseStatus;
use App\Airs\Jobs\RunAirsCheck;
use App\Airs\Models\ReviewVersion;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

/**
 * Recovers AIRS checks: re-dispatches checks whose queue delivery was lost (UPLOADED, never started,
 * older than 2 minutes) and retries checks that failed because MATRIX was unavailable (up to 3 attempts).
 * The job claims only UPLOADED versions, so duplicate dispatches are harmless.
 */
final class RecoverAirsChecks extends Command
{
    public const MAX_ATTEMPTS = 3;

    protected $signature = 'airs:recover-checks';

    protected $description = 'Re-queue lost AIRS checks and retry checks that failed while MATRIX was unavailable';

    public function handle(): int
    {
        $lost = ReviewVersion::query()->where('status', 'UPLOADED')->where('created_at', '<', now()->subMinutes(2))
            ->whereDoesntHave('processingTasks')->orderBy('created_at')->limit(20)->pluck('id');

        $transient = ReviewVersion::query()->where('status', 'FAILED')->where('attempts', '<', self::MAX_ATTEMPTS)
            ->where('updated_at', '<', now()->subMinutes(5))
            ->whereHas('reviewCase', fn ($query) => $query->where('error_code', RunAirsCheck::TRANSIENT_FAILURE))
            ->orderBy('updated_at')->limit(20)->get();
        $retried = collect();
        foreach ($transient as $version) {
            $reset = DB::transaction(function () use ($version): bool {
                $updated = ReviewVersion::query()->whereKey($version->id)->where('status', 'FAILED')->update(['status' => 'UPLOADED', 'updated_at' => now()]);
                if ($updated === 1) {
                    $version->reviewCase->forceFill(['status' => ReviewCaseStatus::Uploaded, 'progress' => 0, 'error_code' => null,
                        'error_message' => null, 'current_step' => 'Retrying after MATRIX was unavailable'])->save();
                }

                return $updated === 1;
            });
            if ($reset) {
                $retried->push($version->id);
            }
        }

        foreach ($lost->merge($retried) as $id) {
            RunAirsCheck::dispatch($id);
        }
        $this->info("Re-queued {$lost->count()} lost and {$retried->count()} transient AIRS check(s).");

        return self::SUCCESS;
    }
}
