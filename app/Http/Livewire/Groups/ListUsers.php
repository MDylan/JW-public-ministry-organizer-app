<?php

namespace App\Http\Livewire\Groups;

use App\Models\Group;
use Illuminate\Support\Facades\Auth;
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
        // dd($users->toArray());
        return view('livewire.groups.list-users', [
            'editor' => $this->group->editors()->wherePivot('user_id', Auth::id())->count(),
            'users' => $users,
            'group_name' => $this->group->name
        ]);
    }
}
