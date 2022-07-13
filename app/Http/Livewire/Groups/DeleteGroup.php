<?php

namespace App\Http\Livewire\Groups;

use App\Http\Controllers\GroupDelete;
use App\Models\Group;
use App\Models\GroupUser;
use Livewire\Component;

class DeleteGroup extends Component
{

    public $group;
    public $deleteUsers = false;
    private $groupId;

    public function mount(Group $group) {
        $this->group = $group;
    }

    public function render()
    {
        return view('livewire.groups.delete-group');
    }

    public function deleteGroup() {
        // $this->groupId = $this->group->id;
        // $users = GroupUser::with('user')->where('group_id', $this->groupId)->get();
        // dump($users->toArray());
        // foreach($users as $user) {
        //     $groups = $user->user->userGroups()->get(['groups.id'])->toArray();
        //     dump($groups);
        //     if(count($groups) == 0) {
        //         $user->user->anonymize();
        //     }
        // }

        // dd('na', $this->group->id, $this->deleteUsers);
        $del = new GroupDelete();
        $del->index($this->group->id, $this->deleteUsers);

    }
}
