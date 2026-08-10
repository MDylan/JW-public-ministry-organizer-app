<?php

namespace App\Observers;

use App\Models\GroupLiterature;
use App\Models\LogHistory;
use App\Support\Concerns\ResolvesCauser;

class GroupLiteratureObserver
{
    use ResolvesCauser;

    /**
     * Handle the GroupLiterature "created" event.
     *
     * @param  \App\Models\GroupLiterature  $groupLiterature
     * @return void
     */
    public function created(GroupLiterature $groupLiterature)
    {
        $store = [];
        $fillable = $groupLiterature->getFillable();
        foreach($fillable as $field) {
            $new = $groupLiterature->$field;
            $store['new'][$field] = $new;
        }
        
        $saved_data = [
            'event' => 'created',
            'group_id' => $groupLiterature->group_id,
            'causer_id' => $this->causerId(),
            'changes' => json_encode($store)
        ];

        $history = new LogHistory($saved_data);
        $groupLiterature->histories()->save($history);
    }

    /**
     * Handle the GroupLiterature "updated" event.
     *
     * @param  \App\Models\GroupLiterature  $groupLiterature
     * @return void
     */
    public function updated(GroupLiterature $groupLiterature)
    {
        $changes = $groupLiterature->getDirty();
        $store = [];
        if(count($changes)) {
            $fillable = $groupLiterature->getFillable();
            foreach($fillable as $field) {
                // array_key_exists(), NOT isset(): isset() is false for a key
                // with a NULL value, so every set-to-NULL change was invisible
                // in the audit log. getDirty() only returns fields that
                // actually changed, and the $old !== $new guard below stays
                // in place, so this simply lets in the previously missing
                // cases.
                if(array_key_exists($field, $changes)) {
                    $old = $groupLiterature->getOriginal($field);
                    $new = $groupLiterature->$field;
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
                'group_id' => $groupLiterature->group_id,
                'causer_id' => $this->causerId(),
                'changes' => json_encode($store)
            ];
            $history = new LogHistory($saved_data);
            $groupLiterature->histories()->save($history);
        }
    }

    /**
     * Handle the GroupLiterature "deleted" event.
     *
     * @param  \App\Models\GroupLiterature  $groupLiterature
     * @return void
     */
    public function deleted(GroupLiterature $groupLiterature)
    {
        $store = [];
        $fillable = $groupLiterature->getFillable();
        foreach($fillable as $field) {
            $new = $groupLiterature->$field;
            $store['old'][$field] = $new;
        }
        
        $saved_data = [
            'event' => 'deleted',
            'group_id' => $groupLiterature->group_id,
            'causer_id' => $this->causerId(),
            'changes' => json_encode($store)
        ];

        $history = new LogHistory($saved_data);
        $groupLiterature->histories()->save($history);
    }

    /**
     * Handle the GroupLiterature "restored" event.
     *
     * @param  \App\Models\GroupLiterature  $groupLiterature
     * @return void
     */
    public function restored(GroupLiterature $groupLiterature)
    {
        //
    }

    /**
     * Handle the GroupLiterature "force deleted" event.
     *
     * @param  \App\Models\GroupLiterature  $groupLiterature
     * @return void
     */
    public function forceDeleted(GroupLiterature $groupLiterature)
    {
        //
    }
}
