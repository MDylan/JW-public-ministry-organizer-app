<?php

namespace App\Http\Controllers;

use App\Models\Group;
use DateTime;
use Illuminate\Http\Request;

class GetEvents extends Controller
{
    //

    public function index() {
        $start = request()->input('start');
        $end = request()->input('end');
        $groupId = request()->input('groupId');

        // dd('ok');

        // if(!$start || !$end || !$groupId) {
        //     abort('404');
        // }

        $start_date = new DateTime( $start );
        $sd = $start_date->format("Y-m-d");

        $end_date = new DateTime( $end );
        $ed = $end_date->format("Y-m-d");

        $group = Group::findOrFail($groupId);
        $events = $group->between_events($sd, $ed)->get()->toArray();
        // dd($events);
        $array = [];
        foreach($events as $event) {
            $array[] = [
                'id' => $event['id'],
                'title' => $event['user']['name'],
                'start' => date("Y-m-d H:i", $event['start']),
                'end' => date("Y-m-d H:i", $event['end']),
                'className' => '', //class
                'resourceEditable' => true
            ];
        }

        return response()->json($array);
    }
}
