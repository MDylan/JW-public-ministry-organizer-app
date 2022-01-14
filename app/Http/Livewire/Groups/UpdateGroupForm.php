<?php

namespace App\Http\Livewire\Groups;

use App\Classes\CalculateDatesEvents;
use App\Classes\GenerateStat;
use App\Http\Livewire\AppComponent;
use App\Models\User;
use App\Models\Group;
use App\Models\GroupDate;
use Illuminate\Support\Str;
use App\Models\GroupDay;
use App\Models\GroupLiterature;
use App\Notifications\FinishRegistration;
use App\Notifications\GroupUserAddedNotification;
// use App\Notifications\LoginData;
use DateTime;
use Illuminate\Support\Facades\Auth;
// use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Facades\Session;
use Illuminate\Support\Facades\URL;

class UpdateGroupForm extends AppComponent
{

    public $users = [];
    public $users_old = []; //eredeti userek
    public $search = "";
    public $group;
    public $days = [];
    public $admins = [];

    public $dateAdd = [];
    public $dates = [];
    public $editedDate = null;
    public $editedDateRemove = [];

    public $literatures = [];
    public $editedLiteratureType = null;
    public $editedLiteratureId = null;
    public $editedLiteratureRemove = [];

    public $default_colors = [
        'color_default' => '#CECECE',
        'color_empty' => '#00FF00',
        'color_someone' => '#1259B2',
        'color_minimum' => '#ffff00',
        'color_maximum' => '#ff0000',
    ];

    public $listeners = ['literatureDeleteConfirmed', 'dateDeleteConfirmed'];

    public function mount(Group $group) {
        // dd($group->days);

        $this->state = $group->toArray();
        foreach($this->default_colors as $field => $color) {
            if(empty($this->state[$field])) {
                $this->state[$field] = $color;
            }
        }
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
        $dates = $group->dates()->whereIn('date_status', [0,2])->get()->toArray();
        if(count($dates)) {
            foreach($dates as $date) {
                $date_start = new DateTime($date['date_start']);
                $date['date_start'] = $date_start->format("H:i");
                $date_end = new DateTime($date['date_end']);
                $date['date_end'] = $date_end->format("H:i");
                $date['type'] = 'current';
                $this->dates[$date['date'].""] = $date;
            }
        }
        $this->dateEditCancel();
        // dd($dates->get()->toArray());
        
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
                    'hidden' => $user->pivot->hidden == 1 ? true : false,
                    'deleted_at' => null
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
                    'hidden' => false,
                    'deleted_at' => null
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
            // unset($this->users[$email]);
            $this->users[$email]['deleted_at'] = date("Y-m-d H:i:s");
        }
    }

    /**
     * Elmenti a csoport adatait
     */
    public function updateGroup() {
        // dd($this->state);

        $this->state['name'] = strip_tags($this->state['name']);

        $admins = 0;
        $current_admins = [];
        $reGenerateStat = [];

        foreach($this->users as $slug => $user) {
            if($user['group_role'] == "admin") {
                $admins++;
                $current_admins[$slug] = true;
            }
        }

        $pattern = "/^#([a-fA-F0-9]{6}|[a-fA-F0-9]{3})$/";

        $v = Validator::make($this->state, [
            'name' => 'required|string|max:50|min:2',
            'max_extend_days' => 'required|numeric|digits_between:1,365',
            'min_publishers' => 'required|numeric|digits_between:1,10|lte:max_publishers',
            'max_publishers' => 'required|numeric|digits_between:1,10|gte:min_publishers',
            'min_time' => 'required|numeric|in:30,60,120|lte:max_time',
            'max_time' => 'required|numeric|in:60,120,180,240,320|gte:min_time',            
            'need_approval' => 'required|numeric|in:0,1',
            'color_default' => ['sometimes', 'regex:'.$pattern],
            'color_empty' => ['sometimes', 'regex:'.$pattern],
            'color_someone' => ['sometimes', 'regex:'.$pattern],
            'color_minimum' => ['sometimes', 'regex:'.$pattern],
            'color_maximum' => ['sometimes', 'regex:'.$pattern],
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
        // $this->group->days()->delete();
        // dd($validatedDays);
        if(isset($validatedDays)) {
            // $day_sync = [];
            foreach($validatedDays as $d => $day) {
                // dd($day);
                if(!isset($day['day_number'])) continue;
                if($day['day_number'] == false) {
                    $del = GroupDay::where('group_id', $this->group->id)->where('day_number', $d)->first();
                    $del->delete();
                } else {
                    GroupDay::updateOrCreate(
                        [
                            'group_id' => $this->group->id,
                            'day_number' => $day['day_number']
                        ], 
                        [
                            'start_time' => $day['start_time'],
                            'end_time' => $day['end_time']
                        ]
                    );
                }
                // $day_sync[] = new GroupDay([
                //     'day_number' => $day['day_number'],
                //     'start_time' => $day['start_time'],
                //     'end_time' => $day['end_time']
                // ]);                
            }      
            // dd($day_sync);      
            // $this->group->days()->saveMany($day_sync);
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
                    // dd($u);
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
                $user_sync[$us->id] = [
                    'group_role' => $user['group_role'],
                    'note' => strip_tags(trim($user['note'])),
                    'hidden' => $user['hidden'] == 1 ? 1 : 0,
                    'deleted_at' => null //because maybe we try to reattach logged out user
                ];
            }
            
            $res = $this->group->groupUsersAll()->sync($user_sync);
            // dd($user_sync);
            // dd($res);
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
                    $this->group->literatures()->firstWhere('id', $id)->update(['name' => $language]);                    
                }
            }
            if(isset($this->literatures['removed'])) {
                foreach ($this->literatures['removed'] as $id => $language) {
                    $this->group->literatures()->firstWhere('id', $id)->delete();
                }
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
                            'group_id' => $this->group->id,
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
                CalculateDatesEvents::generate($this->group->id, $reGenerateStat);
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



        // foreach($reGenerateStat as $day) {
        //     $stat = new GenerateStat();
        //     $stat->generate($this->group->id, $day);
        // }

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
