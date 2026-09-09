<?php

namespace App\Jobs;

use App\Classes\GenerateSlots;
use App\Helpers\GroupDateHelper;
use Illuminate\Bus\Queueable;
// use Illuminate\Contracts\Queue\ShouldBeUnique;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use App\Models\DayStat;
use App\Models\Group;
use App\Models\GroupDate;
// use App\Models\GroupDayDisabledSlots;
// use Carbon\Carbon;
use App\Support\Retention\RetentionWindow;
use DateTime;

class GenerateStatProcess implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public $groupId = null;
    public $date = null;
    private $day_stat = [];
    private $service_days = [];
    private $date_data = [];
    private $group_data = [];
    private $forceReset = false;
    /**
     * Delete the job if its models no longer exist.
     *
     * @var bool
     */
    public $deleteWhenMissingModels = true;

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
            $q->where('date', '=', $date);
        }])->find($groupId);
    
        if($group) {
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

            if(count($this->date_data) === 0) return false;

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
        } else {
            return false;
        }

        
    }

    /**
     * Execute the job.
     *
     * @return void
     */
    public function handle()
    {
        // We don't regenerate statistics below the retention floor.
        //
        // GroupDateHelper::generateDate()'s past-protection is buggy (on the
        // $date_info branch it evaluates the toArray(), discards it, and then
        // falls through to updateOrCreate()), so editing a group template
        // also dispatches this job for old days. But the source events have
        // already been deleted, so getInfo() would return all-zero slots: a
        // day that was just cleaned up would get a brand-new day_stats row
        // claiming that nobody served there.
        $floor = RetentionWindow::groupDataFloor();
        if($floor !== null && $this->date < $floor->toDateString()) {
            return;
        }

        // $this->groupId = $groupId;
        // $this->date = $date;
        if($this->forceReset) {
            //we reset timeslot for this day
            // ->first() can be null: the queue still runs the job even if the
            // GroupDate row has disappeared since the dispatch (concurrent
            // delete, group deletion, manual data fix). The previous
            // unconditional ->delete() then called a method on null, so the
            // job died with a fatal error instead of simply moving on - and
            // the whole point of the reset is for the row NOT to be there.
            $groupDate = GroupDate::where('group_id', '=', $this->groupId)
                        ->where('date', '=', $this->date)
                        ->first();

            if($groupDate !== null) {
                $groupDate->delete();
            }
        } 

        $res = $this->getInfo();

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
