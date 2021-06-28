<?php

namespace App\Http\Livewire\Groups;

use App\Http\Livewire\AppComponent;
use App\Models\User;
use App\Models\Group;
use App\Models\GroupDay;
use App\Models\GroupLiterature;
use App\Notifications\GroupUserAddedNotification;
use App\Notifications\LoginData;
use DateTime;
// use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Session;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Str;

class CreateGroupForm extends AppComponent
{

    /**
     * Alapértelmezett adatok
     */
    public $state = [
        'max_extend_days' => 60,
        'min_publishers' => 2,
        'max_publishers' => 2,
        'min_time' => 60,
        'max_time' => 240,
    ];

    public $users = [];
    public $search = "";
    public $literatures = [];
    public $editedLiteratureType = null;
    public $editedLiteratureId = null;
    public $editedLiteratureRemove = [];

    public $listeners = ['literatureDeleteConfirmed'];

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
        unset($this->users[$email]);
    }

    /**
     * Létrehozza a csoportot és ment mindent
     */
    public function createGroup() {
        
        // dd($this->users);

        $this->state['name'] = strip_tags($this->state['name']);

        $validatedData = Validator::make($this->state, [
            'name' => 'required|string|max:50|min:2',
            'max_extend_days' => 'required|numeric|digits_between:1,365',
            'min_publishers' => 'required|numeric|digits_between:1,365|lte:max_publishers',
            'max_publishers' => 'required|numeric|digits_between:1,365|gte:min_publishers',
            'min_time' => 'required|numeric|in:30,60,120|lte:max_time',
            'max_time' => 'required|numeric|in:60,120,180,240,320|gte:min_time',
            'days.*.start_time' => 'required|date_format:H:i|before:days.*.end_time',
            'days.*.end_time' => 'required|date_format:H:i|after:days.*.start_time',
            'days.*.day_number' => 'required',
        ])->validate();
        // dd($validatedData['days']);

        $user = Auth()->user(); // User::find(Auth::id());
        $group = Group::create($validatedData);
        // dd('itt');
        if(isset($validatedData['days'])) {
            foreach($validatedData['days'] as $d => $day) {
                // dd($day);
                if(!isset($day['day_number'])) continue;
                $day = new GroupDay([
                    'day_number' => $day['day_number'],
                    'start_time' => $day['start_time'],
                    'end_time' => $day['end_time']
                ]);
                $group->days()->save($day);    
            }
        }

        $user->userGroups()->save($group, ['group_role' => 'admin', 'accepted_at' => date('Y-m-d H:i:s')]);

        //hozzáadjuk a felhasználókat
        if(count($this->users) > 0) {

            $data = [
                'groupAdmin' => auth()->user()->last_name.' '.auth()->user()->first_name, 
                'groupName' => $group->name
            ];

            foreach($this->users as $user) {
                // $us = User::firstOrCreate(
                //     ['email' => $user['email']],
                //     ['password' => bcrypt(Str::random(10))]
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
                $us->userGroups()->save($group, [
                    'group_role' => $user['group_role'],
                    'note' => strip_tags(trim($user['note'])),
                    'hidden' => $user['hidden'] == 1 ? 1 : 0
                ]);                
                //értesítem, hogy hozzá lett adva a csoporthoz
                $us->notify(
                    new GroupUserAddedNotification($data)
                );
            }
        }

        if(count($this->literatures)) {
            if(isset($this->literatures['new'])) {
                $save = [];
                foreach ($this->literatures['new'] as $language) {
                    $save[] = new GroupLiterature([
                        'name' => $language
                    ]);    
                }
                $group->literatures()->saveMany($save);
            }
            // if(isset($this->literatures['current'])) {
            //     foreach ($this->literatures['current'] as $id => $language) {
            //         $this->group->literatures()->whereId($id)->update(['name' => $language]);
            //     }
            // }
            // if(isset($this->literatures['removed'])) {
            //     foreach ($this->literatures['removed'] as $id => $language) {
            //         $this->group->literatures()->whereId($id)->delete();
            //     }
            // }
        }

        Session::flash('message', __('group.groupCreated')); 
        redirect()->route('groups');

        // $this->dispatchBrowserEvent('success', ['message' => __('group.groupCreated')]);

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

        return view('livewire.groups.create-group-form', [
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
