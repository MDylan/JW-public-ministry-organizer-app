<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;
use Laravel\Fortify\Fortify;

/**
 * Strict email format for Fortify's own vendor-side endpoints.
 *
 * Laravel's default `email` rule uses RFCValidation, which ACCEPTS CR/LF
 * characters in the address (GHSA-5vg9-5847-vvmq, high). From there the
 * address ends up in an email header, where the line break opens a new
 * header: an attacker can influence the email's content, have it delivered
 * to a different recipient, or make the mailer send unrelated messages. The
 * fix only landed in 12.60.0, there's no backport for Laravel 8, so this
 * layer is the answer.
 *
 * The application's OWN validations all use `email:filter`
 * (CreateNewUser, UpdateUserProfileInformation, Admin\Users\ListUsers,
 * Groups\ListUsers), which works with `filter_var(FILTER_VALIDATE_EMAIL)` and
 * rejects CRLF outright - nothing to do there. Two endpoints were left with
 * the bare `email` rule, and both are in the VENDOR, where the rule can't be
 * edited without the next `composer update` overwriting it:
 *
 * - `POST /forgot-password` - ANONYMOUS, and sends an email to the given address;
 * - `POST /reset-password` - resolves the password-reset token by address.
 *
 * The middleware runs the same rule on them as the application does on its
 * own forms, so the error message and the return-to-form behavior are also
 * the usual ones. The field name is configurable; by default it follows the
 * `Fortify::email()` setting.
 */
class EnsureWellFormedEmail
{
    public function handle(Request $request, Closure $next, ?string $field = null)
    {
        $field = $field ?: Fortify::email();

        Validator::make($request->all(), [
            $field => ['required', 'string', 'email:filter'],
        ])->validate();

        return $next($request);
    }
}
