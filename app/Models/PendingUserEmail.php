<?php

namespace App\Models;

use Illuminate\Auth\Events\Verified;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\MorphTo;
use Illuminate\Support\Facades\URL;

/**
 * An e-mail address waiting for confirmation.
 *
 * The row lives until the user opens the signed link that was sent to it;
 * `users.email` changes only at that moment. A user can have only one row at a
 * time - the MustVerifyNewEmail trait enforces that by clearing before every
 * new request.
 *
 * HISTORY: this class used to be a subclass of the
 * `protonemedia/laravel-verify-new-email` model with a single overridden method
 * (the anonymization guard). Roadmap TODO 33.5 replaced the package, so the
 * whole behaviour moved here - and the guard stayed, with its reasoning intact.
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
 *    The protection is DELIBERATELY doubled: `User::anonymize()` already clears
 *    the row, but a race, stale data or some future second anonymization path
 *    could let one survive - so the model guards as well.
 *
 * 2. AN ADDRESS TAKEN IN THE MEANTIME. `Rule::unique` runs only at the MOMENT of
 *    the request (UpdateUserProfileInformation); if somebody else registers the
 *    same address while the mail is in flight, saving would throw
 *    `SQLSTATE[23000]` inside a signed-link GET - a 500 page with no way out.
 *    Instead the method reports back, and the controller tells the user what
 *    happened.
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

        if ($user === null || (int) $user->isAnonymized === 1) {
            $this->deleteRowsForThisAddress();

            return self::REJECTED_ANONYMIZED;
        }

        if ($this->addressIsTaken($user)) {
            return self::REJECTED_TAKEN;
        }

        // The event fires when the user was not verified before, OR is really
        // moving to a new address - confirming the address you already have is
        // not an event.
        $dispatchEvent = ! $user->hasVerifiedEmail() || $user->email !== $this->email;

        $user->email = $this->email;
        $user->save();
        $user->markEmailAsVerified();

        $this->deleteRowsForThisAddress();

        if ($dispatchEvent) {
            event(new Verified($user));
        }

        return self::ACTIVATED;
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
     * Deletes every row for this address, not only this user's. Two people can
     * have been waiting for the same address; once it is activated neither
     * request can be honoured any more.
     */
    private function deleteRowsForThisAddress(): void
    {
        static::whereEmail($this->email)->get()->each->delete();
    }
}
