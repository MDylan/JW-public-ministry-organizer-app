<?php

namespace App\Http\Livewire\Events;

use App\Http\Livewire\AppComponent;
use App\Models\DayStat;
use App\Models\Event;
use App\Models\Group;
use App\Models\GroupDate;
use App\Models\GroupDayDisabledSlots;
use Carbon\Carbon;
use DateTime;
// use App\Models\User;
use Illuminate\Support\Facades\Auth;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Support\Facades\DB;

class Events extends AppComponent
{

    public $year = 0;
    public $month = 0;
    // public $calendar = [];
    public $pagination = [];
    private $current_month = "";
    private $groups = [];
    private $cal_service_days = [];
    private $cal_group_data = [];
    public $form_groupId = 0;
    // public $cal_day_data = [
    //     'date' => 0,
    //     'dateFormat' => 0,
    //     'table' => [],
    //     'selects' => [
    //         'start' => [],
    //         'end' => [],
    //     ],
    // ];
    // public $cal_original_day_data = [];
    public $listeners = [
        'openModal', 
        'refresh' => 'render',
        'pollingOn',
        'pollingOff',
        'openEventsModal'
    ];
    // public $cal_active_tab = '';
    private $first_day = null;
    private $last_day = null;
    private $day_stat = [];
    private $userEvents = [];
    public $polling = true;

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

