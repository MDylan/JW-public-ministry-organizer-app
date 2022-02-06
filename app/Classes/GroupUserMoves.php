<?php

namespace App\Classes;

use App\Jobs\UserLogoutFromGroupProcess;
use App\Models\Group;
use App\Models\User;
use App\Notifications\GroupUserAddedNotification;
use App\Notifications\GroupUserLogoutNotification;

class GroupUserMoves {

    private $group;
    private $user;

    public function __construct($groupId, $userId) {
        $this->user = User::findOrFail($userId);
        $this->setGroup($groupId);
    }

    public function setGroup($groupId) {
        $this->group = Group::findorFail($groupId);
    }

    //attach a user to the group
    public function attach() {

        $user_sync[$this->user->id] = [
            'group_role' => 'member',
            'note' => '',
            'hidden' => 0,
            'deleted_at' => null, //because maybe we try to reattach a removed user
            'accepted_at' => null
        ];

        $data = [
            'groupAdmin' => auth()->user()->last_name.' '.auth()->user()->first_name, 
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

    //Run when the user accept invitation
    public function acceptInvitation() {
        $res = $this->user->userGroups()->sync([$this->group->id => [ 'accepted_at' => date('Y-m-d H:i:s')] ], false);

        $childs = $this->group->childGroups();
        if($childs->count()) {
            $acceptGroups = [];
            $acceptGroups = $childs->pluck('id')->toArray();
            foreach($acceptGroups as $groupId) {
                $this->setGroup($groupId);
                $this->acceptInvitation();
            }
        }
        return $res;
    }


    //run when the user reject invitation
    public function rejectInvitation() {
        $user_sync[$this->user->id] = [
            'group_role' => 'member',
            'note' => '',
            'hidden' => 0,
            'deleted_at' => now(),
            'accepted_at' => null
        ];
        $res = $this->group->groupUsersAll()->syncWithoutDetaching($user_sync);
        // $res = $this->group->groupUsers()->detach($this->user->id);

        $childs = $this->group->childGroups();
        if($childs->count()) {
            $rejectGroups = [];
            $rejectGroups = $childs->pluck('id')->toArray();
            foreach($rejectGroups as $groupId) {
                $this->setGroup($groupId);
                $this->rejectInvitation();
            }
        }
        return $res;
    }

    //run when a user removed from group
    public function detach() {
        $user_sync[$this->user->id] = [
            'group_role' => 'member',
            'note' => '',
            'hidden' => 0,
            'deleted_at' => now(),
            'accepted_at' => null
        ];
        $this->group->groupUsersAll()->syncWithoutDetaching($user_sync);

        UserLogoutFromGroupProcess::dispatch($this->group, $this->user, auth()->user()->full_name);

        $this->user->notify(
            new GroupUserLogoutNotification([
                'groupName' => $this->group->name, 
                'userName' => auth()->user()->full_name
            ])
        );
        
        if($this->group->parent_group_id > 0) return;

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