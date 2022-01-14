<?php

namespace App\Http\Livewire\Groups;

use App\Classes\CalculateDatesEvents;
use App\Http\Livewire\AppComponent;
use App\Models\User;
use App\Models\Group;
use App\Models\GroupDate;
use App\Models\GroupDay;
use App\Models\GroupLiterature;
use App\Notifications\FinishRegistration;
use App\Notifications\GroupUserAddedNotification;
// use App\Notifications\LoginData;
use DateTime;
// use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Session;
use Illuminate\Support\Facades\URL;
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

    public $dateAdd = [];
    public $dates = [];
    public $editedDate = null;
    public $editedDateRemove = [];

    public $listeners = ['literatureDeleteConfirmed', 'dateDeleteConfirmed'];

    public function mount() {
        $this->dateEditCancel();
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
                    $url = URL::temporarySignedRoute(
                        'finish_registration', now()->addMinutes(7 * 24 * 60 * 60), ['id' => $u->id]
                    );
                    $u->notify(
                        new FinishRegistration([
                            'groupAdmin' => auth()->user()->last_name.' '.auth()->user()->first_name, 
                            'userMail' => $user['email'],
                            'url' => $url
                        ])
                    );
                    return $u;
                });
                $us->userGroupsAll()->save($group, [
                    'group_role' => $user['group_role'],
                    'note' => strip_tags(trim($user['note'])),
                    'hidden' => $user['hidden'] == 1 ? 1 : 0,
                    'deleted_at' => null //because maybe we try to reattach logged out user
                ]);                
                //értesítem, hogy hozzá lett adva a csoporthoz
                if($us->email_verified_at) {
                    $us->notify(
                        new GroupUserAddedNotification($data)
                    );
                }
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
        }

        $deleteAfterCalculate = [];
        //add or modify special dates
        if(count($this->dates)) {
            foreach($this->dates as $date) {
                if($date['type'] == 'new') {
                    if($date['date_status'] == 0) {
                        $start = $end = $date['date'];
                    } else {
                        $start = $date['date']." ".$date['date_start'];
                        $end = $date['date']." ".$date['date_end'];
                    }
                    GroupDate::updateOrCreate(
                        [
                            'group_id' => $group->id,
                            'date' => $date['date']
                        ], 
                        [
                            'date_start' => $start,
                            'date_end' => $end,
                            'date_status' => $date['date_status'],
                            'note' => $date['note'],
                            'date_min_publishers' => $date['date_min_publishers'],
                            'date_max_publishers' => $date['date_max_publishers'],
                            'date_min_time' => $date['date_min_time'],
                            'date_max_time' => $date['date_max_time'],
                        ]
                    );
                    $reGenerateStat[$date['date']] = $date['date'];
                }
                if($date['type'] == "changed") {
                    $start = $date['date']." ".$date['date_start'];
                    $end = $date['date']." ".$date['date_end'];
                    GroupDate::whereId($date['id'])->update(
                        [
                            'date_start' => $start,
                            'date_end' => $end,
                            'date_status' => $date['date_status'],
                            'note' => $date['note'],
                            'date_min_publishers' => $date['date_min_publishers'],
                            'date_max_publishers' => $date['date_max_publishers'],
                            'date_min_time' => $date['date_min_time'],
                            'date_max_time' => $date['date_max_time'],
                        ]
                    );
                    $reGenerateStat[$date['date']] = $date['date'];
                }
                if($date['type'] == 'removed') {
                    //update or delete this day, based on if it's a service day or not
                    $d = new DateTime($date['date']);
                    $dayOfWeek = $d->format("w");
                    if(!isset($validatedDays[$dayOfWeek])) {
                        //it's not a service day, delete after calculate
                        $deleteAfterCalculate[$date['date']] = $date['date'];
                        $start = $date['date']." ".$date['date_start'];
                        $end = $date['date']." ".$date['date_end'];
                        $status = 0;
                    } else {
                        //it's a service day, we must restore original data
                        $status = 1;
                        $start = $date['date']." ".$this->days[$dayOfWeek]['start_time'].":00";
                        $end = $date['date']." ".$this->days[$dayOfWeek]['end_time'].":00";
                    }
                    
                    GroupDate::whereId($date['id'])->update(
                        [
                            'date_start' => $start,
                            'date_end' => $end,
                            'date_status' => $status,
                            'note' => null,
                            'date_min_publishers' => $this->state['min_publishers'],
                            'date_max_publishers' => $this->state['max_publishers'],
                            'date_min_time' => $this->state['min_time'],
                            'date_max_time' => $this->state['max_time'],
                        ]
                    );
                    $reGenerateStat[$date['date']] = $date['date'];
                }
            }
            if(count($reGenerateStat)) {
                CalculateDatesEvents::generate($group->id, $reGenerateStat);
                if(count($deleteAfterCalculate)) {
                    foreach($deleteAfterCalculate as $day) {
                        GroupDate::where('group_id', '=', $day)
                                ->where('date', '=', $day)
                                ->where('date_status', '=', 0)
                                ->delete();
                    }
                }
            }
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

    public function dateAdd() {
        $validatedDate = Validator::make($this->dateAdd, [
            'date' => 'required|date_format:Y-m-d|after_or_equal:today',
            'date_status' => 'required|numeric|in:0,2',
            'note' => 'required|string|min:3|max:255',
            'date_start' => 'required_if:date_status,2|date_format:H:i|before:date_end',
            'date_end' => 'required_if:date_status,2|date_format:H:i|after:date_start',
            'date_min_publishers' => 'required_if:date_status,2|numeric|digits_between:1,10|lte:date_max_publishers',
            'date_max_publishers' => 'required_if:date_status,2|numeric|digits_between:1,10|gte:date_min_publishers',
            'date_min_time' => 'required_if:date_status,2|numeric|in:30,60,120|lte:date_max_time',
            'date_max_time' => 'required_if:date_status,2|numeric|in:60,120,180,240,320|gte:date_min_time',
        ])->validate();

        // $validatedDate['date_start'] = $validatedDate['date']." ".$validatedDate['date_start'];
        // $validatedDate['date_end'] = $validatedDate['date']." ".$validatedDate['date_end'];
        $validatedDate['type'] = 'new';
        $this->dates[$validatedDate['date'].""] = $validatedDate;

        $this->dateEditCancel();
    }

    public function dateEdit($date /*$type, $id*/) {
        $this->dateAdd = $this->dates[$date]; //[$type][$id];
        $this->editedDate = $date; /*[
            'type' => $type,
            'id'   => $id,
        ];*/
    }

    public function dateEditCancel() {
        $this->editedDate = null;/* [
            'type' => null,
            'id' => null,
        ];*/
        $this->dateAdd = [
            'date' => '',
            'date_status' => 2,
            'note' => '',
            'date_start' => '',
            'date_end' => '',
            'date_min_publishers' => $this->state['min_publishers'],
            'date_max_publishers' => $this->state['max_publishers'],
            'date_min_time' => $this->state['min_time'],
            'date_max_time' => $this->state['max_time'],
        ];
    }

    public function dateSave() {
        if($this->editedDate === null) return;

        $validatedDate = Validator::make($this->dateAdd, [
            'id' => 'sometimes|numeric',
            'date' => 'required|date_format:Y-m-d|after_or_equal:today',
            'date_status' => 'required|numeric|in:0,2',
            'note' => 'required|string|min:3|max:255',
            'date_start' => 'required_if:date_status,2|date_format:H:i|before:date_end',
            'date_end' => 'required_if:date_status,2|date_format:H:i|after:date_start',            
            'date_min_publishers' => 'required|numeric|digits_between:1,10|lte:date_max_publishers',
            'date_max_publishers' => 'required|numeric|digits_between:1,10|gte:date_min_publishers',
            'date_min_time' => 'required|numeric|in:30,60,120|lte:date_max_time',
            'date_max_time' => 'required|numeric|in:60,120,180,240,320|gte:date_min_time',
        ])->validate();

        // $validatedDate['date_start'] = $validatedDate['date']." ".$validatedDate['date_start'];
        // $validatedDate['date_end'] = $validatedDate['date']." ".$validatedDate['date_end'];

        //we must update it later, move to changed array
        $type = $this->dates[$this->editedDate]['type'] == "current" ? "changed" : $this->dates[$this->editedDate]['type'];
        $validatedDate['type']  = $type;
        $this->dates[$this->editedDate] = $validatedDate;

        // if($this->editedDate['type'] == "current" ) {
        //     unset($this->dates["current"][$this->editedDate['id']]);
        // }

        $this->dateEditCancel();
    }

    public function dateRemove($date /* $type, $id*/) {
        if($this->dates[$date]['type'] == "new") {
            unset($this->dates[$date]);
            $this->dispatchBrowserEvent('success', ['message' => __('group.special_dates.confirmDelete.success')]);
        } else {
            $this->editedDateRemove = $date; // ['type' => $type, 'id' => $id];
            $this->dispatchBrowserEvent('show-special_dates-confirmation', ['date' => $date] /* $this->dates[$type][$id]]['date']*/);
        }
    }

    public function dateDeleteConfirmed() {
        $this->dates[$this->editedDateRemove]['type'] = 'removed';// $this->dates[$this->editedDateRemove['type']][$this->editedDateRemove['id']];
        // unset($this->dates[$this->editedDateRemove['type']][$this->editedDateRemove['id']]);
        $this->dispatchBrowserEvent('success', ['message' => __('group.special_dates.confirmDelete.success')]);
    }

    public function render()
    {

        $group_times = $this->hoursRange( 0, 86400, 1800 );
        if(count($this->dates))
            ksort($this->dates);

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
