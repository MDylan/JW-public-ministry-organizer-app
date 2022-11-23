<?php

namespace App\Http\Controllers\User;

use App\Http\Controllers\Controller;

class Profile extends Controller
{

    public function getUserFirstDay() {
        $firstDay = auth()->user()->firstDay;
        if($firstDay === null) {
            $first_day_name = date('l', strtotime("this week"));
            $firstDay = ($first_day_name == "Monday") ? 1 : 0;
        } 
        return $firstDay;
    }

    public function __invoke()
    {        
        return view('user.profile', [
            'firstday' => $this->getUserFirstDay(),
            'notifications' => [
                'EventDeletedAdminsNotification',
                'EventDeletedNotification',
                'UserProfileChangedNotification',
                'GroupPriorityMessageNotification'
            ]
        ]);
    }
}
