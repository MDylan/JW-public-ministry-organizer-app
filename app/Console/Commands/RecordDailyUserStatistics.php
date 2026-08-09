<?php

namespace App\Console\Commands;

use App\Models\Statistics;
use App\Models\User;
use Illuminate\Console\Command;

/**
 * Korábban a napi takarító closure harmadik része volt az
 * App\Console\Kernel::schedule()-ben, daily() ütemezéssel.
 */
class RecordDailyUserStatistics extends Command
{
    protected $signature = 'statistics:record-daily-users';

    protected $description = 'Record the daily active user count';

    public function handle()
    {
        $dailyUsers = User::where('last_activity', '>=', now()->subDay())->count();

        // A 'dialy_users' típusnév elgépelés, de szándékosan marad:
        // a meglévő adatsorok és az Admin\Statistics komponens is erre
        // a stringre szűr. Átnevezés csak adatmigrációval együtt lehetséges.
        Statistics::insert([
            'type' => 'dialy_users',
            'date' => now()->subDay()->format('Y-m-d'),
            'number' => $dailyUsers ?? 0,
        ]);

        $this->info("Recorded {$dailyUsers} daily user(s).");

        return self::SUCCESS;
    }
}
