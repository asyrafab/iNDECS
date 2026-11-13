<?php

declare(strict_types=1);

namespace App\Jobs;

use App\Models\AirsReview;
use App\Services\Airs\AirsReviews;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Queue\Middleware\WithoutOverlapping;

final class SyncAirsReview implements ShouldQueue
{
    use Queueable;

    public int $tries = 3;

    public int $timeout = 100;

    public function __construct(public string $reviewId)
    {
        $this->onConnection('airs');
    }

    public function backoff(): array
    {
        return [30, 120, 300];
    }

    public function middleware(): array
    {
        return [(new WithoutOverlapping('airs:'.$this->reviewId))->shared()->releaseAfter(15)->expireAfter(150)];
    }

    public function handle(AirsReviews $reviews): void
    {
        $reviews->sync(AirsReview::findOrFail($this->reviewId));
    }
}
