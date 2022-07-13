<?php

namespace App\Jobs;

use App\Models\DayStat;
use App\Models\Event;
use App\Models\Group;
use App\Models\GroupDate;
use App\Models\GroupDay;
use App\Models\GroupDayDisabledSlots;
use App\Models\GroupLiterature;
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
        GroupDayDisabledSlots::where('group_id', $this->groupId)->delete();
        GroupLiterature::where('group_id', $this->groupId)->delete();
        GroupNews::where('group_id', $this->groupId)->delete();
        GroupNewsUserLogs::where('group_id', $this->groupId)->delete();
        GroupPosters::where('group_id', $this->groupId)->delete();
        GroupSurvey::where('group_id', $this->groupId)->delete();
        
        GroupUser::withoutEvents(function () {
            $users = GroupUser::with('user')->where('group_id', $this->groupId)->get();
            foreach($users as $user) {
                if($this->deleteUsers !== false) {
                    $groups = $user->user->userGroups()->get(['groups.id'])->toArray();
                    if(count($groups) == 0) {
                        $user->user->anonymize();
                    }
                }
                $user->delete();
            }
        });
    }
}
