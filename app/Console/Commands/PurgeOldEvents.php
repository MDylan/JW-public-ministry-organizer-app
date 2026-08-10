<?php

namespace App\Console\Commands;

use App\Models\Event;
use App\Support\Retention\RetentionWindow;
use Illuminate\Support\Facades\DB;
use Illuminate\Console\Command;

/**
 * PERMANENT deletion of events older than the retention window.
 *
 * WHY IT EXISTS
 *
 * Nothing cleaned up the `events` table by age. `maintenance:daily-cleanup`
 * only purges rows that are ALREADY soft-deleted, at 3
 * months; it doesn't touch live events - so in production 164,371 live
 * event rows had piled up going back to June 2022, including every publisher's
 * every service assignment. This is personal data, so it belongs under the GDPR
 * toggle.
 *
 * THE DELETION IS BUILDER-LEVEL, AND THIS IS NOT A STYLE CHOICE
 *
 * Eloquent\Builder::forceDelete() is a single `$this->query->delete()`, so it
 * does NOT fire model events. Deleting instance by instance would run
 * EventObserver::deleted() on every row: it would send a mail to the affected
 * publisher AND every admin of the group, and write a
 * log_histories entry per row. On the first production run that's 121,000 events -
 * several hundred thousand emails, and a log table that would grow bigger in the
 * process than what the deletion freed up. DailyCleanup uses the
 * same approach.
 *
 * BATCHING GOES BY `id`
 *
 * `events.day` isn't indexed on its own (only in the `(group_id, day)`
 * composite), but in a continuously growing table `id` and `day` are
 * strongly correlated, so batches of 1000 by primary key
 * close quickly. A single 121,000-row DELETE would also be one long
 * InnoDB transaction, cascade included.
 *
 * WHAT GOES WITH IT
 *
 * `event_service_reports.event_id` is a foreign key with ON DELETE CASCADE, so
 * the event's service reports (placements, videos, return visits,
 * Bible studies) are deleted too. This is deliberate: the report is just as much
 * personal data as the event itself. The command counts them
 * BEFORE running, so the report doesn't only state the number of events.
 *
 * withTrashed() adds practically nothing - DailyCleanup already empties the
 * trash at 3 months -, but without it old soft-deleted rows
 * would be left out. Not load-bearing, just thorough.
 */
class PurgeOldEvents extends Command
{
    /** This many events deleted per batch. */
    private const BATCH = 1000;

    protected $signature = 'gdpr:purge-old-events {--dry-run : Report what would be deleted without deleting anything}';

    protected $description = 'Permanently delete events older than the GDPR retention window, with their service reports';

    public function handle()
    {
        // The guard lives here, not in the scheduler: it must also protect
        // against a manually started run. All gdpr:-prefixed commands do it this way.
        if (! config('gdpr.enabled')) {
            $this->warn('GDPR handling is disabled, nothing to do.');

            return self::SUCCESS;
        }

        $floor = RetentionWindow::eventsFloor()->toDateString();

        $reports = DB::table('event_service_reports')
            ->join('events', 'events.id', '=', 'event_service_reports.event_id')
            ->where('events.day', '<', $floor)
            ->count();

        if ($this->option('dry-run')) {
            $events = Event::withTrashed()->where('day', '<', $floor)->count();

            $this->info("Dry run: {$events} event(s) and {$reports} service report(s) before {$floor} would be deleted.");

            return self::SUCCESS;
        }

        $deleted = 0;

        do {
            $ids = Event::withTrashed()
                ->where('day', '<', $floor)
                ->orderBy('id')
                ->limit(self::BATCH)
                ->pluck('id');

            if ($ids->isEmpty()) {
                break;
            }

            $deleted += Event::withTrashed()->whereIn('id', $ids)->forceDelete();
        } while (true);

        $this->info("Deleted {$deleted} event(s) and {$reports} service report(s) before {$floor}.");

        return self::SUCCESS;
    }
}
