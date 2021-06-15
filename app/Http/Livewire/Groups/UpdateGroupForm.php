<?php

namespace App\Http\Livewire\Groups;

use App\Http\Livewire\AppComponent;
use App\Models\User;
use App\Models\Group;
use Illuminate\Support\Str;
use App\Models\GroupDay;
use App\Notifications\GroupUserAddedNotification;
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
        // dd($days);
        // dd($this->state['days'], $group->days, $collection);
        if($group->groupUsers) {
            foreach($group->groupUsers as $user) {
                $slug = Str::slug($user->email, '-');
                $this->users[$slug] = [
                    'email' => $user->email,
                    'group_role' => $user->pivot->group_role,
                    'note' => $user->pivot->note,
                    'user_id' => $user->id
                ];
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
                    'note' => ''
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
        } else {
            unset($this->users[$email]);
        }
    }

    /**
     * Elmenti a csoport adatait
     */
    public function updateGroup() {
        
        // dd('here');

        // dd($this->days);

        $this->state['name'] = strip_tags($this->state['name']);

        $admins = 0;
        foreach($this->users as $slug => $user) {
            if($user['group_role'] == "admin") $admins++;
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

        $v->after(function ($validator) use ($admins) {
            if ($admins == 0) {
                $validator->errors()->add(
                    'users', __('group.error_no_admin_user')
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
                $us = User::firstOrCreate(
                    ['email' => $user['email']],
                    ['password' => bcrypt(Str::random(10))]
                );
                $user_sync[$us->id] = [
                    'group_role' => $user['group_role'],
                    'note' => strip_tags(trim($user['note']))
                ];
            }
            $res = $this->group->groupUsers()->sync($user_sync);
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
                    $us = User::find($user);
                    $us->feature_events()
                        ->where('group_id', $this->group->id)
                        ->delete();
                }                
            }
        } else {
            //nincs egy user sem, törlöm aki eddig volt
            $this->group->groupUsers()->sync([]);
        }

        $this->group->refresh();

        Session::flash('message', __('group.groupUpdated')); 
        redirect()->route('groups');

        // $this->dispatchBrowserEvent('success', ['message' => __('group.groupUpdated')]);
        
    }

    public function render()
    {
        return view('livewire.groups.update-group-form');
    }
}
