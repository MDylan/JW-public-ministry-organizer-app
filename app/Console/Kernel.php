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

        // A retenciós takarítás hajnali 3 után fut, nem a 00:00-s torlódásban
        // (purge-log-history, daily-cleanup, record-daily-users mind ott van).
        // Az események előbb, a belőlük származtatott csoportadatok utána.

        // A Spatie parancsa eddig SOHA nem futott, pedig a
        // config/activitylog.php 90 napos retenciót deklarál - a beállítás
        // ezért négy éve nem lépett életbe (élesben a 7739 sorból 7581 már
        // túl van a 90 napon).
        //
        // A --force nem elhagyható: a CleanActivitylogCommand a
        // ConfirmableTrait::confirmToProceed()-del indul, ami production
        // környezetben megerősítést kér. Ütemezőből futva nincs TTY, a
        // confirm() a false alapértéket adja, a parancs kiírja, hogy
        // "Command Cancelled!", 1-gyel kilép és SEMMIT nem töröl - némán,
        // mert a scheduler kilépőkódját semmi nem jelzi ki.
        //
        // Az őr itt when()-ben van, nem a parancs törzsében: a vendor
        // parancsba nem tudunk beleírni. Kézzel indítva ezért megkerülhető -
        // az viszont explicit üzemeltetői művelet.
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

        // Háromóránként. Az ingyenes OpenWeather szint 1000 hívás/nap, egy
        // település frissítése 2 hívás - ez így nagyjából 60 települést bír el
        // a kereten belül. Az előrejelzés maga is 3 óránkénti felbontású, tehát
        // sűrűbb futás nem adna több információt.
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
