<?php

namespace App\Http\Livewire\Events;

use App\Classes\GenerateSlots;
use App\Classes\GenerateStat;
use App\Helpers\GroupDateHelper;
use App\Http\Livewire\AppComponent;
use App\Models\Event;
use App\Models\Group;
use App\Models\GroupDate;
use App\Models\GroupDayDisabledSlots;
use App\Models\GroupUser;
use Carbon\Carbon;
use DateTime;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Validator;

class EventEdit extends AppComponent
{

    public $groupId;
    public $date;
    public $day_data;
    public $formText = [];
    public $eventId = null;
    public $editEvent = null;
    public $original_day_data = [];
    public $group_data = [];
    public $listeners = [
        'setStart',
        'createForm',
        'editForm',
        'deleteConfirmed'
    ];
    public $users = [];
    public $role = null;
    private $pleaseWait = false;
    private $error = false;
    public $date_data = [];
    public $other_events = [];
    public $user_statistics = [];

    public function mount($groupId, $date) {
        $check = auth()->user()->userGroups()->whereId($groupId);
        if(!$check) {
            $this->error = __('event.error.invalid_group');
        } else {
            $this->groupId = $groupId;
            $this->date = $date;
            $this->state = [];
            $this->users = [];
            $this->error = false;
            $this->user_statistics = [];
            $this->getRole();
            $this->createForm();
        }
    }

    public function getRole() {
        $info = GroupUser::where('user_id', '=', Auth::id())
            ->where('group_id', '=', $this->groupId)
            ->select('group_role')
            ->first()->toArray();
        $this->role = $info['group_role'];
    }

    public function createForm() {
        $this->eventId = null;
        $this->state = [];
        $this->state['user_id'] = Auth::id();
        $this->error = false;
        $this->getInfo();        
        $this->formText['title'] = __('event.create_event');
        $this->state['status'] = ($this->group_data['need_approval'] == 1) ? 0 : 1;
    }

    public function editForm($eventId) {
        $this->eventId = $eventId;
        $editEvent = $this->checkUserRole();
        if($editEvent === false) return;
        
        $this->editEvent = $editEvent;
        $this->state['user_id'] = $editEvent['user_id'];

        // $this->getRole();

        // if($this->eventId !== null) {
        //     $editEvent = Event::with(['user', 'accept_user', 'histories.user'])->firstWhere('id', $this->eventId)->toArray();

        //     if(!in_array($this->role, ['admin', 'roler', 'helper']) 
        //         && $editEvent['user_id'] !== Auth::id()) {
        //             $this->error = __('event.error.no_permission');
        //             $this->cancelEdit();
        //         return;
        //     }            
        //     $this->editEvent = $editEvent;
        //     $this->state['user_id'] = $editEvent['user_id'];
        // }

        $this->getInfo();
        $this->change_start();
        $this->change_end();
        $this->formText['title'] = __('event.edit_event');
    }

    public function checkUserRole() {
        $this->getRole();
        if($this->eventId !== null) {
            $editEvent = Event::with(['user', 'accept_user', 'histories.user'])->firstWhere('id', $this->eventId)->toArray();

            if(!in_array($this->role, ['admin', 'roler', 'helper']) 
                && $editEvent['user_id'] !== Auth::id()) {
                    $this->error = __('event.error.no_permission');
                    $this->cancelEdit();
                return false;
            }            
            return $editEvent;
        }
    }

