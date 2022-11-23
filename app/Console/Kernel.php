<?php

namespace App\Console;

use App\Classes\updateGroupFutureChanges;
use App\Models\AdminNewsletter;
use App\Models\Event;
use App\Models\GroupFutureChange;
use App\Models\GroupMessage;
use App\Models\LogHistory;
use App\Models\Settings;
use App\Models\User;
use App\Notifications\Newsletter;
use App\Notifications\UserWillBeAnonymizeNotification;
use App\Notifications\UserWillBeAnyonimizeAdminNotification;
use Carbon\Carbon;
use Illuminate\Console\Scheduling\Schedule;
use Illuminate\Foundation\Console\Kernel as ConsoleKernel;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Crypt;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Notification;

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

            //notify users admins from future anonymization
            $editors_list = [];
            $submonths = config('gdpr.settings.ttl');
            $minDate = Carbon::now()->subMonths($submonths);
            $minDate->addDays(7);

            $maxDate = Carbon::now()->subMonths($submonths);
            $maxDate->addDays(6);
            $anonymizableUsers = DB::table('users AS U')
                            ->join('group_user AS GU', function($join) {
                                $join->on('U.id', '=', 'GU.user_id')
                                    ->whereNotNull('GU.accepted_at')
                                    ->whereNull('GU.deleted_at');
                            })
                            ->join('groups AS G', function($join) {
                                $join->on('GU.group_id', '=', 'G.id')
                                    ->whereNull('G.parent_group_id');
                            })
                            ->join('group_user as ADMIN', function($join) {
                                $join->on('ADMIN.group_id', '=', 'GU.group_id')
                                    ->whereNotNull('ADMIN.accepted_at')
                                    ->whereNull('ADMIN.deleted_at')
                                    ->whereIn('ADMIN.group_role',['roler', 'admin']);
                            })
                            ->select('U.id', 'U.email', 'U.name', 'U.last_activity', 'G.id as group_id', 'G.name as group_name', 'ADMIN.user_id as admin_id')
                                ->where('U.last_activity', '!=', null)
                                ->where('U.isAnonymized', 0)
                                ->whereBetween('U.last_activity', [$minDate->format("Y-m-d"), $maxDate->format("Y-m-d")])
                                // ->where('U.last_activity', '<=', $minDate->format("Y-m-d"))
                            ->get();
            foreach ($anonymizableUsers as $user) {
                $date = Carbon::parse($user->last_activity)->addMonths($submonths);
                $data['lastDate'] = $date->format("Y-m-d");

                $editors_list[$user->group_id]['admins'][$user->admin_id] = $user->admin_id;
                $editors_list[$user->group_id]['users'][$user->id] = [
                    'lastDate' => $data['lastDate'],
                    'last_activity' => $user->last_activity,
                    'name' => Crypt::decryptString($user->name)
                ];
                $editors_list[$user->group_id]['name'] = Crypt::decryptString($user->group_name);
            }

            if(count($editors_list)) {
                foreach($editors_list as $list) {
                    $admins = User::whereIn('id', $list['admins'])
                        ->get();

                    Notification::send($admins, new UserWillBeAnyonimizeAdminNotification($list));
                }
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

            //delete old group messages
            GroupMessage::where('created_at', '<', now()->subDays(7))->delete();
        })->daily();

        $schedule->call(function () {
            //check group's future changes
            $changes = GroupFutureChange::where('change_date', '=', date("Y-m-d"))->get();
            foreach($changes as $change) {
                $init = new updateGroupFutureChanges();
                $init->initChanges($change->group_id);
            }

            $newsletters = AdminNewsletter::where('date', today())
                ->where('send_newsletter', 1)
                ->where('status', 1)
                ->whereNull('sent_time')
                ->get();
            foreach($newsletters as $newsletter) {
                if($newsletter->send_to == 'groupCreators') {
                    $users = User::whereIn('role', ['groupCreator', 'mainAdmin'])->get();
                } elseif($newsletter->send_to == 'groupServants') {
                    $users = User::whereHas('userGroupsEditable')->get();
                } else {
                    return;
                }
                foreach($users as $user) {
                    $data = [
                    'newsletter_id' => $newsletter->id."_".$user->id,
                    'subject' => $newsletter->getTranslation($user->preferredLocale())->subject,
                    'content' => $newsletter->getTranslation($user->preferredLocale())->content,
                    ];
                    $user->notify(
                        new Newsletter($data)
                    );
                }
                $newsletter->sent_time = date("Y-m-d H:i:s");
                $newsletter->save();
            }
        })->everyMinute();

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
