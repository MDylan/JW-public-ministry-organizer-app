<?php

namespace App\Http\Livewire\Groups;

use App\Classes\GenerateStat;
use App\Http\Livewire\AppComponent;
use App\Models\User;
use App\Models\Group;
use Illuminate\Support\Str;
use App\Models\GroupDay;
use App\Models\GroupLiterature;
use App\Notifications\GroupUserAddedNotification;
use App\Notifications\LoginData;
use DateTime;
use Illuminate\Support\Facades\Auth;
// use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Facades\Session;

class UpdateGroupForm extends AppComponent
{

    public $users = [];
    public $users_old = []; //eredeti userek
    public $search = "";
    public $group;
    public $days = [];
    public $admins = [];
    public $literatures = [];
    public $editedLiteratureType = null;
    public $editedLiteratureId = null;
    public $editedLiteratureRemove = [];

    public $listeners = ['literatureDeleteConfirmed'];

    public function mount(Group $group) {
        // dd($group->days);

        $this->state = $group->toArray();
        $days = [];
        // $collection = new Collection();
        foreach($group->days as $day) {
            // dd($day);
            // $collection->push((object) [
            //     'day_number' => $day->day_number,
            //     'start_time' => $day->start_time,
            //     'end_time' => $day->end_time,
            // ]);
            $days[$day->day_number] = [
                'day_number' => $day->day_number,
                'start_time' => $day->start_time,
                'end_time' => $day->end_time,
            ];
        }
        $this->days = $days;
        $literatures = $group->literatures;
        if(count($literatures)) {
            foreach($literatures as $literature) {
                $this->literatures['current'][$literature->id] = $literature->name;
            }
        }
        
        // dd($days);
        // dd($this->state['days'], $group->days, $collection);
        if($group->groupUsers) {
            foreach($group->groupUsers as $user) {
                $slug = Str::slug($user->email, '-');
                $this->users[$slug] = [
                    'email' => $user->email,
                    'group_role' => $user->pivot->group_role,
                    'note' => $user->pivot->note,
                    'user_id' => $user->id,
                    'full_name' => $user->full_name,
                    'hidden' => $user->pivot->hidden == 1 ? true : false
                ];
                if($user->pivot->group_role == 'admin') {
                    $this->admins[$slug] = true;
                }
            }
        }

        $this->users_old = $this->users;
        
        $this->group = $group;
    }

    /**
     * Hozzáadja a usert a listához
     */
    public function userAdd() {

        $email_array = preg_split('/\r\n|[\r\n]/', trim($this->search));

        $email = [];
        if(count($email_array)) {
            foreach($email_array as $mail) {
                $email['email'][] = $mail;
            }
        }


        // dd($email_array);

        $validatedData = Validator::make($email, [
            'email.*' => 'required|email',
        ])->validate();

        // dd($validatedData);
        if(count($validatedData['email'])) {
            foreach($validatedData['email'] as $mail) {
                $slug = Str::slug($mail, '-');
                if(isset($this->users[$slug])) continue;
                $this->users[$slug] = [
                    'email' => $mail,
                    'group_role' => 'member',
                    'note' => '',
                    'user_id' => false,
                    'full_name' => '?',
                    'hidden' => false
                ];
            }
        }
        $this->search = "";
    }

    /**
     * Törli a listából a usert
     */
    public function removeUser($email) {
        $admins = 0;
        foreach($this->users as $slug => $user) {
            if($slug == $email) continue;
            if($user['group_role'] == "admin") $admins++;
        }
        if($admins == 0) {
            //no other admin, can't remove this user
            $this->dispatchBrowserEvent('sweet-error', [
                'title' => __('group.logout.error'),
                'message' => __('group.logout.no_other_admin'),
            ]);
        } elseif($this->users[$email]['user_id'] == Auth::id()) {
            $this->dispatchBrowserEvent('sweet-error', [
                'title' => __('group.logout.error'),
                'message' => __('group.logout.self_delete_error'),
            ]);
        } else {
            unset($this->users[$email]);
        }
    }

