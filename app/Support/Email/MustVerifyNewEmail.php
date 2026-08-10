<?php

namespace App\Support\Email;

use App\Models\PendingUserEmail;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Password;

/**
 * Pending e-mail address handling on the user model.
 *
 * WHY THIS IS NOT A PACKAGE ANY MORE
 *
 * `protonemedia/laravel-verify-new-email` 1.6.0 was the ONLY dependency in this
 * project with a genuine upper bound: `illuminate/support ^8.67 || ^9.0`, so
 * Composer would have failed on it at the Laravel 10 hop, and no 1.x release
 * supports Laravel 13 at all. Its consumed surface, on the other hand, was this
 * trait, one model, a thin controller and two Mailables - the in-house code is
 * smaller than the cost of maintaining a fork, and it also closes the five
 * defects roadmap TODO 19 measured.
 *
 * WHAT IS PRESERVED VERBATIM
 *
 * The public API keeps the package's names, because four call sites depend on
 * them (UpdateUserProfileInformation, User\Profile and two Blade views) and the
 * `tests/Feature/NewEmail/` suite was written against the vendor code - it is
 * the before-and-after comparison. So does the "one pending address per user"
 * rule, the verified/unverified Mailable split, and the token source
 * (`Password::broker()->getRepository()->createNewToken()`).
 *
 * The activation itself - and the two guards that go with it - lives in the
 * PendingUserEmail model, not here.
 */
trait MustVerifyNewEmail
{
    /**
     * Drops the user's earlier attempts, creates a row for the given address
     * and sends the signed confirmation link to it.
     *
     * Returns null when an ALREADY VERIFIED user asks for the address they
     * already have: there is nothing to confirm. The same request from an
     * unverified user is meaningful - that is the "resend the first mail" path.
     */
    public function newEmail(string $email, callable $withMailable = null): ?Model
    {
        if ($this->getEmailForVerification() === $email && $this->hasVerifiedEmail()) {
            return null;
        }

        $pendingUserEmail = $this->createPendingUserEmailModel($email);

        $this->sendPendingEmailVerificationMail($pendingUserEmail, $withMailable);

        return $pendingUserEmail;
    }

    /**
     * The model that stores pending addresses.
     *
     * Resolved through the config because the key has existed since the package
     * and the test suite asks for the class through it in three places.
     */
    public function getEmailVerificationModel(): Model
    {
        return app(config('verify-new-email.model') ?: PendingUserEmail::class);
    }

    /**
     * A user can have only one pending address at a time, so this clears before
     * it creates. That is what turns a resend into a token rotation rather than
     * an accumulation.
     */
    public function createPendingUserEmailModel(string $email): Model
    {
        $this->clearPendingEmail();

        return $this->getEmailVerificationModel()->create([
            'user_type' => get_class($this),
            'user_id' => $this->getKey(),
            'email' => $email,
            'token' => Password::broker()->getRepository()->createNewToken(),
        ]);
    }

    /**
     * The address waiting for confirmation, or null when there is none.
     *
     * The profile page badge and the layout warning banner call this on every
     * request.
     */
    public function getPendingEmail(): ?string
    {
        return $this->getEmailVerificationModel()->forUser($this)->value('email');
    }

    /**
     * Deletes every pending address of this user.
     *
     * Two callers sit outside the flow itself: `User::anonymize()`, so the real
     * address does not stay in the table, and `UserObserver::deleted()`, because
     * `morphs()` creates no foreign key.
     */
    public function clearPendingEmail(): void
    {
        $this->getEmailVerificationModel()->forUser($this)->get()->each->delete();
    }

    /**
     * Sends the confirmation mail to the NEW address.
     *
     * Which Mailable goes out depends on whether the user's CURRENT address is
     * verified: on a freshly registered account this is the first confirmation,
     * not an address change.
     */
    public function sendPendingEmailVerificationMail(Model $pendingUserEmail, callable $withMailable = null)
    {
        $mailableClass = $pendingUserEmail->user->hasVerifiedEmail()
            ? config('verify-new-email.mailable_for_new_email')
            : config('verify-new-email.mailable_for_first_verification');

        $mailable = new $mailableClass($pendingUserEmail);

        if ($withMailable) {
            $withMailable($mailable, $pendingUserEmail);
        }

        return Mail::to($pendingUserEmail->email)->send($mailable);
    }

    /**
     * Resend: same address, NEW token - which invalidates the earlier link. The
     * caller (User\Profile) checks with getPendingEmail() first, so firstOrFail()
     * here really does mean an unexpected state.
     */
    public function resendPendingEmailVerificationMail(): ?Model
    {
        $pendingUserEmail = $this->getEmailVerificationModel()->forUser($this)->firstOrFail();

        return $this->newEmail($pendingUserEmail->email);
    }
}
