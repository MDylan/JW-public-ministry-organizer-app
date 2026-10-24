<?php

namespace App\Http\Controllers;

use App\Classes\GroupUserMoves;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Session;

class GroupLogout extends Controller
{
    public function index($group) {

        // The groups page asks the same before it offers the exit, but this
        // route is reachable without the page (roadmap TODO 70).
        if(pwbs_check_group_other_admins((int) $group, Auth::id()) === false) {
            Session::flash('message', __('group.logout.no_admin'));
            return redirect()->route('groups');
        }

        $logout = new GroupUserMoves($group, Auth::id());
        $res = $logout->detach();

        if($res) {
            Session::flash('message', __('group.logout.success'));
        } else {
            Session::flash('message', __('group.logout.error')); 
        }

        return redirect()->route('groups');
    }
}
