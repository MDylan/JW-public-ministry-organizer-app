<?php

namespace App\Observers;

// The EventAutoCheck job was removed in v1-patch B9. It was unable to run
// (empty foreach, invalid '=<' SQL operator, and its body read an
// object property on an array element), and its two dispatch sites had been
// commented out from the start - see below -, so it demonstrably never ran.
//
// Automatic approval as a FEATURE hasn't gone away: the auto_approval and
// auto_back group fields remain, there's just no implementation behind them.
// If it's ever built, it needs a new job, not reviving this one.
use App\Models\Event;
use App\Models\Group;
use App\Models\LogHistory;
use App\Models\User;
use App\Notifications\EventCreatedNotification;
use App\Notifications\EventDeletedAdminsNotification;
use App\Notifications\EventDeletedNotification;
use App\Notifications\EventStatusChangedNotification;
use App\Notifications\EventUpdatedNotification;
use App\Support\Concerns\ResolvesCauser;
use Illuminate\Support\Facades\Notification;

class EventObserver
{
    use ResolvesCauser;

    /**
     * Handle the Event "created" event.
     *
     * @param  \App\Models\Event  $event
     * @return void
     */
    public function created(Event $event)
    {
        $causerId = $this->causerId();

        $saved_data = [
            'event' => 'created',
            'group_id' => $event->group_id,
            'causer_id' => $causerId,
            'changes' => '',
        ];

        $history = new LogHistory($saved_data);
        $event->histories()->save($history);

        // The system (causerId 0) never matches the event owner, so the
        // notification always goes out in this case - just as updated() and
        // deleted() also do.
        if($event->user_id != $causerId) {
            $data = [
                'userName' => $this->causerName(),
                'groupName' => $event->groups->name,
                'replyTo' => $event->groups->replyTo,
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

        // The commented-out EventAutoCheck dispatch used to sit here (v1-patch B9).
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
                // array_key_exists(), NOT isset(): isset() is false for a key
                // with a NULL value, so every set-to-NULL change was invisible
                // in the audit log. getDirty() only returns fields that
                // actually changed, and the $old !== $new guard below stays
                // in place, so this simply lets in the previously missing
                // cases.
                if(array_key_exists($field, $changes)) {
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
        $causerId = $this->causerId();
        if(count($store)) {
            $saved_data = [
                'event' => 'updated',
                'group_id' => $event->group_id,
                'causer_id' => $causerId,
                'changes' => json_encode($store)
            ];

            $history = new LogHistory($saved_data);
            $event->histories()->save($history);

            if(isset($store['new']['start']) || isset($store['new']['end'])) {
                $data = [
                    'userName' => $this->causerName(),
                    'groupName' => $event->groups->name,
                    'replyTo' => $event->groups->replyTo,
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
                if($event->user_id != $causerId) {
                    //notify user if not he modified this event
                    $us = User::find($event->user_id);
                    $us->notify(
                        new EventUpdatedNotification($data)
                    );
                }
            }

            if(isset($store['new']['status'])) {
                $data = [
                    'groupName' => $event->groups->name,
                    'date' => $event->day,
                    'replyTo' => $event->groups->replyTo,
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

                // The other commented-out EventAutoCheck dispatch used to sit here (v1-patch B9).

                if($event->status == 1) {
                    //accept this event, delete in other groups
                    Event::where('status', '=', 0)
                        ->where('user_id', '=', $event->user_id)
                        ->where('group_id', '!=', $event->group_id)
                        ->where('start', '<', date("Y-m-d H:i", $event->end))
                        ->where('end', '>', date("Y-m-d H:i", $event->start))
                        ->update(['status' => 2]);
                }
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
        $causerId = $this->causerId();
        $saved_data = [
            'event' => 'deleted',
            'group_id' => $event->group_id,
            'causer_id' => $causerId,
            'changes' => ''
        ];

        $history = new LogHistory($saved_data);
        $event->histories()->save($history);

        $data = [
            // Used to be false on a system delete, which showed up empty in
            // the email; now it's "SYSTEM", as updated() has long written it.
            'userName' => $this->causerName(),
            'groupName' => $event->groups->name,
            'replyTo' => $event->groups->replyTo,
            'date' => $event->day,
            'oldService' => [
                'start' => date("Y-m-d H:i:s", $event->start),
                'end' => date("Y-m-d H:i:s", $event->end),
            ],
            'reason' => session()->has('reason') ? session('reason') : false 
        ];
        $us = User::find($event->user_id);
        if(!$us->isAnonymized) {
            $data['event_user'] = $us->name;
        } else {
            $data['event_user'] = 'anonym';
        }
        if($event->user_id != $causerId && !$us->isAnonymized) {
            //notify user if not he deleted this event            
            $us->notify(
                new EventDeletedNotification($data)
            );
        }
        if($event->status == 1) {
            $group_id = $event->group_id;
            $group_editors = Group::find($group_id)
                ->editors()
                ->get()
                ->toArray();
            $editors = [];
            foreach($group_editors as $editor) {
                $editors[$editor['id']] = $editor['id'];
            }
            $admins = User::whereIn('id', $editors)
                        ->get();
            
            Notification::send($admins, new EventDeletedAdminsNotification($data));
        }

    }
}