    public function getInfo($saveProcess = false) {
        if($this->eventId == null) $this->editEvent = null;

        $groupId = $this->groupId;
        $date = $this->date;

        $group = Group::with(['current_date' => function($q) use ($date) {
            // $q->select(['group_id', 'date', 'date_start', 'date_end']);
            $q->where('date', '=', $date);
        }])->findOrFail($groupId);
        // dd($group);
        if(in_array($this->role, ['admin', 'roler', 'helper'])) {
            $this->users = $group->users()->select('users.id', 'users.name')->get()->toArray();
        }
    
        $this->day_data = [];
        $this->service_days = [];
        $d = new DateTime( $date );
        $dayOfWeek = $d->format("w");
        $this->day_data['date'] = $d->format("Y-m-d"); //$date;
        $this->day_data['dateFormat'] = $d->format('Y.m.d');
        
        $days = $group->days()->get()->toArray();
        if(count($days)) {
            foreach($days as $day) {
                $this->service_days[$day['day_number']] = [
                    'start_time' => $day['start_time'],
                    'end_time' => $day['end_time'],
                ];
            }
        }
        
        $this->group_data = $group->toArray();
        $slots = $disabled_slots = [];

        if($this->group_data['current_date'] === null) {
            $helper = new GroupDateHelper($this->form_groupId);
            $generate = $helper->generateDate($this->date);
            if(is_array($generate)) {
                $this->date_data = [
                    'min_publishers' => $generate['date_min_publishers'],
                    'max_publishers' => $generate['date_max_publishers'],
                    'min_time' => $generate['date_min_time'],
                    'max_time' => $generate['date_max_time'],
                    'peak' => 0
                ];
                $start = strtotime($generate['date_start']);
                $max = strtotime($generate['date_end']);
                $disabled_slots = $generate['disabled_slots'];
                $group['current_date']['disabled_slots'] = $generate['disabled_slots'];
            }
            // $start = strtotime($date." ".$this->service_days[$dayOfWeek]['start_time'].":00");
            // $end_date = $date;
            // if(strtotime($this->service_days[$dayOfWeek]['end_time']) == strtotime("00:00")) {
            //     $end_date = Carbon::parse($date)->addDay()->format("Y-m-d");
            // }
            // $max = strtotime($end_date." ".$this->service_days[$dayOfWeek]['end_time'].":00");
            // $disabled_slots_insert = [];
            // //get disabled time slot for this day
            // $disableds = GroupDayDisabledSlots::where('group_id', '=', $this->groupId)
            //     ->where('day_number', '=', $dayOfWeek)
            //     ->orderBy('slot', 'asc')
            //     ->get()
            //     ->toArray();
            // foreach($disableds as $sl) {
            //     $ts = strtotime($date->format("Y-m-d")." ".$sl['slot']);
            //     $slot_key = "'".date("Hi", $ts)."'";
            //     $disabled_slots[$slot_key] = true;
            //     $slots[$slot_key][] = true;
            //     $disabled_slots_insert[$sl['slot']] = true;
            // }

            // GroupDate::create([
            //     'group_id' => $groupId,
            //     'date' => $date,
            //     'date_start' => date("Y-m-d H:i:s", $start),
            //     'date_end' => date("Y-m-d H:i:s", $max),
            //     'date_status' => 1,
            //     'date_min_publishers' => $this->group_data['min_publishers'],
            //     'date_max_publishers' => $this->group_data['max_publishers'],
            //     'date_min_time' => $this->group_data['min_time'],
            //     'date_max_time' => $this->group_data['max_time'],
            //     'disabled_slots' => $disabled_slots_insert
            // ]);
            // $this->date_data = [
            //     'min_publishers' => $this->group_data['min_publishers'],
            //     'max_publishers' => $this->group_data['max_publishers'],
            //     'min_time' => $this->group_data['min_time'],
            //     'max_time' => $this->group_data['max_time']
            // ];
        } else {
            $start = strtotime($this->group_data['current_date']['date_start']);
            $max = strtotime($this->group_data['current_date']['date_end']);
            $this->date_data = [
                'min_publishers' => $this->group_data['current_date']['date_min_publishers'],
                'max_publishers' => $this->group_data['current_date']['date_max_publishers'],
                'min_time' => $this->group_data['current_date']['date_min_time'],
                'max_time' => $this->group_data['current_date']['date_max_time']
            ];
        }

        $step = $this->date_data['min_time'] * 60;
        $day_table = [];
        $day_selects = [];
        $row = 1;
        // dd($date->format("Y-m-d"), date("Y-m-d H:i", $start), date("Y-m-d H:i", $max), $step);
        $slots_array = GenerateSlots::generate($date->format("Y-m-d"), $start, $max, $step);
        foreach($slots_array as $current) {
            $key = "'".date('Hi', $current)."'";
            $day_table[$key] = [
                'ts' => $current,
                'hour' => date("H:i", $current),
                'row' => $row,
                'status' => 'free',
                'publishers' => 0,                
                'accepted' => 0,
            ];
            for ($i=1; $i <= ($this->date_data['max_publishers'] + config('events.max_columns')); $i++) {
                $day_table[$key]['cells'][$i] = true;
            }
            $day_selects['start'][$current] = date("H:i", $current);
            if($current != $start)
                $day_selects['end'][$current] = date("H:i", $current);

            $row++;
        }
        $day_selects['end'][$max] = date("H:i", $max);
        //other events
        $events = $group->day_events($date)->get()->toArray();
        //set disabled slots
        if(count(($group['current_date']['disabled_slots'] ?? []))) {
            $disabled_slots = [];
            foreach($group['current_date']['disabled_slots'] as $slot_key => $v) {
                $key = "'".str_replace(":", "", $slot_key)."'";
                $disabled_slots[$key] = true;
                $slots[$key][] = true;
            }
        }
        
        foreach($events as $event) {
            if($this->eventId == $event['id']) {
                if($saveProcess === false) {
                    $this->state = $event;
                    // $this->state['status'] = ($this->group_data['need_approval'] == 1 && is_null($event['accepted_at'])) ? 0 : 1;
                }
                continue;
            }

            $steps = ceil(($event['end'] - $event['start']) / $step);     
            
            if(!isset($day_table["'".date('Hi', $event['start'])."'"])) continue;

            $row = $day_table["'".date('Hi', $event['start'])."'"]['row'];
            $key = "'".date('Hi', $event['start'])."'";
            $cell = 1;
            if(isset($slots[$key])) {
                $cell = min(array_keys($day_table[$key]['cells']));
            }
            
            $cell_start = $event['start'];
            for($i=0;$i < $steps;$i++) {
                $slot_key = "'".date("Hi", $cell_start)."'";
                $slots[$slot_key][] = is_null($event['accepted_at']) ? false : true;
                unset($day_table[$slot_key]['cells'][$cell]);
                if($event['status'] == 1) {
                    $day_table[$slot_key]['publishers']++;
                    $day_table[$slot_key]['accepted']++;
                }
                if($this->state['user_id'] == $event['user_id']) {
                    $disabled_slots[$slot_key] = true;
                }
                $cell_start += $step;
            }
        }
        // dd($day_table, $slots, $day_selects, $disabled_slots);
        //filter out what not available
        foreach($slots as $key => $times) {
            if(count($times) >= ($this->group_data['need_approval'] 
                    ? ($day_table[$key]['accepted'] >= $this->date_data['max_publishers'] 
                            ? $this->date_data['max_publishers'] 
                            : ($this->date_data['max_publishers'] + config('events.max_columns')))
                        : $this->date_data['max_publishers']) 
                    || isset($disabled_slots[$key])) {
                $day_table[$key]['status'] = 'full';
                $k = $day_table[$key]['ts'];
                unset($day_selects['start'][$k]);
                if(!isset($day_selects['end'][$k + $step]) && ($k + $step) > $max) {
                    //if somehow last time slot not the same exactly to the end
                    unset($day_selects['end'][$max]);
                }
                unset($day_selects['end'][$k + $step]);
            } elseif($day_table[$key]['accepted'] >= $this->date_data['min_publishers']) {
                $day_table[$key]['status'] = 'ready';
            } 
        }        
        // dd($slots, $day_selects, $disabled_slots);
        $this->day_data['table'] = $day_table;
        $this->day_data['selects'] = $day_selects;
        $this->original_day_data = $this->day_data;

        $this->user_statistics = [];
        if(in_array($this->role, ['admin', 'roler'])) {
            $date = strtotime($this->date);
            $date_back = date("Y-m-d", $date - (30 * 24 * 60 * 60));
            $date_future = date("Y-m-d", $date + (15 * 24 * 60 * 60));

            $stats = DB::table('events')
                            ->join('groups', 'events.group_id', '=', 'groups.id')
                            ->select('events.start', 'events.end', 'groups.name', 'events.status')
                            ->whereNull('events.deleted_at')
                            ->whereNull('groups.deleted_at')
                            ->where('events.user_id', '=', $this->state['user_id'])
                            ->where(function($query) {
                                $query->where('groups.id', '=', $this->groupId);                                
                                if($this->group_data['parent_group_id']) {
                                    $query->orWhere('groups.id', '=', $this->group_data['parent_group_id']);
                                    $query->orWhereIn('groups.parent_group_id', [
                                        $this->groupId,  
                                        $this->group_data['parent_group_id'] 
                                    ]);
                                } else {
                                    $query->orWhere('groups.parent_group_id', '=', $this->groupId);
                                }
                            })
                            ->whereBetween('events.day', [
                                $date_back,
                                $date_future
                            ])
                            ->orderByDesc('events.day')
                            ->orderByDesc('events.start')
                            ->orderByDesc('events.group_id')
                            ->get()
                            ->toArray();
            $this->user_statistics = json_decode(json_encode($stats), true);
        }
    }

