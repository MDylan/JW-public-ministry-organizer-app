<?php

namespace App\Http\Livewire\Groups;

use App\Models\Group;
use Livewire\Component;

class ListUsers extends Component
{

    private $group;

    public function mount(Group $group) {
        $this->group = $group;        
    }

    public function render()
    {
        $users = $this->group->groupUsers()->get();
        return view('livewire.groups.list-users', [
            'users' => $users,
            'group_name' => $this->group->name
        ]);
    }
}
