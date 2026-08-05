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
    /** Parancsnév => cron kifejezés. */
    private const EXPECTED_SCHEDULE = [
        'queue:work --name=kozteruletek-job-1' => '* * * * *',
        'users:purge-unverified' => '50 * * * *',
        'events:expire-pending' => '*/5 * * * *',
        'gdpr:anonymize-inactive' => '0 7 * * *',
        'gdpr:notify-anonymization' => '10 7 * * *',
        'maintenance:purge-log-history' => '0 0 * * *',
        'maintenance:daily-cleanup' => '0 0 * * *',
        'statistics:record-daily-users' => '0 0 * * *',
        'groups:apply-future-changes' => '* * * * *',
        'newsletters:send-due' => '* * * * *',
        'statistics:record-active-users' => '0 * * * *',
        'scheduler:heartbeat' => '* * * * *',
        // A Dialect GDPR csomag saját ütemezése.
        'gdpr:anonymizeInactiveUsers' => '0 0 * * *',
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
        // Ez a TODO 06 lényege: minden ütemezett feladat nevesített parancs,
        // így a `schedule:list` önmagában dokumentálja a rendszert, és minden
        // feladat külön tesztelhető.
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