    public function change_user() {
        $time = $this->state['start'];
        $this->getInfo();
        $this->state['start'] = $time;
        $this->change_end();
    }

    public function setStart($time) {
        $this->createForm();
        $this->state['start'] = $time;
        if(in_array($this->role, ['admin', 'roler', 'helper'])) {
            //this time not available for this user...
            //need when admin/helper want to set a time slot when he is already in service
            if(!isset($this->day_data['selects']['start'][$time])) {
                $this->state['user_id'] = 0;
                $this->change_user();
            } else {        
                $this->change_end();
            } 
        } else {        
            $this->change_end();
        } 
    }

    public function change_start() {
        if($this->state['end'] == 0) {
            $this->day_data['selects']['start'] = $this->original_day_data['selects']['start'];
            return;
        }

        $min_time = $this->state['end'] - ($this->date_data['max_time'] * 60);
        $step = $this->date_data['min_time'] * 60;
        // dd('here', date("Y.m.d H:i", $this->state['start']));  
        
        if(count($this->original_day_data['selects']['start'])) {
            $this->day_data['selects']['start'] = [];
            foreach($this->original_day_data['selects']['start'] as $key => $value) {
                if($key < $this->state['end']  && $key >= $min_time) {
                    
                    if(isset($last_key) && $key > ($last_key + $step)) {
                        unset($this->day_data['selects']['start'][$last_key]);
                    };

                    $this->day_data['selects']['start'][$key] = $value;
                    $last_key = $key;
                }
            }
        }
        $this->getUserOtherEvents();
    }

