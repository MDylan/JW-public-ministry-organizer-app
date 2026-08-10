<?php

namespace App\Console\Commands;

use App\Models\Statistics;
use App\Models\User;
use Illuminate\Console\Command;

/**
 * Previously this was the third part of the daily cleanup closure in
 * App\Console\Kernel::schedule(), scheduled with daily().
 */
class RecordDailyUserStatistics extends Command
{
    protected $signature = 'statistics:record-daily-users';

    protected $description = 'Record the daily active user count';

    public function handle()
    {
        $dailyUsers = User::where('last_activity', '>=', now()->subDay())->count();

        // The 'dialy_users' type name is a typo, but it's intentionally kept:
        // both the existing data rows and the Admin\Statistics component filter on
        // this string. Renaming is only possible together with a data migration.
        Statistics::insert([
            'type' => 'dialy_users',
            'date' => now()->subDay()->format('Y-m-d'),
            'number' => $dailyUsers ?? 0,
        ]);

        $this->info("Recorded {$dailyUsers} daily user(s).");

        return self::SUCCESS;
    }
}
