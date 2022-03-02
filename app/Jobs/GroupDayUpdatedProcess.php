<?php

namespace App\Jobs;

use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldBeUnique;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use App\Classes\GenerateStat;
use App\Models\Event;
use App\Models\Group;
use App\Models\GroupDate;
use App\Models\User;
use App\Notifications\EventDeletedNotification;
use App\Notifications\EventUpdatedNotification;
use DateTime;
use Illuminate\Support\Facades\DB;

class GroupDayUpdatedProcess implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    private $date;
    private $group_id;
    private $day_number;
    private $start_time;
    private $end_time;
    private $user_id;
    /**
     * Create a new job instance.
     *
     * @return void
     */
    public function __construct($date, $group_id, $day_number, $start_time, $end_time, $user_id)
    {
        $this->date = $date;
        $this->group_id = $group_id;
        
        // because MySQL wierd weekday issue...
        // The WEEKDAY() function returns the weekday number for a given date.
        // Note: 0 = Monday, 1 = Tuesday, 2 = Wednesday, 3 = Thursday, 4 = Friday, 5 = Saturday, 6 = Sunday.
        // in PHP date("w) 
        //       1 = Monday, 2 = Tuesday, 3 = Wednesday, 4 = Thursday, 5 = Friday, 6 = Saturday, 0 = Sunday.
        //0 (for Sunday) through 6 (for Saturday)

        // keys are PHP values, values are MySQL values
        $mySql_days = [
            0 => 6,
            1 => 0,
            2 => 1,
            3 => 2,
            4 => 3,
            5 => 4,
            6 => 5,
        ];

        $this->day_number = $mySql_days[$day_number];
        $this->start_time = $start_time;
        $this->end_time = $end_time;
        $this->user_id = $user_id;
    }

    /**
     * Execute the job.
     *
     * @return void
     */
    public function handle()
    {
        $group = Group::find($this->group_id);
        $causer_user = User::find($this->user_id);
        //we must delete feature events, which not in right timeslot
        $events = DB::select('SELECT e.id, e.day, e.start, e.end, gd.date, gd.date_status, e.user_id
                                    FROM events as e
                                    LEFT JOIN group_dates AS gd ON gd.group_id = e.group_id AND gd.date = e.day
                                    WHERE e.deleted_at IS NULL
                                    AND e.group_id = ?
                                    AND e.day >= ?
                                    AND WEEKDAY(e.day) = ?', [
                                        $this->group_id, 
                                        $this->date,
                                        $this->day_number
                                    ]);
        // dd($events);
        $deletes = [];
        $updates = [];
        $updateDays = [];
        $users = [];
        $notifies = [];
        foreach($events as $event) {
            //if it's a special date, we dont modify it
            if($event->date_status == 2) continue;
            
            // $d = new DateTime($event->day);
            // $dayOfWeek = $d->format("w");

            // //it's not right day, skip this
            // if($dayOfWeek != $this->day_number) continue;

            $start = strtotime($event->day." ".$this->start_time);
            $end = strtotime($event->day." ".$this->end_time);

            $event->start = strtotime($event->start);
            $event->end = strtotime($event->end);

            if($event->end <= $start || $event->start >= $end) {
                //it's totally out of slot, must delete
                $deletes[$event->id] = $event->id;
                $updateDays[$event->day] = $event->day;
                $notifies[$event->user_id]['deletes'][] = [
                    'userName' => $causer_user->name, 
                    'groupName' => $group->name,
                    'date' => $event->day,
                    'oldService' => [
                        'start' => date("Y-m-d H:i:s", $event->start),
                        'end' => date("Y-m-d H:i:s", $event->end),
                    ],
                    'reason' => 'modified_service_time'
                ];
            } elseif($event->start < $start && $event->end > $start) {
                //only the start not good, we update it
                $updates[$event->id]['start'] = date("Y-m-d H:i:s", $start);
                $updateDays[$event->day] = $event->day;

                $notifies[$event->user_id]['updates'][] = [
                    'userName' => $causer_user->name, 
                    'groupName' => $group->name,
                    'date' => $event->day,
                    'oldService' => [
                        'start' => date("Y-m-d H:i:s", $event->start),
                        'end' => date("Y-m-d H:i:s", $event->end),
                    ],
                    'newService' => [
                        'start' => date("Y-m-d H:i:s", $start),
                        'end' => date("Y-m-d H:i:s", $event->end),                        
                    ],
                    'reason' => 'modified_service_time'
                ];

            } elseif($event->end > $end && $event->start < $end) {
                //only the start not good, we update it
                $updates[$event->id]['end'] = date("Y-m-d H:i:s", $end);
                $updateDays[$event->day] = $event->day;

                $notifies[$event->user_id]['updates'][] = [
                    'userName' => $causer_user->name, 
                    'groupName' => $group->name,
                    'date' => $event->day,
                    'oldService' => [
                        'start' => date("Y-m-d H:i:s", $event->start),
                        'end' => date("Y-m-d H:i:s", $event->end),
                    ],
                    'newService' => [
                        'start' => date("Y-m-d H:i:s", $event->start),
                        'end' => date("Y-m-d H:i:s", $end),                        
                    ],
                    'reason' => 'modified_service_time'
                ];
            }
        }

        //Deleting events
        //don't want duplicate notifications
        Event::withoutEvents(function () use ($deletes) {
            Event::destroy($deletes);
        });

        //update events
        Event::withoutEvents(function () use ($updates) {
            foreach($updates as $id => $field) {
                Event::where('id', '=', $id)->update($field);
            }
        });

        //notify users
        if(count($notifies) > 0) {
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
        $groupdates = DB::select('SELECT gd.id, gd.date, gd.date_status
                                    FROM group_dates as gd
                                    WHERE gd.group_id = ?
                                    AND gd.date >= ?
                                    AND gd.date_status = 1
                                    AND WEEKDAY(gd.date) = ?', [
                                        $this->group_id, 
                                        $this->date,
                                        $this->day_number
                                    ]);

        foreach($groupdates as $day) {

            // $d = new DateTime($day->date);
            // $dayOfWeek = $d->format("w");

            // //it's not right day, skip this
            // if($dayOfWeek != $this->day_number) continue;

            $start = strtotime($day->date." ".$this->start_time);
            $end = strtotime($day->date." ".$this->end_time);

            GroupDate::where('id', '=', $day->id)->update([
                'date_start' => date("Y-m-d H:i:s", $start),
                'date_end' => date("Y-m-d H:i:s", $end),
            ]);

            $stat = new GenerateStat();
            $stat->generate($this->group_id, $day->date);
        }
    }
}
