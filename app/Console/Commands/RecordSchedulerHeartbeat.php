<?php

namespace App\Console\Commands;

use App\Models\Settings;
use Illuminate\Console\Command;

/**
 * Korábban névtelen closure volt az App\Console\Kernel::schedule()-ben,
 * everyMinute() ütemezéssel.
 *
 * A last_schedule_run beállítást az admin felület használja annak
 * jelzésére, hogy fut-e egyáltalán a cron.
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