    /**
     * Elmenti a csoport adatait
     */
    public function updateGroup() {
        // dd($this->users);

        $this->state['name'] = strip_tags($this->state['name']);

        $admins = 0;
        $current_admins = [];
        foreach($this->users as $slug => $user) {
            if($user['group_role'] == "admin") {
                $admins++;
                $current_admins[$slug] = true;
            }
        }

        $v = Validator::make($this->state, [
            'name' => 'required|string|max:50|min:2',
            'max_extend_days' => 'required|numeric|digits_between:1,365',
            'min_publishers' => 'required|numeric|digits_between:1,365|lte:max_publishers',
            'max_publishers' => 'required|numeric|digits_between:1,365|gte:min_publishers',
            'min_time' => 'required|numeric|in:30,60,120|lte:max_time',
            'max_time' => 'required|numeric|in:60,120,180,240,320|gte:min_time',
            'days.*.start_time' => 'required|date_format:H:i|before:days.*.end_time',
            'days.*.end_time' => 'required|date_format:H:i|after:days.*.start_time',
            'days.*.day_number' => 'required',
        ]);

        $v->after(function ($validator) use ($admins, $current_admins) {
            if ($admins == 0) {
                $validator->errors()->add(
                    'users', __('group.error_no_admin_user')
                );
            }
            $current_user = $this->group->groupUsers()->where('user_id', Auth::id())->first(); //->toArray();

            if($current_admins != $this->admins && $current_user->pivot->group_role != 'admin') {
                $validator->errors()->add(
                    'users', __('group.error_no_right')
                );
            }
        });
        $validatedData = $v->validate();

        $validatedDays = Validator::make($this->days, [
            '*.day_number' => 'required',
            '*.start_time' => 'required|date_format:H:i|before:*.end_time',
            '*.end_time' => 'required|date_format:H:i|after:*.start_time',
            // '*.day_number' => 'required',
        ])->validate();


        // dd($validatedDays);

        $user = Auth()->user(); // User::find(Auth::id());
        // $group = Group::findOrFail($this->group_id);
        $this->group->update($validatedData);
        // dd('itt');
        $this->group->days()->delete();
        if(isset($validatedDays)) {
            $day_sync = [];
            foreach($validatedDays as $d => $day) {
                // dd($day);
                if(!isset($day['day_number'])) continue;
                $day_sync[] = new GroupDay([
                    'day_number' => $day['day_number'],
                    'start_time' => $day['start_time'],
                    'end_time' => $day['end_time']
                ]);                
            }      
            // dd($day_sync);      
            $this->group->days()->saveMany($day_sync);
        }

        //eltávolítom azokat, akik törölve lettek
        // if(count($this->users_old)) {
        //     foreach($this->users_old as $slug => $olduser) {
        //         if(!isset($this->users[$slug])) {
        //             $this->group->groupUsers()->detach($olduser['user_id']);
        //         }
        //     }
        // }

        //hozzáadjuk az új felhasználókat
        if(count($this->users) > 0) {
            $data = [
                'groupAdmin' => auth()->user()->last_name.' '.auth()->user()->first_name, 
                'groupName' => $this->state['name']
            ];
            $user_sync = [];
            foreach($this->users as $slug => $user) {
                // $password = Str::random(10);
                // $us = User::firstOrCreate(
                //     ['email' => $user['email']],
                //     ['password' => bcrypt($password)]
                // );
                $us = User::where('email', $user['email'])->firstOr(function () use ($user) {
                    $password = Str::random(10);
                    $u = User::create([
                        'email' => $user['email'],
                        'password' => bcrypt($password)
                    ]);
                    $u->notify(
                        new LoginData([
                            'groupAdmin' => auth()->user()->last_name.' '.auth()->user()->first_name, 
                            'userMail' => $user['email'],
                            'userPassword' => $password
                        ])
                    );
                    return $u;
                });
                $user_sync[$us->id] = [
                    'group_role' => $user['group_role'],
                    'note' => strip_tags(trim($user['note'])),
                    'hidden' => $user['hidden'] == 1 ? 1 : 0,
                    'deleted_at' => null //because maybe we try to reattach logged out user
                ];
            }
            // dd($user_sync);
            $res = $this->group->groupUsersAll()->sync($user_sync);
            // dd($res);
            //az újakat értesítem, hogy hozzá lett adva a csoporthoz
            if(isset($res['attached'])) {
                foreach($res['attached'] as $user) {
                    $us = User::find($user);
                    $us->notify(
                        new GroupUserAddedNotification($data)
                    );
                }                
            }
            if(isset($res['detached'])) {
                foreach($res['detached'] as $user) {
                    // dd($user);
                    $us = User::find($user);
                    if($us) {
                        $events = $us->feature_events()
                            ->where('group_id', $this->group->id);
                        if($events) {
                            $days = [];
                            foreach($events->get()->toArray() as $event) {
                                $days[] = $event['day'];
                            }
                            $events->delete();
                            foreach($days as $day) {
                                $stat = new GenerateStat();
                                $stat->generate($this->group->id, $day);
                            }
                        }
                    }
                }                
            }
        } else {
            //nincs egy user sem, törlöm aki eddig volt
            $this->group->groupUsers()->sync([]);
        }

        if(count($this->literatures)) {
            if(isset($this->literatures['new'])) {
                $save = [];
                foreach ($this->literatures['new'] as $language) {
                    $save[] = new GroupLiterature([
                        'name' => $language
                    ]);    
                }
                $this->group->literatures()->saveMany($save);
            }
            if(isset($this->literatures['current'])) {
                foreach ($this->literatures['current'] as $id => $language) {
                    $this->group->literatures()->whereId($id)->update(['name' => $language]);
                }
            }
            if(isset($this->literatures['removed'])) {
                foreach ($this->literatures['removed'] as $id => $language) {
                    $this->group->literatures()->whereId($id)->delete();
                }
            }
        }

        $this->group->refresh();

        Session::flash('message', __('group.groupUpdated')); 
        redirect()->route('groups');

        // $this->dispatchBrowserEvent('success', ['message' => __('group.groupUpdated')]);
        
    }

