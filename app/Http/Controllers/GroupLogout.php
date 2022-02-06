<?php

namespace App\Http\Controllers;

// use Illuminate\Http\Request;

use App\Classes\GenerateStat;
use App\Classes\GroupUserMoves;
use App\Jobs\UserLogoutFromGroupProcess;
use App\Models\Group;
use App\Models\GroupUser;
use App\Models\User;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Session;

class GroupLogout extends Controller
{
    public function index($group) {

        $logout = new GroupUserMoves($group, Auth::id());
        $res = $logout->detach();
        

        // $group_model = Group::findOrFail($group);
        // UserLogoutFromGroupProcess::dispatch($group_model, User::findOrFail(Auth::id()));
        // // $events = auth()->user()->feature_events()
        // //             ->where('group_id', $group);
        // // //recalculate events
        // // $days = [];
        // // foreach($events->get()->toArray() as $event) {
        // //     $days[] = $event['day'];
        // // }
        // // $events->delete();
        // // foreach($days as $day) {
        // //     $stat = new GenerateStat();
        // //     $stat->generate($group, $day);
        // // }
        // $pivot_data = $group_model->groupUsers()->wherePivot('user_id', Auth::id())->first()->toArray();
        // // dd($pivot_data);
        // $pivot = GroupUser::findOrFail($pivot_data['pivot']['id']);
        // $res = $pivot->update([
        //     'deleted_at' => now(),
        //     'accepted_at' => null
        // ]);

        if($res) {
            Session::flash('message', __('group.logout.success'));
        } else {
            Session::flash('message', __('group.logout.error')); 
        }

        return redirect()->route('groups');
    }
}
