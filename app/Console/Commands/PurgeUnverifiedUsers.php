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
        $deleted = 0;

        User::whereNull('email_verified_at')
            // TODO 33.2: an anonymized row is kept deliberately - the user's
            // data is replaced, the row itself stays so events.user_id and
            // group_user.user_id keep resolving. Anonymization now empties
            // email_verified_at, which would otherwise put every anonymized
            // user in front of this hard delete within the hour.
            ->where('isAnonymized', 0)
            ->where('created_at', '<', date('Y-m-d H:i:s', strtotime('-1 week')))
            // TODO 33.5: DELIBERATELY one instance at a time, not a mass
            // `->delete()` on the builder. A builder delete fires no model
            // events, so UserObserver::deleted() never ran - and these are
            // exactly the users most likely to have a pending_user_emails row,
            // because an unverified account is the one that gets the first
            // confirmation mail. That row is a real, never-confirmed address of
            // a user who is being erased, and it has no foreign key to take it
            // along.
            //
            // The cost is bounded: the observer's other job is
            // CalulcateUserNameIndexProcess, which is ShouldBeUnique on a fixed
            // uniqueId, so dispatching it per user collapses to one run.
            ->each(function (User $user) use (&$deleted) {
                $user->delete();
                $deleted++;
            });

        $this->info("Deleted {$deleted} unverified user(s).");

        return self::SUCCESS;
    }
}
