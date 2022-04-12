<?php

namespace App\Observers;

use App\Jobs\GroupDayDeletedProcess;
use App\Jobs\GroupDayUpdatedProcess;
use App\Models\GroupDay;
use App\Models\LogHistory;


class GroupDayObserver
{
    /**
     * Handle the GroupDay "created" event.
     *
     * @param  \App\Models\GroupDay  $groupDay
     * @return void
     */
    public function created(GroupDay $groupDay)
    {
        $store = [];
        $fillable = $groupDay->getFillable();
        foreach($fillable as $field) {
            $new = $groupDay->$field;
            $store[$field] = $new;
        }
        $saved_data = [
            'event' => 'created',
            'group_id' => $groupDay->group_id,
            'causer_id' => auth()->user()->id,
            'changes' => json_encode($store)
        ];

        $history = new LogHistory($saved_data);
        $groupDay->histories()->save($history);
    }

    /**
     * Handle the GroupDay "updated" event.
     *
     * @param  \App\Models\GroupDay  $groupDay
     * @return void
     */
    public function updated(GroupDay $groupDay)
    {
        $changes = $groupDay->getDirty();
        $store = [];
        if(count($changes)) {
            $fillable = $groupDay->getFillable();
            foreach($fillable as $field) {
                if(isset($changes[$field])) {
                    $old = $groupDay->getOriginal($field);
                    $new = $groupDay->$field;
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
                'group_id' => $groupDay->group_id,
                'causer_id' => auth()->user()->id,
                'changes' => json_encode($store)
            ];

            $history = new LogHistory($saved_data);
            $groupDay->histories()->save($history);

            if(isset($changes['start_time']) || isset($changes['end_time'])) {
                // //we must delete feature events, which not in right timeslot

                GroupDayUpdatedProcess::dispatch(
                    date('Y-m-d'),
                    $groupDay->group_id,
                    $groupDay->day_number,
                    $groupDay->start_time,
                    $groupDay->end_time,
                    auth()->user()->id
                );
            }
        }
    }

    /**
     * Handle the GroupDay "deleted" event.
     *
     * @param  \App\Models\GroupDay  $groupDay
     * @return void
     */
    public function deleted(GroupDay $groupDay)
    {
        // dd('deleted');
        $store = [];
        $fillable = $groupDay->getFillable();
        foreach($fillable as $field) {
            $new = $groupDay->$field;
            $store[$field] = $new;
        }
        $saved_data = [
            'event' => 'deleted',
            'group_id' => $groupDay->group_id,
            'causer_id' => auth()->user()->id,
            'changes' => json_encode($store)
        ];

        $history = new LogHistory($saved_data);
        $groupDay->histories()->save($history);

        GroupDayDeletedProcess::dispatch(
            date('Y-m-d'),
            $groupDay->group_id,
            $groupDay->day_number,
            $groupDay->start_time,
            $groupDay->end_time,
            auth()->user()->id
        );
    }

    /**
     * Handle the GroupDay "force deleted" event.
     *
     * @param  \App\Models\GroupDay  $groupDay
     * @return void
     */
    public function forceDeleted(GroupDay $groupDay)
    {
        $store = [];
        $fillable = $groupDay->getFillable();
        foreach($fillable as $field) {
            $new = $groupDay->$field;
            $store[$field] = $new;
        }
        $saved_data = [
            'event' => 'deleted',
            'group_id' => $groupDay->group_id,
            'causer_id' => auth()->user()->id,
            'changes' => json_encode($store)
        ];

        $history = new LogHistory($saved_data);
        $groupDay->histories()->save($history);

        GroupDayDeletedProcess::dispatch([
            date('Y-m-d'),
            $groupDay->group_id,
            $groupDay->day_number,
            $groupDay->start_time,
            $groupDay->end_time,
            auth()->user()->id
        ]);
    }
}
