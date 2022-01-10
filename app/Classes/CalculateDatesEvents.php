<?php

namespace App\Classes;

use App\Models\Event;
use App\Models\GroupDate;
use App\Models\LogHistory;
use App\Observers\EventObserver;
use DateTime;
use Illuminate\Container\Container;
use Illuminate\Events\Dispatcher;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\DB;

class CalculateDatesEvents {
    /**
     * Calculate events based on group_dates
     */
    static function generate(int $group_id, array|string $date) {
        $dates = [];
        if(is_array($date)) {
            $dates = $date; 
        } else {
            $dates = [$date];
        }

        $events = DB::select('SELECT e.id, e.day, e.start, e.end, gd.date, gd.date_status, gd.date_start, gd.date_end, gd.date_max_publishers, gd.date_min_time
            FROM events as e
            INNER JOIN group_dates AS gd ON gd.group_id = e.group_id AND gd.date = e.day
        WHERE e.deleted_at IS NULL
            AND e.group_id = ?
            AND e.day IN (?)
            AND e.status IN (1)
            ORDER BY e.day, e.start', [$group_id, implode(",", $dates)]);
        // dd($events);
        $deletes = [];
        $updates = [];
        $day_events = [];
        foreach($events as $event) {
            if($event->date_status == 0) {
                //it's a disabled day, delete event
                $deletes[$event->id] = $event->id;
                continue;
            }

            $start = strtotime($event->date_start);
            $end = strtotime($event->date_end);

            $event->start = strtotime($event->start);
            $event->end = strtotime($event->end);

            if($event->end <= $start || $event->start >= $end) {
                //it's totally out of slot, must delete
                $deletes[$event->id] = $event->id;
            } elseif($event->start < $start && $event->end > $start) {
                //only the start not good, we update it
                $updates[$event->id]['start'] = date("Y-m-d H:i:s", $start);
            } elseif($event->end > $end && $event->start < $end) {
                //only the start not good, we update it
                $updates[$event->id]['end'] = date("Y-m-d H:i:s", $end);
            }

            //search if we reach max publishers
            $step = $event->date_min_time * 60;
            $steps = (strtotime($event->date_end) - strtotime($event->date_start)) / $step;
            $cell_start = strtotime($event->start);
            for($i=0;$i < $steps;$i++) {
                $slot_key = "'".date("Hi", $cell_start)."'";
                if(!isset($day_events[$event->day][$slot_key]['events'])) 
                    $day_events[$event->day][$slot_key]['events'] = 0;
                $day_events[$event->day][$slot_key]['events']++;
                if($day_events[$event->day][$slot_key]['events'] > $event->date_max_publishers) {
                    $deletes[$event->id] = $event->id;
                }
                $cell_start += $step;
            }
        }
        //we will notify users about modify/delete reason
        session()->now('reason', 'modified_service_time');

        //we use firstDelete for store LogHistory event
        foreach($deletes as $deleteId) {
            Event::firstWhere('id', '=', $deleteId)->delete();
        }        
        foreach($updates as $id => $field) {
            Event::firstWhere('id', '=', $id)->update($field);
        }

        //regenerate stat for affected days
        foreach($dates as $day) {
            $stat = new GenerateStat();
            $stat->generate($group_id, $day);
        }

        

        // $groupdates = DB::select('SELECT gd.id, gd.date, gd.date_status
        //         FROM group_dates as gd
        //         WHERE gd.group_id = ?
        //         AND gd.date >= ?
        //         AND gd.date_status = 1', [$groupDay->group_id, $date]);

        // foreach($groupdates as $day) {

        //     $d = new DateTime($day->date);
        //     $dayOfWeek = $d->format("w");

        //     //it's not right day, skip this
        //     if($dayOfWeek != $groupDay->day_number) continue;

        //     $start = strtotime($day->date." ".$groupDay->start_time);
        //     $end = strtotime($day->date." ".$groupDay->end_time);

        //     GroupDate::where('id', '=', $day->id)->update([
        //         'date_start' => date("Y-m-d H:i:s", $start),
        //         'date_end' => date("Y-m-d H:i:s", $end),
        //     ]);

        //     $stat = new GenerateStat();
        //     $stat->generate($groupDay->group_id, $day->date);
        // }        

    }
}