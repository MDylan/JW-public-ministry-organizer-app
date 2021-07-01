<?php

namespace App\Observers;

use App\Models\Group;
use App\Models\LogHistory;

class GroupObserver
{
    /**
     * Handle the Group "updated" event.
     *
     * @param  \App\Models\Group  $group
     * @return void
     */
    public function updated(Group $group)
    {
        $changes = $group->getDirty();
        $store = [];
        if(count($changes)) {
            $fillable = $group->getFillable();
            foreach($fillable as $field) {
                if(isset($changes[$field])) {
                    $old = $group->getOriginal($field);
                    $new = $group->$field;
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
                'group_id' => $group->id,
                'causer_id' => auth()->user()->id,
                'changes' => json_encode($store)
            ];

            $history = new LogHistory($saved_data);
            $group->histories()->save($history);
        }
    }

    /**
     * Handle the Group "deleted" event.
     *
     * @param  \App\Models\Group  $group
     * @return void
     */
    public function deleted(Group $group)
    {
        $saved_data = [
            'event' => 'deleted',
            'group_id' => $group->group_id,
            'causer_id' => auth()->user()->id,
            'changes' => ''
        ];

        $history = new LogHistory($saved_data);
        $group->histories()->save($history);
    }
}
