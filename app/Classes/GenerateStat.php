<?php

namespace App\Classes;

use App\Jobs\GenerateStatProcess;
use App\Models\GroupDate;

class GenerateStat {

    public function generate($groupId, $date, $forceReset = false) {

        $res = GroupDate::where('group_id', '=', $groupId)
                        ->where('date', '=', $date)
                        ->first(['run_job']);
        $run = true;
        if($res) {
            $run = !$res->run_job;
        }
        if($run) {
            GroupDate::where('group_id', '=', $groupId)
                        ->where('date', '=', $date)
                        ->update(['run_job' => 1]);

            GenerateStatProcess::dispatch($groupId, $date, $forceReset);
        }
    }
}
