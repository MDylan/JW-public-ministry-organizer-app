<?php

namespace App\Classes;

use App\Models\DayStat;
use App\Models\Group;
use DateTime;

class GenerateStat {

    public $groupId = null;
    public $date = null;
    private $day_stat = [];
    private $service_days = [];

    public function generate($groupId, $date) {
        $this->groupId = $groupId;
        $this->date = $date;

        $this->getInfo();

        // dd($this->day_stat);

        DayStat::where([
            'group_id' => $this->groupId,
            'day' => $this->date
        ])->delete();

        DayStat::insert(
            $this->day_stat
        );
    }

    private function getInfo() {

        $groupId = $this->groupId;
        $date = $this->date;

        $group = Group::findOrFail($groupId);
    
        $this->service_days = [];
        $this->day_stat = [];
        $d = new DateTime( $date );
        $dayOfWeek = $d->format("w");
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
        
        for($current=$start;$current < $max;$current+=$step) {
            $key = "'".date('Hi', $current)."'";
            $this->day_stat[$key] = [
                'group_id' => $this->groupId,
                'day' => $this->date,
                'time_slot' => date('Y-m-d H:i', $current),
                'events' => 0
            ];
        }
        //események
        $events = $group->day_events($date)->get()->toArray();
        
        foreach($events as $event) {
            $steps = ($event['end'] - $event['start']) / $step;
            $key = "'".date('Hi', $event['start'])."'";
            $cell_start = $event['start'];
            for($i=0;$i < $steps;$i++) {
                $slot_key = "'".date("Hi", $cell_start)."'";
                $this->day_stat[$slot_key]['events']++;
                $cell_start += $step;
            }
        }
    }

}
