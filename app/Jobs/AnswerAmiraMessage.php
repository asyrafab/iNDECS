<?php

declare(strict_types=1);

namespace App\Jobs;

use App\Models\AmiraConversation;
use App\Services\Amira\AmiraClient;
use App\Services\Amira\ContextBuilder;
use App\Services\Amira\FallbackGuidance;
use App\Services\Amira\ProposalSanitiser;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Queue\Middleware\WithoutOverlapping;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use RuntimeException;
use Throwable;

/**
 * Answers the PSP's latest question through the AMIRA backend (ADR-369). Any failure becomes scripted fallback
 * guidance, so a question is never left unanswered. Message bodies are never logged.
 */
final class AnswerAmiraMessage implements ShouldQueue
{
    use Queueable;

    public int $tries = 1;

    public int $timeout = 180;

    public function __construct(public readonly string $conversationId)
    {
        $this->onConnection('amira');
        $this->onQueue('amira');
    }

    public function middleware(): array
    {
        return [(new WithoutOverlapping($this->conversationId))->releaseAfter(5)->expireAfter($this->timeout + 30)];
    }

    public function handle(AmiraClient $client, ContextBuilder $context, FallbackGuidance $fallback): void
    {
        $conversation = AmiraConversation::query()->find($this->conversationId);
        $question = $conversation?->awaiting();
        if ($question === null) {
            return;
        }
        // A question that waited past the stale limit (no worker for a while) is not answered late by the model.
        if (! AmiraConversation::isFresh($question)) {
            $this->store($conversation, ['body' => $fallback->for($conversation), 'fallback' => true]);

            return;
        }
        $started = hrtime(true);
        try {
            $built = $context->for($conversation);
            $reply = $client->ask($built['request']);
        } catch (RuntimeException $exception) {
            Log::warning('AMIRA answer fell back to scripted guidance.', ['conversation_id' => $conversation->id, 'error' => $exception->getMessage()]);
            $this->store($conversation, ['body' => $fallback->for($conversation), 'fallback' => true]);

            return;
        }
        $offered = collect($built['sources'])->keyBy('id');
        $sources = collect($reply->sourceIds)->unique()->filter(fn (string $id) => $offered->has($id))->values()
            ->map(fn (string $id) => ['library_document_id' => $offered[$id]['library_document_id'], 'version' => $offered[$id]['version'], 'title' => $offered[$id]['title']])
            ->all();
        $this->store($conversation, [
            'body' => $reply->reply, 'sources' => $sources === [] ? null : $sources,
            'proposal' => ProposalSanitiser::clean($reply->proposal, $built['options']),
            'mentions_outcome' => $reply->mentionsOutcome, 'model' => $reply->model,
            'latency_ms' => (int) ((hrtime(true) - $started) / 1_000_000),
        ]);
    }

    /** A crashed or timed-out answer still gets a reply, once. */
    public function failed(?Throwable $exception = null): void
    {
        $conversation = AmiraConversation::query()->find($this->conversationId);
        if ($conversation?->awaiting() !== null) {
            $this->store($conversation, ['body' => app(FallbackGuidance::class)->for($conversation), 'fallback' => true]);
        }
    }

    /** One reply per question: under a row lock, write only if the question is still unanswered. */
    private function store(AmiraConversation $conversation, array $attributes): void
    {
        DB::transaction(function () use ($conversation, $attributes): void {
            AmiraConversation::query()->whereKey($conversation->id)->lockForUpdate()->first();
            if ($conversation->awaiting() === null) {
                return;
            }
            $conversation->messages()->create(['role' => 'amira'] + $attributes);
            $conversation->forceFill(['last_message_at' => now()])->save();
        });
    }
}
