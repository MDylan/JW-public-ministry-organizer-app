<?php

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