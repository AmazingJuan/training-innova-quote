<?php

namespace Tests\Feature;

use App\Support\BusinessCalendar;
use Carbon\CarbonImmutable;
use Tests\TestCase;

class BusinessCalendarTest extends TestCase
{
    public function test_colombian_holidays_of_2026(): void
    {
        $this->assertSame([
            '2026-01-01', '2026-01-12', '2026-03-23', '2026-04-02', '2026-04-03', '2026-05-01',
            '2026-05-18', '2026-06-08', '2026-06-15', '2026-06-29', '2026-07-20', '2026-08-07',
            '2026-08-17', '2026-10-12', '2026-11-02', '2026-11-16', '2026-12-08', '2026-12-25',
        ], collect(array_keys(BusinessCalendar::holidays(2026)))->sort()->values()->all());
    }

    public function test_business_days_skip_weekends_and_holidays(): void
    {
        // Friday 13 Nov 2026 + 3 business days: Monday 16 is a holiday → Thursday 19.
        $this->assertSame('2026-11-19', BusinessCalendar::addBusinessDays(CarbonImmutable::parse('2026-11-13 10:00'), 3)->format('Y-m-d'));
        // Monday + 3 → Thursday.
        $this->assertSame('2026-11-26', BusinessCalendar::addBusinessDays(CarbonImmutable::parse('2026-11-23 18:00'), 3)->format('Y-m-d'));
    }

    public function test_the_acta_example_of_the_reminder_turns(): void
    {
        // Sent on Monday at 9:00 a. m.: 5th turn on Wednesday at 2:00 p. m., 10th on the next Monday at 8:00 a. m.
        $sent = CarbonImmutable::parse('2026-11-23 09:00');

        $this->assertSame(4, BusinessCalendar::turnsBetween($sent, CarbonImmutable::parse('2026-11-25 13:59')));
        $this->assertSame(5, BusinessCalendar::turnsBetween($sent, CarbonImmutable::parse('2026-11-25 14:00')));
        $this->assertSame(9, BusinessCalendar::turnsBetween($sent, CarbonImmutable::parse('2026-11-27 23:00')));
        $this->assertSame(10, BusinessCalendar::turnsBetween($sent, CarbonImmutable::parse('2026-11-30 08:00')));
    }
}
