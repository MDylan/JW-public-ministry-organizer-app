<?php

namespace App\Console\Commands;

use App\Models\User;
use Illuminate\Console\Command;

/**
 * Korábban névtelen closure volt az App\Console\Kernel::schedule()-ben,
 * hourlyAt(50) ütemezéssel.
 */
class PurgeUnverifiedUsers extends Command
{
    protected $signature = 'users:purge-unverified';

    protected $description = 'Delete users who have not verified their email address within a week';

    public function handle()
    {
        $deleted = User::whereNull('email_verified_at')
            ->where('created_at', '<', date('Y-m-d H:i:s', strtotime('-1 week')))
            ->delete();

        $this->info("Deleted {$deleted} unverified user(s).");

        return self::SUCCESS;
    }
}
