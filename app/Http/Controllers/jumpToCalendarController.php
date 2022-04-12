<?php

namespace App\Http\Controllers;

use App\Models\Group;

class jumpToCalendarController extends Controller
{
    public function jump(Group $group, $year, $month) {

        $check = $group->groupUsers()
                    ->where('user_id', '=', auth()->user()->id)
                    ->count();
        // dd($check);
        if($check) {
            session(['groupId' => $group->id]);
            return redirect()->route('calendar', [
                'year' => $year,
                'month' => $month
            ]);
        } else {
            return abort(403, __("This action is unauthorized."));
        }
    }
}
