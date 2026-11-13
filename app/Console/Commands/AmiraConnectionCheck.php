<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Services\Amira\AmiraClient;
use App\Services\Amira\ContextBuilder;
use Illuminate\Console\Command;
use Throwable;

/** ADR-369: check iNDECS → AMIRA → model end to end with one canned question. Never prints the token. */
final class AmiraConnectionCheck extends Command
{
    protected $signature = 'amira:check';

    protected $description = 'Ask AMIRA one canned question through the configured service and model';

    public function handle(AmiraClient $client, ContextBuilder $context): int
    {
        $started = hrtime(true);
        try {
            $reply = $client->ask([
                'subject' => hash('sha256', 'amira:check'), 'locale' => 'ms-MY',
                'question' => 'Apakah dokumen wajib untuk permohonan ini?', 'history' => [],
                'context' => ['page' => 'home', 'application_type' => null, 'particulars' => null, 'checklist' => [],
                    'options' => $context->options(), 'sources' => [], 'files' => ['total' => 0, 'drafts' => []]],
            ]);
        } catch (Throwable $exception) {
            $this->error('AMIRA tidak dapat dihubungi: '.$exception->getMessage());

            return self::FAILURE;
        }
        $this->info(sprintf('AMIRA menjawab dalam %.1f s menggunakan model %s.', (hrtime(true) - $started) / 1e9, $reply->model));
        $this->line($reply->reply);

        return self::SUCCESS;
    }
}
