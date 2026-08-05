<?php

namespace App\Observers;

use App\Models\GroupNews;
use App\Models\GroupNewsTranslation;
use App\Models\LogHistory;
use App\Observers\Concerns\ResolvesCauser;

class GroupNewsTranslationObserver
{
    use ResolvesCauser;

    private function resolveGroupId(GroupNewsTranslation $groupNewsTranslation): ?int
    {
        return GroupNews::withTrashed()
            ->whereKey($groupNewsTranslation->group_news_id)
            ->value('group_id');
    }

    /**
     * Handle the GroupNewsTranslation "created" event.
     *
     * @param  \App\Models\GroupNewsTranslation  $groupNewsTranslation
     * @return void
     */
    public function created(GroupNewsTranslation $groupNewsTranslation)
    {
        $store = [];
        $fillable = $groupNewsTranslation->getFillable();
        foreach($fillable as $field) {
            $new = $groupNewsTranslation->$field;
            $store['new'][$field] = $new;
        }
        
        $saved_data = [
            'event' => 'created',
            'group_id' => $this->resolveGroupId($groupNewsTranslation),
            'causer_id' => $this->causerId(),
            'changes' => json_encode($store)
        ];

        $history = new LogHistory($saved_data);
        $groupNewsTranslation->histories()->save($history);
    }

    /**
     * Handle the GroupNewsTranslation "updated" event.
     *
     * @param  \App\Models\GroupNewsTranslation  $groupNewsTranslation
     * @return void
     */
    public function updated(GroupNewsTranslation $groupNewsTranslation)
    {
        $changes = $groupNewsTranslation->getDirty();
        $store = [];
        if(count($changes)) {
            $fillable = $groupNewsTranslation->getFillable();
            foreach($fillable as $field) {
                if(isset($changes[$field])) {
                    $old = $groupNewsTranslation->getOriginal($field);
                    $new = $groupNewsTranslation->$field;
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
                'group_id' => $this->resolveGroupId($groupNewsTranslation),
                'causer_id' => $this->causerId(),
                'changes' => json_encode($store)
            ];

            $history = new LogHistory($saved_data);
            $groupNewsTranslation->histories()->save($history);
        }
    }

    /**
     * Handle the GroupNewsTranslation "deleted" event.
     *
     * @param  \App\Models\GroupNewsTranslation  $groupNewsTranslation
     * @return void
     */
    public function deleted(GroupNewsTranslation $groupNewsTranslation)
    {
        $saved_data = [
            'event' => 'deleted',
            'group_id' => $this->resolveGroupId($groupNewsTranslation),
            'causer_id' => $this->causerId(),
            'changes' => ''
        ];

        $history = new LogHistory($saved_data);
        $groupNewsTranslation->histories()->save($history);
    }

    /**
     * Handle the GroupNewsTranslation "restored" event.
     *
     * @param  \App\Models\GroupNewsTranslation  $groupNewsTranslation
     * @return void
     */
    public function restored(GroupNewsTranslation $groupNewsTranslation)
    {
        //
    }

    /**
     * Handle the GroupNewsTranslation "force deleted" event.
     *
     * @param  \App\Models\GroupNewsTranslation  $groupNewsTranslation
     * @return void
     */
    public function forceDeleted(GroupNewsTranslation $groupNewsTranslation)
    {
        //
    }
}
