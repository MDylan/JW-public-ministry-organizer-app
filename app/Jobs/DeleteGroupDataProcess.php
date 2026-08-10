<?php

namespace App\Jobs;

use App\Models\DayStat;
use App\Models\Event;
use App\Models\Group;
use App\Models\GroupDate;
use App\Models\GroupDay;
use App\Models\GroupDayDisabledSlots;
use App\Models\GroupFutureChange;
use App\Models\GroupLiterature;
use App\Models\GroupMessage;
use App\Models\GroupNews;
use App\Models\GroupNewsFile;
use App\Models\GroupNewsUserLogs;
use App\Models\GroupPosters;
use App\Models\GroupSurvey;
use App\Models\GroupUser;
use App\Models\User;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldBeUnique;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;

class DeleteGroupDataProcess implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public $groupId;
    public $deleteUsers = false;

    /**
     * Create a new job instance.
     *
     * @return void
     */
    public function __construct($groupId, $deleteUsers)
    {
        $this->groupId = $groupId;
        $this->deleteUsers = $deleteUsers;
    }

    /**
     * Execute the job.
     *
     * @return void
     */
    public function handle()
    {
        //delete group's data
        Event::where('group_id', $this->groupId)->delete();
        GroupDate::where('group_id', $this->groupId)->delete();
        GroupDay::where('group_id', $this->groupId)->delete();
        DayStat::where('group_id', $this->groupId)->delete();
        GroupDayDisabledSlots::where('group_id', $this->groupId)->delete();
        GroupLiterature::where('group_id', $this->groupId)->delete();
        GroupNews::where('group_id', $this->groupId)->delete();
        GroupNewsUserLogs::where('group_id', $this->groupId)->delete();
        GroupPosters::where('group_id', $this->groupId)->delete();
        GroupSurvey::where('group_id', $this->groupId)->delete();
        GroupMessage::where('group_id', $this->groupId)->delete();
        GroupFutureChange::where('group_id', $this->groupId)->delete();
        
        GroupUser::withoutEvents(function () {
            $users = GroupUser::with('user', 'user.userGroups')->where('group_id', $this->groupId)->get();
            foreach($users as $user) {
                if($this->deleteUsers !== false && $user->user !== null) {
                    // The condition used to be `count($user->user->userGroups) == 0`,
                    // which was ONLY true if the group was ALREADY soft-deleted
                    // by the time the job ran: the userGroups relation joins
                    // to the groups table, and a row that had dropped out of
                    // there didn't count. The live GroupDelete path deletes
                    // first and dispatches afterward, so it worked there -
                    // but calling the job on a still-live group made the
                    // anonymization SILENTLY not happen.
                    //
                    // Now it's explicit: we exclude the group itself from the
                    // count, so the result no longer depends on when the
                    // group itself gets deleted.
                    $otherGroups = $user->user->userGroups
                        ->filter(function ($group) {
                            return (int) $group->id !== (int) $this->groupId;
                        })
                        ->count();

                    if($otherGroups === 0) {
                        // User::anonymize() can itself reject the request if
                        // the succession rule isn't satisfied (TODO 12.2).
                        $user->user->anonymize();
                    }
                }

                // The ORDER of the null check was also wrong: the old
                // condition read $user->user->userGroups first, and only
                // afterward checked whether $user->user even exists.
                $user->delete();
            }
        });
    }
}
