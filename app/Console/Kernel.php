<?php

namespace App\Console;

use Illuminate\Console\Scheduling\Schedule;
use Illuminate\Foundation\Console\Kernel as ConsoleKernel;

class Kernel extends ConsoleKernel
{
    /**
     * The Artisan commands provided by your application.
     *
     * @var array
     */
    // Empty since TODO 33.2. It used to hold PackageAnonymizeInactiveUsers, a
    // subclass registered here purely to shadow the Dialect package's own
    // gdpr:anonymizeInactiveUsers - a trick that worked only because
    // Kernel::getArtisan() resolves $commands AFTER the Artisan::starting()
    // callbacks a service provider registers through. A data-protection
    // guarantee resting on framework-internal ordering is exactly what an
    // 8 -> 13 upgrade would disturb, and removing the package removed the need.
    // Everything else is discovered from app/Console/Commands.
    protected $commands = [
        //
    ];

    /**
     * Define the application's command schedule.
     *
     * @param  \Illuminate\Console\Scheduling\Schedule  $schedule
     * @return void
     */
    protected function schedule(Schedule $schedule)
    {
        // Named commands run instead of the previous anonymous closures.
        // The scheduling and order are unchanged; the commands' bodies live in the
        // app/Console/Commands directory and are individually testable.
        $schedule->command('queue:work --name=kozteruletek-job-1 --queue=default --max-time=25 --max-jobs=100 --sleep=3 --tries=3 --backoff=20')
                    ->everyMinute()
                    ->withoutOverlapping(1);

        $schedule->command('users:purge-unverified')->hourlyAt(50);

        $schedule->command('events:expire-pending')->everyFiveMinutes();

        $schedule->command('gdpr:anonymize-inactive')->dailyAt('7:00');

        $schedule->command('gdpr:notify-anonymization')->dailyAt('7:10');

        // The retention cleanup runs after 3am, not in the 00:00 pile-up
        // (purge-log-history, daily-cleanup, record-daily-users are all there).
        // Events first, the group data derived from them after.

        // Spatie's command has NEVER run until now, even though
        // config/activitylog.php declares a 90-day retention - the setting
        // therefore hasn't taken effect for four years (in production, 7581 of the
        // 7739 rows are already past 90 days).
        //
        // --force cannot be omitted: CleanActivitylogCommand starts with
        // ConfirmableTrait::confirmToProceed(), which asks for confirmation in
        // production environments. Running from the scheduler there's no TTY, so
        // confirm() returns the false default, the command prints
        // "Command Cancelled!", exits with 1 and deletes NOTHING - silently,
        // because nothing surfaces the scheduler's exit code.
        //
        // The guard is here in when(), not in the command body: we can't
        // write into the vendor command. So running it manually bypasses it -
        // but that is an explicit operator action.
        $schedule->command('activitylog:clean --force')
                    ->dailyAt('3:20')
                    ->when(fn () => (bool) config('gdpr.enabled'));

        $schedule->command('gdpr:purge-old-events')->dailyAt('3:30');

        $schedule->command('maintenance:purge-old-group-data')->dailyAt('3:40');

        $schedule->command('maintenance:purge-log-history')->daily();

        $schedule->command('maintenance:daily-cleanup')->daily();

        $schedule->command('statistics:record-daily-users')->daily();

        $schedule->command('groups:apply-future-changes')->everyMinute();

        $schedule->command('newsletters:send-due')->everyMinute();

        $schedule->command('statistics:record-active-users')->hourly();

        $schedule->command('scheduler:heartbeat')->everyMinute();

        // Every three hours. The free OpenWeather tier is 1000 calls/day, and
        // refreshing one city is 2 calls - this fits roughly 60 cities within
        // the budget. The forecast itself has 3-hourly resolution, so a
        // denser run wouldn't yield more information.
        $schedule->command('weather:refresh')->cron('0 */3 * * *');
    }

    /**
     * Register the commands for the application.
     *
     * @return void
     */
    protected function commands()
    {
        $this->load(__DIR__.'/Commands');

        require base_path('routes/console.php');
    }
}
