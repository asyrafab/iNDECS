<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Models\AmiraConversation;
use Illuminate\Console\Command;

/** ADR-369: delete AMIRA conversations (and their messages) idle longer than the retention period. */
final class PruneAmiraConversations extends Command
{
    protected $signature = 'amira:prune';

    protected $description = 'Delete AMIRA AI conversations idle longer than the retention period';

    public function handle(): int
    {
        $cutoff = now()->subMonths(max(1, (int) config('amira.retention_months', 12)));
        $deleted = AmiraConversation::query()->where('last_message_at', '<', $cutoff)->delete();
        $this->info("Perbualan AMIRA dipadam: {$deleted}.");

        return self::SUCCESS;
    }
}
