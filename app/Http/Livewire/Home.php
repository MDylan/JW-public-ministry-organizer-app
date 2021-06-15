<?php

namespace App\Http\Livewire;

use App\Models\Group;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Livewire\Component;

class Home extends Component
{

    public $days = [];
    public $day_stat = [];
    public $events = [];
    public $available_days = [];
    public $listeners = [
        'refresh' => 'render'
    ];

    public function changeGroup($groupId) {
        $group = Auth()->user()->groupsAccepted()->wherePivot('group_id', $groupId)->firstOrFail()->toArray();
        if($group['pivot']['group_id']) {
            session(['groupId' => $groupId]);
            return redirect()->route('calendar');
        }        
    }

    public function getStat($group_stats) {
        foreach($group_stats as $group) {
            $colors = [];
            foreach($group["stats"] as $stat) {
                $color = '#00ff00'; //green
                if($stat['events'] > 0 && $stat['events'] < $group['min_publishers']) {
                    $color = '#1259B2'; //blue
                }
                if($stat['events'] >= $group['min_publishers']) {
                    $color = '#ffff00'; //yellow
                } 
                if($stat['events'] == $group['max_publishers']) {
                    $color = '#ff0000'; //red
                }
                $colors[$stat['day']][] = $color;
            }
            if(count($colors)) {
                $total_percent = [];
                foreach($colors as $day => $values) {
                    $percent = round(100 / count($values), 3);
                    $total_percent[$group['id']][$day] = 0;
                    $pos = 0;
                    $this->day_stat[$group['id']][$day]['style'] = "linear-gradient(90deg";
                    foreach($values as $k => $color) {
                        $this->day_stat[$group['id']][$day]['style'] .= ", ".$color." ".$percent."% ".$pos."%";
                        $pos+=$percent;
                        $total_percent[$group['id']][$day]+=$percent;
                    }
                    $this->day_stat[$group['id']][$day]['style'] .= ");";
                }
            }
            foreach($group["just_events"] as $event) { 
                $this->day_stat[$group['id']][$event['day']]['event'] = true;
                $this->events[$group['id']][$event['day']][] = $event;
            }
            
            foreach($group["days"] as $day) { 
                $this->available_days[$group['id']][$day['day_number']] = true;
            }
            foreach($this->days as $day) {
                $day_num = date("w", $day);

                $key = date("Y-m-d", $day);
                if(!isset($this->day_stat[$group['id']][$key]['style'] )) {
                    
                    if(isset($this->available_days[$group['id']][$day_num])) {
                        $color = "#00ff00;";
                    } else {
                        $color = "#cecece";
                    }
                    $this->day_stat[$group['id']][$key]['style'] = $color;
                }
                // $this->day_stat[$group['id']][$event['day']]['available'] = false;
            }
        }
        // dd($this->day_stat, $total_percent);
    }

    public function render()
    {
        $this->days = [];
        $this->day_stat = [];
        $this->events = [];
        $this->available_days = [];
        $start = strtotime("today");
        $end = strtotime("+ 10 day");
        $this->days = range($start, $end, (24 * 60 * 60));
        
        $user = Auth()->user();
        $groups = $user->groupsAccepted();
        $stats = $groups->with([
            'stats' => function($q) use($start, $end) {
                $q->whereBetween('day', [date("Y-m-d", $start), date("Y-m-d", $end)]);
                $q->orderBy('time_slot');
            },
            'justEvents' => function($q) use($start, $end) {
                $q->where('user_id', '=', Auth::id());
                $q->whereBetween('day', [date("Y-m-d", $start), date("Y-m-d", $end)]);
            },
            'days'
        ])->get()->toArray();
        // dd($stats);
        $this->getStat($stats);
        return view('livewire.home', [
            'groups' => $groups->get()
        ]);
    }
}
