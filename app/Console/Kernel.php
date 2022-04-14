<?php

namespace App\Console;

use App\Models\Event;
use App\Models\LogHistory;
use App\Models\Settings;
use App\Models\User;
use App\Notifications\UserWillBeAnonymizeNotification;
use Carbon\Carbon;
use Illuminate\Console\Scheduling\Schedule;
use Illuminate\Foundation\Console\Kernel as ConsoleKernel;
use Illuminate\Support\Facades\Cache;

class Kernel extends ConsoleKernel
{
    /**
     * The Artisan commands provided by your application.
     *
     * @var array
     */
    protected $commands = [
        \Dialect\Gdpr\Commands\AnonymizeInactiveUsers::class,
    ];

    /**
     * Define the application's command schedule.
     *
     * @param  \Illuminate\Console\Scheduling\Schedule  $schedule
     * @return void
     */
    protected function schedule(Schedule $schedule)
    {
        $schedule->command('queue:work --name=kozteruletek-job-1 --queue=default --max-time=25 --max-jobs=100 --sleep=3 --tries=3 --backoff=20')
                    ->everyMinute()
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

        //anonymize inactive users
        $schedule->command('gdpr:anonymizeInactiveUsers')->dailyAt('7:00');

        $schedule->call(function () {
            //notify inactive users
            if (!config('gdpr.enabled')) {   
                return;                
            }
            $date = Carbon::now()->subMonths(config('gdpr.settings.ttl'));
            $date->addDays(15);
            $model = config('gdpr.settings.user_model_fqn', 'App\Models\User');
            $user = new $model();
            $anonymizableUsers = $user::where('last_activity', '!=', null)
                                ->where('isAnonymized', 0)
                                ->where('last_activity', '<=', $date)
                                ->get();
    
            foreach ($anonymizableUsers as $user) {
                $date = Carbon::parse($user->last_activity)->addMonths(config('gdpr.settings.ttl'));
                $data['lastDate'] = $date->format("Y-m-d");
                $user->notify(
                    new UserWillBeAnonymizeNotification($data)
                );
            }
        })->dailyAt('7:10');
        
        //delete old LogHistory data
        $schedule->call(function () {
            $date = Carbon::now()->subMonths(3);
            LogHistory::where('created_at', '<=', $date)->delete();
        })->daily();

        //delete old trashed events
        $schedule->call(function () {
            $date = Carbon::now()->subMonths(3);
            Event::onlyTrashed()
                ->where(
                    'day', '<=', $date
                )->forceDelete();
        })->daily();

        //store last schedule run
        $schedule->call(function () {
            Settings::updateOrInsert(
                [ 'name' => 'last_schedule_run' ],
                [ 'value' => now() ]
            );
        })->everyMinute();
        
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
