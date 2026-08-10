<?php

namespace App\Observers;

use App\Models\Group;
use App\Models\LogHistory;
use App\Support\Concerns\ResolvesCauser;

class GroupObserver
{
    use ResolvesCauser;

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
                // array_key_exists(), NOT isset(): isset() is false for a key
                // with a NULL value, so every set-to-NULL change was invisible
                // in the audit log. getDirty() only returns fields that
                // actually changed, and the $old !== $new guard below stays
                // in place, so this simply lets in the previously missing
                // cases.
                if(array_key_exists($field, $changes)) {
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
                'causer_id' => $this->causerId(),
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
            // group_id used to be $group->group_id - a field that doesn't
            // exist on the Group model (the key is named id), so it always
            // put null into the NOT NULL column, and every Eloquent delete
            // crashed.
            'event' => 'deleted',
            'group_id' => $group->id,
            'causer_id' => $this->causerId(),
            'changes' => ''
        ];

        $history = new LogHistory($saved_data);
        $group->histories()->save($history);
    }
}
