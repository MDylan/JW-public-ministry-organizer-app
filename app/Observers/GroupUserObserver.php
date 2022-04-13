<?php

namespace App\Observers;

use App\Models\GroupUser;
use App\Models\LogHistory;

class GroupUserObserver
{
    /**
     * Handle the GroupUser "created" event.
     *
     * @param  \App\Models\GroupUser  $groupUser
     * @return void
     */
    public function created(GroupUser $groupUser)
    {
        //
    }

    /**
     * Handle the GroupUser "updated" event.
     *
     * @param  \App\Models\GroupUser  $groupUser
     * @return void
     */
    public function updated(GroupUser $groupUser)
    {
        $changes = $groupUser->getDirty();
        $store = [];
        if(count($changes)) {
            $fillable = $groupUser->getFillable();
            foreach($fillable as $field) {
                if(isset($changes[$field])) {
                    $old = $groupUser->getOriginal($field);
                    $new = $groupUser->$field;
                    if($old !== $new) {
                        $store['old'][$field] = $old;
                        $store['new'][$field] = $new;
                    }
                }
            }
        }
        if(count($store) && (auth()->user() !== null)) {
            $saved_data = [
                'event' => 'updated',
                'group_id' => $groupUser->group_id,
                'causer_id' => auth()->user()->id,
                'changes' => json_encode($store)
            ];
            $history = new LogHistory($saved_data);
            $groupUser->histories()->save($history);
        }
    }

    /**
     * Handle the GroupUser "deleted" event.
     *
     * @param  \App\Models\GroupUser  $groupUser
     * @return void
     */
    public function deleted(GroupUser $groupUser)
    {
        // dd('id:'.$groupUser->id);
        if(isset($groupUser->id)) {
            $saved_data = [
                // 'model_id' => $groupUser->id,
                'event' => 'deleted',
                'group_id' => $groupUser->group_id,
                'causer_id' => auth()->user()->id,
                'changes' => ''
            ];
            $history = new LogHistory($saved_data);
            $groupUser->histories()->save($history);
        }
    }

    /**
     * Handle the GroupUser "restored" event.
     *
     * @param  \App\Models\GroupUser  $groupUser
     * @return void
     */
    public function restored(GroupUser $groupUser)
    {
        //
    }

    /**
     * Handle the GroupUser "force deleted" event.
     *
     * @param  \App\Models\GroupUser  $groupUser
     * @return void
     */
    public function forceDeleted(GroupUser $groupUser)
    {
        //
    }
}
