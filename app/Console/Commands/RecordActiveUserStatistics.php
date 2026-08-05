<?php

namespace App\Console\Commands;

use App\Models\Statistics;
use App\Models\User;
use Illuminate\Console\Command;

/**
 * Korábban névtelen closure volt az App\Console\Kernel::schedule()-ben,
 * hourly() ütemezéssel.
 */
class RecordActiveUserStatistics extends Command
{
    protected $signature = 'statistics:record-active-users';

    protected $description = 'Record the hourly active user count';

    public function handle()
    {
        $time = now()->subHour();
        $activeUsers = User::where('last_activity', '>=', $time)->count();

        // insert() és nem create(): így írja a kód eredetileg is, és ez
        // kerüli meg a timestamp oszlopok hiányát a statistics táblán.
        Statistics::insert([
            'type' => 'active_users',
            'date' => $time->format('Y-m-d H:i:00'),
            'number' => $activeUsers ?? 0,
        ]);

        $this->info("Recorded {$activeUsers} active user(s).");

        return self::SUCCESS;
    }
}
