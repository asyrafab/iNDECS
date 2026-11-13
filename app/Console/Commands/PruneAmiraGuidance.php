<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Models\AmiraGuidanceTurn;
use App\Models\User;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

final class PruneAmiraGuidance extends Command
{
    protected $signature = 'amira:prune-guidance';

    protected $description = 'Remove complete local-guidance threads after the configured inactivity period';

    public function handle(): int
    {
        $cutoff = now()->subMonths(max(1, (int) config('amira.retention_months', 12)));
        $threads = AmiraGuidanceTurn::query()->select(['user_id', 'context'])->groupBy('user_id', 'context')
            ->havingRaw('MAX(created_at) < ?', [$cutoff])->get();
        foreach ($threads as $thread) {
            DB::transaction(function () use ($thread, $cutoff): void {
                User::query()->whereKey($thread->user_id)->lockForUpdate()->first();
                $query = AmiraGuidanceTurn::query()->where('user_id', $thread->user_id)->where('context', $thread->context);
                if ($query->clone()->where('created_at', '>=', $cutoff)->doesntExist()) {
                    $query->delete();
                }
            });
        }

        return self::SUCCESS;
    }
}
