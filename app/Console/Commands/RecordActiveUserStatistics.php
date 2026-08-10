<?php

namespace App\Console\Commands;

use App\Models\Statistics;
use App\Models\User;
use Illuminate\Console\Command;

/**
 * Previously this was an anonymous closure in App\Console\Kernel::schedule(),
 * scheduled with hourly().
 */
class RecordActiveUserStatistics extends Command
{
    protected $signature = 'statistics:record-active-users';

    protected $description = 'Record the hourly active user count';

    public function handle()
    {
        $time = now()->subHour();
        $activeUsers = User::where('last_activity', '>=', $time)->count();

        // insert() and not create(): this is how the code originally wrote it, and this
        // works around the missing timestamp columns on the statistics table.
        Statistics::insert([
            'type' => 'active_users',
            'date' => $time->format('Y-m-d H:i:00'),
            'number' => $activeUsers ?? 0,
        ]);

        $this->info("Recorded {$activeUsers} active user(s).");

        return self::SUCCESS;
    }
}
