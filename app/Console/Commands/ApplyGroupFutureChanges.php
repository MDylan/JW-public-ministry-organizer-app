<?php

namespace App\Console\Commands;

use App\Classes\updateGroupFutureChanges;
use App\Models\GroupFutureChange;
use Illuminate\Console\Command;

/**
 * Previously this was the first half of the per-minute closure in
 * App\Console\Kernel::schedule(). The second half was moved into a separate
 * command: newsletters:send-due.
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
