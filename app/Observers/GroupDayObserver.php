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
                'causer_id' => $this->causerId(),
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

        // A takarítást (a sablonból kieső jövőbeli események törlése) korábban
        // egy innen indított GroupDayDeletedProcess végezte volna. Azt a jobot
        // a TODO 10.2 törölte: a munkát a GroupDateHelper ->
        // CalculateDateProcess -> CalculateDatesEvents lánc már elvégzi, még
        // mielőtt a group_days sorok egyáltalán módosulnának.
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

        // Itt korábban egy GroupDayDeletedProcess::dispatch([...]) állt, ami a
        // hat konstruktor-argumentumot EGYETLEN tömbként adta át - vagyis
        // ArgumentCountError lett volna belőle, ha a metódus valaha lefut. Nem
        // fut le: a GroupDay nem használ SoftDeletes-t, tehát nincs rajta
        // forceDelete(). A job törlésével (TODO 10.2) a hiba is megszűnt.
    }
}
