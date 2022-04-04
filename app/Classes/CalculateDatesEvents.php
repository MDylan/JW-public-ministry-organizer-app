<?php

namespace App\Classes;

use App\Models\Event;
use App\Models\Group;
// use App\Models\GroupDate;
use App\Models\GroupDayDisabledSlots;
use App\Models\User;
use App\Notifications\EventDeletedNotification;
use App\Notifications\EventUpdatedNotification;
// use App\Models\LogHistory;
// use App\Observers\EventObserver;
// use Carbon\Carbon;
use DateTime;
// use Illuminate\Container\Container;
// use Illuminate\Events\Dispatcher;
// use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\DB;

class CalculateDatesEvents {
    /**
     * Calculate events based on group_dates
     */
    static function generate(int $group_id, array|string $date, $user_id = false) {
        $dates = [];
        if(is_array($date)) {
            $dates = $date; 
        } else {
            $dates = [$date];
        }

        $disabled_slots = [];
        $d_slots = GroupDayDisabledSlots::where('group_id', '=', $group_id)
            ->orderBy('day_number', 'asc')
            ->orderBy('slot', 'asc')
            ->get()->toArray();
        foreach($d_slots as $slot) {
            $disabled_slots[$slot['day_number']][$slot['slot']] = $slot['slot'];
        }
        // dd($disabled_slots);

        $events = DB::table('events')
                    ->join('group_dates', function ($join) {
                        $join->on('group_dates.group_id', '=', 'events.group_id')
                            ->on('group_dates.date', '=', 'events.day');
                    })
                    ->select('events.id', 'events.day', 'events.start', 'events.end', 'events.status', 'events.user_id',
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
        $deletes = [];
        $updates = [];
        $modifies = [];
        $day_events = [];
        $dayOfWeeks = [];
        $debug = [];
        $original_data = [];
        $days_slots = [];
        $days_disabled_slots = [];
        foreach($events as $event) {
            if($event->date_status == 0) {
                //it's a disabled day, delete event
                $deletes[$event->id] = $event->id;
                $modifies[$event->id]['delete'][] = "disabled day";
                continue;
            }
            $original_data[$event->id] = json_decode(json_encode($event), true);

            if(!isset($dayOfWeeks[$event->day])) {
                $d = new DateTime( $event->day );
                $dayOfWeek = $d->format("w");
                $dayOfWeeks[$event->day] = $dayOfWeek;
            } else {
                $dayOfWeek = $dayOfWeeks[$event->day];
                if(isset($disabled_slots[$dayOfWeek])) {
                    $di_slots = $disabled_slots[$dayOfWeek];
                    foreach($di_slots as $di_slot) {
                        $ts = strtotime($event->day." ".$di_slot);
                        $slot_key = date("H:i", $ts);
                        $days_disabled_slots[$event->day][$slot_key] = true;
                    }
                }
            }

            $start = strtotime($event->date_start);
            $end = strtotime($event->date_end);
            $step = $event->date_min_time * 60;

            $event->start = strtotime($event->start);
            $event->end = strtotime($event->end);

            //check day start and end time period
            if($event->end <= $start || $event->start >= $end) {
                //it's totally out of slot, must delete
                $deletes[$event->id] = $event->id;
                $modifies[$event->id]['delete_wrong_time'][] = "wrong start or end";
            } elseif($event->start < $start && $event->end > $start) {
                //only the start not good, we update it
                $modifies[$event->id]['start_modify'][] = "set start time to: ".date("Y-m-d H:i:s", $start);
                $updates[$event->id]['start'] = date("Y-m-d H:i:s", $start);
                $event->start = $start;
            } elseif($event->end > $end && $event->start < $end) {
                //only the start not good, we update it
                $modifies[$event->id]['end_modify'][] = "set end time to: ".date("Y-m-d H:i:s", $end);
                $updates[$event->id]['end'] = date("Y-m-d H:i:s", $end);
                $event->end = $start;
            }

            if(isset($deletes[$event->id])) continue;

            if(isset($days_slots[$event->day])) {
                $slots_array = $days_slots[$event->day];
            } else {
                $slots_array = GenerateSlots::generate($event->day, $start, $end, $step);
                $slots_array[$end] = $end; //add day last slot
                $days_slots[$event->day] = $slots_array;
            }
            $slots = [];
            //set slot's abilities
            foreach($slots_array as $key) {
                $slots[$key] = isset($days_disabled_slots[$event->day][date("H:i", $key)]) ? false : true;
            }
            //find new end slot
            if(!isset($slots[$event->end])) {
                $new_end = 0;
                $reverse_slots = array_reverse($slots_array, true);
                foreach($reverse_slots as $current) {
                    if($current >= $event->end) {
                        $new_end = $current;
                    }
                }
                if($new_end) {
                    $modifies[$event->id]['new_end'] = date("Y-m-d H:i:s", $new_end);
                    $updates[$event->id]['end'] = date("Y-m-d H:i:s", $new_end);
                    $event->end = $new_end;
                } else {
                    $deletes[$event->id] = $event->id;
                    $modifies[$event->id]['new_delete_end'][] = "not_end_slot: ".date("Y-m-d H:i", $event->end)." -- ".date("Y-m-d H:i", $end);
                }
            }
            
            //find new start slot
            if(!isset($slots[$event->start])) {
                $new_start = 0;
                foreach($slots_array as $current) {
                    if($current <= $event->start) {
                        $new_start = $current;
                    }
                }
                if($new_start) {
                    $modifies[$event->id]['new_start'] = date("Y-m-d H:i:s", $new_start);
                    $updates[$event->id]['start'] = date("Y-m-d H:i:s", $new_start);
                    $event->end = $new_end;
                } else {
                    $deletes[$event->id] = $event->id;
                    $modifies[$event->id]['new_delete_start'][] = "not_start_slot: ".date("Y-m-d H:i", $event->start)." -- ".date("Y-m-d H:i", $start);
                }
            }
            if($event->start == $event->end) {
                $deletes[$event->id] = $event->id;
                $modifies[$event->id]['same_start-end'][] = "same_start: ".date("Y-m-d H:i", $event->start)." = ".date("Y-m-d H:i", $event->end);
            }
            //it deleted, skip the next parts
            if(isset($deletes[$event->id])) {
                continue;
            }

            //check all if all slot available
            $new_start = false;
            $new_end = false;
            $oks = 0;
            $i = 0;
            $steps = ceil(($event->end - $event->start) / $step);
            for($event_current = $event->start; $event_current <= $event->end; $event_current += ($step)) {
                $slot_key = date("H:i", $event_current);
                if(($slots[$event_current] ?? false) == true) {
                    $debug[$event->id][] = $slot_key." - ok";
                    if(!$new_start) $new_start = $event_current;
                    $new_end = $event_current;
                    $oks++;
                } else {
                    $debug[$event->id][] = $slot_key." - wrong";
                    if($new_start) {
                        $new_end = $event_current;
                        break;
                    }
                }
                $i++;
            }
            if($oks == 0 || $new_start == $new_end) {
                $deletes[$event->id] = $event->id;
                $modifies[$event->id]['delete'][] = "not_oks";
            }
            if($new_start != $event->start && $new_start) {
                $event->start = $new_start;
                $modifies[$event->id]['start_2'] = date("Y-m-d H:i:s", $new_start);
                $updates[$event->id]['start'] = date("Y-m-d H:i:s", $new_start);
            }
            if($new_end != $event->end && $new_end) {
                $event->end = $new_end;
                $modifies[$event->id]['end_2'] = date("Y-m-d H:i:s", $new_end);
                $updates[$event->id]['end'] = date("Y-m-d H:i:s", $new_end);
            }
            //if it is not accepted event, we won't count with it
            //TODO: Maybe need to be a revision later
            if($event->status == 0) 
                continue;

            //we will delete it, skip counting...
            if(isset($deletes[$event->id])) continue;

            //search if we reach max publishers
            $steps = ceil(($event->end - $event->start) / $step);
            $cell_start = $event->start;
            
            for($i=0;$i < $steps;$i++) {
                $slot_key = "'".date("Hi", $cell_start)."'";
                if(!isset($day_events[$event->day][$slot_key]['events'])) 
                    $day_events[$event->day][$slot_key]['events'] = 0;
                $day_events[$event->day][$slot_key]['events']++;
                
                if($day_events[$event->day][$slot_key]['events'] > $event->date_max_publishers) {
                    $deletes[$event->id] = $event->id;
                    $modifies[$event->id]['delete'][] = "reach max publisher";
                }
                $cell_start += $step;
            }
        }
        // dd(/*$disabled_slots, $debug,*/ $modifies, 'Upd', $updates, 'Del:', $deletes, $events, $original_data);

        if($user_id) {
            $causer_user = User::find($user_id);
        } else {
            $causer_user = auth()->user();
        }
        $group = Group::find($group_id);

        Event::withoutEvents(function () use ($deletes) {
            Event::destroy($deletes);
        });

        $notifies = [];
        foreach($deletes as $event_id => $v) {
            $event = $original_data[$event_id];
            $notifies[$event['user_id']]['deletes'][] = [
                'userName' => $causer_user->name, 
                'groupName' => $group->name,
                'replyTo' => $group->replyTo,
                'date' => $event['day'],
                'oldService' => [
                    'start' => $event['start'],
                    'end' => $event['end'],
                ],
                'reason' => 'modified_service_time'
            ];
        }

        //update events
        Event::withoutEvents(function () use ($updates) {
            foreach($updates as $id => $field) {
                if(!isset($deletes[$id])) {
                    Event::where('id', '=', $id)->update($field);
                }
            }
        });

        foreach($updates as $id => $field) {
            if(!isset($deletes[$id])) {
                $event = $original_data[$id];
                $notifies[$event['user_id']]['updates'][] = [
                    'userName' => $causer_user->name, 
                    'groupName' => $group->name,
                    'replyTo' => $group->replyTo,
                    'date' => $event['day'],
                    'oldService' => [
                        'start' => $event['start'],
                        'end' => $event['end'],
                    ],
                    'newService' => [
                        'start' => ($field['start'] ?? false) ? $field['start'] : $event['start'],
                        'end' => ($field['end'] ?? false) ? $field['end'] :$event['end'],
                    ],
                    'reason' => 'modified_service_time'
                ];
            }
        }

        //notify users
        if(count($notifies) > 0) {
            // dd($notifies);
            foreach($notifies as $user_id => $types) {
                $us = User::find($user_id);
                foreach($types as $type => $datas) {
                    foreach($datas as $data) {
                        if($type == 'updates') {
                            $us->notify(
                                new EventUpdatedNotification($data)
                            );
                        } elseif($type == 'deletes') {
                            $us->notify(
                                new EventDeletedNotification($data)
                            );
                        }
                    }
                }
            }
        }

        //regenerate stat for affected days
        foreach($dates as $day) {
            $stat = new GenerateStat();
            $stat->generate($group_id, $day);
        }
    }
}