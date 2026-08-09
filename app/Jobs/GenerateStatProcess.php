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
        // A retenciós padló alatt nem gyártunk újra statisztikát.
        //
        // A GroupDateHelper::generateDate() múltvédelme hibás (a $date_info
        // ágon kiértékeli a toArray()-t, eldobja, majd átesik az
        // updateOrCreate-re), így egy csoportsablon-szerkesztés a régi
        // napokra is dispatch-eli ezt a jobot. A forrásesemények viszont már
        // törölve vannak, tehát a getInfo() csupa nulla slotot adna vissza:
        // egy éppen most kitakarított napra egy vadonatúj day_stats sor
        // kerülne azzal az állítással, hogy ott senki nem szolgált.
        $floor = RetentionWindow::groupDataFloor();
        if($floor !== null && $this->date < $floor->toDateString()) {
            return;
        }

        // $this->groupId = $groupId;
        // $this->date = $date;
        if($this->forceReset) {
            //we reset timeslot for this day
            // A ->first() null is lehet: a jobot a queue akkor is lefuttatja,
            // ha a GroupDate sor a dispatch óta eltűnt (párhuzamos törlés,
            // csoport-törlés, kézi adatjavítás). A korábbi feltétel nélküli
            // ->delete() ilyenkor null-on hívott metódust, tehát a job fatal
            // hibával halt meg ahelyett, hogy egyszerűen továbbment volna -
            // a reset célja pedig épp az, hogy a sor NE legyen ott.
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
