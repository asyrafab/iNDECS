<?php

declare(strict_types=1);

namespace Tests\Unit;

use App\Models\PublicHoliday;
use App\Services\WorkingCalendar;
use Carbon\CarbonImmutable;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

final class WorkingCalendarTest extends TestCase
{
    use RefreshDatabase;

    private function at(string $time): CarbonImmutable
    {
        return CarbonImmutable::parse($time, 'Asia/Kuala_Lumpur');
    }

    public function test_counts_five_working_days_skipping_weekend(): void
    {
        $due = app(WorkingCalendar::class)->addWorkingDays($this->at('2026-11-13 10:00'), 5);
        $this->assertSame('2026-11-20 23:59:59', $due->setTimezone('Asia/Kuala_Lumpur')->format('Y-m-d H:i:s'));
    }

    public function test_skips_public_holidays(): void
    {
        PublicHoliday::query()->create(['date' => '2026-11-17', 'name' => 'Cuti ujian']);
        $due = app(WorkingCalendar::class)->addWorkingDays($this->at('2026-11-13 10:00'), 5);
        $this->assertSame('2026-11-23 23:59:59', $due->setTimezone('Asia/Kuala_Lumpur')->format('Y-m-d H:i:s'));
    }

    public function test_start_day_is_not_counted_even_if_working(): void
    {
        $due = app(WorkingCalendar::class)->addWorkingDays($this->at('2026-11-16 08:00'), 1);
        $this->assertSame('2026-11-17 23:59:59', $due->setTimezone('Asia/Kuala_Lumpur')->format('Y-m-d H:i:s'));
    }

    public function test_is_working_day(): void
    {
        PublicHoliday::query()->create(['date' => '2026-11-18', 'name' => 'Cuti ujian']);
        $calendar = app(WorkingCalendar::class);
        $this->assertFalse($calendar->isWorkingDay($this->at('2026-11-14 12:00')));
        $this->assertFalse($calendar->isWorkingDay($this->at('2026-11-18 12:00')));
        $this->assertTrue($calendar->isWorkingDay($this->at('2026-11-19 12:00')));
    }
}
