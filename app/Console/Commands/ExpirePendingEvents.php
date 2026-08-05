<?php

namespace App\Console\Commands;

use App\Models\Event;
use Illuminate\Console\Command;

/**
 * Korábban névtelen closure volt az App\Console\Kernel::schedule()-ben,
 * everyFiveMinutes() ütemezéssel.
 */
class ExpirePendingEvents extends Command
{
    protected $signature = 'events:expire-pending';

    protected $description = 'Mark still-pending events as denied once their start time has passed';

    public function handle()
    {
        // status: 0 = függőben, 1 = elfogadva, 2 = elutasítva/lejárt
        $expired = Event::where('status', '=', '0')
            ->where('start', '<=', date('Y-m-d H:i:s'))
            ->update(['status' => 2]);

        $this->info("Expired {$expired} pending event(s).");

        return self::SUCCESS;
    }
}
