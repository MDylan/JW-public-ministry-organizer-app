<?php

namespace App\Console\Commands;

use App\Models\Event;
use Illuminate\Console\Command;

/**
 * Previously this was an anonymous closure in App\Console\Kernel::schedule(),
 * scheduled with everyFiveMinutes().
 */
class ExpirePendingEvents extends Command
{
    protected $signature = 'events:expire-pending';

    protected $description = 'Mark still-pending events as denied once their start time has passed';

    public function handle()
    {
        // status: 0 = pending, 1 = accepted, 2 = denied/expired
        $expired = Event::where('status', '=', '0')
            ->where('start', '<=', date('Y-m-d H:i:s'))
            ->update(['status' => 2]);

        $this->info("Expired {$expired} pending event(s).");

        return self::SUCCESS;
    }
}
