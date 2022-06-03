<?php

namespace App\Jobs;

use App\Classes\GenerateSlots;
use App\Helpers\GroupDateHelper;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldBeUnique;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use App\Models\DayStat;
use App\Models\Group;
use App\Models\GroupDate;
use App\Models\GroupDayDisabledSlots;
use Carbon\Carbon;
use DateTime;

class GenerateStatProcess implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public $groupId = null;
    public $date = null;
    private $day_stat = [];
    private $service_days = [];
    private $date_data = [];
    private $forceReset = false;

    /**
     * Create a new job instance.
     *
     * @return void
     */
    public function __construct($groupId, $date, $forceReset)
    {
        $this->groupId = $groupId;
        $this->date = $date;
        $this->forceReset = $forceReset;
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

        if(($this->group_data['current_date']['date_status'] ?? 1) == 0) {
            return false;
        }

        if($this->group_data['current_date'] === null) {
            $helper = new GroupDateHelper($this->groupId);
            $generate = $helper->generateDate($this->date);
            if(!$generate) return;
            else {
                $this->date_data = [
                    'min_publishers' => $generate['date_min_publishers'],
                    'max_publishers' => $generate['date_max_publishers'],
                    'min_time' => $generate['date_min_time'],
                    'max_time' => $generate['date_max_time']
                ];
            }
            // $start = strtotime($date." ".$this->service_days[$dayOfWeek]['start_time'].":00");
            // $end_date = $date;
            // if(strtotime($this->service_days[$dayOfWeek]['end_time']) == strtotime("00:00")) {
            //     $end_date = Carbon::parse($date)->addDay()->format("Y-m-d");
            // }
            // $max = strtotime($end_date." ".$this->service_days[$dayOfWeek]['end_time'].":00"); 

            // $disabled_slots_insert = [];
            // //get disabled time slot for this day
            // $disableds = GroupDayDisabledSlots::where('group_id', '=', $this->groupId)
            //     ->where('day_number', '=', $dayOfWeek)
            //     ->orderBy('slot', 'asc')
            //     ->get()
            //     ->toArray();
            // foreach($disableds as $sl) {
            //     $disabled_slots_insert[$sl['slot']] = true;
            // }

            // GroupDate::create([
            //     'group_id' => $groupId,
            //     'date' => $date,
            //     'date_start' => date("Y-m-d H:i:s", $start),
            //     'date_end' => date("Y-m-d H:i:s", $max),
            //     'date_status' => 1,
            //     'date_min_publishers' => $this->group_data['min_publishers'],
            //     'date_max_publishers' => $this->group_data['max_publishers'],
            //     'date_min_time' => $this->group_data['min_time'],
            //     'date_max_time' => $this->group_data['max_time'],
            //     'disabled_slots' => $disabled_slots_insert
            // ]);
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
        
        $slots_array = GenerateSlots::generate($date, $start, $max, $step);
        foreach($slots_array as $current) {
            $key = "'".date('Hi', $current)."'";
            $this->day_stat[$key] = [
                'group_id' => $this->groupId,
                'day' => $this->date,
                'time_slot' => date('Y-m-d H:i', $current),
                'events' => 0
            ];
        }
        //events
        $events = $group->day_events_accepted($date)->get()->toArray();
        
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

    /**
     * Execute the job.
     *
     * @return void
     */
    public function handle()
    {
        // $this->groupId = $groupId;
        // $this->date = $date;
        if($this->forceReset) {
            //we reset timeslot for this day
            GroupDate::where('group_id', '=', $this->groupId)
                        ->where('date', '=', $this->date)
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

        GroupDate::where('group_id', '=', $this->groupId)
                        ->where('date', '=', $this->date)
                        ->update(['run_job' => 0]);
    }
}
