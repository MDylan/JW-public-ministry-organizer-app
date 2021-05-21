<?php

namespace App\Http\Livewire\Events;

use App\Http\Livewire\AppComponent;
use App\Models\Group;
use App\Models\User;
use DateTime;
use Illuminate\Support\Facades\Auth;
use App\Models\Event;
use Illuminate\Support\Facades\Validator;

class Events extends AppComponent
{

    public $year = 0;
    public $month = 0;
    // public $calendar = [];
    public $pagination = [];
    public $current_month = "";
    public $groups = [];
    public $service_days = [];
    public $group_data = [];
    public $form_groupId = 0;
    public $day_data = [
        'date' => 0,
        'dateFormat' => 0,
        'table' => [],
        'selects' => [
            'start' => [],
            'end' => [],
        ],
    ];
    public $original_day_data = [];
    public $listeners = ['openModal'];
    public $active_tab = '';

    public function mount(int $year = 0, int $month = 0) {
        if(isset($year)) {
            if(strlen($year) == 4) {
                if($year > 2020 && $year < 9999) {
                    $this->year = $year;            
                }
            }
        } 
        if($this->year == 0)
            $this->year = date('Y');

        if(isset($month)) {
            if($month > 0 && $month < 13) {
                $this->month = $month;            
            }

        } 
        if($this->month == 0) {
            $this->month = date('m');
        }
    }

    public function openModal($date) {
        // dd($date);
        $this->day_data = [];
        

        $d = new DateTime( $date );
        // $day = $d->getTimestamp();
        // $day = strtotime($date);
        $dayOfWeek = $d->format("w");
        $this->day_data['date'] = $date;
        $this->day_data['dateFormat'] = $d->format('Y.m.d');
        // dd($d->format("w"), date('-m-d', $day));

        

        $start = strtotime($date." ".$this->service_days[$dayOfWeek]['start_time'].":00");
        $max = strtotime($date." ".$this->service_days[$dayOfWeek]['end_time'].":00");

        $step = $this->group_data['min_time'] * 60;

        $table = [];
        $selects = [];

        for($current=$start;$current < $max;$current+=$step) {
            $table[date('H:i', $current)] = $current;
            $selects['start'][$current] = date("H:i", $current);
            if($current != $start)
                $selects['end'][$current] = date("H:i", $current);
        }
        $selects['end'][$max] = date("H:i", $max);

        $this->day_data['table'] = $table;
        $this->day_data['selects'] = $selects;
        $this->original_day_data = $this->day_data;
        // dd($this->day_data);

        // dd($date, $dayOfWeek, date("Y-m-d H:i", $start), date("Y-m-d H:i", $max), $table);

        $this->dispatchBrowserEvent('show-form');
    }

    public function setStart($time) {
        $this->state['start'] = $time;
        $this->change_end();
    }

    public function change_end() {
        $this->active_tab = 'event';
        $max_time = $this->state['start'] + ($this->group_data['max_time'] * 60);
        // dd('here', date("Y.m.d H:i", $this->state['start']));  
        
        if(count($this->original_day_data['selects']['end'])) {
            $this->day_data['selects']['end'] = [];
            foreach($this->original_day_data['selects']['end'] as $key => $value) {
                if($key > $this->state['start'] && $key <= $max_time) {
                    $this->day_data['selects']['end'][$key] = $value;
                }
            }
        }
    }

    public function change_start() {
        $this->active_tab = 'event';
        $min_time = $this->state['end'] - ($this->group_data['max_time'] * 60);

        // dd('here', date("Y.m.d H:i", $this->state['start']));  
        
        if(count($this->original_day_data['selects']['start'])) {
            $this->day_data['selects']['start'] = [];
            foreach($this->original_day_data['selects']['start'] as $key => $value) {
                if($key < $this->state['end']  && $key >= $min_time) {
                    $this->day_data['selects']['start'][$key] = $value;
                }
            }
        }
    }
    
