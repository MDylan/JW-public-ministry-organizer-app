<?php

namespace App\Http\Livewire\Groups;

use App\Http\Livewire\Modal\Modal;

// use Livewire\Component;

class UsersEdit extends Modal
{
    public function render()
    {
        dd($this->components);
        // $this->dispatchBrowserEvent('show-modal', ['id' => $this->activeComponent]);
        return view('livewire.groups.users-edit');
    }
}
