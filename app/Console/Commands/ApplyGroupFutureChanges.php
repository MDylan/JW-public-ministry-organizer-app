<?php

namespace App\Console\Commands;

use App\Classes\updateGroupFutureChanges;
use App\Models\GroupFutureChange;
use Illuminate\Console\Command;

/**
 * Korábban a percenkénti closure első fele volt az
 * App\Console\Kernel::schedule()-ben. A második fele külön parancsba
 * került: newsletters:send-due.
 */
class ApplyGroupFutureChanges extends Command
{
    protected $signature = 'groups:apply-future-changes';

    protected $description = 'Apply scheduled group setting changes whose date has arrived';

    public function handle()
    {
        $changes = GroupFutureChange::where('change_date', '=', date('Y-m-d'))->get();

        foreach ($changes as $change) {
            $init = new updateGroupFutureChanges();
            $init->initChanges($change->group_id);
        }

        $this->info('Applied '.$changes->count().' scheduled group change(s).');

        return self::SUCCESS;
    }
}
