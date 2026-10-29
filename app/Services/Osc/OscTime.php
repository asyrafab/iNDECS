<?php

declare(strict_types=1);

namespace App\Services\Osc;

use Carbon\CarbonImmutable;
use DateTimeInterface;

/**
 * eOSC+ stores instants in UTC; officers enter and read times in Malaysian local time
 * (config app.display_timezone). The application timezone itself stays UTC (ADR-314).
 */
final class OscTime
{
    public static function zone(): string
    {
        return (string) config('app.display_timezone', 'Asia/Kuala_Lumpur');
    }

    /** Parse a local `datetime-local` value (e.g. 2026-11-05T17:00) into a UTC instant. */
    public static function fromLocal(string $value): CarbonImmutable
    {
        return CarbonImmutable::parse($value, self::zone())->utc();
    }

    public static function format(?DateTimeInterface $instant, string $format = 'd/m/Y H:i'): string
    {
        return $instant === null ? '' : CarbonImmutable::instance($instant)->setTimezone(self::zone())->format($format);
    }
}
