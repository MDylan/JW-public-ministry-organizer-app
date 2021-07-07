<?php

namespace App\Classes;

use App\Models\DayStat;
use App\Models\Group;
use App\Models\GroupDate;
use DateTime;

class GenerateStat {

    public $groupId = null;
    public $date = null;
    private $day_stat = [];
    private $service_days = [];
    private $date_data = [];
    // private $forceReset = false;

    public function generate($groupId, $date, $forceReset = false) {
        $this->groupId = $groupId;
        $this->date = $date;
        if($forceReset) {
            //we reset timeslot for this day
            GroupDate::where('group_id', '=', $groupId)
                        ->where('date', '=', $date)
                        ->first()
                        ->delete();
        }

        $res = $this->getInfo();


        // dd($this->day_stat);

        DayStat::where([
            'group_id' => $this->groupId,
            'day' => $this->date
        ])->delete();

        if($res) {
            DayStat::insert(
                $this->day_stat
            );
        }
    }

    private function getInfo() {

        $groupId = $this->groupId;
        $date = $this->date;

        $group = Group::with(['current_date' => function($q) use ($date) {
            // $q->select(['group_id', 'date', 'date_start', 'date_end', 'date_status']);
            $q->where('date', '=', $date);
        }])->findOrFail($groupId);


    
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

        if($this->group_data['current_date']['date_status'] == 0) {
            return false;
        }

        if($this->group_data['current_date'] === null) {
            $start = strtotime($date." ".$this->service_days[$dayOfWeek]['start_time'].":00");
            $max = strtotime($date." ".$this->service_days[$dayOfWeek]['end_time'].":00"); 
            GroupDate::create([
                'group_id' => $groupId,
                'date' => $date,
                'date_start' => date("Y-m-d H:i:s", $start),
                'date_end' => date("Y-m-d H:i:s", $max),
                'date_status' => 1,
                'date_min_publishers' => $this->group_data['min_publishers'],
                'date_max_publishers' => $this->group_data['max_publishers'],
                'date_min_time' => $this->group_data['min_time'],
                'date_max_time' => $this->group_data['max_time']
            ]);
            $this->date_data = [
                'min_publishers' => $this->group_data['min_publishers'],
                'max_publishers' => $this->group_data['max_publishers'],
                'min_time' => $this->group_data['min_time'],
                'max_time' => $this->group_data['max_time']
            ];
        } else {
            $start = strtotime($this->group_data['current_date']['date_start']);
            $max = strtotime($this->group_data['current_date']['date_end']);
            $this->date_data = [
                'min_publishers' => $this->group_data['current_date']['date_min_publishers'],
                'max_publishers' => $this->group_data['current_date']['date_max_publishers'],
                'min_time' => $this->group_data['current_date']['date_min_time'],
                'max_time' => $this->group_data['current_date']['date_max_time']
            ];
        }

        $step = $this->date_data['min_time'] * 60;
        
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

        return true;
    }

}
