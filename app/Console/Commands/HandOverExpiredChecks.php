<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Services\PaidCheck\PaidCheckJourneyService;
use Illuminate\Console\Command;

/** Hands over applications whose amendment window ended without an amendment (check 1 becomes final). */
final class HandOverExpiredChecks extends Command
{
    protected $signature = 'paid-check:hand-over-expired';

    protected $description = 'Hand over paid AIRS checks whose amendment window has ended';

    public function handle(PaidCheckJourneyService $journeys): int
    {
        $this->info('Diserahkan: '.$journeys->handOverExpired());

        return self::SUCCESS;
    }
}