    function hoursRange( $lower = 0, $upper = 86400, $step = 3600, $format = '' ) {
        $times = array();
    
        if ( empty( $format ) ) {
            $format = 'H:i';
        }
    
        foreach ( range( $lower, $upper, $step ) as $increment ) {
            $increment = gmdate( 'H:i', $increment );
            list( $hour, $minutes ) = explode( ':', $increment );
            $date = new DateTime( $hour . ':' . $minutes );
            $times[(string) $increment] = $date->format( $format );
        }
    
        return $times;
    }

    public function literatureAdd() {
        if(isset($this->state['literatureAdd'])) {
            if(strlen(trim($this->state['literatureAdd'])) > 2) {
                $this->literatures['new'][] = $this->state['literatureAdd'];
                $this->state['literatureAdd'] = '';
                $this->dispatchBrowserEvent('success', ['message' => __('group.literature.added')]);
            } else {
                $this->dispatchBrowserEvent('sweet-error', [
                    'title' => __('group.literature.add_error'),
                    'message' => __('group.literature.tooShort'),
                ]);
            }
        }
    }

    public function literatureRemove($type, $id) {
        if($type == "new") {
            unset($this->literatures['new'][$id]);
            $this->dispatchBrowserEvent('success', ['message' => __('group.literature.confirmDelete.success')]);
        } else {
            $this->editedLiteratureRemove['type'] = $type;
            $this->editedLiteratureRemove['id'] = $id;
            $this->dispatchBrowserEvent('show-literature-confirmation', ['lang' => $this->literatures[$type][$id]]);
        }
    }

    public function literatureEdit($type, $id) {
        if(isset($this->literatures[$type][$id])) {
            $this->editedLiteratureType = $type;
            $this->editedLiteratureId = $id;
            $this->state['editedLiterature'] = $this->literatures[$type][$id];
        }
    }

    public function literatureDeleteConfirmed() {
        $this->literatures['removed'][$this->editedLiteratureRemove['id']] = true;
        unset($this->literatures[$this->editedLiteratureRemove['type']][$this->editedLiteratureRemove['id']]);
        $this->dispatchBrowserEvent('success', ['message' => __('group.literature.confirmDelete.success')]);
    }

    public function literatureEditCancel() {
        $this->editedLiteratureType = null;
        $this->editedLiteratureId = null;
        $this->state['editedLiterature'] = '';
    }

    public function literatureEditSave() {
        if(strlen(trim($this->state['editedLiterature'])) > 2) {
            $this->literatures[$this->editedLiteratureType][$this->editedLiteratureId] = $this->state['editedLiterature'];
            $this->dispatchBrowserEvent('success', ['message' => __('group.literature.saved')]);
            $this->literatureEditCancel();
        } else {
            $this->dispatchBrowserEvent('sweet-error', [
                'title' => __('group.literature.save_error'),
                'message' => __('group.literature.tooShort'),
            ]);
        }
    }

    public function render()
    {

        $group_times = $this->hoursRange( 0, 86400, 1800 );

        return view('livewire.groups.update-group-form', [
            'min_time_options' => [30,60,120],
            'max_time_options' => [60, 120, 180, 240, 320],
            'group_days' => range(0,6,1),
            'group_times' => $group_times,
            'group_roles' => [
                'member', 
                'helper',
                'roler',
                'admin',
            ]
        ]);
    }
}