    function build_pagination($created_at) {
 
        $prevMonth = $this->month - 1;         
        if ($prevMonth == 0) {
            $prevMonth = 12;
        }         
        if ($prevMonth == 12){  
            $prevYear = $this->year - 1;
        } else {
            $prevYear = $this->year;
        }
        $created = strtotime(date("Y-m-01", strtotime($created_at)));
        $prev = strtotime($prevYear."-".$prevMonth."-01");
        if($prev < $created) {
            $prevYear = false;
            $prevMonth = false;
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
        $this->getGroups();
        $key = array_search($this->form_groupId, array_column($this->groups, 'id'));
        // $group = Auth()->user()->groupsAccepted()->wherePivot('group_id', $this->form_groupId)->firstOrFail()->toArray();
        // if($group['pivot']['group_id']) {
        if($key !== false) {
            session(['groupId' => $this->form_groupId]);
            $this->emitTo('events.modal', 'setGroup', $this->form_groupId);
        }         
    }
    
    public function getGroupData() {
        $this->cal_service_days = [];
        $groupId = session('groupId');
        try {
            //mybe logout the current session group
            $group = Group::findOrFail($groupId);
            $days = $group->days()->get()->toArray();
            if(count($days)) {
                foreach($days as $day) {
                    $this->cal_service_days[$day['day_number']] = [
                        'start_time' => $day['start_time'],
                        'end_time' => $day['end_time'],
                    ];
                }
            }
            $this->cal_group_data = $group->whereId($groupId)->first()->toArray();
        } catch(ModelNotFoundException $e)
        {
            $first = Auth()->user()->groupsAccepted()->first()->toArray();
            if(isset($first['id'])) {
                session(['groupId' => $first['id']]);
                $this->reset();
            } else {
                abort('404');
            }
        }        
    }

    public function getStat($specialDates) {
        $groupId = session('groupId');

        $disabled_slots = [];
        $d_slots = GroupDayDisabledSlots::where('group_id', '=', $groupId)
            ->orderBy('day_number', 'asc')
            ->orderBy('slot', 'asc')
            ->get()->toArray();
        foreach($d_slots as $slot) {
            $disabled_slots[$slot['day_number']][$slot['slot']] = $slot['slot'];
        }

        $stats = DayStat::where('group_id', $groupId)
                            ->whereBetween('day', [$this->first_day, $this->last_day])
                            ->orderBy('time_slot')
                            ->get()
                            ->toArray();
        $colors = $dayOfWeeks = [];
        // dd($stats);
        foreach($stats as $stat) {
            if(!isset($dayOfWeeks[$stat['day']])) {
                $d = new DateTime( $stat['day'] );
                $dayOfWeek = $d->format("w");
                $dayOfWeeks[$stat['day']] = $dayOfWeek;
            } else {
                $dayOfWeek = $dayOfWeeks[$stat['day']];
            }

            $min_publishers = $specialDates[$stat['day']]['date_min_publishers'];
            $max_publishers = $specialDates[$stat['day']]['date_max_publishers'];
            $color = $this->cal_group_data['colors']['color_empty']; //green
            if($stat['events'] > 0 && $stat['events'] < $min_publishers) {
                $color = $this->cal_group_data['colors']['color_someone']; //blue
            }
            if($stat['events'] >= $min_publishers) {
                $color =  $this->cal_group_data['colors']['color_minimum']; //yellow
            } 
            if($stat['events'] == $max_publishers) {
                $color = $this->cal_group_data['colors']['color_maximum']; //red
            }
            $slot_key = Carbon::parse($stat['time_slot'])->format("H:i");
            if(($disabled_slots[$dayOfWeek][$slot_key] ?? false)) {
                $color = $this->cal_group_data['colors']['color_default'];
            }
            $colors[$stat['day']][] = $color;
        }
        if(count($colors)) {
            $total_percent = [];
            foreach($colors as $day => $values) {
                $total = count($values);
                $percent = round(100 / $total, 3);
                $total_percent[$day] = 0;
                $pos = 0;
                $this->day_stat[$day] = "linear-gradient(to right";
                foreach($values as $k => $color) {
                    // $this->day_stat[$day] .= ", ".$color." ".$percent."% ".$pos."%";
                    $this->day_stat[$day] .= ", ".$color." ".$pos."% ".($pos + $percent)."%";
                    $pos+=$percent;
                    $total_percent[$day]+=$percent;
                }
                $this->day_stat[$day] .= ");";
            }
        }
        // dd($this->day_stat, $total_percent);
    }

    public function getGroups() {
        $this->groups = Auth()->user()->groupsAcceptedFiltered()
                                ->with(['dates' => function($q) {
                                    $q->whereBetween('date', [$this->first_day, $this->last_day]);
                                }])
                                ->get()->toArray();
    }

    public function pollingOn() {
        $this->polling = true;
    }

    public function pollingOff() {
        $this->polling = false;
    }

    public function openEventsModal($date) {
        // dd($date);
        $this->emitTo('events.modal', 'openModal', $date, $this->form_groupId);
        $this->polling = false;
    }

    public function render()
    {
        // $this->calendar = [];
        // dd($this->day_data);
        $this->day_stat = [];
        $this->cal_service_days = [];
        $this->cal_group_data = [];
        // What is the first day of the month in question?
        $firstDayOfMonth = mktime(0,0,0,$this->month,1, $this->year);
        $this->current_month = date('F', $firstDayOfMonth);
        $this->first_day = date("Y-m-d", $firstDayOfMonth);
        $this->last_day = date("Y-m-t", $firstDayOfMonth);
        
        // $groups =  User::findOrFail(Auth::id());
        $this->getGroups();
        // dd($this->groups);
        if(count($this->groups) == 0) {
            return view('livewire.default', [
                'error' => __('group.notInGroup')
            ]);
        }

        if(!session('groupId')) {
            // $first = $groups->groupsAccepted()->first()->toArray();
            session(['groupId' => $this->groups[0]['id']]);
        }
        $groupId = session('groupId');
        $this->form_groupId = $groupId;

        $key = array_search($groupId, array_column($this->groups, 'id'));
        if($key === false/* && count($this->groups) == 0*/) {
            if(count($this->groups) == 0) {
                abort('404');
            } else {
                session(['groupId' => $this->groups[0]['id']]);
                $groupId = session('groupId');
                $this->form_groupId = $groupId;
            }
        }
        $this->cal_group_data = $this->groups[$key];
        // dd($this->cal_group_data);
        $days = $this->cal_group_data['days'];
        if(count($days)) {
            foreach($days as $day) {
                $this->cal_service_days[$day['day_number']] = [
                    'start_time' => $day['start_time'],
                    'end_time' => $day['end_time'],
                ];
            }
        }
        $specialDates = [];
        $specialDatesList = [];
        if(count($this->cal_group_data['dates'])) {
            foreach($this->cal_group_data['dates'] as $date) {
                $specialDates[$date['date']] = $date;
                if($date['date_status'] != 1) {
                    $specialDatesList[] = $date;
                }
            }
        }
        // dd($specialDatesList);
        // dd($key." ".$groupId, $this->groups);
        // $this->getGroupData();
        $this->build_pagination($this->cal_group_data['created_at']);

        // dd($this->cal_service_days);

        $calendar = [];

        

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
        $max_day = strtotime('+'.$this->cal_group_data['max_extend_days'].' days');
        $this->cal_group_data['max_day'] = date("Y-m-d", $max_day);
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
            $available = false;
            $service_day = false;
            $this->day_stat[$date] = $this->cal_group_data['colors']['color_default'].";"; // (empty($this->cal_group_data['color_default']) ? "#cecece" : $this->cal_group_data['color_default']).";";
            // $available = ($timestamp >= $today && $timestamp <= $max_day);
            // $service_day = (isset($this->cal_service_days[$weekDays[$dayOfWeek]])) ? true : false;
            if(isset($this->cal_service_days[$weekDays[$dayOfWeek]])) {
                $this->day_stat[$date] = $this->cal_group_data['colors']['color_empty'].";";
            }

            if(isset($specialDates[$date])) {
                if($specialDates[$date]['date_status'] == 0) {
                    $available = false;
                    $service_day = false;
                    // unset($this->day_stat[$date]);
                    $this->day_stat[$date] = $this->cal_group_data['colors']['color_default'].";";
                } else {
                    $available = true;
                    $service_day = true;
                    $this->day_stat[$date] = $this->cal_group_data['colors']['color_empty'].";";
                }
            } elseif($timestamp >= $today && isset($this->cal_service_days[$weekDays[$dayOfWeek]])) {
                //create day data if it's not exists
                $end_date = $date;
                if(strtotime($this->cal_service_days[$weekDays[$dayOfWeek]]['end_time']) == strtotime("00:00")) {
                    $end_date = Carbon::parse($date)->addDay()->format("Y-m-d");
                }
                GroupDate::create([
                    'group_id' => $groupId,
                    'date' => $date,
                    'date_start' => $date." ".$this->cal_service_days[$weekDays[$dayOfWeek]]['start_time'].":00",
                    'date_end' => $end_date." ".$this->cal_service_days[$weekDays[$dayOfWeek]]['end_time'].":00",
                    'date_status' => 1,
                    'date_min_publishers' => $this->cal_group_data['min_publishers'],
                    'date_max_publishers' => $this->cal_group_data['max_publishers'],
                    'date_min_time' => $this->cal_group_data['min_time'],
                    'date_max_time' => $this->cal_group_data['max_time']
                ]);
                
                $available = true;
                $service_day = true;
                $this->day_stat[$date] = $this->cal_group_data['colors']['color_empty'].";";
            }
            if(isset($this->cal_service_days[$weekDays[$dayOfWeek]])) {
                $specialDates[$date] = [
                    'date_min_publishers' => $this->cal_group_data['min_publishers'],
                    'date_max_publishers' => $this->cal_group_data['max_publishers'],
                    'date_min_time' => $this->cal_group_data['min_time'],
                    'date_max_time' => $this->cal_group_data['max_time']
                ];
            }

            if($timestamp > $max_day) {
                $available = false;
                $service_day = false;
            }

            $calendar[$row][] = [
                'colspan' => null,
                'weekDay' => $weekDays[$dayOfWeek],
                'day' => $currentDay,
                'current' => $date == date("Y-m-d") ? true : false,
                'fullDate' => $date,
                'available' => $available,
                'service_day' => $service_day
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

        // dd($specialDates);
        $this->getStat($specialDates);
        $this->userEvents = [];
        $userEvents = Event::where([
            'group_id' => $this->cal_group_data['id'],
            'user_id' => Auth::id(),
        ])
        ->whereIn('status', [0,1])
        ->whereBetween('day', [$this->first_day, $this->last_day])
        ->get()->toArray();
        foreach($userEvents as $ev) {
            $this->userEvents[$ev['day']] = true;
        }

        $notAcceptedEvents = [];
        if(in_array($this->cal_group_data['pivot']['group_role'], ['admin', 'roler', 'helper'])) {
            $notAcceptedEvents = DB::table('events')
                                    ->groupBy('day')
                                    ->whereNull('deleted_at')
                                    ->where('group_id', '=', $this->cal_group_data['id'])
                                    ->where('status', '=', 0)
                                    ->whereBetween('day', [$this->first_day, $this->last_day])
                                    ->pluck('day', 'day')
                                    ->toArray();
        }
        // dd($calendar);
        return view('livewire.events.events', [
            'service_days' => $this->cal_service_days,
            'calendar' => $calendar,
            'specialDatesList' => $specialDatesList,
            'notAcceptedEvents' => $notAcceptedEvents,
            'group_days' => is_array(trans('group.days')) ? trans('group.days') : range(0,6,1),
            'current_month' => $this->current_month,
            'cal_group_data' => $this->cal_group_data,
            'day_stat' => $this->day_stat,
            'userEvents' => $this->userEvents,
            'groups' => $this->groups,
            'group_editor' => in_array($this->cal_group_data['pivot']['group_role'], ['admin', 'roler']) ? true : false
        ]);

        // return view('livewire.events.calendar');
    }
}
