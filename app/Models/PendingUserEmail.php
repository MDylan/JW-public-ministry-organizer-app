<?php

namespace App\Models;

use Illuminate\Auth\Events\Verified;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\MorphTo;
use Illuminate\Database\QueryException;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\URL;

/**
 * An e-mail address waiting for confirmation.
 *
 * The row lives until the user opens the signed link that was sent to it;
 * `users.email` changes only at that moment. A user can have only one row at a
 * time - the MustVerifyNewEmail trait enforces that under a row lock, and the
 * unique index on (user_type, user_id) is the backstop underneath it.
 *
 * HISTORY: this class used to be a subclass of the
 * `protonemedia/laravel-verify-new-email` model with a single overridden method
 * (the anonymization guard). Roadmap TODO 33.5 replaced the package, so the
 * whole behaviour moved here - and the guard stayed, with its reasoning intact.
 *
 * THE LOCKING CONTRACT
 *
 * Every mutation of a user's pending rows runs inside a transaction that holds
 * `lockForUpdate()` on that user's `users` row. That single rule is what makes
 * the two guards below sound rather than merely likely, and it is why the
 * activation re-reads the user AFTER taking the lock instead of trusting the
 * instance the relation loaded.
 *
 * It works against `User::anonymize()` even though anonymize() takes no lock of
 * its own: InnoDB gives an UPDATE an exclusive row lock, so a concurrent
 * anonymization simply waits for the activation to commit (and then clears the
 * row), or lands first and is seen by the re-read.
 *
 * THE TWO GUARDS IN activate()
 *
 * 1. ANONYMIZED USER. GDPR anonymization replaces `users.email`
 *    (User::getAnonymizedEmail()), but a link that was already sent stays valid
 *    until it expires. Without the guard that link would write the REAL address
 *    back onto the anonymized user, and mark it verified on the way - making the
 *    anonymization reversible by an e-mail sitting in an inbox. The row is
 *    dropped here too, so the real address does not stay in the table.
 *
 *    The check is DELIBERATELY made on a freshly locked read. `Model::save()`
 *    writes only the DIRTY attributes, so an activation working from a stale
 *    instance would have written `email` alone: the row would have kept
 *    `isAnonymized = 1` while carrying the real address back - anonymized to
 *    every reader, and not anonymized in fact.
 *
 *    The protection is also doubled at a different level: `User::anonymize()`
 *    already clears the row, but a race, stale data or some future second
 *    anonymization path could let one survive - so the model guards as well.
 *
 * 2. AN ADDRESS TAKEN IN THE MEANTIME. `Rule::unique` runs only at the MOMENT of
 *    the request (UpdateUserProfileInformation); if somebody else registers the
 *    same address while the mail is in flight, saving would throw
 *    `SQLSTATE[23000]` inside a signed-link GET - a 500 page with no way out.
 *
 *    Both halves are needed. The pre-check reports the ordinary case cleanly,
 *    and the catch covers the window between the check and the write, which no
 *    lock this side of the transaction can close: the competing row belongs to
 *    a user that does not exist yet, so there is nothing to lock.
 */
class PendingUserEmail extends Model
{
    /** The address moved onto the user and the row is gone. */
    public const ACTIVATED = 'activated';

    /** The user is anonymized (or no longer exists) - nothing is written. */
    public const REJECTED_ANONYMIZED = 'anonymized';

    /** Somebody else took the address in the meantime. */
    public const REJECTED_TAKEN = 'taken';

    /**
     * The row is never updated: it is either activated and removed, or deleted.
     */
    public const UPDATED_AT = null;

    protected $table = 'pending_user_emails';

    protected $guarded = [];

    public function user(): MorphTo
    {
        return $this->morphTo('user');
    }

    /**
     * The rows belonging to the given user.
     */
    public function scopeForUser($query, Model $user)
    {
        $query->where([
            $this->qualifyColumn('user_type') => get_class($user),
            $this->qualifyColumn('user_id') => $user->getKey(),
        ]);
    }

    /**
     * Moves the pending address onto the user.
     *
     * @return string one of this class's three state constants
     */
    public function activate(): string
    {
        $user = $this->user;

        if ($user === null) {
            $this->deleteRowsForThisAddress();

            return self::REJECTED_ANONYMIZED;
        }

        return DB::transaction(function () use ($user) {
            // Re-read under the lock. Everything decided before this line was
            // decided on data that another request may already have replaced.
            $locked = $user->newQuery()->lockForUpdate()->find($user->getKey());

            if ($locked === null || (int) $locked->isAnonymized === 1) {
                $this->deleteRowsForThisAddress();

                return self::REJECTED_ANONYMIZED;
            }

            if ($this->addressIsTaken($locked)) {
                return self::REJECTED_TAKEN;
            }

            // The event fires when the user was not verified before, OR is
            // really moving to a new address - confirming the address you
            // already have is not an event.
            $dispatchEvent = ! $locked->hasVerifiedEmail() || $locked->email !== $this->email;

            $locked->email = $this->email;
            $locked->email_verified_at = $locked->freshTimestamp();

            try {
                $locked->save();
            } catch (QueryException $e) {
                if (! $this->isUniqueViolation($e)) {
                    throw $e;
                }

                // Somebody took the address between the check above and this
                // write. Reported, not fatal - and the pending row stays, so the
                // user can pick a different address from the profile page.
                return self::REJECTED_TAKEN;
            }

            $this->deleteRowsForThisAddress();

            // The caller reads back `$pendingUserEmail->user` (the
            // login_after_verification branch); without this it would get the
            // instance the relation loaded before the lock.
            $this->setRelation('user', $locked);

            if ($dispatchEvent) {
                event(new Verified($locked));
            }

            return self::ACTIVATED;
        });
    }

    /**
     * A signed, expiring confirmation URL.
     *
     * The expiry comes from `auth.verification.expire`, the same setting the
     * framework's own e-mail verification uses.
     */
    public function verificationUrl(): string
    {
        return URL::temporarySignedRoute(
            'pendingEmail.verify',
            now()->addMinutes(config('auth.verification.expire', 60)),
            ['token' => $this->token]
        );
    }

    /**
     * Did somebody else take this address while the mail was on its way?
     */
    private function addressIsTaken(Model $user): bool
    {
        return $user->newQuery()
            ->where('email', $this->email)
            ->whereKeyNot($user->getKey())
            ->exists();
    }

    /**
     * Is this a unique-constraint violation rather than some other query error?
     *
     * SQLSTATE 23000 is the integrity-constraint class in both MySQL and
     * SQLite, which is what the test suite runs on. Anything else is a real
     * failure and must keep propagating.
     */
    private function isUniqueViolation(QueryException $e): bool
    {
        return (string) $e->getCode() === '23000';
    }

    /**
     * Deletes every row for this address, not only this user's. Two people can
     * have been waiting for the same address; once it is activated neither
     * request can be honoured any more.
     */
    private function deleteRowsForThisAddress(): void
    {
        static::whereEmail($this->email)->get()->each->delete();
    }
}
