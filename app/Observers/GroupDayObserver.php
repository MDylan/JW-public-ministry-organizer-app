<?php

namespace App\Observers;

use App\Jobs\GroupDayUpdatedProcess;
use App\Models\GroupDay;
use App\Models\LogHistory;
use App\Support\Concerns\ResolvesCauser;


class GroupDayObserver
{
    use ResolvesCauser;

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
            'causer_id' => $this->causerId(),
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
                // array_key_exists(), NOT isset(): isset() is false for a key
                // with a NULL value, so every set-to-NULL change was invisible
                // in the audit log. getDirty() only returns fields that
                // actually changed, and the $old !== $new guard below stays
                // in place, so this simply lets in the previously missing
                // cases.
                if(array_key_exists($field, $changes)) {
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
                'causer_id' => $this->causerId(),
                'changes' => json_encode($store)
            ];

            $history = new LogHistory($saved_data);
            $groupDay->histories()->save($history);

            // Same isset() trap as above, but here it's not logging that's at
            // stake, it's a job: setting the opening or closing time to NULL
            // is just as much a time change, and events that fall outside the
            // scope still need to be cleaned up.
            if(array_key_exists('start_time', $changes) || array_key_exists('end_time', $changes)) {
                // //we must delete feature events, which not in right timeslot

                GroupDayUpdatedProcess::dispatch(
                    date('Y-m-d'),
                    $groupDay->group_id,
                    $groupDay->day_number,
                    $groupDay->start_time,
                    $groupDay->end_time,
                    $this->causerId()
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
            'causer_id' => $this->causerId(),
            'changes' => json_encode($store)
        ];

        $history = new LogHistory($saved_data);
        $groupDay->histories()->save($history);

        // The cleanup (deleting future events that fall out of the template)
        // used to be done by a GroupDayDeletedProcess dispatched from here.
        // That job was removed by TODO 10.2: the work is already done by the
        // GroupDateHelper -> CalculateDateProcess -> CalculateDatesEvents
        // chain, before the group_days rows even change.
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
            'causer_id' => $this->causerId(),
            'changes' => json_encode($store)
        ];

        $history = new LogHistory($saved_data);
        $groupDay->histories()->save($history);

        // A GroupDayDeletedProcess::dispatch([...]) call used to sit here,
        // passing the six constructor arguments as a SINGLE array - which
        // would have been an ArgumentCountError if the method ever ran. It
        // never runs: GroupDay doesn't use SoftDeletes, so it has no
        // forceDelete(). Removing the job (TODO 10.2) also removed the bug.
    }
}
