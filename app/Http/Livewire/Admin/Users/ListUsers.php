<?php

namespace App\Http\Livewire\Admin\Users;

use App\Helpers\CollectionHelper;
use App\Http\Livewire\AppComponent;
use App\Models\User;
use Illuminate\Support\Facades\Lang;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;


class ListUsers extends AppComponent
{

    public $user;
    public $userIdBeeingRemoved = null;
    public $searchTerm = null;
    //for search with an url string
    protected $queryString = ['searchTerm' => ['except' => '']];

    public $listeners = ['deleteUser'];

    /**
     * Show Modal
     */
    public function addNew() {
        $this->showEditModal = false;
        $this->state = [];
        $this->dispatchBrowserEvent('show-modal', [
            'id' => 'form',
        ]);
    }

    /**
     * Create new user
     */
    public function createUser() {
        if(!isset($this->state['role'])) {
            $this->state['role'] = 'registered';
        }
        $validatedData = Validator::make($this->state, [
            'email' => 'required|email:filter|unique:users',
            'name' => 'required|string|max:50|min:2',
            'phone_number' => 'nullable|numeric',
            'role' => [
                'required',
                Rule::notIn(Lang::get('roles')),
            ],
        ])->validate();

        $validatedData['password'] = bcrypt(Str::random(10));

        User::create($validatedData);
        
        $this->dispatchBrowserEvent('hide-modal', [
            'id' => 'form',
            'message' => __('user.userSaved'),
            'savedMessage' => __('app.saved')
        ]);

    }

    public function edit(User $user) {

        $this->showEditModal = true;
        $this->state = $user->toArray();
        $this->user = $user;
        $this->dispatchBrowserEvent('show-modal', [
            'id' => 'form',
        ]);
    }

    public function updateUser() {

        $validatedData = Validator::make($this->state, [
            'email' => 'required|email|unique:users,email,'.$this->user->id,
            'name' => 'required|string|max:50|min:2',
            'phone_number' => 'nullable|numeric', 
            'role' => [
                'required',
                Rule::notIn(Lang::get('roles')),
            ],
        ])->validate();

        $this->user->update($validatedData);
        $this->dispatchBrowserEvent('hide-modal', [
            'id' => 'form',
            'message' => __('user.userSaved'),
            'savedMessage' => __('app.saved')
        ]);
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

    public function render()
    {
        $users = User::query()
            ->where(function($query) {
                if(strlen(trim($this->searchTerm)) == 0) {
                    $query->whereIn('users.role', ['mainAdmin', 'translator', 'groupCreator']);
                }  else {
                    $query->where('users.email', 'LIKE', '%'.$this->searchTerm.'%');
                }
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
