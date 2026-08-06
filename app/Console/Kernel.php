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
    protected $commands = [
        // A Dialect csomag gdpr:anonymizeInactiveUsers parancsának helyére a
        // projekt leszármazottja kerül. Innen regisztrálva felülírja a csomag
        // providerből jövő változatát - az indoklás a parancs osztálydokjában
        // van (TODO 12.2). A csomag a saját ütemezését a providerből adja hozzá,
        // ami a Kernel::schedule() után fut, ezért onnan nem szűrhető ki.
        \App\Console\Commands\PackageAnonymizeInactiveUsers::class,
    ];

    /**
     * Define the application's command schedule.
     *
     * @param  \Illuminate\Console\Scheduling\Schedule  $schedule
     * @return void
     */
    protected function schedule(Schedule $schedule)
    {
        // A korábbi névtelen closure-ök helyett nevesített parancsok futnak.
        // Az ütemezés és a sorrend változatlan; a parancsok törzse az
        // app/Console/Commands könyvtárban él és egyenként tesztelhető.
        $schedule->command('queue:work --name=kozteruletek-job-1 --queue=default --max-time=25 --max-jobs=100 --sleep=3 --tries=3 --backoff=20')
                    ->everyMinute()
                    ->withoutOverlapping(1);

        $schedule->command('users:purge-unverified')->hourlyAt(50);

        $schedule->command('events:expire-pending')->everyFiveMinutes();

        $schedule->command('gdpr:anonymize-inactive')->dailyAt('7:00');

        $schedule->command('gdpr:notify-anonymization')->dailyAt('7:10');

        $schedule->command('maintenance:purge-log-history')->daily();

        $schedule->command('maintenance:daily-cleanup')->daily();

        $schedule->command('statistics:record-daily-users')->daily();

        $schedule->command('groups:apply-future-changes')->everyMinute();

        $schedule->command('newsletters:send-due')->everyMinute();

        $schedule->command('statistics:record-active-users')->hourly();

        $schedule->command('scheduler:heartbeat')->everyMinute();
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
