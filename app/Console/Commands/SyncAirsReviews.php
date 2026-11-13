<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Jobs\SyncAirsReview;
use App\Models\AirsFeedback;
use App\Models\AirsReview;
use Illuminate\Console\Command;

final class SyncAirsReviews extends Command
{
    protected $signature = 'airs:sync';

    protected $description = 'Retry queued AIRS requests and refresh active review results';

    public function handle(): int
    {
        if (! config('airs.enabled')) {
            $this->info('AIRS belum diaktifkan.');

            return self::SUCCESS;
        }
        AirsReview::query()->whereIn('state', ['queued', 'processing'])->orderBy('updated_at')->limit(20)->get()
            ->each(fn ($review) => SyncAirsReview::dispatch($review->id)->onQueue(config('airs.queue')));
        // Feedback delivery remains retriable after a completed review.
        AirsReview::query()->whereIn('id', AirsFeedback::query()->select('review_id')->whereNull('sent_at'))
            ->limit(20)->get()->each(fn ($review) => SyncAirsReview::dispatch($review->id)->onQueue(config('airs.queue')));
        $this->info('Penyegerakan AIRS dijadualkan.');

        return self::SUCCESS;
    }
}
