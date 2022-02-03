<?php

namespace App\Http\Livewire\Groups;

use App\Http\Livewire\AppComponent;
use App\Models\Group;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Lang;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
use App\Models\User;
use App\Notifications\FinishRegistration;
use App\Notifications\GroupUserAddedNotification;
use Illuminate\Support\Facades\URL;

class ListUsers extends AppComponent
{
    public $groupId;
    public $state;
    public $selected_user;
    public $searchTerm = null;
    //for search with an url string
    protected $queryString = ['searchTerm' => ['except' => '']];

    static $group_roles = [
        'member', 
        'helper',
        'roler',
        'admin',
    ];

    public $userIdBeeingRemoved = null;
    public $new_users = ""; 

    protected $listeners = [
        'deleteUser', 
        'openModal', 
        'edit', 
        'createUser'
    ];

    public function mount($group) {
        $this->groupId = $group;
    }

    public function openModal($modalId = 'UserModal') {
        // parent::openModal($modalId);
        $this->dispatchBrowserEvent('show-modal', ['id' => $modalId]);
    }

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

        $group = Group::findorFail($this->groupId);
        if($group->editors()->wherePivot('user_id', Auth::id())->count() == 0) {
            abort(403);
        }        

        $email_array = preg_split('/\r\n|[\r\n]/', trim($this->new_users));
        $email = [];
        if(count($email_array)) {
            foreach($email_array as $mail) {
                $email['email'][] = trim($mail);
            }
        }
        // dd($email_array);
        /*
            kdfgdfg@gmail.com
            dfgdfgdf@sdfd.com
            fgnfghidffgdf@free.hu
        */

        $validatedData = Validator::make($email, [
            'email.*' => 'required|email',
        ])->validate();

