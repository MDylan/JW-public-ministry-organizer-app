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
            // A group_id korábban $group->group_id volt - olyan mező, ami a
            // Group modellen nem létezik (a kulcs neve id), így mindig null
            // került a NOT NULL oszlopba, és minden Eloquent-törlés elszállt.
            'event' => 'deleted',
            'group_id' => $group->id,
            'causer_id' => $this->causerId(),
            'changes' => ''
        ];

        $history = new LogHistory($saved_data);
        $group->histories()->save($history);
    }
}
