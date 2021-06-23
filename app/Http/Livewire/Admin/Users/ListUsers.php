<?php

namespace App\Http\Livewire\Admin\Users;

use App\Http\Livewire\AppComponent;
use App\Models\User;
use Illuminate\Support\Facades\Lang;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
use Illuminate\Auth\Events\Registered;


class ListUsers extends AppComponent
{

    public $user;
    public $userIdBeeingRemoved = null;

    /**
     * Megjeleníti a modalt, amikor a gombra kattintunk
     */
    public function addNew() {
        $this->showEditModal = false;
        $this->state = [];
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
            'phone' => 'nullable|numeric|digits_between:9,11',
            'role' => [
                'required',
                Rule::notIn(Lang::get('roles')),    //csak a megadott jogosultság adható ki
            ],
        ])->validate();

        $validatedData['password'] = bcrypt(Str::random(10));

        $user = User::create($validatedData);

        $this->dispatchBrowserEvent('hide-form', ['message' => __('user.userSaved')]);
        // event(new Registered($user));

    }

    public function edit(User $user) {

        $this->showEditModal = true;
        $this->state = $user->toArray();
        $this->user = $user;

        $this->dispatchBrowserEvent('show-form');
    }

    public function updateUser() {

        $validatedData = Validator::make($this->state, [
            'email' => 'required|email|unique:users,email,'.$this->user->id,
            'first_name' => 'required|string|max:50|min:2',
            'last_name' => 'required|string|max:50|min:2',
            'phone' => 'nullable|numeric|digits_between:9,11', 
            'role' => [
                'required',
                Rule::notIn(Lang::get('roles')),    //csak a megadott jogosultság adható ki
            ],
        ])->validate();

            // dd('ok');

        $this->user->update($validatedData);

        // $user = User::create($validatedData);

        $this->dispatchBrowserEvent('hide-form', ['message' => __('user.userSaved')]);

    }

    public function confirmUserRemoval($userId) {
        $this->userIdBeeingRemoved = $userId;
        $this->dispatchBrowserEvent('show-delete-modal');
    }

    public function deleteUser() {
        
        $user = User::findOrFail($this->userIdBeeingRemoved);
        $user->delete();
        $this->dispatchBrowserEvent('hide-delete-modal', ['message' => __('user.userDeleted')]);
    }

    /**
     * Ez felel az oldal tartalmáért
     */
    public function render()
    {

        $users = User::latest()->paginate(20);

        return view('livewire.admin.users.list-users', [
            'users' => $users,
            'userFields' => is_array(trans('user.nameFields')) ? trans('user.nameFields') : ['first_name', 'last_name'],
            'roles' => [
                'registered',
                'activated',
                'groupMember',
                'groupCreator',
                'mainAdmin'
            ]
        ]);
    }
}
