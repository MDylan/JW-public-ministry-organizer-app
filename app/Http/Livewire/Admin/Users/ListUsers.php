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
    public $searchTerm = null;
    //for search with an url string
    protected $queryString = ['searchTerm' => ['except' => '']];

    public $listeners = ['deleteUser'];

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
            'email' => 'required|email:filter|unique:users',
            'name' => 'required|string|max:50|min:2',
            'phone_number' => 'nullable|numeric|digits_between:9,11',
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
            'name' => 'required|string|max:50|min:2',
            'phone_number' => 'nullable|numeric|digits_between:9,11', 
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
        $selected_user = User::where('id', '=', $userId)->first();
        if(!$selected_user->id) abort(403);

        $this->userIdBeeingRemoved = $userId;
        $this->dispatchBrowserEvent('show-deletion-confirmation', [
            'title' => __('user.deleteUser'),
            'text' => __('user.areYouSureDelete', ['userName' => $selected_user->name]),
            'emit' => 'deleteUser'
        ]);
    }

    public function deleteUser() {        
        $user = User::findOrFail($this->userIdBeeingRemoved);
        $user->delete();
        //TODO: maybe delete events or check groups privileges
        $this->dispatchBrowserEvent('success', [
            'message' =>  __('user.userDeleted')
        ]);
    }

    public function updatedSearchTerm() {
        $this->resetPage();
    }

    public function clearSearch() {
        $this->searchTerm = null;
    }

    /**
     * Ez felel az oldal tartalmáért
     */
    public function render()
    {
        $users = User::query()
            ->where(function($query) {
                $query->where('users.name', 'LIKE', '%'.$this->searchTerm.'%');
                // $query->orWhere('users.last_name', 'LIKE', '%'.$this->searchTerm.'%');
                $query->orWhere('users.email', 'LIKE', '%'.$this->searchTerm.'%');
            })
            ->latest()->paginate(20);

        return view('livewire.admin.users.list-users', [
            'users' => $users,
            'roles' => [
                'registered',
                'activated',
                'groupCreator',
                'translator',
                'mainAdmin'
            ]
        ]);
    }
}
