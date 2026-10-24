<?php

namespace App\Http\Livewire\Groups;

use App\Http\Livewire\AppComponent;
use App\Http\Livewire\Concerns\AuthorizesGroupAccess;
use App\Models\Group;
use Carbon\Carbon;
use Carbon\CarbonPeriod;
use App\Http\Livewire\Concerns\LocksProperties;

class History extends AppComponent
{
    use LocksProperties;

    // Server-owned; the client may not write them (TODO 70.1).
    protected $lockedProperties = ['groupId'];
    use AuthorizesGroupAccess;

    protected $group;
    public $groupId = 0;
    public $months = [];
    public $year = 0;
    public $month = 0;
    public $current_month = 0;

    public function mount($group) {
        $this->authorizeGroupEditor($group);
        $this->groupId = $group;
        
        if(!isset($this->state['month'])) {
            $this->state['month'] = date("Y-m-")."01";
        }
        $this->year = date("Y");
        $this->month = date("m");        

    }

    /**
     * Every request after the first (roadmap TODO 70): the route's
     * groupAdmin does not run on component actions, so a demoted or
     * removed editor would otherwise keep the page.
     */
    public function hydrate() {
        $this->authorizeGroupEditor($this->groupId);
    }

    public function getMonthListFromDate(Carbon $start)
    {
        foreach (CarbonPeriod::create($start, '1 month', Carbon::today()) as $month) {
            $this->months[$month->format('Y-m-01')] = $month->format('Y')." ".__($month->format('F'));
        }        
    }

    public function setMonth() {
        if(isset($this->months[$this->state['month']])) {
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
