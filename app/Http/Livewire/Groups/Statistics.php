<?php

namespace App\Http\Livewire\Groups;

use App\Http\Livewire\AppComponent;
use App\Models\DayStat;
use App\Models\Event;
use App\Models\Group;
use Carbon\Carbon;
use Carbon\CarbonPeriod;

class Statistics extends AppComponent
{

    protected $group;
    public $groupId = 0;
    // public $months = [];
    public $year = 0;
    public $month = 0;
    public $current_month = 0;
    public $filter_sub_group = false;
    public $filter_all_event = false;
    public $period = null;
    public $startDate = null;
    public $endDate = null;

    public function mount($group) {
        $this->groupId = $group;
        
        if(!isset($this->state['month'])) {
            $this->state['month'] = date("Y-m-")."01";
        }
        $this->year = date("Y");
        $this->month = date("m");
        
        if(!$this->startDate) {
            $this->startDate = date("Y-m-")."01";
        }
        if(!$this->endDate) {
            $this->endDate = date("Y-m-t", strtotime($this->startDate));
        }
    }

    public function getMonthListFromDate(Carbon $start)
    {
        $period = $start->monthsUntil(Carbon::today());
        foreach ($period as $month) {
            $this->months[$month->format('Y-m-01')] = $month->format('Y')." ".__($month->format('F'));
        }        
    }

    public function setMonth() {
        if(isset($this->months[$this->state['month']])) {
            $month = strtoTime($this->state['month']);
            $this->year = date("Y", $month);
            $this->month = date("m", $month);
        }
    }

