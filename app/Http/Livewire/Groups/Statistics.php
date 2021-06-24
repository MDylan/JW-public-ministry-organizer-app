<?php

namespace App\Http\Livewire\Groups;

use App\Http\Livewire\AppComponent;
use App\Models\Group;

class Statistics extends AppComponent
{

    public $group;

    public function mount(Group $group) {
        $this->group = $group;
        if(!isset($this->state['month'])) {
            $this->state['month'] = date("Y-m-")."01";
        }

        $min_stat = $this->group->stats()->first();
        $now = mktime(0,0,0, date("m"), 1, date("Y"));
        if($min_stat !== null) {
            // $start_day = $min_stat->day->format("Y-m-d");
            $start = mktime(0,0,0, $min_stat->day->format("m"), 1, $min_stat->day->format("Y"));
        } else {
            $start = $now;
        }

        // dd($min_stat->day->format("Y-m-d"));
    }

    public function render()
    {
        return view('livewire.groups.statistics');
    }
}
