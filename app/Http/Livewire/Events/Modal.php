<?php

namespace App\Http\Livewire\Events;

use App\Http\Livewire\AppComponent;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Facades\Auth;
use DateTime;
use App\Models\User;
use App\Models\Group;
use App\Models\Event;


class Modal extends AppComponent
{

    // public $group;
    private $service_days = [];
    public $group_data = [];
    public $day_data = [];
    public $day_events = [];

    public $form_groupId = 0;
    public $original_day_data = [];
    public $listeners = ['openModal', 'refresh'];
    public $active_tab = '';
    public $date = null;
    public $event_edit = [];
    public $all_select = [];

    public function mount($groupId = 0) {
        if($groupId > 0) {
            $check = auth()->user()->userGroups()->whereId($groupId);
            
            if(!$check) {
                abort('403');
            }
            $this->form_groupId = $groupId;
            $this->day_data =  [
                'date' => 0,
                'dateFormat' => 0,
                'table' => [],
                'selects' => [
                    'start' => [],
                    'end' => [],
                ],
            ];
        }
    }

    //dont delete, it's a listener
    public function refresh() {
        $this->getInfo();
    }

    public function getInfo() {
        $this->active_tab = '';
        
        // $this->setVars();

        $groupId = $this->form_groupId;
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
        
        foreach($events as $event) {
            $steps = ($event['end'] - $event['start']) / $step;
            
            $row = $day_table["'".date('Hi', $event['start'])."'"]['row'];
            $key = "'".date('Hi', $event['start'])."'";
            // $cell = 2;
            $cell = 1;
            if(isset($slots[$key])) {
                // $cell = count($slots[$key]) + 2;
                $cell = min(array_keys($day_table[$key]['cells']));
                // $cell = $day_table[$key]['publishers'] + 2;
                
                // $table[$key]['available'] = count($slots[$key]);
            }
            
            $day_events[$key][$event['id']] = $event;
            $day_events[$key][$event['id']]['time'] = date("H:i", $event['start'])." - ".date("H:i", $event['end']);
            $day_events[$key][$event['id']]['height'] = $steps;
            $day_events[$key][$event['id']]['cell'] = $cell;
            $day_events[$key][$event['id']]['row'] = $row;
            $day_events[$key][$event['id']]['start_time'] = date("H:i", $event['start']);
            $day_events[$key][$event['id']]['end_time'] = date("H:i", $event['end']);
            $cell_start = $event['start'];
            for($i=0;$i < $steps;$i++) {
                $slot_key = "'".date("Hi", $cell_start)."'";
                //if($i == 0)
                $slots[$slot_key][] = true;
                unset($day_table[$slot_key]['cells'][$cell]);
                $day_table[$slot_key]['publishers']++;
                $cell_start += $step;
            }
        }
        
        //kiszűröm ami nem elérhető
        foreach($slots as $key => $times) {
            if(count($times) >= $this->group_data['max_publishers']) {
                $day_table[$key]['status'] = 'full';
                $k = $day_table[$key]['ts'];
                unset($day_selects['start'][$k]);
                unset($day_selects['end'][$k + $step]);
            } elseif(count($times) >= $this->group_data['min_publishers']) {
                $day_table[$key]['status'] = 'ready';
            } 
        }
        
        // dd($day_table);
        // dd($this->group->day_events($date));
        $this->day_data['table'] = $day_table;
        $this->day_data['selects'] = $day_selects;
        $this->original_day_data = $this->day_data;
        $this->day_events = $day_events;
    }

    public function openModal($date) {
        $this->date = $date;        
        $this->getInfo();
        $this->dispatchBrowserEvent('show-form');
    }