    public function render()
    {

        $startDate = Carbon::parse($this->startDate);
        $endDate = Carbon::parse($this->endDate);

        if($startDate->gt($endDate)) {
            $this->startDate = $endDate;
            $this->endDate = $startDate;
        }
        if($startDate->eq($endDate)) {
            $this->endDate = $endDate->addDay();
        }

        $this->first_day = $this->startDate;
        $this->last_day = $this->endDate;

        // $firstDayOfMonth = mktime(0,0,0,$this->month,1, $this->year);
        // $this->current_month = date('F', $firstDayOfMonth);
        // $this->first_day = date("Y-m-d", $firstDayOfMonth);
        // $this->last_day = date("Y-m-t", $firstDayOfMonth);

        $this->group = Group::with([
                'dates' => function($q) {
                    $q->whereBetween('date', [$this->first_day, $this->last_day]);
                },
                'literatures'
                ])->firstWhere('id', '=', $this->groupId);
        $start = strtotime($this->group->created_at);
        // $this->getMonthListFromDate(Carbon::parse(date("Y-m-01", $start)));

        $stats = DayStat::where('group_id', $this->group->id)
                            ->whereBetween('day', [$this->first_day, $this->last_day])
                            ->orderBy('time_slot')
                            ->get()
                            ->toArray();
        $total = [
            'service_hour' => 0,    //hány órát töltöttünk szolgálatban
            'ready' => 0,   //hány alkalommal volt legalább elég a hírnökszám
            'max' => 0,      //hány alkalommal volt teljes a létszám
            'empty' => 0,   //hány alkalommal nem volt senki
            'not_enough' => 0 //hány alkalommal nem volt meg a minimális létszám
        ];
        
        $hours = [
            30 => 0.5,
            60 => 1,
            90 => 1.5,
            120 => 2
        ];

        $dates_array = $this->group->dates->toArray();
        $dates = $date_info = [];
        if(isset($dates_array)) {
            foreach($dates_array as $d) {
                $key = strtotime($d['date']);
                $dates[$key] = $d;
                $date_info[$d['date_status']][$key] = $d;
            }
        }

        $slots = [];

        $day_stats = [];
        //totals
        if(count($stats)) {
            foreach($stats as $stat) {
                $ts = strtotime($stat['time_slot']);
                $key = strtotime($stat['day']);
                if(!isset($day_stats[$key]['service_hour'])) $day_stats[$key]['service_hour'] = 0;
                if(!isset($day_stats[$key]['ready'])) $day_stats[$key]['ready'] = 0;
                if(!isset($day_stats[$key]['max'])) $day_stats[$key]['max'] = 0;
                if(!isset($day_stats[$key]['empty'])) $day_stats[$key]['empty'] = 0;
                if(!isset($day_stats[$key]['not_enough'])) $day_stats[$key]['not_enough'] = 0;
                
                if(isset($dates[$key])) {
                    $date_data = [
                        'min_publishers' => $dates[$key]['date_min_publishers'],
                        'max_publishers' => $dates[$key]['date_max_publishers'],
                        'min_time' => $dates[$key]['date_min_time'],
                        'max_time' => $dates[$key]['date_max_time'],
                    ];
                } else {
                    $date_data = [
                        'min_publishers' => $this->group->min_publishers,
                        'max_publishers' => $this->group->max_publishers,
                        'min_time' => $this->group->min_time,
                        'max_time' => $this->group->max_time,
                    ];
                }

                //reach min publishers
                if($stat['events'] >= $date_data['min_publishers']) {
                    $total['service_hour'] += ($hours[$date_data['min_time']] * $stat['events']);
                    $total['ready'] += $hours[$date_data['min_time']];
                    $slots[$ts]['status'] = "ready";
                    $day_stats[$key]['service_hour'] += ($hours[$date_data['min_time']] * $stat['events']);
                    $day_stats[$key]['ready'] += $hours[$date_data['min_time']];
                }
                //reach max publishers
                if($stat['events'] == $date_data['max_publishers']) {
                    $total['max'] += $hours[$date_data['min_time']];
                    $slots[$ts]['status'] = "max";
                    $day_stats[$key]['max'] += $hours[$date_data['min_time']];
                }
                //empty
                if($stat['events'] == 0) {
                    $total['empty'] += $hours[$date_data['min_time']];
                    $slots[$ts]['status'] = "empty";
                    $day_stats[$key]['empty'] += $hours[$date_data['min_time']];
                }
                //not reach minimum publishers
                if($stat['events'] > 0 && $stat['events'] < $date_data['min_publishers'])  {
                    $total['not_enough'] += $hours[$date_data['min_time']];
                    $slots[$ts]['status'] = "not_enough";
                    $day_stats[$key]['not_enough'] += $hours[$date_data['min_time']];
                }
                $slots[$ts]['events'] = $stat['events'];
            }
        }

        // $days_array = $this->group->days->toArray();
        $period = CarbonPeriod::create($this->first_day, $this->last_day);
        // $days = [];
        // foreach($days_array as $day) {
        //     $days[$day['day_number']] = $day;
        // }
        //total slots
        foreach ($period as $date) {
            $dayOfWeek = $date->format('w');
            $date_format = $date->format("Y-m-d");
            // dump($date_format);
            $key = $date->format('U');

            if(!isset($day_stats[$key]['service_hour'])) $day_stats[$key]['service_hour'] = 0;
            if(!isset($day_stats[$key]['ready'])) $day_stats[$key]['ready'] = 0;
            if(!isset($day_stats[$key]['max'])) $day_stats[$key]['max'] = 0;
            if(!isset($day_stats[$key]['empty'])) $day_stats[$key]['empty'] = 0;
            if(!isset($day_stats[$key]['not_enough'])) $day_stats[$key]['not_enough'] = 0;

            if(!isset($day_stats[$key]['min_available_time'])) $day_stats[$key]['min_available_time'] = 0;
            if(!isset($day_stats[$key]['max_available_time'])) $day_stats[$key]['max_available_time'] = 0;

            if(/*isset($days[$dayOfWeek]) ||*/ isset($dates[$key]) ) {
                // if(isset($dates[$key])) {
                    $start = strtotime($dates[$key]['date_start']);
                    $max = strtotime($dates[$key]['date_end']);
                // } else {
                //     $start = strtotime($date->format('Y-m-d')." ".$days[$dayOfWeek]['start_time'].":00");
                //     $max = strtotime($date->format('Y-m-d')." ".$days[$dayOfWeek]['end_time'].":00");  
                // }
                
                $day_stats[$key]['date'] = $date_format;



                //if it's a disabled date, skip it
                if( isset($dates[$key]) ) {
                    if($dates[$key]['date_status'] == 0)
                        continue;

                    $date_data = [
                        'min_publishers' => $dates[$key]['date_min_publishers'],
                        'max_publishers' => $dates[$key]['date_max_publishers'],
                        'min_time' => $dates[$key]['date_min_time'],
                        'max_time' => $dates[$key]['date_max_time'],
                    ];

                } else {
                    $date_data = [
                        'min_publishers' => $this->group->min_publishers,
                        'max_publishers' => $this->group->max_publishers,
                        'min_time' => $this->group->min_time,
                        'max_time' => $this->group->max_time,
                    ];
                }

                $step = $date_data['min_time'] * 60;

                $needMath = [];

                for($current=$start;$current < $max;$current+=$step) {
                    $slots[$current]['slot'] =  ($hours[$date_data['min_time']] * 1);
                                        
                    $day_stats[$key]['min_available_time'] += ($hours[$date_data['min_time']] * $date_data['min_publishers']);
                    $day_stats[$key]['max_available_time'] += ($hours[$date_data['min_time']] * $date_data['max_publishers']);

                    //if it's not set any slot, it's empty, so need to calculate with it
                    if(isset($needMath[$current])) {
                        $day_stats[$key]['empty'] += $hours[$date_data['min_time']];
                    }
                    if(!isset($slots[$current]['status'])) {
                        $needMath[$current] = true;
                        $day_stats[$key]['empty'] = $hours[$date_data['min_time']];
                    } 
                }
            } else {
                unset($day_stats[$key]);
            }
        }
        ksort($day_stats);

        //Publisher's stats
        $users_stats = [];
        $placements_stats = [];
        $placements_total = [];
        $placements_list = [
            'placements',
            'videos',
            'return_visits',
            'bible_studies'
        ];
        $users = $this->group->groupUsersAll()->orderBy('users.name_index', 'asc')->get();
        foreach($users as $user) {
            $users_stats[$user->id] = [
                'name' => $user->name,
                'joined' => $user->pivot->accepted_at,
                'events' => 0,
                'hours' => 0,
                'last_event' => 0,
                'days' => []
            ];

            foreach($placements_list as $placement) {
                $users_stats[$user->id][$placement] = 0;
            }
        }

        $groups = [];
        $groups[] = $this->group->id;
        if($this->group->childGroups && $this->filter_sub_group) {
            $groups = array_merge($groups, $this->group->childGroups->pluck('id')->toArray());
        }

        $filter_events = [1];
        if($this->filter_all_event) {
            $filter_events = [0,1,2];
        }
      
        $events = Event::whereIn('group_id', $groups)
                    ->whereBetween('day', [$this->first_day, $this->last_day])
                    ->whereIn('status', $filter_events)
                    ->orderBy('start', 'asc')
                    ->with(['serviceReports.literature']) 
                    ->get();
        if(count($events)) {
            foreach($events as $event) {
                $users_stats[$event->user_id]['events']++;
                $startTime = Carbon::parse($event->start);
                $finishTime = Carbon::parse($event->end);

                $totalDuration = $finishTime->diffInHours($startTime);
                $users_stats[$event->user_id]['hours'] += $totalDuration;
                $users_stats[$event->user_id]['days'][$event->day.''] = true;
                $users_stats[$event->user_id]['last_event'] = $event->day;

                if(count($event->serviceReports)) {
                    foreach($event->serviceReports as $report) {
                        $lang = $report->literature->name;

                        foreach($placements_list as $placement) {
                            if(!isset($placements_stats[$event->day.''][$lang][$placement]))
                                $placements_stats[$event->day.''][$lang][$placement] = 0;
                            $placements_stats[$event->day.''][$lang][$placement] += $report->$placement;

                            if(!isset($placements_total[$lang][$placement]))
                                $placements_total[$lang][$placement] = 0;
                            $placements_total[$lang][$placement] += $report->$placement;

                            $users_stats[$event->user_id][$placement] += $report->$placement;
                        }
                    }
                }

            }
        }

        return view('livewire.groups.statistics', [
            'groupName' => $this->group->name,
            'groupId' => $this->group->id,
            'childs' => count($this->group->childGroups->toArray()),
            'need_approval' => $this->group->need_approval,
            'total' => $total,
            'day_stats' => $day_stats,
            'users_stats' => $users_stats,
            'placements_stats' => $placements_stats,
            'placements_total' => $placements_total,
            'date_info' => $date_info,
            'literatures' => count($this->group->literatures),
            'picker' => [
                'minDate' => $this->group->created_at->format("Y-m-d")
            ]
        ]);
    }
}
