<?php

namespace App\Http\Controllers;

use App\Classes\GroupUserMoves;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Session;

class GroupLogout extends Controller
{
    public function index($group) {

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
