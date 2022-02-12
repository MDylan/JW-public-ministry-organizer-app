<?php

namespace App\Console;

use App\Models\Event;
use App\Models\User;
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
        // $schedule->command('inspire')->hourly();
        $schedule->command('queue:work --name=kozteruletek-job-1 --queue=default --max-time=25 --max-jobs=100 --sleep=3 --tries=3 --backoff=20')
                    ->everyMinute()
                    // ->runInBackground()
                    ->withoutOverlapping(1);
        
        $schedule->call(function () {
            //delete users who not verify their emails more then one week
            User::whereNull('email_verified_at')
                        ->where('created_at', '<', date("Y-m-d H:i:s", strtotime("-1 week")))->delete();
            
        })->hourlyAt(50);

        $schedule->call(function () {            
            //set event's statuses to deleted if not accepted in time
            Event::where('status', '=', '0')
                    ->where('start', '<=', date("Y-m-d H:i:s"))
                    ->update(['status' => 2]);
            
        })->everyFiveMinutes();
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
