<?php

namespace App\Http\Livewire\Events;

use App\Http\Livewire\AppComponent;
use App\Models\Event;
use App\Models\Group;
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
    private $day_events = [];
    public $group_data = [];
    // public $disabled_slots = [];
    public $listeners = [
        'setStart',
        'createForm',
        'editForm',
        'deleteConfirmed'
    ];

    public function mount($groupId, $date) {
        $this->groupId = $groupId;
        $this->date = $date;
        $this->state = [];
        $this->createForm();
    }

    public function createForm() {
        $this->eventId = null;
        $this->getInfo();
        // dd($this->day_data['selects']);
        $this->formText['title'] = __('event.create_event');
    }

    public function editForm($eventId) {
        $this->eventId = $eventId;
        $this->getInfo();
        $this->change_start();
        $this->change_end();
        $this->formText['title'] = __('event.edit_event');
    }

    public function getInfo() {
        if($this->eventId == null) $this->editEvent = null;

        $groupId = $this->groupId;
        $date = $this->date;

        $group = Group::findOrFail($groupId);
    
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

        $start = strtotime($date." ".$this->service_days[$dayOfWeek]['start_time'].":00");
        $max = strtotime($date." ".$this->service_days[$dayOfWeek]['end_time'].":00");

        $step = $this->group_data['min_time'] * 60;

        $day_table = [];
        $day_selects = [];
        $day_events = [];
        
        $row = 1;
        for($current=$start;$current < $max;$current+=$step) {
            $key = "'".date('Hi', $current)."'";
            $day_table[$key] = [
                'ts' => $current,
                'hour' => date("H:i", $current),
                'row' => $row,
                'status' => 'free',
                'publishers' => 0,
            ];
            for ($i=1; $i <= $this->group_data['max_publishers']; $i++) { 
                $day_table[$key]['cells'][$i] = true;
            }
            
            $day_selects['start'][$current] = date("H:i", $current);
            if($current != $start)
                $day_selects['end'][$current] = date("H:i", $current);

            $row++;
        }
        $day_selects['end'][$max] = date("H:i", $max);
        // dd($day_table);
        //események
        $events = $group->day_events($date)->get()->toArray();
                
        $slots = [];
        $disabled_slots = [];
        
        foreach($events as $event) {

                if($this->eventId == $event['id']) {
                    $this->editEvent = $event;
                    $this->state = $event;
                    continue;
                }

            $steps = ($event['end'] - $event['start']) / $step;
            
            $row = $day_table["'".date('Hi', $event['start'])."'"]['row'];
            $key = "'".date('Hi', $event['start'])."'";
            $cell = 1;
            if(isset($slots[$key])) {
                $cell = min(array_keys($day_table[$key]['cells']));
            }
            
            $day_events[$event['id']] = $event;
            $day_events[$event['id']]['time'] = date("H:i", $event['start'])." - ".date("H:i", $event['end']);
            $day_events[$event['id']]['height'] = $steps;
            $day_events[$event['id']]['cell'] = $cell;
            $day_events[$event['id']]['row'] = $row;
            $day_events[$event['id']]['start_time'] = date("H:i", $event['start']);
            $day_events[$event['id']]['end_time'] = date("H:i", $event['end']);
            $cell_start = $event['start'];
            for($i=0;$i < $steps;$i++) {
                $slot_key = "'".date("Hi", $cell_start)."'";
                $slots[$slot_key][] = true;
                unset($day_table[$slot_key]['cells'][$cell]);
                $day_table[$slot_key]['publishers']++;
                if(Auth::id() == $event['user_id']) {
                    $disabled_slots[$slot_key] = true;
                }
                $cell_start += $step;
            }
        }
        
        //kiszűröm ami nem elérhető
        foreach($slots as $key => $times) {
            if(count($times) >= $this->group_data['max_publishers'] || $disabled_slots[$key]) {
                $day_table[$key]['status'] = 'full';
                $k = $day_table[$key]['ts'];
                unset($day_selects['start'][$k]);
                unset($day_selects['end'][$k + $step]);
            } elseif(count($times) >= $this->group_data['min_publishers']) {
                $day_table[$key]['status'] = 'ready';
            } 
        }
        

        $this->day_data['table'] = $day_table;
        $this->day_data['selects'] = $day_selects;
        $this->original_day_data = $this->day_data;
        $this->day_events = $day_events;
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

        $min_time = $this->state['end'] - ($this->group_data['max_time'] * 60);
        $step = $this->group_data['min_time'] * 60;
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
        $max_time = $this->state['start'] + ($this->group_data['max_time'] * 60);
        $step = $this->group_data['min_time'] * 60;
        // dd('here', date("Y.m.d H:i", $this->state['start']));  
        if(count($this->original_day_data['selects']['end'])) {
            $this->day_data['selects']['end'] = [];
            foreach($this->original_day_data['selects']['end'] as $key => $value) {
                //kiszűröm azokat, amik egyébként nem folytonosan jönnek
                if(isset($last_key) && $key > ($last_key + $step)) continue;

                if($key > $this->state['start'] && $key <= $max_time) {
                    $this->day_data['selects']['end'][$key] = $value;
                    $last_key = $key;
                }
            }
        }
        // dd(count($this->day_data['selects']['end']));
        if(count($this->day_data['selects']['end']) == 1) {
            $this->state['end'] = array_key_last($this->day_data['selects']['end']);
        }
    }

    public function cancelEdit() {
        $this->eventId = null;
        $this->editEvent = null;
    }

    public function saveEvent() {
        //check if time still ok or not
        $this->getInfo();
        $step = $this->group_data['min_time'] * 60;
        $publishers_ok = true;
        for ($i=$this->state['start']; $i <=$this->state['end'] ; $i+=$step) {
            $slot_key = "'".date("Hi", $i)."'";
            if($this->day_data['table'][$slot_key]['publishers'] >= $this->group_data['max_publishers']) {
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
            'user_id' => Auth::id(),
            'accepted_at' => date("Y-m-d H:i:s"),
            'accepted_by' => Auth::id()
        ];
        $v = Validator::make($data, [
            'user_id' => 'required|exists:App\Models\User,id',
            'start' => 'required|numeric|lte:end', 
            'end' => 'required|numeric|gte:start',
            'day' => 'required|date_format:Y-m-d',
            'accepted_by' => 'sometimes|required|exists:App\Models\User,id',
            'accepted_at' => 'sometimes|required|date_format:Y-m-d H:i:s'
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

        // dd($validatedData);
        if($this->editEvent !== null) {
            //update event
            $group->events()->whereId($this->editEvent['id'])->update(
                $validatedData
            );
        } else {
            //save new event
            $event = new Event($validatedData);
            $group->events()->save($event); 
        }
        
        
        
        $this->emitUp('refresh');
        $this->emitTo('partials.events-bar', 'refresh');
        $this->dispatchBrowserEvent('success', ['message' => __('event.saved')]);
        
        // $this->dispatchBrowserEvent('hide-form', ['message' => __('event.saved')]);
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
    }

    public function render()
    {
        return view('livewire.events.event-edit');
    }
}