    public function setStart($time) {
        $this->active_tab = 'event';
        $this->emitTo('events.event-edit', 'setStart', $time);
        // $this->state['start'] = $time;
        // $this->change_end();
    }
/*
    public function change_end() {
        $this->active_tab = 'event';
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
    }

    public function change_start() {
        $this->active_tab = 'event';

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
    
    public function createEvent() {
        // $groupId = session('groupId');
        $group = Group::findOrFail($this->form_groupId);

        $data = [
            'day' => $this->day_data['date'],
            'start' => date("Y-m-d H:i", $this->state['start']),
            'end' => date("Y-m-d H:i", $this->state['end']),
            'user_id' => Auth::id(),
            'accepted_at' => date("Y-m-d H:i:s"),
            'accepted_by' => Auth::id()
        ];

        // dd($data);

        $validatedData = Validator::make($data, [
            'user_id' => 'required|exists:App\Models\User,id',
            'start' => 'required|date_format:Y-m-d H:i|before:end',
            'end' => 'required|date_format:Y-m-d H:i|after:start',
            'day' => 'required|date_format:Y-m-d',
            'accepted_by' => 'sometimes|required|exists:App\Models\User,id',
            'accepted_at' => 'sometimes|required|date_format:Y-m-d H:i:s'
        ])->validate();

        // dd($validatedData);

        $event = new Event($validatedData);
        
        $group->events()->save($event); 
        $this->dispatchBrowserEvent('hide-form', ['message' => __('event.saved')]);
    }
    */

    public function editEvent_modal($id) {

        $this->active_tab = 'event';
        $this->emitTo('events.event-edit', 'editForm', $id);
/*
        return;

        $this->active_tab = 'event_edit';

        $event = Event::findOrFail($id)->toArray();
        // dd($this->day_data['table']);
        $this->event_edit = $event;
        $this->day_data['edit_selects'] = $this->day_data['selects'];
        $this->day_data['edit_selects']['start'][$event['start']] = date("H:i", $event['start']);
        $start = $event['start'];
        $max = $event['end'];

        $step = $this->group_data['min_time'] * 60;
        for($current=$start;$current <= $max;$current+=$step) {
            $this->day_data['edit_selects']['start'][$current] = date("H:i", $current);
            if($current != $start)
                $this->day_data['edit_selects']['end'][$current] = date("H:i", $current);
        }

        $min = $event['end'] - ($this->group_data['max_time'] * 60);
        $max = $event['start'] + ($this->group_data['max_time'] * 60);

        $selects = $this->day_data['edit_selects']['start'];
        krsort($selects);
        $last_ts = 0;
        $starts = [];
        foreach($selects as $ts => $time) {
            if($ts <= $event['end']) {
                if($last_ts == 0 || $ts + $step == $last_ts) {
                    $starts[$ts] = $time;
                } else {
                    if($last_ts > 0) break;
                }
                $last_ts = $ts;
            }               
        }
        ksort($starts);

        $selects = $this->day_data['edit_selects']['end'];
        ksort($selects);
        $last_ts = 0;
        $ends = [];
        foreach($selects as $ts => $time) {
            if($ts > $event['start']) {
                if($last_ts == 0 || $ts - $step == $last_ts) {
                    $ends[$ts] = $time;
                } else {
                    // if($last_ts > 0) break;
                    $ends[$time] = "".($ts - $step);
                }
                $last_ts = $ts;
            }               
        }
        // ksort($starts);


        dd($event['start'], $selects, $starts, $ends);

        $starts = array_filter($selects, function($k) use ($min, $max) {
            return $k >= $min && $k <= $max;
        }, ARRAY_FILTER_USE_KEY);
        ksort($starts);

        dd($starts);
*/
        // $this->day_data['edit_selects']['end'][$event['end']] = date("H:i", $event['end']);
    }


    public function cancelEdit() {
        $this->active_tab = '';
        $this->event_edit = null;
    }

    public function render()
    {
        if($this->date !== null) {

            
            
            // dd($this->day_data);

            return view('livewire.events.modal', [
                'group_data' => $this->group_data,
                'service_days' => $this->service_days,
                // 'day_events' => $this->day_events,
                'day_data' => $this->day_data
            ]);
        }
        
        return <<<'blade'
        <div></div>
        blade;
    }
}
