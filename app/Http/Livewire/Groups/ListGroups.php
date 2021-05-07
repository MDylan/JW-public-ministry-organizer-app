<?php

namespace App\Http\Livewire\Groups;

use App\Http\Livewire\AppComponent;
use App\Models\Group;
use App\Models\User;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Validator;

class ListGroups extends AppComponent
{

    public $group;
    public $groupBeeingRemoved = null;

    public function confirmGroupRemoval($groupId) {
        $this->groupBeeingRemoved = $groupId;
        $this->dispatchBrowserEvent('show-delete-modal');
    }

    public function deleteGroup() {
        
        $user = User::findOrFail(Auth::id());
        foreach($user->userGroups as $group) {
            $group->whereId($this->groupBeeingRemoved)->delete();
        }
        $user->userGroups()->detach($this->groupBeeingRemoved);

        // $group = Group::findOrFail($this->groupBeeingRemoved);
        // $group->delete();

        $this->dispatchBrowserEvent('hide-delete-modal', ['message' => __('group.groupDeleted')]);
    }

    public function render()
    {
        
        $groups = Group::whereHas('groupUsers', function ($query) {
            return $query->where('users.id', '=', Auth::id());
        })->with('groupUsers')->paginate(20);


        // $groups = Group::with('currentList')->get();
        $user = User::findOrFail(Auth::id());
        $groups = $user->userGroups()->paginate();



        // foreach($user->userGroups as $u) {
        //     dd($u);
        // }

        // foreach($groups as $group) {
        //     dd($group->pivot);
        // }
        // // dd($groups);

        return view('livewire.groups.list-groups', [
            'groups' => $groups
        ]);
    }
}
