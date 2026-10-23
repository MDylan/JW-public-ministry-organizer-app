<?php

use Illuminate\Foundation\Inspiring;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Schedule;

/*
|--------------------------------------------------------------------------
| Console Routes
|--------------------------------------------------------------------------
|
| This file is where you may define all of your Closure based console
| commands. Each Closure is bound to a command instance allowing a
| simple approach to interacting with each command's IO methods.
|
*/

Artisan::command('inspire', function () {
    $this->comment(Inspiring::quote());
})->purpose('Display an inspiring quote');

/*
|--------------------------------------------------------------------------
| The Schedule
|--------------------------------------------------------------------------
|
| App\Console\Kernel::schedule() until roadmap TODO 56. The commands
| themselves are discovered from app/Console/Commands; this file is loaded by
| bootstrap/app.php's withRouting(commands: ...). SchedulerRegressionTest pins
| every entry below - command and cron expression.
|
*/

// Named commands run instead of the previous anonymous closures.
// The scheduling and order are unchanged; the commands' bodies live in the
// app/Console/Commands directory and are individually testable.
Schedule::command('queue:work --name=kozteruletek-job-1 --queue=default --max-time=25 --max-jobs=100 --sleep=3 --tries=3 --backoff=20')
            ->everyMinute()
            ->withoutOverlapping(1);

Schedule::command('users:purge-unverified')->hourlyAt(50);

Schedule::command('events:expire-pending')->everyFiveMinutes();

Schedule::command('gdpr:anonymize-inactive')->dailyAt('7:00');

Schedule::command('gdpr:notify-anonymization')->dailyAt('7:10');

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
Schedule::command('activitylog:clean --force')
            ->dailyAt('3:20')
            ->when(fn () => (bool) config('gdpr.enabled'));

Schedule::command('gdpr:purge-old-events')->dailyAt('3:30');

Schedule::command('maintenance:purge-old-group-data')->dailyAt('3:40');

Schedule::command('maintenance:purge-log-history')->daily();

Schedule::command('maintenance:daily-cleanup')->daily();

Schedule::command('statistics:record-daily-users')->daily();

Schedule::command('groups:apply-future-changes')->everyMinute();

Schedule::command('newsletters:send-due')->everyMinute();

Schedule::command('statistics:record-active-users')->hourly();

Schedule::command('scheduler:heartbeat')->everyMinute();

// Every three hours. The free OpenWeather tier is 1000 calls/day, and
// refreshing one city is 2 calls - this fits roughly 60 cities within
// the budget. The forecast itself has 3-hourly resolution, so a
// denser run wouldn't yield more information.
Schedule::command('weather:refresh')->cron('0 */3 * * *');
