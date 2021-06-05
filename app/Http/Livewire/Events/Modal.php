<?php

namespace App\Http\Livewire\Events;

use App\Http\Livewire\AppComponent;
use App\Models\DayStat;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Facades\Auth;
use DateTime;
use App\Models\User;
use App\Models\Group;
use App\Models\Event;


class Modal extends AppComponent
{

    // public $group;
    public $service_days = [];
    public $group_data = [];
    public $day_data = [];
    public $day_events = [];

    public $form_groupId = 0;
    public $original_day_data = [];
    public $listeners = [
        'openModal', 
        'refresh', 
        'cancelEdit',
        'setGroup' => 'mount'
    ];
    public $active_tab = '';
    public $date = null;
    public $event_edit = [];
    public $all_select = [];
    private $weekdays = [
        1 => 'monday',
        2 => 'tuesday',
        3 => 'wednesday',
        4 => 'thursday',
        5 => 'friday',
        6 => 'saturday',
        0 => 'sunday'
    ];
    private $day_stat = [];

    public function mount($groupId = 0) {
        $this->date = null;
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

        $stat = DayStat::where([
            'group_id' => $this->form_groupId,
            'day' => $this->date
        ]);
        $stat->delete();

        DayStat::insert(
            $this->day_stat
        );

        $this->emitUp('refresh');

    }

    // public function setGroup($groupId) {
    //     $check = auth()->user()->userGroups()->whereId($groupId);            
    //     if(!$check) {
    //         abort('403');
    //     }
    //     $this->form_groupId = $groupId;
    // }

    public function getInfo() {
        $this->active_tab = '';
        
        // $this->setVars();

        $groupId = $this->form_groupId;
        $date = $this->date;

        $group = Group::findOrFail($groupId);
    
        $this->day_data = [];
        $this->service_days = [];
        $this->day_stat = [];
        $d = new DateTime( $date );
        $dayOfWeek = $d->format("w");
        $this->day_data['date'] = $date;
        $this->day_data['dateFormat'] = $d->format('Y.m.d.,')." ".__('event.weekdays_short.'.$dayOfWeek);
        
        $days = $group->days()->get()->toArray();
        $next = $prev = false;
        $days_array = [];
        if(count($days)) {
            foreach($days as $day) {
                $days_array[] = $day['day_number'];
                $this->service_days[$day['day_number']] = [
                    'start_time' => $day['start_time'],
                    'end_time' => $day['end_time'],
                ];
            }
        }

        //calculate next end previous day
        $key = array_search($dayOfWeek, $days_array);
        $aArray = $days_array;
        $aKeys = array_keys($aArray); //every element of aKeys is obviously unique
        $aIndices = array_flip($aKeys); //so array can be flipped without risk
        $i = $aIndices[$key]; //index of key in aKeys
        if ($i > 0) $prev = $aArray[$aKeys[$i-1]]; //use previous key in aArray
        if ($i < count($aKeys)-1) $next = $aArray[$aKeys[$i+1]]; //use next key in aArray
        if ($prev === false) $prev = end($aArray);
        if ($next === false) $next = $aArray[array_key_first($aArray)];

        $next_date = new DateTime($date);
        $next_date->modify("next ".$this->weekdays[$next]);
        $this->day_data['next_date'] = $next_date->format("Y-m-d");
        $prev_date = new DateTime($date);
        $prev_date->modify('last '.$this->weekdays[$prev]);
        $this->day_data['prev_date'] = $prev_date->format("Y-m-d");

        $this->group_data = $group->toArray();

        $start = strtotime($date." ".$this->service_days[$dayOfWeek]['start_time'].":00");
        $max = strtotime($date." ".$this->service_days[$dayOfWeek]['end_time'].":00");

        $step = $this->group_data['min_time'] * 60;

        $day_table = [];
        $day_selects = [];
        $day_events = [];
        $now = time();
        $row = 1;
        for($current=$start;$current < $max;$current+=$step) {
            $key = "'".date('Hi', $current)."'";
            $day_table[$key] = [
                'ts' => $current,
                'hour' => date("H:i", $current),
                'row' => $row,
                'status' => ($current < $now) ? 'full' : 'free',
                'publishers' => 0,
            ];
            $this->day_stat[$key] = [
                'group_id' => $this->form_groupId,
                'day' => $this->date,
                'time_slot' => date('Y-m-d H:i', $current),
                'events' => 0
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
                
        $disabled_slots = $slots = [];
        
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
            $day_events[$key][$event['id']]['status'] = $event['start'] < $now ? 'disabled' : '';
            $cell_start = $event['start'];
            for($i=0;$i < $steps;$i++) {
                $slot_key = "'".date("Hi", $cell_start)."'";
                //if($i == 0)
                $slots[$slot_key][] = true;
                unset($day_table[$slot_key]['cells'][$cell]);
                $day_table[$slot_key]['publishers']++;
                $this->day_stat[$slot_key]['events']++;
                if(Auth::id() == $event['user_id']) {
                    $disabled_slots[$slot_key] = true;
                }
                $cell_start += $step;
            }
        }
        
        //kiszűröm ami nem elérhető
        foreach($slots as $key => $times) {
            if(count($times) >= $this->group_data['max_publishers'] || isset($disabled_slots[$key])) {
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

    public function setDate($date) {
        $this->date = $date;
        $this->getInfo();
    }

    public function setStart($time) {
        $this->active_tab = 'event';
        $this->emitTo('events.event-edit', 'setStart', $time);
        // $this->state['start'] = $time;
        // $this->change_end();
    }

    public function editEvent_modal($id) {

        $this->active_tab = 'event';
        $this->emitTo('events.event-edit', 'editForm', $id);

    }


    public function cancelEdit() {
        $this->active_tab = '';
        $this->event_edit = null;
        $this->emitTo('events.event-edit', 'createForm');
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
