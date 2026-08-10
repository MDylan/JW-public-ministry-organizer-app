<?php

namespace Tests\Unit\Events;

use App\Classes\GenerateSlots;
use Tests\TestCase;

/**
 * TODO 07.1: App\Classes\GenerateSlots is the foundation of the calendar's
 * entire time-slot arithmetic. Events\EventEdit and Events\Modal both build
 * their day table from it, and the capacity and overlap checks walk a
 * requested range with this same step. If this class drifts, every rule built
 * on top of it drifts with it - that's why it gets its own, DB-free coverage.
 *
 * A significant portion of the tests are characterization tests: they record
 * current behavior, not what would be ideal. Deviations are marked by
 * comments.
 */
class GenerateSlotsTest extends TestCase
{
    private const DATE = '2026-09-15';

    /**
     * @return int[] the generated slots' unix timestamps, in order
     */
    private function slots(string $date, string $from, string $to, int $stepMinutes, ?string $endDate = null): array
    {
        return array_values(GenerateSlots::generate(
            $date,
            strtotime($date.' '.$from),
            strtotime(($endDate ?? $date).' '.$to),
            $stepMinutes * 60
        ));
    }

    /**
     * @return string[] the generated slots in H:i form, for readable assertions
     */
    private function slotTimes(string $date, string $from, string $to, int $stepMinutes, ?string $endDate = null): array
    {
        return array_map(
            fn ($ts) => date('H:i', $ts),
            $this->slots($date, $from, $to, $stepMinutes, $endDate)
        );
    }

    // =========================================================================
    // Whole-hour step
    // =========================================================================

    public function test_hourly_steps_cover_the_range_without_the_closing_boundary(): void
    {
        // The closing time gets no slot: 08:00-12:00 produces four slots, not
        // five. The 11:00 slot holds the 11:00-12:00 time.
        $this->assertSame(
            ['08:00', '09:00', '10:00', '11:00'],
            $this->slotTimes(self::DATE, '08:00', '12:00', 60)
        );
    }

    public function test_the_array_is_keyed_by_the_timestamp_it_contains(): void
    {
        // The callers (EventEdit:231, Modal:283) use the value, not the key -
        // but the two must match, otherwise the foreach loops drift.
        $generated = GenerateSlots::generate(
            self::DATE,
            strtotime(self::DATE.' 08:00'),
            strtotime(self::DATE.' 12:00'),
            3600
        );

        foreach ($generated as $key => $value) {
            $this->assertSame($key, $value);
        }
    }

    public function test_a_single_step_range_produces_exactly_one_slot(): void
    {
        $this->assertSame(['08:00'], $this->slotTimes(self::DATE, '08:00', '09:00', 60));
    }

    public function test_an_empty_range_produces_no_slots(): void
    {
        $this->assertSame([], $this->slotTimes(self::DATE, '08:00', '08:00', 60));
    }

    // =========================================================================
    // Half-hour step
    // =========================================================================

    public function test_half_hour_steps_split_every_hour(): void
    {
        $this->assertSame(
            ['08:00', '08:30', '09:00', '09:30', '10:00', '10:30', '11:00', '11:30'],
            $this->slotTimes(self::DATE, '08:00', '12:00', 30)
        );
    }

    public function test_a_half_hour_start_keeps_the_offset_across_whole_hour_steps(): void
    {
        // The $start_half branch (GenerateSlots.php:21-24): starting from
        // 08:30, an hourly step keeps producing half-hour slots throughout,
        // it does not re-align back to whole hours.
        $this->assertSame(
            ['08:30', '09:30', '10:30', '11:30'],
            $this->slotTimes(self::DATE, '08:30', '12:00', 60)
        );
    }

    public function test_a_half_hour_end_leaves_the_last_slot_hanging_over_it(): void
    {
        // Characterization: for a day ending at 11:30, the 11:00 slot is
        // still created, even though only half an hour of room remained for
        // it. The code does not trim it back - the $max_hour correction
        // (GenerateSlots.php:26-29) has been DEAD CODE since the rewrite to a
        // while loop, because nothing uses $max_hour.
        $this->assertSame(
            ['08:00', '09:00', '10:00', '11:00'],
            $this->slotTimes(self::DATE, '08:00', '11:30', 60)
        );
    }

    public function test_a_half_hour_start_and_end_together_stay_aligned(): void
    {
        // If the start is also on the half hour, the closing correction is
        // skipped because of the $start_half switch - here the 11:30 slot
        // still fits.
        $this->assertSame(
            ['08:30', '09:30', '10:30', '11:30'],
            $this->slotTimes(self::DATE, '08:30', '12:30', 60)
        );
    }

    // =========================================================================
    // A day running to midnight
    // =========================================================================

    public function test_a_range_ending_at_midnight_runs_to_the_end_of_the_day(): void
    {
        // The max_hour == 0 -> 24 branch (GenerateSlots.php:16). The loop
        // steps into the next day via the "24:00" string, so it does not
        // wrap back to 00:00.
        $this->assertSame(
            ['20:00', '21:00', '22:00', '23:00'],
            $this->slotTimes(self::DATE, '20:00', '00:00', 60, '2026-09-16')
        );
    }

    // =========================================================================
    // Daylight saving time - the class's declared purpose
    // =========================================================================

    /**
     * According to the class's doc-block, it exists in order to "escape" the
     * daylight saving time switch. The test timezone is UTC, where there is
     * no switch, so this can only be covered by an explicit override.
     */
    private function withTimezone(string $timezone, callable $callback): void
    {
        $original = date_default_timezone_get();
        date_default_timezone_set($timezone);

        try {
            $callback();
        } finally {
            date_default_timezone_set($original);
        }
    }

    public function test_spring_forward_does_not_produce_a_duplicate_or_missing_slot(): void
    {
        // 2026-03-29 02:00 does not exist in Budapest: the clock jumps from
        // 02:00 to 03:00. The generator normalizes the non-existent "2:00"
        // string to 03:00, and the next step lands on the same value - the
        // array-keyed storage absorbs the duplicate. This is why the list
        // stays gap-free and strictly increasing.
        $this->withTimezone('Europe/Budapest', function () {
            $times = $this->slotTimes('2026-03-29', '00:00', '06:00', 60);

            $this->assertNotContains('02:00', $times, 'A 02:00 helyi idő ezen a napon nem létezik.');
            $this->assertSame($times, array_values(array_unique($times)), 'Nem lehet duplikált sáv.');

            $slots = $this->slots('2026-03-29', '00:00', '06:00', 60);
            $sorted = $slots;
            sort($sorted);
            $this->assertSame($sorted, $slots, 'A sávoknak szigorúan növekvő sorrendben kell lenniük.');
        });
    }

    public function test_fall_back_hour_is_visited_only_once(): void
    {
        // 2026-10-25 02:00 occurs twice in Budapest. The generator resolves
        // one string per hour, and strtotime picks the first one (CEST) -
        // so it SKIPS the repeated hour. Characterization test: on this day
        // the day appears one hour shorter in the calendar.
        $this->withTimezone('Europe/Budapest', function () {
            $times = $this->slotTimes('2026-10-25', '00:00', '06:00', 60);

            $this->assertSame($times, array_values(array_unique($times)));
            $this->assertContains('02:00', $times);
        });
    }
}
