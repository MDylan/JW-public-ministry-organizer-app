<?php

namespace App\Http\Livewire\Admin\Users;

use App\Models\User;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Lang;
use Livewire\Component;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
use Illuminate\Auth\Events\Registered;

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
            'email' => 'required|email|unique:users',
            'first_name' => 'required|string|max:50|min:2',
            'last_name' => 'required|string|max:50|min:2',
            'phone' => 'numeric',
            'role' => [
                'required',
                Rule::notIn(Lang::get('roles')),    //csak a megadott jogosultság adható ki
            ],
        ])->validate();

        $validatedData['password'] = bcrypt(Str::random(10));

        $user = User::create($validatedData);

        $this->dispatchBrowserEvent('hide-form', ['message' => __('app.saved')]);
        event(new Registered($user));

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
