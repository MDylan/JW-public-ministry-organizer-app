<?php

namespace App\Http\Livewire;

use App\Models\GroupDayDisabledSlots;
use Carbon\Carbon;
use Carbon\CarbonPeriod;
use DateTime;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Livewire\Component;

class Home extends Component
{

    private $days = [];
    private $day_stat = [];
    private $events = [];
    private $available_days = [];
    public $listeners = [
        'refresh' => 'render',
        'pollingOn',
        'pollingOff'
    ];
    private $groups = [];
    private $group_roles = [];
    public $polling = false;

    public function changeGroup($groupId) {
        $group = Auth()->user()->groupsAccepted()->wherePivot('group_id', $groupId)->firstOrFail()->toArray();
        if($group['pivot']['group_id']) {
            session(['groupId' => $groupId]);
            return redirect()->route('calendar');
        }        
    }

    public function getStat($group_stats) {
        foreach($group_stats as $group) {
            $this->groups[] = $group['id'];
            $this->group_roles[$group['id']] = $group['pivot']['group_role'];
            $default_color = $group['colors']['color_default'];
            $green_color =$group['colors']['color_empty'];
            $service_days = [];
            foreach($group["days"] as $day) { 
                $service_days[$day['day_number']] = true;
            }
            //set the defaults based on current settings
            foreach($this->days as $day) {
                $dayNum = date("w", $day);
                if(isset($service_days[$dayNum])) {
                    //group dates changed and missing add it to array
                    $this->available_days[$group['id']][$day] = true;
                    $dates[$day] = ['date_status' => 1];
                    $this->day_stat[$group['id']][$day]['style'] = $green_color;
                } else {
                    $this->available_days[$group['id']][$day] = false;
                    $this->day_stat[$group['id']][$day]['style'] = $default_color;
                }
            }
            
            foreach($group["just_events"] as $event) { 
                $day = strtotime($event['day']);
                $this->day_stat[$group['id']][$day]['event'] = true;
                $this->events[$group['id']][$day][] = $event;
            }

            $dates = $future_days = [];
            //load dates info, and enable/disable date if needed
            if(count($group['dates'])) {
                foreach($group['dates'] as $date) {
                    $day = strtotime($date['date']);
                    if($date['date_status'] == 0) {
                        //disabled day
                        $this->available_days[$group['id']][$day] = false;
                        $this->day_stat[$group['id']][$day]['style'] = $default_color;
                    } else {
                        $this->available_days[$group['id']][$day] = true;
                        $this->day_stat[$group['id']][$day]['style'] = $green_color;
                    }
                    $dates[$date['date']] = $date;
                }
            }
            if($group['future_changes'] !== null) {
                $change_date = Carbon::parse($group['future_changes']['change_date']);
                foreach($group['future_changes']['days'] as $d) {
                    if($d['day_number'] === false) continue;
                    $future_days[$d['day_number']] = [
                        'start_time' => $d['start_time'],
                        'end_time' => $d['end_time'],
                    ];
                }
                foreach($this->available_days[$group['id']] as $day => $value) {
                    $date_now = Carbon::createFromTimestamp($day);
                    if($date_now->gte($change_date)) {
                        $check = isset($future_days[$date_now->format("w")]);

                        if($check && ($dates[$date_now->format("Y-m-d")]['date_status'] ?? 1) != 0) {
                            $this->available_days[$group['id']][$day] = true;
                            $this->day_stat[$group['id']][$day]['style'] = $green_color;
                        } else {
                            $this->available_days[$group['id']][$day] = false;
                            $this->day_stat[$group['id']][$day]['style'] = $default_color;
                        }
                    }
                }
            }

            //loading stats from db
            $colors = [];
            foreach($group["stats"] as $stat) {
                $day = strtotime($stat['day']);
                //if it's a disabled day, skip this
                if(!$this->available_days[$group['id']][$day]) continue;

                if(!isset($dayOfWeeks[$stat['day']])) {
                    $d = new DateTime( $stat['day'] );
                    $dayOfWeek = $d->format("w");
                    $dayOfWeeks[$stat['day']] = $dayOfWeek;
                } else {
                    $dayOfWeek = $dayOfWeeks[$stat['day']];
                }
                $color = $green_color; //green
                $min_publishers = isset($dates[$stat['day']]['date_min_publishers']) ? $dates[$stat['day']]['date_min_publishers'] : $group['min_publishers'];
                $max_publishers = isset($dates[$stat['day']]['date_max_publishers']) ? $dates[$stat['day']]['date_max_publishers'] : $group['max_publishers'];
                if($stat['events'] > 0 && $stat['events'] < $min_publishers) {
                    $color = $group['colors']['color_someone']; //blue
                }
                if($stat['events'] >= $min_publishers) {
                    $color = $group['colors']['color_minimum']; //'#ffff00'; //yellow
                } 
                if($stat['events'] == $max_publishers) {
                    $color = $group['colors']['color_maximum'];// '#ff0000'; //red
                }
                $slot_key = Carbon::parse($stat['time_slot'])->format("H:i");
                if(($dates[$stat['day']]['disabled_slots'][$slot_key] ?? false)) {
                    $color = $default_color;
                }
                $colors[$day][] = $color;
            }
            //generate color bar for each date
            if(count($colors)) {
                $total_percent = [];
                foreach($colors as $day => $values) {
                    $percent = round(100 / count($values), 3);
                    $total_percent[$group['id']][$day] = 0;
                    $pos = 0;
                    $this->day_stat[$group['id']][$day]['style'] = "linear-gradient(to right";
                    foreach($values as $k => $color) {
                        $this->day_stat[$group['id']][$day]['style'] .= ", ".$color." ".$pos."% ".($pos + $percent)."%";

                        $pos+=$percent;
                        $total_percent[$group['id']][$day]+=$percent;
                    }
                    $this->day_stat[$group['id']][$day]['style'] .= ");";
                }
            }
        }
    }

