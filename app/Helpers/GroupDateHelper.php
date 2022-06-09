<?php

namespace App\Helpers;

use App\Jobs\CalculateDateProcess;
use App\Models\Group;
use App\Models\GroupDate;
use Carbon\Carbon;
use Illuminate\Support\Facades\Log;

class GroupDateHelper {
    
    private $group;
    private $deleteAfterCalculate = [];
    private $reGenerateStat = [];

    public function __construct(int $group_id)
    {
        $this->group = Group::where('id', '=', $group_id)
                        ->with([
                            'days',
                            'disabled_slots',
                            'futureChanges'
                        ])
                        ->first();
        // dd($this->group->toArray());
    }

    public function generateDate(string $date) {
        // Log::debug($date);
        $date = Carbon::parse($date);                       

        $date_info = $this->group->dates()->where('date', '=', $date)->first();
        
        //don't modify past dates and not modify special dates
        if(Carbon::today()->gt($date) || ($date_info->date_status ?? 1) !== 1) {
            if($date_info === null) return false;
            else $date_info->toArray();        
        }
        // dd($date_info->toArray());
        $group_data = $this->group->toArray();
        $service_days = $disabled_slots = [];
        foreach($this->group->days as $day) {
            $service_days[$day->day_number] =  [
                'start_time' => $day->start_time,
                'end_time' => $day->end_time,
            ];
        }
        if(count($this->group->disabled_slots)) {
            // dd($this->group->disabled_slots);
            foreach($this->group->disabled_slots as $slot) {
                // dd($slot, $slot->day_number, $slot->slot);
                $disabled_slots[$slot->day_number][$slot->slot->format("H:i")] = true;
            }
            // dd('ok');
        }

        $future_changes = $this->group->futureChanges;
        if($future_changes !== null) {
            // Log::debug('Vannak jövőbeli változtatások');
            $change_date = Carbon::parse($future_changes->change_date);
            if($date->gte($change_date)) {
                // Log::debug('jövőbeli, így módosítom');
                //overwrite current datas for future changes
                $group_data = $future_changes->group;
                $service_days = $disabled_slots = [];
                foreach($future_changes->days as $day) {
                    if($day['day_number'] === false) continue;
                    $service_days[$day['day_number']] =  [
                        'start_time' => $day['start_time'],
                        'end_time' => $day['end_time'],
                    ];
                }
                if(count($future_changes->disabled_slots)) {
                    foreach($future_changes->disabled_slots as $slots) {
                        foreach($slots as $slot) {
                            $disabled_slots[$slot['day_number']][$slot['slot']] = true;
                        }
                    }
                }
            } else {
                // Log::debug('múltbeli dátum');
            }
        }
        $dayOfWeek = $date->format("w");
        $date_format = $date->format("Y-m-d");
        if(isset($service_days[$dayOfWeek])) {
            // Log::debug('szolgálati nap');
            $start = $date_format." ".$service_days[$dayOfWeek]['start_time'].":00";
            $end_date = $date_format;
            if(strtotime($service_days[$dayOfWeek]['end_time']) == strtotime("00:00")) {
                $end_date = $date->addDay()->format("Y-m-d");
            }
            $end = $end_date." ".$service_days[$dayOfWeek]['end_time'].":00";
            $updates = [
                'date' => $date_format,
                'date_start' => $start,
                'date_end' => $end,
                'date_status' => 1,
                'note' => null,
                'date_min_publishers' => $group_data['min_publishers'],
                'date_max_publishers' => $group_data['max_publishers'],
                'date_min_time' => $group_data['min_time'],
                'date_max_time' => $group_data['max_time'],
                'disabled_slots' => ($disabled_slots[$dayOfWeek] ?? null)
            ];
            $res = GroupDate::updateOrCreate(
                ['date' => $date_format, 'group_id' => $this->group->id],
                $updates
            );
            //if it's updated we will regenerate stat
            if(!$res->wasRecentlyCreated && $res->wasChanged()){ 
                $this->reGenerateStat[$date_format] = $date_format;
            }
            return $updates;
        } else {
            // Log::debug('törölve lesz');
            //it's not a service day, delete if exists
            if($date_info !== null) {
                $date_info->update([
                    'date_status' => 0
                ]);
                $this->reGenerateStat[$date_format] = $date_format;
                $this->deleteAfterCalculate[$date_format] = $date_format;
            }

            return false;
        }
    }

    public function recalculateDates() {
        // Log::debug('Érintett dátumok', $this->reGenerateStat);
        if(count($this->reGenerateStat) > 0) {
            CalculateDateProcess::dispatch($this->group->id, $this->reGenerateStat, auth()->user()->id ?? 0, $this->deleteAfterCalculate);
        }
    }

}