<?php

namespace App\Http\Livewire\Admin\Users;

use App\Models\User;
use Livewire\Component;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Str;

class ListUsers extends Component
{
    public $state = [];

    /**
     * Megjeleníti a modalt, amikor a gombra kattintunk
     */
    public function addNew() {
        $this->dispatchBrowserEvent('show-form');
    }

    /**
     * Elmenti a felhasználó adatait
     */
    public function createUser() {
        
        $validatedData = Validator::make($this->state, [
            'email' => 'required|email|unique:users'
        ])->validate();
        $validatedData['password'] = bcrypt(Str::random(10));

        User::create($validatedData);

        $this->dispatchBrowserEvent('hide-form');

        return redirect()->back();

        // dd($validatedData);
    }

    /**
     * Ez felel az oldal tartalmáért
     */
    public function render()
    {

        $users = User::latest()->paginate();

        return view('livewire.admin.users.list-users', [
            'users' => $users
        ]);
    }
}
