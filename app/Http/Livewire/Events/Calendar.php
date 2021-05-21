<?php

namespace App\Http\Livewire\Events;

use App\Models\Group;
use Livewire\Component;

class Calendar extends Component
{

    public $year = 0;
    public $month = 0;
    // public $calendar = [];
    public $pagination = [];
    public $service_days = [];
    public $group_data = [];

    public $listeners = ['change', 'openModal'];

    public function change() {
        
    }

    public function openModal($date) {
        dd('here:'. $date);

    }

    public function setMonth($year, $month) {
        $this->year = $year;
        $this->month = $month;
    }

    public function getGroupData() {
        $this->service_days = [];
        $groupId = session('groupId');
        $group = Group::findOrFail($groupId);
        $days = $group->days()->get()->toArray();
        if(count($days)) {
            foreach($days as $day) {
                $this->service_days[$day['day_number']] = [
                    'start_time' => $day['start_time'],
                    'end_time' => $day['end_time'],
                ];
            }
        }
        $this->group_data = $group->whereId($groupId)->first()->toArray();
    }

    public function render()
    {
        $calendar = [];
        $this->getGroupData();

        // What is the first day of the month in question?
        $firstDayOfMonth = mktime(0,0,0,$this->month,1, $this->year);
        $this->current_month = date('F', $firstDayOfMonth);

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
        $max_day = strtotime('+'.$this->group_data['max_extend_days'].' days');
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
            $available = ($timestamp >= $today && $timestamp <= $max_day);

            $calendar[$row][] = [
                'colspan' => null,
                'weekDay' => $weekDays[$dayOfWeek],
                'day' => $currentDay,
                'current' => $date == date("Y-m-d") ? true : false,
                'fullDate' => $date,
                'available' => $available,
                'service_day' => (isset($this->service_days[$weekDays[$dayOfWeek]])) ? true : false
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

        // dd($this->service_days);

        return view('livewire.events.calendar', [
            'service_days' => $this->service_days,
            'calendar' => $calendar
        ]);
    }
}
