<?php

namespace App\Classes;

use App\Models\Group;
// use App\Models\GroupDate;
use App\Models\GroupDay;
use App\Models\GroupDayDisabledSlots;
use App\Models\GroupFutureChange;
// use App\Models\GroupLiterature;
// use Carbon\Carbon;
// use DateTime;

class updateGroupFutureChanges {

    private $group;
    private $group_data = [];
    private $state = [];
    private $days = [];
    private $literatures = [];
    private $changes = [];
    private $disabled_slots = [];

    public function getChanges($group_id) {
        $group = Group::where('id', $group_id)->with(['days'])->first();
        if($group === null) return false;
        $this->state = $group->toArray();
        $this->default_colors = config('events.default_colors');
        foreach($this->default_colors as $field => $color) {
            if(empty($this->state[$field])) {
                $this->state[$field] = $color;
            }
        }
        $days = [];
        if(count($group->days)) {
            foreach($group->days as $day) {
                $days[$day->day_number] = [
                    'day_number' => ''.$day->day_number.'',
                    'start_time' => $day->start_time,
                    'end_time' => $day->end_time,
                ];
            }
        }
        $this->days_original = $days;
        $literatures = $group->literatures;
        if(count($literatures)) {
            foreach($literatures as $literature) {
                $this->literatures['current'][$literature->id] = $literature->name;
            }
        }
        $this->group = $group;
        if($group->parent_group_id) {
            $this->parent_group = Group::findOrFail($group->parent_group_id)->toArray();
        }

        // $slots = GroupDayDisabledSlots::where('group_id', '=', $this->group->id)
        //                     ->orderBy('day_number', 'asc')
        //                     ->orderBy('slot', 'asc')
        //                     ->get()->toArray();
        // foreach($slots as $slot) {
        //     $this->disabled_slots[$slot['day_number']][$slot['slot']] = true;
        // }

        $this->changes = $this->group->futureChanges;
        // dd($this->changes);
        $this->days = $this->changes->days;
        foreach($this->changes->disabled_slots as $slots) {
            foreach($slots as $slot) {
                $this->disabled_slots[$slot['day_number']][$slot['slot']] = true; //$slot['slot'];
            }
        }
        return true;
        // $this->disabled_slots = ;
    }

    public function getState() {
        return $this->state;
    }

    public function getDays() {
        return $this->days;
    }

    public function getDisabledSlots() {
        return $this->disabled_slots;
    }

    public function initChanges($group_id) {
        if(!$this->getChanges($group_id)) {
            //Delete if some error happend with group
            GroupFutureChange::where('group_id', $group_id)->delete();
            return;
        }
        $this->group->update($this->changes->group);

        $childs = Group::where('parent_group_id', '=', $this->group->id)->get();
        foreach($childs as $child) {
            $modify = false;
            if(($child->copy_from_parent['signs'] ?? null) == true) {
                $child->signs = $this->changes->group['signs'];
                $modify = true;
            }
            if($modify) {
                $child->save();
            }
        }
        //check disabled slots
        $original_disabled_slots = [];
        $slots = GroupDayDisabledSlots::where('group_id', '=', $this->group->id)
            ->orderBy('day_number', 'asc')
            ->orderBy('slot', 'asc')
            ->get()->toArray();
        foreach($slots as $slot) {
            $original_disabled_slots[$slot['day_number']][$slot['slot']] = true;
        }
        //compare current and old slots
        $d_slots_compare = ($original_disabled_slots === $this->disabled_slots);
        // dd($this->disabled_slots);
        if(!$d_slots_compare) {
            GroupDayDisabledSlots::where('group_id', '=', $this->group->id)->delete();
            if(count($this->disabled_slots)) {
                foreach($this->disabled_slots as $day => $slots) {
                    foreach($slots as $slot => $value) {
                        if(!$value) continue;
                        GroupDayDisabledSlots::create([
                            'group_id' => $this->group->id,
                            'day_number' => $day,
                            'slot' => $slot
                        ]);
                    }
                }
            }
            $must_refresh = true;
        }

        //some day updated
        $days_compare = ($this->days === $this->days_original);
        if(!$days_compare) {
            $must_refresh = true;
        }

        $updates = [];
        // if($must_refresh) {
        //     $refresh_dates = GroupDate::where('group_id', '=', $this->group->id)
        //                         ->where('date', '>=', date("Y-m-d"))
        //                         ->where('date_status', '=', 1)
        //                         ->get();
        //     foreach($refresh_dates as $rdate) {
        //         $d = new DateTime($rdate->date);
        //         $dayOfWeek = $d->format("w");
        //         $status = 1;
        //         $start = $rdate->date." ".$this->days[$dayOfWeek]['start_time'].":00";
        //         $end_date = $rdate->date;
        //         if(strtotime($this->days[$dayOfWeek]['end_time']) == strtotime("00:00")) {
        //             $end_date = Carbon::parse($rdate->date)->addDay()->format("Y-m-d");
        //         }
        //         $end = $end_date." ".$this->days[$dayOfWeek]['end_time'].":00";
        //         $updates = [
        //             'date_start' => $start,
        //             'date_end' => $end,
        //             'date_status' => $status,
        //             'note' => null,
        //             'date_min_publishers' => $this->state['min_publishers'],
        //             'date_max_publishers' => $this->state['max_publishers'],
        //             'date_min_time' => $this->state['min_time'],
        //             'date_max_time' => $this->state['max_time'],
        //             'disabled_slots' => ($this->disabled_slots[$dayOfWeek] ?? null)
        //         ];
        //         $rdate->update(
        //             $updates
        //         );
        //         $reGenerateStat[$rdate->date] = $rdate->date;
        //     }
        // }

        if(isset($this->days)) {
            foreach($this->days as $d => $day) {
                if(!isset($day['day_number'])) {
                    continue;
                }                
                if($day['day_number'] === false) {
                    $del = GroupDay::where('group_id', $this->group->id)
                    ->where('day_number', $d)
                    ->first();
                    if($del)
                        $del->delete();
                } else {
                    GroupDay::updateOrCreate(
                        [
                            'group_id' => $this->group->id,
                            'day_number' => $day['day_number']
                        ], 
                        [
                            'start_time' => $day['start_time'],
                            'end_time' => $day['end_time']
                        ]
                    );
                }
            }      
        }

        $this->group->futureChanges()->delete();
    }

}