    public function change_end() {
        if($this->state['start'] == 0) {
            $this->day_data['selects']['end'] = $this->original_day_data['selects']['end'];
            return;
        }
        $max_time = $this->state['start'] + ($this->date_data['max_time'] * 60);
        $step = $this->date_data['min_time'] * 60;
        if(count($this->original_day_data['selects']['end'])) {
            $this->day_data['selects']['end'] = [];
            foreach($this->original_day_data['selects']['end'] as $key => $value) {
                //filter out what is not continuous
                if(isset($last_key) && $key > ($last_key + $step)) continue;

                if($key > $this->state['start'] && $key <= $max_time) {
                    $this->day_data['selects']['end'][$key] = $value;
                    $last_key = $key;
                }
            }
        }
        if(count($this->day_data['selects']['end']) == 1) {
            $this->state['end'] = array_key_last($this->day_data['selects']['end']);
        }
        $this->getUserOtherEvents();
        // dd(date("Y-m-d H:i", $this->state['start']), date("Y-m-d H:i", $this->state['end']));
    }

    public function cancelEdit() {
        $this->eventId = null;
        $this->editEvent = null;
        $this->state = null;
        $this->state['user_id'] = Auth::id();
        $this->user_statistics = [];
    }

    public function saveEvent() {
        //check if time still ok or not
        // dd($this->state);
        $this->getRole();
        $this->getInfo(true);
        // $this->getUserOtherEvents();

        if($this->editEvent !== null) {
            if(!in_array($this->role, ['admin', 'roler', 'helper']) 
                && $this->editEvent['user_id'] !== Auth::id()) {
                    $this->error = __('event.error.no_permission');
                    return;
            }  
        }
        $publishers_ok = true;
        $invalid = [];
        if($this->state['status'] != 2 || $this->editEvent === null) {
            //we check fields only when needed
            //check the start/end date 
            Validator::make($this->state, [
                'start' => 'required|numeric|lte:end', 
                'end' => 'required|numeric|gte:start',
            ])->validate();
            // dd($this->day_data['table']);
            $step = $this->date_data['min_time'] * 60;            
            for ($i=$this->state['start']; $i < $this->state['end'] ; $i+=$step) {
                $slot_key = "'".date("Hi", $i)."'";
                if($this->day_data['table'][$slot_key]['publishers'] >= (
                    $this->group_data['need_approval'] 
                        ? ($this->day_data['table'][$slot_key]['accepted'] >= $this->date_data['max_publishers'] 
                            ? $this->date_data['max_publishers'] 
                            : ($this->date_data['max_publishers'] + config('events.max_columns')))
                        : $this->date_data['max_publishers'])) {
                    $publishers_ok = false;                
                }
            }
            //check valid time, maybe user modified it in browser            
            if(!isset($this->day_data['selects']['start'][$this->state['start']]))
                $invalid['start'] = true;
            if(!isset($this->day_data['selects']['end'][$this->state['end']]))
                $invalid['end'] = true;

            // $group = Group::findOrFail($this->groupId);
            // dd($this->day_data);
            $data = [
                'day' => $this->day_data['date'],
                'start' => $this->state['start'],
                'end' => $this->state['end'],
                'user_id' => $this->editEvent !== null ? $this->editEvent['user_id'] : $this->state['user_id'],
                'accepted_at' => null,
                'accepted_by' => null, //$group->need_approval ? null : Auth::id()
                'status' => 0,
                'comment' => isset($this->state['comment']) 
                                ? (!empty($this->state['comment']) ? trim($this->state['comment']) : null)
                                : null
            ];
        } else {
            //this set, if we edit an existing event and deny it. 
             $data = [
                'day' => $this->day_data['date'],
                'start' => $this->editEvent['start'],
                'end' => $this->editEvent['end'],
                'user_id' => $this->editEvent !== null ? $this->editEvent['user_id'] : $this->state['user_id'],
                'accepted_at' => null,
                'accepted_by' => null,
                'status' => 2,
                'comment' => isset($this->state['comment']) 
                                ? (!empty($this->state['comment']) ? trim($this->state['comment']) : null)
                                : null
            ];
        }
        $group = Group::findOrFail($this->groupId);
        if($this->editEvent === null) {
            //it's a new event, set approval status
            if($group->need_approval == 0) {
                $data['accepted_at'] = date("Y-m-d H:i:s");
                $data['accepted_by'] = Auth::id();
                $data['status'] = 1;
            } else {
                if(in_array($this->role, ['admin', 'roler', 'helper']) 
                    && $this->state['status']) {
                        $data['accepted_at'] = date("Y-m-d H:i:s");
                        $data['accepted_by'] = Auth::id();
                        $data['status'] = $this->state['status'];
                } 
            }
        } else {
            //it's an existing event, set approval status
            if(in_array($this->role, ['admin', 'roler', 'helper']) 
                && ($this->editEvent['status'] != $this->state['status'])) {
                    $data['accepted_at'] = date("Y-m-d H:i:s");
                    $data['accepted_by'] = Auth::id();
                    $data['status'] = $this->state['status'];
            }
            if(in_array($this->role, ['admin', 'roler', 'helper']) 
                && !is_null($this->editEvent['accepted_by']) 
                && !$this->state['status']) {
                    $data['accepted_at'] = null;
                    $data['accepted_by'] = null;
                    $data['status'] = 0;
            }
            if($group->need_approval == 0) {
                $data['status'] = 1;
            }
        }
        
        $v = Validator::make($data, [
            'user_id' => 'required|exists:App\Models\User,id',
            'start' => 'required|numeric|lte:end', 
            'end' => 'required|numeric|gte:start',
            'day' => 'required|date_format:Y-m-d',
            'accepted_by' => 'nullable|exists:App\Models\User,id',
            'accepted_at' => 'nullable|date_format:Y-m-d H:i:s',
            'status' => 'required|in:0,1,2',
            'comment' => 'sometimes|max:80',
        ]);
        
        $v->after(function ($validator) use ($publishers_ok, $invalid, $data) {
            if ($publishers_ok === false) {
                $validator->errors()->add(
                    'start', __('event.reach_max_publisher')
                );
                $validator->errors()->add(
                    'end', __('event.reach_max_publisher')
                );
            }
            if(count($invalid) > 0) {
                foreach($invalid as $field => $v) {
                    $validator->errors()->add(
                        $field, __('event.invalid_value')
                    );                    
                }
            }
            $busy = DB::table('events')
                        ->join('groups', 'events.group_id', '=', 'groups.id')
                        ->whereNull('groups.deleted_at')
                        ->whereNull('events.deleted_at')
                        ->where('events.status', '=', 1)
                        ->where('events.user_id', '=', $data['user_id'])
                        ->where('events.group_id', '!=', $this->groupId)
                        ->where('events.start', '<', date("Y-m-d H:i", $data['end']))
                        ->where('events.end', '>', date("Y-m-d H:i", $data['start']))
                        ->first();
            // dd($busy->start);
            if($busy !== null) {
                $validator->errors()->add(
                    'busy', __('event.error.publisher_busy', [
                        'start' => Carbon::parse($busy->start)->format(__('app.format.datetime')),
                        'end' => Carbon::parse($busy->end)->format(__('app.format.datetime')),
                    ])
                );
            }
        });
        // dd($v);
        $validatedData = $v->validate();
        $validatedData['start'] = date("Y-m-d H:i", $validatedData['start']);
        $validatedData['end'] = date("Y-m-d H:i", $validatedData['end']);
        // $validatedData['group_id'] = $this->groupId;
        // dd($validatedData);
        if($this->editEvent !== null) {
            //update event
            $exists = $group->events()->whereId($this->editEvent['id']);
            if($exists !== null) {
                $event = Event::findOrFail($this->editEvent['id']);
                $event->update($validatedData);
            }


            $this->cancelEdit();
        } else {
            //save new event
            $event = new Event($validatedData);
            $group->events()->save($event); 
        }

        //IMPORTANT: Check observers too

        $this->emitUp('refresh');
        $this->emitTo('partials.events-bar', 'refresh');

        $stat = new GenerateStat();
        $stat->generate($this->groupId, $validatedData['day']);

        $this->dispatchBrowserEvent('success', ['message' => __('event.saved')]);
        $this->pleaseWait = true;
    }

