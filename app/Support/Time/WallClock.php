<?php

namespace App\Support\Time;

/**
 * Wall-clock times of a day template ('H:i'), counted in minutes since
 * midnight - never as timestamps of a real date.
 *
 * Until roadmap TODO 72.3 the group form and the special-date modal built
 * their time selects from today's timestamps (strtotime("00:00") plus
 * 24 * 60 * 60, and `± min_time * 60`). On the two days a year the
 * timezone changes clock time that is an hour off: on the 25-hour autumn
 * day "midnight" came out as 23:00, so a template ending at midnight was
 * clamped to 23:00, and the night lists held 02:00 twice; on the 23-hour
 * spring day the lists ran past midnight and skipped 02:00. A day
 * template has no date, so its lists must not depend on one.
 */
final class WallClock
{
    public const DAY = 24 * 60;

    /**
     * Minutes since midnight of an 'H:i' or 'H:i:s' time; of a 'Y-m-d H:i'
     * value only the time is read, as strtotime()-then-date('H:i') did.
     */
    public static function minutes(string $time): int
    {
        $time = trim($time);
        if (str_contains($time, ' ')) {
            $time = substr($time, strrpos($time, ' ') + 1);
        }

        [$hours, $minutes] = array_map('intval', explode(':', $time) + [0, 0]);

        return $hours * 60 + $minutes;
    }

    /** 'H:i' of a minute count, wrapped into one day. */
    public static function format(int $minutes): string
    {
        $minutes = (($minutes % self::DAY) + self::DAY) % self::DAY;

        return sprintf('%02d:%02d', intdiv($minutes, 60), $minutes % 60);
    }

    /** The time $minutes later (or earlier), wrapped into one day. */
    public static function shift(string $time, int $minutes): string
    {
        return self::format(self::minutes($time) + $minutes);
    }

    /**
     * The options of a time select: from $start (00:00 if not given) in
     * $step minutes, below $end (a whole day if not given). An end of 00:00
     * means the end of the day, and 00:00 itself is the last option a list
     * may reach - the same contract as the generateTimeArray() methods this
     * replaced, on every date.
     *
     * @return list<string>
     */
    public static function options(?string $end = null, ?string $start = null, int|string $step = 30): array
    {
        // A step of 0 would never leave the loop.
        $step = max(1, (int) $step);

        $first = $start ? self::minutes($start) : 0;
        $max = $end ? self::minutes($end) : $first + self::DAY;
        if ($max === 0) {
            $max = $first + self::DAY;
        }

        $times = [];
        for ($current = $first; $current < $max; $current += $step) {
            if ($current > self::DAY) {
                break;
            }
            $times[] = self::format($current);
        }

        return $times;
    }
}
