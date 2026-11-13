<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Services\PaidCheck\PaidCheckJourneyService;
use Illuminate\Console\Command;

/** Lists paid AIRS checks that will not finish by themselves, for staff follow-up. */
final class ListStuckPaidChecks extends Command
{
    protected $signature = 'paid-check:stuck';

    protected $description = 'List paid AIRS check journeys whose check never started or failed permanently';

    public function handle(PaidCheckJourneyService $journeys): int
    {
        $rows = array_map(fn (array $item) => [$item['journey']->application->application_reference, $item['journey']->state->value,
            $item['reason'], $item['journey']->updated_at?->toIso8601String()], $journeys->stuck());
        if ($rows === []) {
            $this->info('Tiada semakan tersekat.');

            return self::SUCCESS;
        }
        $this->table(['Rujukan', 'Keadaan', 'Sebab', 'Kemas kini'], $rows);

        return self::SUCCESS;
    }
}
