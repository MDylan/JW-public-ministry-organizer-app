<?php

namespace App\Observers;

use App\Models\Event;
use App\Models\LogHistory;
use App\Models\User;
use App\Notifications\EventCreatedNotification;
use App\Notifications\EventDeletedNotification;
use App\Notifications\EventStatusChangedNotification;
use App\Notifications\EventUpdatedNotification;

class EventObserver
{

    /**
     * Handle the Event "created" event.
     *
     * @param  \App\Models\Event  $event
     * @return void
     */
    public function created(Event $event)
    {
        // $store = [];
        // $fillable = $event->getFillable();
        // foreach($fillable as $field) {
        //     $new = $event->$field;
        //     $store['new'][$field] = $new;
        // }
        $saved_data = [
            'event' => 'created',
            'group_id' => $event->group_id,
            'causer_id' => auth()->user()->id,
            'changes' => '', // json_encode($store)
        ];

        $history = new LogHistory($saved_data);
        $event->histories()->save($history);

        if($event->user_id != auth()->user()->id) {
            $data = [
                'userName' => auth()->user()->full_name, 
                'groupName' => $event->groups->name,
                'date' => $event->day,
                'newService' => [
                    'start' => date("Y-m-d H:i:s", $event->start),
                    'end' => date("Y-m-d H:i:s", $event->end),                        
                ],
                'reason' => false 
            ];
            
            $us = User::find($event->user_id);
            $us->notify(
                new EventCreatedNotification($data)
            );
        }
    }

    /**
     * Handle the Event "updated" event.
     *
     * @param  \App\Models\Event  $event
     * @return void
     */
    public function updated(Event $event)
    {
        $changes = $event->getDirty();
        $store = [];
        if(count($changes)) {
            $fillable = $event->getFillable();
            foreach($fillable as $field) {
                if(isset($changes[$field])) {
                    $old = $event->getOriginal($field);
                    $new = $event->$field;
                    if($old !== $new) {
                        if(in_array($field, array('start', 'end'))) {
                            $old = date('H:i', $old);
                            $new = date('H:i', $new);
                        }
                        $store['old'][$field] = $old;
                        $store['new'][$field] = $new;
                    }
                }
            }
        }
        if(count($store)) {
            $saved_data = [
                'event' => 'updated',
                'group_id' => $event->group_id,
                'causer_id' => auth()->user()->id,
                'changes' => json_encode($store)
            ];

            $history = new LogHistory($saved_data);
            $event->histories()->save($history);

            if(isset($store['new']['start']) || isset($store['new']['end'])) {
                $data = [
                    'userName' => auth()->user()->full_name, 
                    'groupName' => $event->groups->name,
                    'date' => $event->day,
                    'oldService' => [
                        'start' => date("Y-m-d H:i:s", $event->getOriginal('start')),
                        'end' => date("Y-m-d H:i:s", $event->getOriginal('end')),
                    ],
                    'newService' => [
                        'start' => date("Y-m-d H:i:s", $event->start),
                        'end' => date("Y-m-d H:i:s", $event->end),                        
                    ],
                    'reason' => session()->has('reason') ? session('reason') : false 
                ];
                
                $us = User::find($event->user_id);
                $us->notify(
                    new EventUpdatedNotification($data)
                );
            }

            if(isset($store['new']['status'])) {
                $data = [
                    'userName' => auth()->user()->full_name, 
                    'groupName' => $event->groups->name,
                    'date' => $event->day,
                    'newService' => [
                        'start' => date("Y-m-d H:i:s", $event->start),
                        'end' => date("Y-m-d H:i:s", $event->end),                        
                    ],
                    'status' => $event->status 
                ];
                
                $us = User::find($event->user_id);
                $us->notify(
                    new EventStatusChangedNotification($data)
                );
            }
        }
    }

    /**
     * Handle the Event "deleted" event.
     *
     * @param  \App\Models\Event  $event
     * @return void
     */
    public function deleted(Event $event)
    {
        $saved_data = [
            'event' => 'deleted',
            'group_id' => $event->group_id,
            'causer_id' => auth()->user()->id,
            'changes' => ''
        ];

        $history = new LogHistory($saved_data);
        $event->histories()->save($history);

        $data = [
            'userName' => auth()->user()->full_name, 
            'groupName' => $event->groups->name,
            'date' => $event->day,
            'oldService' => [
                'start' => date("Y-m-d H:i:s", $event->start),
                'end' => date("Y-m-d H:i:s", $event->end),
            ],
            'reason' => session()->has('reason') ? session('reason') : false 
        ];
        
        $us = User::find($event->user_id);
        $us->notify(
            new EventDeletedNotification($data)
        );
    }
}