    public function createEvent() {
        $groupId = session('groupId');
        $group = Group::findOrFail($groupId);

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

    function build_pagination() {
 
        $prevMonth = $this->month - 1;         
        if ($prevMonth == 0) {
            $prevMonth = 12;
        }         
        if ($prevMonth == 12){  
            $prevYear = $this->year - 1;
        } else {
            $prevYear = $this->year;
        }

        $nextMonth = $this->month + 1;
         
        if ($nextMonth == 13) {
            $nextMonth = 1;
        }
       
        if ($nextMonth == 1){  
            $nextYear = $this->year + 1;
        } else {
            $nextYear = $this->year;
        }
        
        $this->pagination['prev'] = [
            'year' => $prevYear,
            'month' => $prevMonth
        ];
        $this->pagination['next'] = [
            'year' => $nextYear,
            'month' => $nextMonth
        ];
    }

    public function changeGroup() {
        $group = Group::findOrFail($this->form_groupId)->whereId($this->form_groupId)->first()->toArray();
        if($group['id']) {
            session(['groupId' => $this->form_groupId]);
        }
        // $this->emitTo('events.calendar', 'change');
    }
    
    public function getGroupData() {
        $this->service_days = [];
        $groupId = session('groupId');
        $group = Group::findOrFail($groupId);
        $days = $group->days()->get()->toArray();
        if(count($days)) {
            foreach($days as $day) {
                $this->service_days[$day['day_number']] = [
                    'start_time' => $day['start_time'],
                    'end_time' => $day['end_time'],
                ];
            }
        }
        $this->group_data = $group->whereId($groupId)->first()->toArray();
    }


    public function render()
    {
        // $this->calendar = [];
        // dd($this->day_data);
        
        $groups = User::findOrFail(Auth::id());
        $this->groups = $groups->userGroups()->get()->toArray();

        if(!session('groupId')) {
            $first = $groups->userGroups()->first()->toArray();
            session(['groupId' => $first['id']]);
        }
        $this->getGroupData();
        $this->build_pagination();

        $calendar = [];

        // What is the first day of the month in question?
        $firstDayOfMonth = mktime(0,0,0,$this->month,1, $this->year);
        $this->current_month = date('F', $firstDayOfMonth);

        // How many days does this month contain?
        $numberDays = date('t',$firstDayOfMonth);

        // Retrieve some information about the first day of the
        // month in question.
        $dayOfWeek = strftime("%u", $firstDayOfMonth) - 1;

        $weekDays = [
            1,2,3,4,5,6,0
        ];
        $row = 1;
        $currentDay = 1;

        if ($dayOfWeek > 0) { 
            $calendar[$row][] = [
                'colspan' => $dayOfWeek,
                'day' => '',
                'current' => '',
                'weekDay' => '',
                'fullDate' => '',
                'available' => false,
                'service_day' => false,
            ];
        }

        $month = str_pad($this->month, 2, "0", STR_PAD_LEFT);
        $today = strtotime('today');
        $max_day = strtotime('+'.$this->group_data['max_extend_days'].' days');
        // dd(date('Y-m-d', $max_day), date('Y-m-d', $today));

        while ($currentDay <= $numberDays) {
            //start new row
            if ($dayOfWeek == 7) {

                $dayOfWeek = 0;
                $row++;
            }
            
            $currentDayRel = str_pad($currentDay, 2, "0", STR_PAD_LEFT);
            
            $date = "$this->year-$month-$currentDayRel";
            $timestamp = strtotime($date);
            $available = ($timestamp >= $today && $timestamp <= $max_day);

            $calendar[$row][] = [
                'colspan' => null,
                'weekDay' => $weekDays[$dayOfWeek],
                'day' => $currentDay,
                'current' => $date == date("Y-m-d") ? true : false,
                'fullDate' => $date,
                'available' => $available,
                'service_day' => (isset($this->service_days[$weekDays[$dayOfWeek]])) ? true : false
            ];
            // Increment counters
            $currentDay++;
            $dayOfWeek++;
        }

        // Complete the row of the last week in month, if necessary

        if ($dayOfWeek != 7) { 

            $remainingDays = 7 - $dayOfWeek;

            $calendar[$row][] = [
                'colspan' => $remainingDays,
                'day' => '',
                'current' => '',
                'weekDay' => '',
                'fullDate' => '',
                'available' => false,
                'service_day' => false,
            ];
        }        
        
        // dd($calendar);
        return view('livewire.events.events', [
            'service_days' => $this->service_days,
            'calendar' => $calendar
        ]);
    }
}