    public function confirmEventDelete() {
        $this->dispatchBrowserEvent('show-deletion-confirmation', [
            'title' => __('event.confirmDelete.question'),
            'text' => __('event.confirmDelete.message'),
            'emit' => 'deleteConfirmed'
        ]);
    }

    public function deleteConfirmed() {
        $editEvent = $this->checkUserRole();
        if($editEvent === false) return;

        $event = Event::findOrFail($this->eventId);
        $res = $event->delete();
        if($res) {
            $this->dispatchBrowserEvent('success', ['message' => __('event.confirmDelete.success')]);
            $stat = new GenerateStat();
            $stat->generate($event->group_id, $event['day']);
        } else {
            $this->dispatchBrowserEvent('error', ['message' => __('event.confirmDelete.error')]);
        }
        $this->emitTo('partials.events-bar', 'refresh');
        $this->emitUp('refresh');
        $this->pleaseWait = true;
    }

    public function getUserOtherEvents() {
        $this->other_events = [];
        // return;
        if($this->state['user_id'] != 0) {
            if(!isset($this->state['start']) || !isset($this->state['end'])) return;
            
            $start = date("Y-m-d H:i", $this->state['start']);
            $end = date("Y-m-d H:i", $this->state['end']);
            // dd('na', $start, $end, $this->state['user_id']);
            $others = DB::table('events')
                ->join('groups', 'events.group_id', '=', 'groups.id')
                ->select('events.start', 'events.end', 'groups.name')
                ->whereNull('events.deleted_at')
                ->whereNull('groups.deleted_at')
                ->whereIn('events.status', [0,1])
                ->where('events.user_id', '=', $this->state['user_id'])
                ->where('events.group_id', '!=', $this->groupId)
                ->where('events.start', '<', $end)
                ->where('events.end', '>', $start)
                ->get()
                ->toArray();

            $this->other_events = json_decode(json_encode($others), true);
                // ->toSql();
                
                // ->toArray();
            // dd($this->other_events, 'na');
            // dd($this->other_events, $start, $end, $this->state['user_id'], $this->groupId);
        }
    }

    public function render()
    {
        if($this->error !== false) {
            return view('livewire.default', ['error' => $this->error]);
        }

        if($this->pleaseWait === true) {
            return view('livewire.events.pleasewait');
        } else {
            return view('livewire.events.event-edit');
        }
    }
}
