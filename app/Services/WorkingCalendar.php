<?php

declare(strict_types=1);

namespace App\Services;

use App\Models\PublicHoliday;
use Carbon\CarbonImmutable;

/** Working days in Malaysia time: Monday–Friday, excluding dates in core.public_holidays. */
final class WorkingCalendar
{
    private const ZONE = 'Asia/Kuala_Lumpur';

    public function isWorkingDay(CarbonImmutable $day): bool
    {
        $local = $day->setTimezone(self::ZONE);

        return ! $local->isWeekend() && ! PublicHoliday::query()->whereKey($local->toDateString())->exists();
    }

    /**
     * End (23:59:59 Malaysia time) of the Nth working day after $from; $from's own day never counts.
     * Returned as a UTC instant (ADR-314: store UTC, display local).
     */
    public function addWorkingDays(CarbonImmutable $from, int $days): CarbonImmutable
    {
        $day = $from->setTimezone(self::ZONE)->startOfDay();
        $counted = 0;
        while ($counted < $days) {
            $day = $day->addDay();
            if ($this->isWorkingDay($day)) {
                $counted++;
            }
        }

        return $day->endOfDay()->startOfSecond()->utc();
    }
}
