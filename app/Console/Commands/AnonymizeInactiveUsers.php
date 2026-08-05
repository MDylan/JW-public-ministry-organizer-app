<?php

namespace App\Console\Commands;

use App\Models\GroupUser;
use App\Models\User;
use Carbon\Carbon;
use Illuminate\Console\Command;

/**
 * Korábban névtelen closure volt az App\Console\Kernel::schedule()-ben,
 * dailyAt('7:00') ütemezéssel.
 *
 * Figyelem: nem tévesztendő össze a Dialect\Gdpr\Commands\AnonymizeInactiveUsers
 * csomag-paranccsal, ami külön van regisztrálva a Kernel $commands tömbjében.
 * Ez a parancs a projekt saját, szigorúbb szabályait alkalmazza (kihagyja a
 * mainAdmin és groupCreator szerepeket, és előbb bontja a csoporttagságokat).
 */
class AnonymizeInactiveUsers extends Command
{
    protected $signature = 'gdpr:anonymize-inactive';

    protected $description = 'Anonymize users who have been inactive beyond the GDPR retention period';

    public function handle()
    {
        if (! config('gdpr.enabled')) {
            $this->warn('GDPR handling is disabled, nothing to do.');

            return self::SUCCESS;
        }

        $users = User::where('last_activity', '<=', Carbon::now()->subMonths(config('gdpr.settings.ttl')))
            ->where('isAnonymized', 0)
            ->whereNotIn('role', ['mainAdmin', 'groupCreator'])
            ->get();

        foreach ($users as $user) {
            GroupUser::where('user_id', $user->id)->delete();
            $user->anonymize();
        }

        $this->info('Anonymized '.$users->count().' inactive user(s).');

        return self::SUCCESS;
    }
}
