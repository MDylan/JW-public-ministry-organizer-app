<?php

namespace App\Classes;

use App\Jobs\UserLogoutFromGroupProcess;
use App\Models\Group;
use App\Models\GroupUser;
use App\Models\User;
use App\Notifications\GroupUserAddedNotification;
use App\Notifications\GroupUserLogoutNotification;

class GroupUserMoves {

    private $group;
    private $user;

    public function __construct($groupId, $userId) {
        $this->user = User::find($userId);
        if($this->user === null) return;
        $this->setGroup($groupId);
    }

    public function setGroup($groupId) {
        $this->group = Group::findorFail($groupId);
    }

    //attach a user to the group
    public function attach() {
        if($this->user === null) return;
        $user_sync[$this->user->id] = [
            'group_role' => 'member',
            'note' => '',
            'hidden' => 0,
            'deleted_at' => null, //because maybe we try to reattach a removed user
            'accepted_at' => null
        ];

        $data = [
            'groupAdmin' => auth()->user()->name, 
            'groupName' => $this->group->name
        ];
        $res = $this->group->groupUsersAll()->syncWithoutDetaching($user_sync);
        //az újakat értesítem, hogy hozzá lett adva a csoporthoz
        if(isset($res['attached'])) {
            foreach($res['attached'] as $user) {
                if($this->user->email_verified_at) {
                    $this->user->notify(
                        new GroupUserAddedNotification($data)
                    );
                }
            }             
        }
        if(isset($res['updated'])) {
            foreach($res['updated'] as $user) {
                if($this->user->email_verified_at) {
                    $this->user->notify(
                        new GroupUserAddedNotification($data)
                    );
                }
            }             
        }

        //add to child groups also
        $childs = $this->group->childGroups();
        if($childs->count()) {
            $addToGroups = [];
            $addToGroups = $childs->pluck('id')->toArray();
            foreach($addToGroups as $groupId) {
                $this->setGroup($groupId);
                $this->attach();
            }
        }
    }

    /**
     * Accepts the user's PENDING invitation to the group (and to its child
     * groups). Returns whether there was one.
     *
     * Roadmap TODO 70: this used to sync() the pivot, which ATTACHES a row
     * when there is none - so accept(<any group id>) from the groups page
     * made anyone an accepted member of any group, no invitation needed.
     * It now only updates an invitation row that exists and is still
     * pending; an accepted or removed membership is left alone.
     */
    public function acceptInvitation() {
        if($this->user === null) return false;
        $res = $this->pendingInvitation()->update(['accepted_at' => now()]) > 0;

        // Only an invitation that was really open carries on into the
        // child groups.
        $childs = $this->group->childGroups();
        if($res && $childs->count()) {
            $acceptGroups = [];
            $acceptGroups = $childs->pluck('id')->toArray();
            foreach($acceptGroups as $groupId) {
                $this->setGroup($groupId);
                $this->acceptInvitation();
            }
        }
        return $res;
    }


    /**
     * Rejects the user's PENDING invitation (and those to the child groups).
     * Returns whether there was one.
     *
     * Roadmap TODO 70: this used to write a removed row whatever the
     * membership's state, so "rejecting" an ACCEPTED membership was a way to
     * leave a group past the last-admin check and the password confirmation
     * of the groups.logout route. Leaving is that route's job; this only
     * withdraws an invitation that is still open.
     */
    public function rejectInvitation() {
        if($this->user === null) return false;
        $res = $this->pendingInvitation()->update([
            'group_role' => 'member',
            'hidden' => 0,
            'deleted_at' => now(),
        ]) > 0;

        // Only an invitation that was really open carries on into the
        // child groups.
        $childs = $this->group->childGroups();
        if($res && $childs->count()) {
            $rejectGroups = [];
            $rejectGroups = $childs->pluck('id')->toArray();
            foreach($rejectGroups as $groupId) {
                $this->setGroup($groupId);
                $this->rejectInvitation();
            }
        }
        return $res;
    }

    private function pendingInvitation()
    {
        return GroupUser::query()
            ->where('group_id', $this->group->id)
            ->where('user_id', $this->user->id)
            ->whereNull('accepted_at');
    }

    //run when a user removed from group
    public function detach() {
        if($this->user === null) return;
        $user_sync[$this->user->id] = [
            'group_role' => 'member',
            'note' => '',
            'hidden' => 0,
            'deleted_at' => now(),
            'accepted_at' => null
        ];
        $this->group->groupUsersAll()->syncWithoutDetaching($user_sync);

        UserLogoutFromGroupProcess::dispatch($this->group, $this->user, auth()->user()->name);

        if(!$this->user->isAnonymized) {
            $this->user->notify(
                new GroupUserLogoutNotification([
                    'groupName' => $this->group->name, 
                    'userName' => auth()->user()->name
                ])
            );
        }
        
        // A child group has no children of its own; the exit itself is done,
        // so this is a success (it used to return null, and GroupLogout
        // reported an error for a completed exit from a child group).
        if($this->group->parent_group_id > 0) return true;

        //remove from child groups also
        $childs = $this->group->childGroups();
        if($childs->count()) {
            $removeFromGroups = [];
            $removeFromGroups = $childs->pluck('id')->toArray();
            foreach($removeFromGroups as $groupId) {
                $this->setGroup($groupId);
                $this->detach();
            }
        }

        return true;
    }
}