<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class SaveGroupDatesDisabledSlots extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        $disabled_slots = [];
        $created_dates = [];

        foreach (\App\Models\GroupDayDisabledSlots::all() as $sl) {
            $disabled_slots[$sl->group_id][$sl->day_number][$sl->slot->format("H:i")] = true;
            $created_dates[$sl->group_id] = $sl->created_at->format("Y-m-d");
        }
        if(count($disabled_slots) > 0) {
            foreach($disabled_slots as $group_id => $slots) {
                $refresh_dates = \App\Models\GroupDate::where('group_id', '=', $group_id)
                    ->where('date_status', '=', 1)
                    ->where('date', '>=', $created_dates[$group_id])
                    ->get();
                foreach($refresh_dates as $rdate) {
                    $d = new DateTime($rdate->date);
                    $dayOfWeek = $d->format("w");
                    $updates = [
                        'disabled_slots' => ($disabled_slots[$group_id][$dayOfWeek] ?? null)
                    ];
                    $rdate->update(
                        $updates
                    );
                }
            }
        }
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {

    }
}
