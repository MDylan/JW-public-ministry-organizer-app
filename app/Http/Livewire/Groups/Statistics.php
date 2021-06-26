<?php

namespace App\Http\Livewire\Groups;

use App\Http\Livewire\AppComponent;
use App\Models\DayStat;
use App\Models\Group;
// use DateTime;
use Carbon\Carbon;
use Carbon\CarbonPeriod;
use Illuminate\Support\Facades\DB;

class Statistics extends AppComponent
{

    public $group;
    public $months = [];
    public $year = 0;
    public $month = 0;
    public $current_month = 0;

    public function mount(Group $group) {
        $this->group = $group;
        
        if(!isset($this->state['month'])) {
            $this->state['month'] = date("Y-m-")."01";
        }
        $this->year = date("Y");
        $this->month = date("m");
        $this->getMonthListFromDate(Carbon::parse($this->group->created_at));

    }

    public function getMonthListFromDate(Carbon $start)
    {
        foreach (CarbonPeriod::create($start, '1 month', Carbon::today()) as $month) {
            $this->months[$month->format('Y-m-01')] = $month->format('Y')." ".__($month->format('F'));
        }        
    }

    public function setMonth() {
        if(isset($this->months[$this->state['month']])) {
            // $this->state['month'] = $yearMonth;
            $month = strtoTime($this->state['month']);
            $this->year = date("Y", $month);
            $this->month = date("m", $month);
        }
    }

    public function render()
    {
        $firstDayOfMonth = mktime(0,0,0,$this->month,1, $this->year);
        $this->current_month = date('F', $firstDayOfMonth);
        $this->first_day = date("Y-m-d", $firstDayOfMonth);
        $this->last_day = date("Y-m-t", $firstDayOfMonth);

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
            120 => 2
        ];
        $slots = [];

        $day_stats = [];

        //totals
        if(count($stats)) {
            foreach($stats as $stat) {
                $ts = strtotime($stat['time_slot']);
                // $key = "'".$stat['day']."'";
                $key = strtotime($stat['day']);
                if(!isset($day_stats[$key]['service_hour'])) $day_stats[$key]['service_hour'] = 0;
                if(!isset($day_stats[$key]['ready'])) $day_stats[$key]['ready'] = 0;
                if(!isset($day_stats[$key]['max'])) $day_stats[$key]['max'] = 0;
                if(!isset($day_stats[$key]['empty'])) $day_stats[$key]['empty'] = 0;
                if(!isset($day_stats[$key]['not_enough'])) $day_stats[$key]['not_enough'] = 0;
                
                // echo date("Y-m-d H:i", $ts)."<br/>";
                //reach min publishers
                if($stat['events'] >= $this->group->min_publishers) {
                    $total['service_hour'] += ($hours[$this->group->min_time] * $stat['events']);
                    $total['ready'] += $hours[$this->group->min_time];
                    $slots[$ts]['status'] = "ready";
                    $day_stats[$key]['service_hour'] += ($hours[$this->group->min_time] * $stat['events']);
                    $day_stats[$key]['ready'] += $hours[$this->group->min_time];
                }
                //reach max publishers
                if($stat['events'] == $this->group->max_publishers) {
                    $total['max'] += $hours[$this->group->min_time];
                    $slots[$ts]['status'] = "max";
                    $day_stats[$key]['max'] += $hours[$this->group->min_time];
                }
                //empty
                if($stat['events'] == 0) {
                    $total['empty'] += $hours[$this->group->min_time];
                    $slots[$ts]['status'] = "empty";
                    $day_stats[$key]['empty'] += $hours[$this->group->min_time];
                }
                //not reach minimum publishers
                if($stat['events'] >= 0 && $stat['events'] < $this->group->min_publishers)  {
                    $total['not_enough'] += $hours[$this->group->min_time];
                    $slots[$ts]['status'] = "not_enough";
                    $day_stats[$key]['not_enough'] += $hours[$this->group->min_time];
                }
                $slots[$ts]['events'] = $stat['events'];
            }
        }

        $days_array = $this->group->days->toArray();
        $step = $this->group->min_time * 60;
        $period = CarbonPeriod::create($this->first_day, $this->last_day);
        $days = [];
        foreach($days_array as $day) {
            $days[$day['day_number']] = $day;
        }
        // echo "<hr>";
        //total slots
        foreach ($period as $date) {
            $dayOfWeek = $date->format('w');
            if(isset($days[$dayOfWeek])) {
                // echo $date->format("Y-m-d")."<br/>";
                $start = strtotime($date->format('Y-m-d')." ".$days[$dayOfWeek]['start_time'].":00");
                $max = strtotime($date->format('Y-m-d')." ".$days[$dayOfWeek]['end_time'].":00");  
                $key = $date->format('U');

                if(!isset($day_stats[$key]['service_hour'])) $day_stats[$key]['service_hour'] = 0;
                if(!isset($day_stats[$key]['ready'])) $day_stats[$key]['ready'] = 0;
                if(!isset($day_stats[$key]['max'])) $day_stats[$key]['max'] = 0;
                if(!isset($day_stats[$key]['empty'])) $day_stats[$key]['empty'] = 0;
                if(!isset($day_stats[$key]['not_enough'])) $day_stats[$key]['not_enough'] = 0;

                for($current=$start;$current < $max;$current+=$step) {
                    $slots[$current]['slot'] =  ($hours[$this->group->min_time] * 1);
                    // $key = "'".date("Y-m-d", $current)."'";
                    if(!isset($day_stats[$key]['min_available_time'])) $day_stats[$key]['min_available_time'] = 0;
                    if(!isset($day_stats[$key]['max_available_time'])) $day_stats[$key]['max_available_time'] = 0;                    
                    $day_stats[$key]['min_available_time'] += ($hours[$this->group->min_time] * $this->group->min_publishers);
                    $day_stats[$key]['max_available_time'] += ($hours[$this->group->min_time] * $this->group->max_publishers);

                    if(!isset($slots[$current]['status'])) {
                        $day_stats[$key]['empty'] += $hours[$this->group->min_time];                        
                    } elseif($slots[$current]['status'] == 'empty') {
                        $day_stats[$key]['empty'] += $hours[$this->group->min_time];
                    }
                    // echo date("Y-m-d H:i", $current)."<br/>";
                }

            }
        }
        ksort($day_stats);

        //Publisher's stats

        $users_stats = [];
        $users = $this->group->groupUsers;
        foreach($users as $user) {
            $users_stats[$user->id] = [
                'name' => $user->full_name,
                'events' => 0,
                'hours' => 0,
                'days' => []
            ];
        }

        $events = DB::select('SELECT id, user_id, day, start, end 
                                FROM events 
                                WHERE deleted_at IS NULL
                                    AND accepted_at IS NOT NULL
                                    AND group_id = ?
                                    AND day BETWEEN ? AND ?', 
                                    [$this->group->id, $this->first_day, $this->last_day]);

        
        if(count($events)) {
            foreach($events as $event) {
                // dd($event);
                $users_stats[$event->user_id]['events']++;
                $startTime = Carbon::parse($event->start);
                $finishTime = Carbon::parse($event->end);

                $totalDuration = $finishTime->diffInHours($startTime);
                $users_stats[$event->user_id]['hours'] += $totalDuration;
                $users_stats[$event->user_id]['days'][$event->day] = true;

            }
        }
        // dd($users_stats);
        // dd($day_stats);
        // dd($total);

        return view('livewire.groups.statistics', [
            'total' => $total,
            'day_stats' => $day_stats,
            'users_stats' => $users_stats
        ]);
    }
}
