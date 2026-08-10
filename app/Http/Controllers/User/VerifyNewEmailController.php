<?php

namespace App\Http\Controllers\User;

use App\Http\Controllers\Controller;
use App\Models\PendingUserEmail;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\Auth;

/**
 * The endpoint behind the signed confirmation link.
 *
 * The route DELIBERATELY carries no `auth` middleware: the link is normally
 * opened on a different device than the one that made the request. The
 * protection is the signature plus the token, not the session; `throttle:6,1`
 * is what makes guessing a token hopeless.
 *
 * FOUR OUTCOMES
 *
 * - success, which redirects to the configured page with `verified` in session;
 * - an unknown token, or one already used, or deleted since;
 * - the address was taken by somebody else in the meantime;
 * - the user was anonymized since the link went out.
 *
 * The last three share one rule: back to the profile when logged in, to the
 * login page as a guest - in a SINGLE redirect, so the flash message actually
 * arrives. After two hops it would already have aged out.
 *
 * WHAT CHANGED AGAINST THE PACKAGE. The vendor controller threw an
 * `InvalidVerificationLinkException` on an unknown token, which extended
 * Illuminate's `AuthenticationException` - so the framework redirected to the
 * login page and the package's own message never reached anyone. The direction
 * is unchanged, but there is now a message with it, and it no longer depends on
 * exception-handler behaviour, which the Laravel 11 skeleton reshapes.
 */
class VerifyNewEmailController extends Controller
{
    public function verify(string $token): RedirectResponse
    {
        $pendingUserEmail = app(config('verify-new-email.model') ?: PendingUserEmail::class)
            ->whereToken($token)
            ->first();

        if ($pendingUserEmail === null) {
            return $this->failure(__('user.newEmail.invalid_link'));
        }

        $result = $pendingUserEmail->activate();

        if ($result === PendingUserEmail::REJECTED_TAKEN) {
            return $this->failure(__('user.newEmail.taken', ['email' => $pendingUserEmail->email]));
        }

        if ($result === PendingUserEmail::REJECTED_ANONYMIZED) {
            return $this->failure(__('user.newEmail.invalid_link'));
        }

        if (config('verify-new-email.login_after_verification')) {
            Auth::guard()->login($pendingUserEmail->user, config('verify-new-email.login_remember'));
        }

        return redirect(config('verify-new-email.redirect_to'))->with('verified', true);
    }

    /**
     * The shared exit of the rejection paths.
     *
     * The login page is the only one a logged-out visitor is guaranteed to see;
     * for somebody logged in the profile is where the pending address and its
     * resend button live.
     *
     * The `profile_message` key is DELIBERATE and must not be renamed to
     * `error`: exactly two views in this project render it as a red alert - the
     * profile page and the login page - and NOTHING renders a flash called
     * `error`. A better-named message that never appears would be the same
     * defect this change set fixes; the package's own error message never
     * reached anyone either.
     */
    private function failure(string $message): RedirectResponse
    {
        $target = Auth::check() ? route('user.profile') : route('login');

        return redirect($target)->with('profile_message', $message);
    }
}
