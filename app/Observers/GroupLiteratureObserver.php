<?php

namespace App\Observers;

use App\Models\GroupLiterature;
use App\Models\LogHistory;

class GroupLiteratureObserver
{
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
            'causer_id' => auth()->user()->id,
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
                if(isset($changes[$field])) {
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
                'causer_id' => auth()->user()->id,
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
            'causer_id' => auth()->user()->id,
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
