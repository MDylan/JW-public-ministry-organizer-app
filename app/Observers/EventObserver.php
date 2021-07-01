<?php

namespace App\Observers;

use App\Models\Event;
use App\Models\LogHistory;

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
        $store = [];
        $fillable = $event->getFillable();
        foreach($fillable as $field) {
            $new = $event->$field;
            $store['new'][$field] = $new;
        }
        $saved_data = [
            'event' => 'create',
            'group_id' => $event->group_id,
            'causer_id' => auth()->user()->id,
            'changes' => json_encode($store)
        ];

        $history = new LogHistory($saved_data);
        $event->histories()->save($history);
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
                        $store['old'][$field] = $old;
                        $store['new'][$field] = $new;
                    }
                }
            }
        }
        if(count($store)) {
            $saved_data = [
                'event' => 'update',
                'group_id' => $event->group_id,
                'causer_id' => auth()->user()->id,
                'changes' => json_encode($store)
            ];

            $history = new LogHistory($saved_data);
            $event->histories()->save($history);
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
            'event' => 'delete',
            'group_id' => $event->group_id,
            'causer_id' => auth()->user()->id,
            'changes' => ''
        ];

        $history = new LogHistory($saved_data);
        $event->histories()->save($history);
    }
}
