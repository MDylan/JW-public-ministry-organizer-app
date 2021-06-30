<?php

namespace App\Http\Controllers;

// use App\Models\Group;
// use Illuminate\Http\Request;
use Illuminate\Support\Facades\Session;

class GroupDelete extends Controller
{
    public function index($group) {
        $user = Auth()->user();
        $del = $user->userGroupsDeletable()->where('group_id', $group)->delete();
        
        if($del == 1) {
            $user->userGroupsDeletable()->detach($group);
            Session::flash('message', __('group.groupDeleted')); 
        } else {
            Session::flash('message', __('app.notAllowed')); 
        }
        return redirect()->route('groups');
    }
}
