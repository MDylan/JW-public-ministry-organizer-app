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
                    // A feltétel korábban `count($user->user->userGroups) == 0`
                    // volt, ami CSAK akkor teljesült, ha a csoport a job
                    // futásakor MÁR soft-deleted: a userGroups reláció a groups
                    // táblához joinol, és az onnan kiesett sor nem számított
                    // bele. Az éles GroupDelete útvonal előbb töröl, aztán
                    // dispatch-el, ezért működött - de a jobot élő csoportra
                    // hívva az anonimizálás NÉMÁN kimaradt.
                    //
                    // Most explicit: a saját csoportot zárjuk ki a számlálásból,
                    // tehát az eredmény független attól, mikor törlik a
                    // csoportot magát.
                    $otherGroups = $user->user->userGroups
                        ->filter(function ($group) {
                            return (int) $group->id !== (int) $this->groupId;
                        })
                        ->count();

                    if($otherGroups === 0) {
                        // A User::anonymize() maga is elutasíthatja a kérést,
                        // ha az utódlási szabály nem teljesül (TODO 12.2).
                        $user->user->anonymize();
                    }
                }

                // A null-ellenőrzés SORRENDJE is hibás volt: a régi feltétel
                // előbb olvasta a $user->user->userGroups-ot, és csak utána
                // vizsgálta, hogy $user->user egyáltalán létezik-e.
                $user->delete();
            }
        });
    }
}
