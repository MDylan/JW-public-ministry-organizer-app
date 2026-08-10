<?php

namespace App\Console\Commands;

use App\Models\LogHistory;
use Carbon\Carbon;
use Illuminate\Console\Command;

/**
 * Previously this was an anonymous closure in App\Console\Kernel::schedule(),
 * scheduled with daily().
 */
class PurgeLogHistory extends Command
{
    protected $signature = 'maintenance:purge-log-history';

    protected $description = 'Delete log history entries older than three months';

    public function handle()
    {
        $deleted = LogHistory::where('created_at', '<=', Carbon::now()->subMonths(3))->delete();

        $this->info("Deleted {$deleted} log history record(s).");

        return self::SUCCESS;
    }
}
