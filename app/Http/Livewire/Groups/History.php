<?php

namespace App\Http\Livewire\Groups;

use App\Http\Livewire\AppComponent;
use App\Models\Group;
use Carbon\Carbon;
use Carbon\CarbonPeriod;

class History extends AppComponent
{

    protected $group;
    public $groupId = 0;
    public $months = [];
    public $year = 0;
    public $month = 0;
    public $current_month = 0;

    public function mount($group) {
        $this->groupId = $group;
        
        if(!isset($this->state['month'])) {
            $this->state['month'] = date("Y-m-")."01";
        }
        $this->year = date("Y");
        $this->month = date("m");        

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

        $this->group = Group::findOrFail($this->groupId);
        $this->getMonthListFromDate(Carbon::parse($this->group->created_at));


        $firstDayOfMonth = mktime(0,0,0,$this->month,1, $this->year);
        $this->current_month = date('F', $firstDayOfMonth);
        $this->first_day = date("Y-m-d", $firstDayOfMonth);
        $this->last_day = date("Y-m-t", $firstDayOfMonth);

        return view('livewire.groups.history', [
            'groupName' => $this->group->name,
            'groupId' => $this->group->id,
        ]);
    }
}
