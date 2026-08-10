<?php

namespace App\Console\Commands;

use App\Models\Event;
use App\Models\GroupMessage;
use Carbon\Carbon;
use Illuminate\Console\Command;

/**
 * Previously this was an anonymous closure in App\Console\Kernel::schedule(),
 * scheduled with daily(). The third part of the closure (daily statistics) was moved
 * into a separate command: statistics:record-daily-users.
 */
class DailyCleanup extends Command
{
    protected $signature = 'maintenance:daily-cleanup';

    protected $description = 'Permanently remove old trashed events and expired group messages';

    public function handle()
    {
        // Permanently delete already soft-deleted events older than three months.
        $purgedEvents = Event::onlyTrashed()
            ->where('day', '<=', Carbon::now()->subMonths(3))
            ->forceDelete();

        // Group messages older than one week.
        $purgedMessages = GroupMessage::where('created_at', '<', now()->subDays(7))->delete();

        $this->info("Purged {$purgedEvents} trashed event(s) and {$purgedMessages} group message(s).");

        return self::SUCCESS;
    }
}
