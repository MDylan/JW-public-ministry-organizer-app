<?php

namespace App\Support\Concerns;

use App\Models\User;

/**
 * Shared "who caused the change" contract for observers, jobs and their
 * associated service classes.
 *
 * Model events and background processing can start from more than an HTTP request:
 * a scheduled command, a queue worker, the console, or a seeder can also write a model. In that
 * case there is no logged-in user.
 *
 * Previously three different behaviors coexisted for this same situation -
 * in one place the log entry was missing, in another a 0 was written in, and in
 * another auth()->user()->id caused a fatal error. TODO 10 unified this:
 *
 *     causer_id = 0  means: the change was caused by the SYSTEM, not a human.
 *
 * The log_histories.causer_id column has no foreign key, so 0 is safe;
 * the LogHistory::user() relation then simply returns null.
 *
 * IMPORTANT: the receiving side also has to handle the ID 0. That's why jobs and
 * CalculateDatesEvents use causerNameFor() instead of calling
 * User::find() directly - in a queue worker auth() never returns a user, and
 * User::find(0) is always null.
 */
trait ResolvesCauser
{
    /**
     * The ID of the user who caused the change, or 0 if it was the system.
     */
    protected function causerId(): int
    {
        return auth()->user()?->id ?? 0;
    }

    /**
     * The name of the change's causer, for notifications. "SYSTEM" for the system -
     * EventObserver::updated() already used this text previously too.
     */
    protected function causerName(): string
    {
        return static::causerNameFor(auth()->user()?->id);
    }

    /**
     * The name belonging to a PREVIOUSLY RECORDED causer ID.
     *
     * Jobs get the ID at the moment of dispatch, and run later,
     * in a queue worker - there auth() is no longer usable as a
     * fallback. 0, false and null all equally mean a system-caused change.
     *
     * Static, because CalculateDatesEvents::generate() is also static.
     */
    protected static function causerNameFor($userId): string
    {
        if (empty($userId)) {
            return 'SYSTEM';
        }

        return User::find($userId)?->name ?? 'SYSTEM';
    }
}
