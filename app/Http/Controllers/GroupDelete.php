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

        if($del == 1) {
            //update child groups if needed - only once the group is really
            // gone: this ran before the check, so a roler (whom groupAdmin
            // lets onto the delete page) detached every child group with a
            // refused deletion (roadmap TODO 70)
            Group::where('parent_group_id', $group)->update(
                [
                    'parent_group_id' => null,
                    'copy_from_parent' => null
                ]
            );

            $user->userGroupsDeletable()->detach($group);
            DeleteGroupDataProcess::dispatch($group, $deleteUsers);
            Session::flash('message', __('group.groupDeleted')); 
        } else {
            Session::flash('message', __('app.notAllowed')); 
        }
        return redirect()->route('groups');
    }
}
