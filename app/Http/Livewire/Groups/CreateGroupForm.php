<?php

namespace App\Http\Livewire\Groups;

use App\Http\Livewire\AppComponent;
use App\Models\User;
use App\Models\Group;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Validator;

class CreateGroupForm extends AppComponent
{

    /**
     * Elmenti a felhasználó adatait
     */
    public function createGroup() {
        
        // dd($this->state);

        $validatedData = Validator::make($this->state, [
            'name' => 'required|string|max:50|min:2',
        ])->validate();

        $user = User::find(Auth::id());
        $user->userGroups()->save(Group::create($validatedData), ['group_role' => 'admin']);

        $this->dispatchBrowserEvent('alert', ['message' => __('group.groupCreated')]);

    }

    public function render()
    {
        return view('livewire.groups.create-group-form');
    }
}
