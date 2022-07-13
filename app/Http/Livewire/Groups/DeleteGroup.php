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

    public function mount(Group $group) {
        $this->group = $group;
    }

    public function render()
    {
        return view('livewire.groups.delete-group');
    }

    public function deleteGroup() {
        $del = new GroupDelete();
        $del->index($this->group->id, $this->deleteUsers);
    }
}
