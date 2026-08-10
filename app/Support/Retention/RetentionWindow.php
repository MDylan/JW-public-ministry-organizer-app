<?php

namespace App\Support\Retention;

use Carbon\Carbon;

/**
 * v1-patch E: how far back we retain data.
 *
 * This is the ONE place where a retention floor date is produced. The two delete
 * commands and the four UI limits all call these same methods, because
 * the two must not drift apart from each other: if the calendar allows opening a
 * month that the purge has already cleared out, the UI doesn't show an empty
 * table, it shows false data - zeros for a period that was actually worked.
 *
 * TWO PITFALLS SOLVED HERE, ONCE
 *
 * 1. The group-data setting's value must NOT BE CAST. The Livewire Admin\Settings
 *    writes into the settings table without validation, and $state is a public
 *    property - a sneaked-in 'x' value would become zero with (int), and
 *    subMonths(0) would put the floor on TODAY, i.e. the command would wipe out the
 *    entire day_stats and group_dates table. Hence a whitelist
 *    (config('retention.group_data_options')), not a conversion, and every
 *    unknown value - including null - means a disabled state.
 *
 * 2. subMonthsNoOverflow(), and day-precision comparison. Carbon's
 *    subMonths() overflows by default (2026-03-31 minus 13 months gives
 *    2025-03-03 for it, not 2025-02-28), and events.day and day_stats.day are
 *    DATE columns: compared against a timestamp, the boundary-day rows would
 *    get deleted or kept depending on what hour the scheduler ran.
 *    So the floor is always the start of a day, and must be compared with
 *    toDateString().
 *
 * The returned Carbon is a fresh instance on every call, so the caller can
 * safely mutate it (startOfMonth(), format(), etc.).
 */
class RetentionWindow
{
    /**
     * The events floor. Null if GDPR handling is disabled -
     * in that case no event gets deleted.
     */
    public static function eventsFloor(): ?Carbon
    {
        if (! config('gdpr.enabled')) {
            return null;
        }

        return self::floor((int) config('retention.events_months'));
    }

    /**
     * The floor for group data (day_stats, group_dates). Null if the admin
     * UI setting is disabled or set to an unknown value.
     *
     * Deliberately NOT gated by gdpr.enabled: these tables contain no
     * personal data (group, day, time slot, count), their deletion is justified by
     * size.
     */
    public static function groupDataFloor(): ?Carbon
    {
        $months = config('settings_group_data_retention');

        if (! is_scalar($months)) {
            return null;
        }

        $months = (string) $months;

        if (! in_array($months, config('retention.group_data_options', ['0']), true)) {
            return null;
        }

        if ($months === '0') {
            return null;
        }

        return self::floor((int) $months);
    }

    /**
     * The earliest day that can be displayed in the UI: the LATER of the
     * two active floors.
     *
     * The later one, because a view can only reliably show what all of
     * its sources have. A calendar that renders both events and
     * day_stats is only accurate as long as both are alive.
     *
     * Careful: this is only good for views that use BOTH data sources.
     * Anything that queries only events (Events\LastEvents) should use
     * eventsFloor() - displayFloor() would hide existing, editable
     * events there.
     */
    public static function displayFloor(): ?Carbon
    {
        $events = self::eventsFloor();
        $groupData = self::groupDataFloor();

        if ($events === null) {
            return $groupData;
        }

        if ($groupData === null) {
            return $events;
        }

        return $events->greaterThan($groupData) ? $events : $groupData;
    }

    private static function floor(int $months): Carbon
    {
        return Carbon::today()->subMonthsNoOverflow($months)->startOfDay();
    }
}
