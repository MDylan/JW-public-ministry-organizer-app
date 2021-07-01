<?php

namespace App\Observers;

use App\Models\GroupNews;
use App\Models\LogHistory;

class GroupNewsObserver
{
    /**
     * Handle the GroupNews "created" event.
     *
     * @param  \App\Models\GroupNews  $groupNews
     * @return void
     */
    public function created(GroupNews $groupNews)
    {
        //
    }

    /**
     * Handle the GroupNews "updated" event.
     *
     * @param  \App\Models\GroupNews  $groupNews
     * @return void
     */
    public function updated(GroupNews $groupNews)
    {
        $changes = $groupNews->getDirty();
        $store = [];
        if(count($changes)) {
            $fillable = $groupNews->getFillable();
            foreach($fillable as $field) {
                if(isset($changes[$field])) {
                    $old = $groupNews->getOriginal($field);
                    $new = $groupNews->$field;
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
                'group_id' => $groupNews->group_id,
                'causer_id' => auth()->user()->id,
                'changes' => json_encode($store)
            ];

            $history = new LogHistory($saved_data);
            $groupNews->histories()->save($history);
        }
    }

    /**
     * Handle the GroupNews "deleted" event.
     *
     * @param  \App\Models\GroupNews  $groupNews
     * @return void
     */
    public function deleted(GroupNews $groupNews)
    {
        $saved_data = [
            'event' => 'deleted',
            'group_id' => $groupNews->group_id,
            'causer_id' => auth()->user()->id,
            'changes' => ''
        ];

        $history = new LogHistory($saved_data);
        $groupNews->histories()->save($history);
    }

    /**
     * Handle the GroupNews "restored" event.
     *
     * @param  \App\Models\GroupNews  $groupNews
     * @return void
     */
    public function restored(GroupNews $groupNews)
    {
        //
    }

    /**
     * Handle the GroupNews "force deleted" event.
     *
     * @param  \App\Models\GroupNews  $groupNews
     * @return void
     */
    public function forceDeleted(GroupNews $groupNews)
    {
        //
    }
}
