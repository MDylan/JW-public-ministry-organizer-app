<?php

namespace App\Jobs;

use App\Classes\GenerateStat;
use App\Models\Group;
use App\Models\GroupUser;
use App\Models\User;
use App\Notifications\EventDeletedNotification;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldBeUnique;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;

class UserLogoutFromGroupProcess implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    private $group;
    private $user;
    private $start;
    private $logoutUserName;

    /**
     * Create a new job instance.
     *
     * @return void
     */
    public function __construct(Group $group, User $user, $logoutUserName)
    {
        $this->group = $group;
        $this->user = $user;
        $this->start = date('Y-m-d H:i:s');
        $this->logoutUserName = $logoutUserName;
    }

    /**
     * Execute the job.
     *
     * @return void
     */
    public function handle()
    {
        $events = $this->user->events()
            ->where('start', '>=', $this->start)
            ->where('group_id', $this->group->id);
        if($events) {
            $days = [];
            $notifies = [];
            foreach($events->get() as $event) {
                if($event->status == 1) {
                    $days[] = $event->day;
                    $notifies[] =  [
                        'userName' => $this->logoutUserName,
                        'groupName' => $this->group->name,
                        'date' => $event->day,
                        'oldService' => [
                            'start' => date("Y-m-d H:i:s", $event->start),
                            'end' => date("Y-m-d H:i:s", $event->end),
                        ],
                        'reason' => 'user_logout'
                    ];
                }
            }
            $events->delete();
            foreach($days as $day) {
                $stat = new GenerateStat();
                $stat->generate($this->group->id, $day);
            }

            foreach($notifies as $notify) {
                $this->user->notify(
                    new EventDeletedNotification($notify)
                );
            }
        }
    }
}
