<?php

namespace Tests\Unit\Support;

use App\Support\Time\WallClock;
use Tests\TestCase;

/**
 * Roadmap TODO 72.3: the time selects of a day template, as wall-clock
 * minutes.
 *
 * The lists below are what the old generateTimeArray() produced on an
 * ordinary day - the contract the form tests (GroupDayTimeRangeTest) were
 * written against. The old code met it on every other day of the year; on
 * the two clock change days in Europe/Budapest it did not, because it counted from
 * today's timestamps. WallClock takes no date, so these hold on every day.
 */
class WallClockTest extends TestCase
{
    public function test_a_whole_day_in_half_hours(): void
    {
        $times = WallClock::options();

        $this->assertCount(48, $times);
        $this->assertSame('00:00', $times[0]);
        $this->assertSame('23:30', $times[47]);
    }

    public function test_an_end_of_midnight_means_the_end_of_the_day(): void
    {
        $this->assertSame(WallClock::options(), WallClock::options('00:00'));
    }

    public function test_the_start_list_stops_below_the_end(): void
    {
        $this->assertSame(['00:00', '00:30', '01:00', '01:30'], WallClock::options('02:00'));
    }

    public function test_the_end_list_reaches_midnight_and_no_further(): void
    {
        $this->assertSame(
            ['18:00', '19:00', '20:00', '21:00', '22:00', '23:00', '00:00'],
            WallClock::options(null, '18:00', 60)
        );
    }

    public function test_a_start_the_step_cannot_leave_lists_only_itself(): void
    {
        // The case behind GroupDayTimeRangeTest's
        // test_a_template_the_clamp_cannot_repair_is_reported_on_the_field:
        // 23:00 + 120 minutes is past midnight.
        $this->assertSame(['23:00'], WallClock::options(null, '23:00', 120));
    }

    public function test_the_night_of_a_clock_change_is_an_ordinary_night(): void
    {
        // 02:00-03:00 is the hour that is skipped in spring and repeated in
        // autumn. Today's timestamps listed 02:00 twice (2026-10-25) or not
        // at all (2026-03-29) - measured on the old code.
        $this->assertSame(['01:00', '02:00', '03:00', '04:00'], WallClock::options('05:00', '01:00', 60));
    }

    public function test_shifting_wraps_around_midnight(): void
    {
        $this->assertSame('23:00', WallClock::shift('00:00', -60));
        $this->assertSame('01:30', WallClock::shift('23:30', 120));
        $this->assertSame('04:00', WallClock::shift('05:00', -60));
    }

    public function test_seconds_and_a_date_part_are_ignored(): void
    {
        $this->assertSame(8 * 60 + 15, WallClock::minutes('08:15:00'));
        $this->assertSame(8 * 60 + 15, WallClock::minutes('2026-10-25 08:15'));
    }

    public function test_a_step_of_zero_cannot_loop_forever(): void
    {
        $this->assertCount(2, WallClock::options('00:02', null, 0));
    }
}
