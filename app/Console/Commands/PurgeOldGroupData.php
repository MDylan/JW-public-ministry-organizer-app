<?php

namespace App\Console\Commands;

use App\Models\DayStat;
use App\Models\GroupDate;
use App\Support\Retention\RetentionWindow;
use Illuminate\Console\Command;

/**
 * Permanent deletion of group data older than the retention window.
 *
 * WHY A SEPARATE COMMAND, AND WHY IT'S NOT CONTROLLED BY THE GDPR TOGGLE
 *
 * The two affected tables don't contain personal data: the `day_stats` rows
 * carry a group, a day, a time slot and a COUNT, and `group_dates`
 * carries the group's time settings for a given day. Their deletion is
 * justified by size - 471,754 and 48,033 rows respectively in production, going back
 * to June 2022, without retention. That's why they have their own toggle,
 * settable in the admin UI (`settings.group_data_retention`), not the GDPR one: it's
 * turned off on this installation, and a data-protection toggle must not
 * block a maintenance task.
 *
 * WHY GROUP_DATES GOES TOO
 *
 * Groups\Statistics builds the daily rows from `group_dates`, not from
 * `day_stats` (`isset($dates[$key])`). If only the statistics disappeared,
 * the UI wouldn't show an empty table, but a row for every old day stating
 * that the group served 0 hours out of N available. False data is
 * worse than missing data, so the two go together.
 *
 * BATCHING
 *
 * Neither model has an observer or soft deletes, so the builder's ->delete()
 * is a raw DELETE to begin with. 350,000 rows, however, can't go in a single
 * transaction, so it's done in batches of 5000 by `id` - MySQL's grammar supports the
 * DELETE ... ORDER BY ... LIMIT form.
 */
class PurgeOldGroupData extends Command
{
    /** This many rows deleted per batch. */
    private const BATCH = 5000;

    protected $signature = 'maintenance:purge-old-group-data {--dry-run : Report what would be deleted without deleting anything}';

    protected $description = 'Permanently delete day statistics and group date settings past the configured retention window';

    public function handle()
    {
        $floor = RetentionWindow::groupDataFloor();

        if ($floor === null) {
            $this->info('Group data retention is disabled; nothing to purge.');

            return self::SUCCESS;
        }

        $floor = $floor->toDateString();

        if ($this->option('dry-run')) {
            $stats = DayStat::where('day', '<', $floor)->count();
            $dates = GroupDate::where('date', '<', $floor)->count();

            $this->info("Dry run: {$stats} day statistic(s) and {$dates} group date(s) before {$floor} would be deleted.");

            return self::SUCCESS;
        }

        $stats = $this->purge(DayStat::query()->where('day', '<', $floor));
        $dates = $this->purge(GroupDate::query()->where('date', '<', $floor));

        $this->info("Deleted {$stats} day statistic(s) and {$dates} group date(s) before {$floor}.");

        return self::SUCCESS;
    }

    /**
     * Batched deletion. The closing condition is the number of actually affected rows,
     * not a separate count() - so a concurrent write can't drive it into an
     * infinite loop.
     */
    private function purge($query): int
    {
        $deleted = 0;

        do {
            $rows = (clone $query)->orderBy('id')->limit(self::BATCH)->delete();
            $deleted += $rows;
        } while ($rows > 0);

        return $deleted;
    }
}
