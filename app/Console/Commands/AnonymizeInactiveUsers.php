<?php

namespace App\Console\Commands;

use App\Models\GroupUser;
use App\Models\User;
use App\Support\Gdpr\AnonymizationPolicy;
use Carbon\Carbon;
use Illuminate\Console\Command;

/**
 * Korábban névtelen closure volt az App\Console\Kernel::schedule()-ben,
 * dailyAt('7:00') ütemezéssel.
 *
 * TODO 33.2: this is now the ONLY anonymizer. It used to share the night with
 * Dialect\Gdpr\Commands\AnonymizeInactiveUsers, which ran at 00:00 - seven
 * hours earlier - and left group memberships in place, which is how an
 * anonymized user stayed on the newsletter recipient list. That package is
 * gone, and with it the divergence.
 *
 * TODO 12.2: a korábbi szerepszűrés (whereNotIn('role', ['mainAdmin',
 * 'groupCreator'])) megszűnt. Helyette az AnonymizationPolicy dönt, ami az
 * UTÓDLÁST nézi: van-e, aki átveszi a főadmin szerepet, illetve a csoportokat.
 * A szabály a User::anonymize()-ban is ott van, tehát a közvetlen modellhívások
 * sem kerülhetik meg - az itteni ellenőrzés a helyes sorrendért és a
 * jelentésért van.
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
            ->get();

        $anonymized = 0;
        $skipped = 0;

        foreach ($users as $user) {
            // A sorrend kritikus: az alkalmasságot ELŐBB kell eldönteni, mint
            // hogy a tagságokat bontanánk. Fordítva a blokkolt felhasználó
            // tagság nélkül, de anonimizálatlanul maradna - és épp az utódlás
            // bizonyítéka veszne el.
            if (! AnonymizationPolicy::for($user)->allows()) {
                $skipped++;

                continue;
            }

            GroupUser::where('user_id', $user->id)->delete();
            $user->anonymize();
            $anonymized++;
        }

        $this->info('Anonymized '.$anonymized.' inactive user(s).');

        if ($skipped > 0) {
            $this->warn('Skipped '.$skipped.' user(s) with no successor.');
        }

        return self::SUCCESS;
    }
}
