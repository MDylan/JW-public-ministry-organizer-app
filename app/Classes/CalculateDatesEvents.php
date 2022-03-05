<?php

namespace App\Classes;

use App\Models\Event;
use App\Models\GroupDate;
use App\Models\LogHistory;
use App\Observers\EventObserver;
use Carbon\Carbon;
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

        $events = DB::table('events')
                    ->join('group_dates', function ($join) {
                        $join->on('group_dates.group_id', '=', 'events.group_id')
                            ->on('group_dates.date', '=', 'events.day');
                    })
                    ->select('events.id', 'events.day', 'events.start', 'events.end', 'events.status', 
                                'group_dates.date', 'group_dates.date_status', 'group_dates.date_start', 'group_dates.date_end', 
                                'group_dates.date_max_publishers', 'group_dates.date_min_time')
                    ->whereNull('events.deleted_at')
                    ->where('events.group_id', '=', $group_id)
                    ->whereIn('events.day', $dates)
                    ->whereIn('events.status', [0,1])
                    ->orderByDesc('events.day')
                    ->orderByDesc('events.start')
                    ->get();
        // dd($events);

        // $events = DB::select('SELECT e.id, e.day, e.start, e.end, e.status,
        //                                 gd.date, gd.date_status, gd.date_start, gd.date_end, gd.date_max_publishers, gd.date_min_time
        //                         FROM events as e
        //                         INNER JOIN group_dates AS gd ON gd.group_id = e.group_id AND gd.date = e.day
        //                     WHERE e.deleted_at IS NULL
        //                         AND e.group_id = ?
        //                         AND e.day IN (?)
        //                         AND e.status IN (0,1)
        //                         ORDER BY e.day, e.start', 
        //                         [
        //                             $group_id, 
        //                             "'".implode("','", $dates)."'"
        //                         ]
        //                     );
        // dd($events);
        $deletes = [];
        $updates = [];
        $modifies = [];
        $day_events = [];
        foreach($events as $event) {
            if($event->date_status == 0) {
                //it's a disabled day, delete event
                $deletes[$event->id] = $event->id;
                continue;
            }

            $start = strtotime($event->date_start);
            $end = strtotime($event->date_end);
            $step = $event->date_min_time * 60;

            $event->start = strtotime($event->start);
            $event->end = strtotime($event->end);

            $slots = [];
            $next = $prev = 0;
            for($current=$start;$current < $end;$current+=$step) {
                $key = $current;
                $slots[$key] = true;
                if($key <= $event->end) {
                    $prev = $key;
                }
                if($key >= $event->start && $next == 0) {
                    $next = $key;
                }
            }

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

            //we mofify event time, if the slot not available any more
            if(!isset($slots[$event->start])) {
                if($next) {
                    $updates[$event->id]['start'] = date("Y-m-d H:i:s", $next);
                    $modifies[$event->id]['start'] = date("Y-m-d H:i:s", $next);
                } else {
                    $deletes[$event->id] = $event->id;
                }
            }
            if(!isset($slots[$event->end])) {
                if($prev) {
                    $updates[$event->id]['end'] = date("Y-m-d H:i:s", $prev);
                    $modifies[$event->id]['end'] = date("Y-m-d H:i:s", $prev);
                } else {
                    $deletes[$event->id] = $event->id;
                }
            }
            if(!isset($slots[$event->start]) || !isset($slots[$event->end])) {
                if($next == $prev && $next > 0) {
                    $deletes[$event->id] = $event->id;
                }
            }
            //if it is not accepted event, we won't count with it
            //TODO: Maybe need to be a revision later
            if($event->status == 0) 
                continue;

            //we will delete it, skip counting...
            if(isset($deletes[$event->id])) continue;

            //search if we reach max publishers
            $steps = ($event->end - $event->start) / $step;
            $cell_start = $event->start;
            
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
        // dd($modifies, $updates, $deletes, $events);
        //we will notify users about modify/delete reason
        session()->now('reason', 'modified_service_time');
        // dd($day_events, $updates, $deletes); 
        //we use firstDelete for store LogHistory event
        foreach($deletes as $deleteId) {
            Event::firstWhere('id', '=', $deleteId)->delete();
        } 
              
        foreach($updates as $id => $field) {
            if(!isset($deletes[$id])) {
                Event::firstWhere('id', '=', $id)->update($field);
            }
        }

        //regenerate stat for affected days
        foreach($dates as $day) {
            $stat = new GenerateStat();
            $stat->generate($group_id, $day);
        }
    }
}