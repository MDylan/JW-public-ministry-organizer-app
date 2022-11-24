<?php

use App\Models\Group;
use Illuminate\Support\Facades\DB;

if(!function_exists('pwbs_poster_set_read')) {
    function pwbs_poster_set_read(int $poster_id) {
        //toggle read state
        $res = DB::table('group_poster_reads')->where('user_id', auth()->id())->where('poster_id', $poster_id);
        if($res->count() == 1) {
            $res->delete();
        } else {
            DB::table('group_poster_reads')->insert([
                'user_id' => auth()->id(),
                'poster_id' => $poster_id
            ]);
        }
    }
}

if(!function_exists('pwbs_check_group_admins')) {
    function pwbs_check_group_other_admins(int $groupId, int $userId) {
        $group = Group::findOrFail($groupId);
        $users = $group->groupAdmins()->get()->toArray();
        $total_group = 1;
        $admins = 0;
        $main_admins = [];
        $user_is_admin = false;
        foreach($users as $user) {
            if($user['id'] != $userId) {
                $admins++;
                $main_admins[$user['id']] = 1;
            } else {
                $user_is_admin = true;
            }
        }
        //check child groups too!
        $child_groups = $group->childGroups()->with('groupAdmins')->get()->toArray();
        if(count($child_groups) > 0) {
            $total_group += count($child_groups);
            foreach($child_groups as $child_group) {
                foreach($child_group['group_admins'] as $user) {
                    if($user['id'] != $userId) {
                        $admins++;
                        if(!isset($main_admins[$user['id']])) 
                            $main_admins[$user['id']] = 1;
                        else $main_admins[$user['id']]++;
                    } else {
                        $user_is_admin = true;
                    }
                }
            }
        }

        //he is not admin, so he can quit
        if($user_is_admin === false) return true;

        return (array_search($total_group, $main_admins, true) === false) ? false : true;
    }
}

if(!function_exists('pwbs_get_newsletter_roles')) {
    function pwbs_get_newsletter_roles() {
        $in = [];

        if(auth()->user()->can('is-groupCreator') || auth()->user()->can('is-admin')) {
            //create group
            $in[] = 'groupCreators';
        } 
        if(auth()->user()->can('is-groupservant')) {
            $in[] = 'groupServants';            
        }
        if(auth()->user()->can('is-groupadmin')) {
            $in[] = 'groupAdmins';            
        }
        return $in;
    }
}