<?php

namespace App\Http\Livewire\Events;

use App\Http\Livewire\AppComponent;
use App\Models\Event;
use App\Models\Group;
use App\Models\GroupDate;
use App\Models\GroupUser;
use DateTime;
use Illuminate\Support\Facades\Auth;
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
        // $groupId = $this->groupId;
        // $date = $this->date;
        $this->getRole();

        // $group = Group::findOrFail($groupId);

        if($this->eventId !== null) {
            // $editEvent_old = $group->day_events($date)->whereId($this->eventId)->firstOrFail()->toArray();
            // $event = $group->load('events.histories')->firstWhere('id', $this->eventId);
            $editEvent = Event::with(['user', 'accept_user', 'histories.user'])->firstWhere('id', $this->eventId)->toArray();
            // dd($editEvent);
            // dd($editEvent_old, $editEvent);

            if(!in_array($this->role, ['admin', 'roler', 'helper']) 
                && $editEvent['user_id'] !== Auth::id()) {
                    $this->error = __('event.error.no_permission');
                    $this->cancelEdit();
                return;
            }            
            $this->editEvent = $editEvent;
            $this->state['user_id'] = $editEvent['user_id'];
        }

        $this->getInfo();
        $this->change_start();
        $this->change_end();
        $this->formText['title'] = __('event.edit_event');
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
            $this->users = $group->users()->get()->toArray();
        }
    
        $this->day_data = [];
        $this->service_days = [];
        $d = new DateTime( $date );
        $dayOfWeek = $d->format("w");
        $this->day_data['date'] = $date;
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

        

        if($this->group_data['current_date'] === null) {
            $start = strtotime($date." ".$this->service_days[$dayOfWeek]['start_time'].":00");
            $max = strtotime($date." ".$this->service_days[$dayOfWeek]['end_time'].":00");
            GroupDate::create([
                'group_id' => $groupId,
                'date' => $date,
                'date_start' => date("Y-m-d H:i:s", $start),
                'date_end' => date("Y-m-d H:i:s", $max),
                'date_status' => 1,
                'date_min_publishers' => $this->group_data['min_publishers'],
                'date_max_publishers' => $this->group_data['max_publishers'],
                'date_min_time' => $this->group_data['min_time'],
                'date_max_time' => $this->group_data['max_time']
            ]);
            $this->date_data = [
                'min_publishers' => $this->group_data['min_publishers'],
                'max_publishers' => $this->group_data['max_publishers'],
                'min_time' => $this->group_data['min_time'],
                'max_time' => $this->group_data['max_time']
            ];
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
        for($current=$start;$current < $max;$current+=$step) {
            $key = "'".date('Hi', $current)."'";
            $day_table[$key] = [
                'ts' => $current,
                'hour' => date("H:i", $current),
                'row' => $row,
                'status' => 'free',
                'publishers' => 0,                
                'accepted' => 0,
            ];
            for ($i=1; $i <= config('events.max_columns'); $i++) {
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
                
        $slots = [];
        $disabled_slots = [];
        
        foreach($events as $event) {
            if($this->eventId == $event['id']) {
                if($saveProcess === false) {
                    $this->state = $event;
                    // $this->state['status'] = ($this->group_data['need_approval'] == 1 && is_null($event['accepted_at'])) ? 0 : 1;
                }
                continue;
            }

            $steps = ($event['end'] - $event['start']) / $step;     
            
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
        // dd($slots, $day_selects);
        //filter out what not available
        foreach($slots as $key => $times) {
            if(count($times) >= ($this->group_data['need_approval'] 
                    ? ($day_table[$key]['accepted'] >= $this->date_data['max_publishers'] 
                            ? $this->date_data['max_publishers'] 
                            : config('events.max_columns'))
                        : $this->date_data['max_publishers']) 
                    || isset($disabled_slots[$key])) {
                $day_table[$key]['status'] = 'full';
                $k = $day_table[$key]['ts'];
                unset($day_selects['start'][$k]);
                unset($day_selects['end'][$k + $step]);
            } elseif($day_table[$key]['accepted'] >= $this->date_data['min_publishers']) {
                $day_table[$key]['status'] = 'ready';
            } 
        }        
        // dd($temp, $slots, $day_selects, $disabled_slots);
        $this->day_data['table'] = $day_table;
        $this->day_data['selects'] = $day_selects;
        $this->original_day_data = $this->day_data;
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
        $this->change_end();        
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
    }

    public function cancelEdit() {
        $this->eventId = null;
        $this->editEvent = null;
        $this->state = null;
        $this->state['user_id'] = Auth::id();
    }

    public function saveEvent() {
        //check if time still ok or not
        // dd($this->state);
        $this->getRole();
        $this->getInfo(true);

        if($this->editEvent !== null) {
            if(!in_array($this->role, ['admin', 'roler', 'helper']) 
                && $this->editEvent['user_id'] !== Auth::id()) {
                    $this->error = __('event.error.no_permission');
                    return;
            }  
        }

        //check the start/end date 
        Validator::make($this->state, [
            'start' => 'required|numeric|lte:end', 
            'end' => 'required|numeric|gte:start',
        ])->validate();
        // dd($this->day_data['table']);
        $step = $this->date_data['min_time'] * 60;
        $publishers_ok = true;
        for ($i=$this->state['start']; $i < $this->state['end'] ; $i+=$step) {
            $slot_key = "'".date("Hi", $i)."'";
            if($this->day_data['table'][$slot_key]['publishers'] >= (
                $this->group_data['need_approval'] 
                    ? ($this->day_data['table'][$slot_key]['accepted'] >= $this->date_data['max_publishers'] 
                        ? $this->date_data['max_publishers'] 
                        : config('events.max_columns'))
                    : $this->date_data['max_publishers'])) {
                $publishers_ok = false;                
            }
        }
        //check valid time, maybe user modified it in browser
        $invalid = [];
        if(!isset($this->day_data['selects']['start'][$this->state['start']]))
            $invalid['start'] = true;
        if(!isset($this->day_data['selects']['end'][$this->state['end']]))
            $invalid['end'] = true;

        $group = Group::findOrFail($this->groupId);
        
        $data = [
            'day' => $this->day_data['date'],
            'start' => $this->state['start'],
            'end' => $this->state['end'],
            'user_id' => $this->editEvent !== null ? $this->editEvent['user_id'] : $this->state['user_id'],
            'accepted_at' => null,
            'accepted_by' => null, //$group->need_approval ? null : Auth::id()
            'status' => 0
        ];
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
        ]);
        
        $v->after(function ($validator) use ($publishers_ok, $invalid) {
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
        });

        $validatedData = $v->validate();
        $validatedData['start'] = date("Y-m-d H:i", $validatedData['start']);
        $validatedData['end'] = date("Y-m-d H:i", $validatedData['end']);
        // $validatedData['group_id'] = $this->groupId;
        
        if($this->editEvent !== null) {
            //update event
            $exists = $group->events()->whereId($this->editEvent['id']);
            if($exists !== null) {
                $event = Event::findOrFail($this->editEvent['id']);
                $event->update($validatedData);
            }

            // $group->events()->whereId($this->editEvent['id'])->update(
            //     $validatedData
            // );
            $this->cancelEdit();
        } else {
            //save new event
            $event = new Event($validatedData);
            $group->events()->save($event); 
        }

        //IMPORTANT: Check observers too

        $this->emitUp('refresh');
        $this->emitTo('partials.events-bar', 'refresh');
        $this->dispatchBrowserEvent('success', ['message' => __('event.saved')]);
        $this->pleaseWait = true;
    }

    public function confirmEventDelete() {
        $this->dispatchBrowserEvent('show-eventDelete-confirmation');
    }

    public function deleteConfirmed() {
        $event = Event::findOrFail($this->eventId);
        $res = $event->delete();
        if($res) {
            $this->dispatchBrowserEvent('success', ['message' => __('event.confirmDelete.success')]);
        } else {
            $this->dispatchBrowserEvent('error', ['message' => __('event.confirmDelete.error')]);
        }
        $this->emitTo('partials.events-bar', 'refresh');
        $this->emitUp('refresh');
        $this->pleaseWait = true;
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
