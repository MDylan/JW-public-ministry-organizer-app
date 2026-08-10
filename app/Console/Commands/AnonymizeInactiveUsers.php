<?php

namespace App\Console\Commands;

use App\Models\GroupUser;
use App\Models\User;
use App\Support\Gdpr\AnonymizationPolicy;
use Carbon\Carbon;
use Illuminate\Console\Command;

/**
 * Previously an anonymous closure in App\Console\Kernel::schedule(),
 * scheduled with dailyAt('7:00').
 *
 * TODO 33.2: this is now the ONLY anonymizer. It used to share the night with
 * Dialect\Gdpr\Commands\AnonymizeInactiveUsers, which ran at 00:00 - seven
 * hours earlier - and left group memberships in place, which is how an
 * anonymized user stayed on the newsletter recipient list. That package is
 * gone, and with it the divergence.
 *
 * TODO 12.2: the previous role filter (whereNotIn('role', ['mainAdmin',
 * 'groupCreator'])) is gone. AnonymizationPolicy decides instead, looking at
 * SUCCESSION: whether there is someone to take over the main-admin role or
 * the groups. The rule also lives in User::anonymize(), so direct model calls
 * can't bypass it either - the check here exists for the correct ordering and
 * for reporting.
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
            // The order is critical: eligibility must be decided BEFORE
            // dissolving memberships. In reverse order, a blocked user would
            // end up without membership but still not anonymized - and exactly
            // the evidence of succession would be lost.
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