    public function pollingOn() {
        $this->polling = true;
    }

    public function pollingOff() {
        $this->polling = false;
    }

    public function render()
    {
        $this->days = [];
        $this->day_stat = [];
        $this->events = [];
        $this->available_days = [];
        $start = strtotime("today");
        $end = strtotime("+ 10 day");
        $period = CarbonPeriod::create(today(), now()->addDays(10));
        foreach ($period as $date) {
            $this->days[] = $date->timestamp;
        }
        $stats = Auth::user()->groupsAccepted()->with([
            'stats' => function($q) use($start, $end) {
                $q->whereBetween('day', [date("Y-m-d", $start), date("Y-m-d", $end)]);
                $q->orderBy('time_slot');
            },
            'justEvents' => function($q) use($start, $end) {
                $q->where('user_id', '=', Auth::id());
                $q->whereIn('status', [0,1]);
                $q->whereBetween('day', [date("Y-m-d", $start), date("Y-m-d", $end)]);
            },
            'days',
            'dates' => function($q) use($start, $end) {
                $q->whereBetween('date', [date("Y-m-d", $start), date("Y-m-d", $end)]);
            },
            'posters' => function($q) {
                $q->where('show_date', '<=', date("Y-m-d"));
                $q->where(function ($q) {
                    $q->where('hide_date', '>=', date("Y-m-d"))
                        ->orWhereNull('hide_date');
                });
            },
            'futureChanges'
        ])->get()->toArray();
        // dd($stats);
        $ids = [];
        foreach($stats as $stat) {
            $ids[] = $stat['id'];
        }

        $this->getStat($stats);
        
        $notAcceptedEvents = DB::table('events')
                                ->groupBy('group_id', 'day')
                                ->whereNull('deleted_at')
                                ->whereIn('group_id', $this->groups)
                                ->where('status', '=', '0')
                                ->whereBetween('day', [date("Y-m-d", $start), date("Y-m-d", $end)])
                                ->get(['group_id', 'day']);
        $notAccepts = [];
        if(count($notAcceptedEvents)) {
            foreach($notAcceptedEvents as $event) {
                $notAccepts[$event->group_id][$event->day] = true;
            }
        }

        return view('livewire.home', [
            'groups' => $stats,
            'notAccepts' => $notAccepts,
            'days' => $this->days,
            'day_stat' => $this->day_stat,
            'events' => $this->events,
            'available_days' => $this->available_days,
            'group_roles' => $this->group_roles
        ]);
    }
}
