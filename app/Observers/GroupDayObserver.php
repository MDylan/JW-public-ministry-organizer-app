<?php

namespace App\Observers;

use App\Classes\GenerateStat;
use App\Models\Event;
use App\Models\GroupDate;
use App\Models\GroupDay;
use App\Models\LogHistory;
use DateTime;
use Illuminate\Support\Facades\DB;

class GroupDayObserver
{
    /**
     * Handle the GroupDay "created" event.
     *
     * @param  \App\Models\GroupDay  $groupDay
     * @return void
     */
    public function created(GroupDay $groupDay)
    {
        $store = [];
        $fillable = $groupDay->getFillable();
        foreach($fillable as $field) {
            $new = $groupDay->$field;
            $store[$field] = $new;
        }
        $saved_data = [
            'event' => 'created',
            'group_id' => $groupDay->group_id,
            'causer_id' => auth()->user()->id,
            'changes' => json_encode($store)
        ];

        $history = new LogHistory($saved_data);
        $groupDay->histories()->save($history);
    }

    /**
     * Handle the GroupDay "updated" event.
     *
     * @param  \App\Models\GroupDay  $groupDay
     * @return void
     */
    public function updated(GroupDay $groupDay)
    {
        $changes = $groupDay->getDirty();
        $store = [];
        if(count($changes)) {
            $fillable = $groupDay->getFillable();
            foreach($fillable as $field) {
                if(isset($changes[$field])) {
                    $old = $groupDay->getOriginal($field);
                    $new = $groupDay->$field;
                    if($old !== $new) {
                        $store['old'][$field] = $old;
                        $store['new'][$field] = $new;
                    }
                }
            }
        }
        if(count($store)) {
            $saved_data = [
                'event' => 'updated',
                'group_id' => $groupDay->group_id,
                'causer_id' => auth()->user()->id,
                'changes' => json_encode($store)
            ];

            $history = new LogHistory($saved_data);
            $groupDay->histories()->save($history);

            if(isset($changes['start_time']) || isset($changes['end_time'])) {
                //we must delete feature events, which not in right timeslot
                $date = date("Y-m-d");
                

                // $feature_dates = GroupDate::where('group_id', '=', $groupDay->group_id)
                //                             ->where('date', '>=', $date)
                //                             ->get()
                //                             ->toArray();
                
                $events = DB::select('SELECT e.id, e.day, e.start, e.end, gd.date, gd.date_status
                                            FROM events as e
                                            LEFT JOIN group_dates AS gd ON gd.group_id = e.group_id AND gd.date = e.day
                                            WHERE e.deleted_at IS NULL
                                            AND e.group_id = ?
                                            AND e.day >= ?', [$groupDay->group_id, $date]);
                // dd($events);
                $deletes = [];
                $updates = [];
                $updateDays = [];
                foreach($events as $event) {
                    //if it's a special date, we dont modify it
                    if($event->date_status == 2) continue;
                    
                    $d = new DateTime($event->day);
                    $dayOfWeek = $d->format("w");

                    //it's not right day, skip this
                    if($dayOfWeek != $groupDay->day_number) continue;

                    $start = strtotime($event->day." ".$groupDay->start_time);
                    $end = strtotime($event->day." ".$groupDay->end_time);

                    $event->start = strtotime($event->start);
                    $event->end = strtotime($event->end);

                    if($event->end <= $start || $event->start >= $end) {
                        //it's totally out of slot, must delete
                        $deletes[$event->id] = $event->id;
                        $updateDays[$event->day] = $event->day;
                    } elseif($event->start < $start && $event->end > $start) {
                        //only the start not good, we update it
                        $updates[$event->id]['start'] = date("Y-m-d H:i:s", $start);
                        $updateDays[$event->day] = $event->day;
                    } elseif($event->end > $end && $event->start < $end) {
                        //only the start not good, we update it
                        $updates[$event->id]['end'] = date("Y-m-d H:i:s", $end);
                        $updateDays[$event->day] = $event->day;
                    }
                }
                // dd($updateDays);
                //we use firstDelete for store LogHistory event
                foreach($deletes as $deleteId) {
                    Event::firstWhere('id', '=', $deleteId)->delete();
                }
                foreach($updates as $id => $field) {
                    Event::firstWhere('id', '=', $id)->update($field);
                }
                //regenerate stat for affected days

                $groupdates = DB::select('SELECT gd.id, gd.date, gd.date_status
                                            FROM group_dates as gd
                                            WHERE gd.group_id = ?
                                            AND gd.date >= ?
                                            AND gd.date_status = 1', [$groupDay->group_id, $date]);

                foreach($groupdates as $day) {

                    $d = new DateTime($day->date);
                    $dayOfWeek = $d->format("w");

                    //it's not right day, skip this
                    if($dayOfWeek != $groupDay->day_number) continue;

                    $start = strtotime($day->date." ".$groupDay->start_time);
                    $end = strtotime($day->date." ".$groupDay->end_time);

                    GroupDate::where('id', '=', $day->id)->update([
                        'date_start' => date("Y-m-d H:i:s", $start),
                        'date_end' => date("Y-m-d H:i:s", $end),
                    ]);

                    $stat = new GenerateStat();
                    $stat->generate($groupDay->group_id, $day->date);
                }
            }
        }
    }

    /**
     * Handle the GroupDay "deleted" event.
     *
     * @param  \App\Models\GroupDay  $groupDay
     * @return void
     */
    public function deleted(GroupDay $groupDay)
    {
        // dd('deleted');
        $store = [];
        $fillable = $groupDay->getFillable();
        foreach($fillable as $field) {
            $new = $groupDay->$field;
            $store[$field] = $new;
        }
        $saved_data = [
            'event' => 'deleted',
            'group_id' => $groupDay->group_id,
            'causer_id' => auth()->user()->id,
            'changes' => json_encode($store)
        ];

        $history = new LogHistory($saved_data);
        $groupDay->histories()->save($history);
    }

    /**
     * Handle the GroupDay "force deleted" event.
     *
     * @param  \App\Models\GroupDay  $groupDay
     * @return void
     */
    public function forceDeleted(GroupDay $groupDay)
    {
        $store = [];
        $fillable = $groupDay->getFillable();
        foreach($fillable as $field) {
            $new = $groupDay->$field;
            $store[$field] = $new;
        }
        $saved_data = [
            'event' => 'deleted',
            'group_id' => $groupDay->group_id,
            'causer_id' => auth()->user()->id,
            'changes' => json_encode($store)
        ];

        $history = new LogHistory($saved_data);
        $groupDay->histories()->save($history);
    }
}
