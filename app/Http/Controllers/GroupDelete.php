<?php

namespace App\Http\Controllers;

use App\Jobs\DeleteGroupDataProcess;
use App\Models\Group;
use Illuminate\Support\Facades\Session;

class GroupDelete extends Controller
{
    public function index($group, $deleteUsers = false) {
        $user = Auth()->user();
        $del = $user->userGroupsDeletable()->where('group_id', $group)->delete();
        //update child groups if needed
        Group::where('parent_group_id', $group)->update(
            [
                'parent_group_id' => null, 
                'copy_from_parent' => null
            ]
         );
        
        if($del == 1) {
            $user->userGroupsDeletable()->detach($group);
            DeleteGroupDataProcess::dispatch($group, $deleteUsers);
            Session::flash('message', __('group.groupDeleted')); 
        } else {
            Session::flash('message', __('app.notAllowed')); 
        }
        return redirect()->route('groups');
    }
}