        // dd($validatedData);
        if(count($validatedData['email'])) {
            $current_users = $group->groupUsers()->pluck('users.id')->toArray();
            $data = [
                'groupAdmin' => auth()->user()->last_name.' '.auth()->user()->first_name, 
                'groupName' => $group->name
            ];
            $user_sync = [];
            foreach($validatedData['email'] as $mail) {
                $us = User::where('email',  $mail)->firstOr(function () use ($mail) {
                    $password = Str::random(10);
                    $u = User::create([
                        'email' => $mail,
                        'password' => bcrypt($password)
                    ]);
                    // dd($u);
                    $url = URL::temporarySignedRoute(
                        'finish_registration', now()->addMinutes(7 * 24 * 60 * 60), ['id' => $u->id]
                    );
                    $u->notify(
                        new FinishRegistration([
                            'groupAdmin' => auth()->user()->last_name.' '.auth()->user()->first_name, 
                            'userMail' => $mail,
                            'url' => $url
                        ])
                    );
                    return $u;
                });
                //skip user, if he is already in the group
                if(in_array($us->id, $current_users)) continue;

                $user_sync[$us->id] = [
                    'group_role' => 'member',
                    'note' => '',
                    'hidden' => 0,
                    'deleted_at' => null, //because maybe we try to reattach a removed user
                    'accepted_at' => null
                ];
            }
            
            $res = $group->groupUsersAll()->syncWithoutDetaching($user_sync);
            //az újakat értesítem, hogy hozzá lett adva a csoporthoz
            if(isset($res['attached'])) {
                foreach($res['attached'] as $user) {
                    $us = User::find($user);
                    if($us->email_verified_at) {
                        $us->notify(
                            new GroupUserAddedNotification($data)
                        );
                    }
                }             
            }
            if(isset($res['updated'])) {
                foreach($res['updated'] as $user) {
                    $us = User::find($user);
                    if($us->email_verified_at) {
                        $us->notify(
                            new GroupUserAddedNotification($data)
                        );
                    }
                }             
            }
            // dd($res);

            $this->dispatchBrowserEvent('hide-modal', [
                'id' => 'UserAddModal',
                'message' => __('group.user.add.success', ['number' => count($res['attached']) + count($res['updated'])]),
                'savedMessage' => __('app.saved')
            ]);
        }
        $this->new_users = "";       
        

    }

    public function edit($UserId) {
        // dd($UserId);
        $group = Group::findorFail($this->groupId);
        if($group->editors()->wherePivot('user_id', Auth::id())->count() == 0) {
            abort(403);
        }

        $user = $group->groupUsers()->where('user_id', '=', $UserId)->first();
        // dd($user);
        $this->selected_user = [
            'id' => $user->id,
            'full_name' => $user->full_name,
            'email' => $user->email,
        ];
        $this->state = [
            'group_role' => $user->pivot->group_role,
            'note' => $user->pivot->note,
            'hidden' => $user->pivot->hidden
        ];

        $this->dispatchBrowserEvent('show-modal', ['id' => 'UserModal']);
    }

    public function updateUser() {

        $group = Group::findorFail($this->groupId);

        if($group->editors()->wherePivot('user_id', Auth::id())->count() == 0) {
            abort(403);
        }        
        $admins = $group->groupAdmins()->get()->count();
        $current_user = $group->groupUsers()->where('user_id', Auth::id())->first();
        $selected_user = $group->groupUsers()->where('user_id', $this->selected_user['id'])->first();
        // dd($selected_user->toArray());

        $v = Validator::make($this->state, [
            'hidden' => 'required|boolean',
            'note' => 'nullable|string|max:50', 
            'group_role' => [
                'required',
                Rule::In(self::$group_roles),    //csak a megadott jogosultság adható ki
            ],
        ]);
        $v->after(function ($validator) use ($admins, $current_user, $selected_user) {
            //if modified user rule
            if($selected_user->pivot->group_role != $this->state['group_role']) {
                if ($admins <= 1 && $selected_user->pivot->group_role == 'admin') {
                    $validator->errors()->add(
                        'users', __('group.error_no_admin_user')
                    );
                }
                if($current_user->pivot->group_role != 'admin' &&  $selected_user->pivot->group_role == 'admin') {
                    $validator->errors()->add(
                        'users', __('group.error_no_right_to_remove_admin')
                    );
                }
                if($current_user->pivot->group_role != 'admin' && $this->state['group_role'] == 'admin') {
                    $validator->errors()->add(
                        'users', __('group.error_no_right')
                    );
                }
            }
        });
        
        $validatedData = $v->validate();
        $user_sync[$this->selected_user['id']] = $validatedData;

        $group->groupUsersAll()->syncWithoutDetaching($user_sync);

        $this->dispatchBrowserEvent('hide-modal', [
            'id' => 'UserModal',
            'message' => __('group.user.saved').' ('.$selected_user->full_name.')',
            'savedMessage' => __('app.saved')
        ]);
    }

    public function confirmUserRemoval($userId) {
        $this->userIdBeeingRemoved = $userId;

        $group = Group::findorFail($this->groupId);
        if($group->editors()->wherePivot('user_id', Auth::id())->count() == 0) {
            abort(403);
        } 

        $selected_user = $group->groupUsers()->where('user_id', $userId)->first();
        if(!$selected_user->id) abort(403);

        $this->dispatchBrowserEvent('show-deletion-confirmation', [
            'title' => __('group.user.confirmDelete.question', ['name' => $selected_user->full_name]),
            'text' => __('group.user.confirmDelete.message'),
            'emit' => 'deleteUser'
        ]);
    }

    public function deleteUser() {
        $group = Group::findorFail($this->groupId);
        if($group->editors()->wherePivot('user_id', Auth::id())->count() == 0) {
            abort(403);
        }
        if($group->groupUsersAll()->detach($this->userIdBeeingRemoved)) {
            $this->dispatchBrowserEvent('success', [
                'message' => __('group.user.confirmDelete.success')
            ]);
        } else {
            $this->dispatchBrowserEvent('error', [
                'message' => __('group.user.confirmDelete.error')
            ]);
        }
        $this->userIdBeeingRemoved = null;
    }

    public function updatedSearchTerm() {
        $this->resetPage();
    }

    public function clearSearch() {
        $this->searchTerm = null;
    }

    public function render()
    {
        // dd($this->groupId);
        $group = Group::findorFail($this->groupId);
        
        $users = $group->groupUsers()
                    ->where(function($query) {
                        $query->where('users.first_name', 'LIKE', '%'.$this->searchTerm.'%');
                        $query->orWhere('users.last_name', 'LIKE', '%'.$this->searchTerm.'%');
                        $query->orWhere('users.email', 'LIKE', '%'.$this->searchTerm.'%');
                    })
                    // ->get()
                    ->paginate(10);
        // dd($users->toArray());
        return view('livewire.groups.list-users', [
            'editor' => $group->editors()->wherePivot('user_id', Auth::id())->count(),
            'users' => $users,
            'group_name' => $group->name,
            'group_id' => $group->id,
            'group_roles' => self::$group_roles
        ]);
    }
}
