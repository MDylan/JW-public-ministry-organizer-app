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
                // array_key_exists(), NEM isset(): az isset() NULL értékű
                // kulcsra hamis, ezért minden NULL-ra állítás láthatatlan volt
                // az audit naplóban. A getDirty() csak ténylegesen változott
                // mezőket ad vissza, és az alatta lévő $old !== $new őr
                // megmarad, tehát ez pontosan a hiányzó eseteket engedi be.
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
