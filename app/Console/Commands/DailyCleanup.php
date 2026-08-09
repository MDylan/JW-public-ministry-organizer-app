<?php

namespace App\Console\Commands;

use App\Models\Event;
use App\Models\GroupMessage;
use Carbon\Carbon;
use Illuminate\Console\Command;

/**
 * Korábban névtelen closure volt az App\Console\Kernel::schedule()-ben,
 * daily() ütemezéssel. A closure harmadik része (napi statisztika) külön
 * parancsba került: statistics:record-daily-users.
 */
class DailyCleanup extends Command
{
    protected $signature = 'maintenance:daily-cleanup';

    protected $description = 'Permanently remove old trashed events and expired group messages';

    public function handle()
    {
        // Három hónapnál régebbi, már soft-deletelt események végleges törlése.
        $purgedEvents = Event::onlyTrashed()
            ->where('day', '<=', Carbon::now()->subMonths(3))
            ->forceDelete();

        // Egy hétnél régebbi csoportüzenetek.
        $purgedMessages = GroupMessage::where('created_at', '<', now()->subDays(7))->delete();

        $this->info("Purged {$purgedEvents} trashed event(s) and {$purgedMessages} group message(s).");

        return self::SUCCESS;
    }
}
