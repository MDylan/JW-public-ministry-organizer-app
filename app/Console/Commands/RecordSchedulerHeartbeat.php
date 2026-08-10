<?php

namespace App\Console\Commands;

use App\Models\Settings;
use Illuminate\Console\Command;

/**
 * Previously this was an anonymous closure in App\Console\Kernel::schedule(),
 * scheduled with everyMinute().
 *
 * The last_schedule_run setting is used by the admin panel to
 * indicate whether the cron is running at all.
 */
class RecordSchedulerHeartbeat extends Command
{
    protected $signature = 'scheduler:heartbeat';

    protected $description = 'Record the timestamp of the last scheduler run';

    public function handle()
    {
        Settings::updateOrInsert(
            ['name' => 'last_schedule_run'],
            ['value' => now()]
        );

        return self::SUCCESS;
    }
}
