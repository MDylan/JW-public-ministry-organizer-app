<?php

namespace Tests\Unit\Scheduler;

use Illuminate\Console\Scheduling\Schedule;
use Tests\TestCase;

/**
 * Since TODO 06 the scheduler contains no anonymous closures: every task is a
 * named Artisan command living under app/Console/Commands. This test pins the
 * full schedule - command name and cron expression - so an upgrade cannot
 * silently drop or re-time a task.
 */
class SchedulerRegressionTest extends TestCase
{
    /** Command name => cron expression. */
    private const EXPECTED_SCHEDULE = [
        'queue:work --name=kozteruletek-job-1' => '* * * * *',
        'users:purge-unverified' => '50 * * * *',
        'events:expire-pending' => '*/5 * * * *',
        'gdpr:anonymize-inactive' => '0 7 * * *',
        'gdpr:notify-anonymization' => '10 7 * * *',
        // v1-patch E: the retention cleanup runs after 3am, so it does not fall
        // into the 00:00 pile-up. Events first, the group data derived from them
        // afterwards.
        //
        // --force is PART of the command name, and must remain so: without it,
        // Spatie's command running from the scheduler would ask for confirmation,
        // get a negative answer for lack of a TTY, and silently delete nothing.
        'activitylog:clean --force' => '20 3 * * *',
        'gdpr:purge-old-events' => '30 3 * * *',
        'maintenance:purge-old-group-data' => '40 3 * * *',
        'maintenance:purge-log-history' => '0 0 * * *',
        'maintenance:daily-cleanup' => '0 0 * * *',
        'statistics:record-daily-users' => '0 0 * * *',
        'groups:apply-future-changes' => '* * * * *',
        'newsletters:send-due' => '* * * * *',
        'statistics:record-active-users' => '0 * * * *',
        'scheduler:heartbeat' => '* * * * *',
        // v1-patch C: refreshing the weather cache. The free OpenWeather quota
        // (1000 calls/day) together with the 3-hourly forecast justifies this
        // frequency - see RefreshWeatherCache.
        'weather:refresh' => '0 */3 * * *',
        // TODO 33.2 removed an 18th entry: gdpr:anonymizeInactiveUsers at
        // 00:00, scheduled by the Dialect package's own service provider from
        // an app->booted() callback - i.e. after Kernel::schedule(), so it
        // could not be filtered out from here. With the package gone,
        // gdpr:anonymize-inactive at 07:00 is the only anonymizer, and the
        // divergence between the two (only one of them detached group
        // memberships) is retired for good.
    ];

    private function scheduledEvents(): \Illuminate\Support\Collection
    {
        return collect(app(Schedule::class)->events());
    }

    public function test_every_expected_task_is_scheduled_at_the_expected_frequency(): void
    {
        $events = $this->scheduledEvents();

        foreach (self::EXPECTED_SCHEDULE as $command => $expression) {
            $this->assertTrue(
                $events->contains(
                    static fn ($event) => $event->command !== null
                        && str_contains($event->command, $command)
                        && $event->expression === $expression
                ),
                "Scheduled task '{$command}' is missing or no longer runs at '{$expression}'."
            );
        }
    }

    public function test_the_scheduler_contains_no_anonymous_closures(): void
    {
        // This is the essence of TODO 06: every scheduled task is a named
        // command, so `schedule:list` documents the system on its own, and every
        // task can be tested individually.
        $closures = $this->scheduledEvents()->filter(static fn ($event) => $event->command === null);

        $this->assertCount(
            0,
            $closures,
            'The scheduler gained an anonymous closure again; extract it into an Artisan command.'
        );
    }

    public function test_the_schedule_has_no_unexpected_extra_tasks(): void
    {
        $this->assertCount(
            count(self::EXPECTED_SCHEDULE),
            $this->scheduledEvents(),
            'The number of scheduled tasks changed; update EXPECTED_SCHEDULE deliberately.'
        );
    }

    public function test_queue_worker_runs_without_overlapping(): void
    {
        $worker = $this->scheduledEvents()->first(
            static fn ($event) => $event->command !== null
                && str_contains($event->command, 'queue:work --name=kozteruletek-job-1')
        );

        $this->assertNotNull($worker);
        $this->assertNotEmpty($worker->mutexName(), 'The queue worker lost its withoutOverlapping guard.');
    }
}
