# Fortify Routes Documentation

## Integration Architecture

Fortify is customized in this project:

- `Fortify::ignoreRoutes()` is called in `FortifyServiceProvider::register()`.
- Custom Fortify routes are loaded manually from `routes/fortify.php`.
- Route group settings use Fortify config:
  - middleware: `config('fortify.middleware', ['web'])`
  - domain: `config('fortify.domain')`
  - prefix: `config('fortify.prefix')` (currently empty)

## Enabled Fortify Features

From `config/fortify.php`:

- Registration
- Password reset
- Email verification
- Profile update
- Password update
- Passkeys, with `confirmPassword` (since roadmap TODO 69, see *Passkeys* below)
- Two-factor authentication, with `confirm` (since roadmap TODO 69: a second factor counts only once the
  user has entered a code from it, through Fortify's own `two-factor.confirm`) and `confirmPassword`

## Custom Fortify Behavior

### Views

Fortify view endpoints are bound to custom Blade views:

- login -> `auth.login`
- register -> `auth.register`
- forgot password -> `auth.forgot-password`
- reset password -> `auth.reset-password`
- 2FA challenge -> `auth.two-factor-challenge`

### Authentication pipeline

Fortify's default login pipeline, since roadmap TODO 69. The application used to replace it
(`Fortify::authenticateThrough` with `App\Actions\Fortify\RedirectIfTwoFactorConfirmed`) so that
only a **confirmed** second factor challenges at login; with `confirm` on, Fortify's own
`RedirectIfTwoFactorAuthenticatable` checks `two_factor_confirmed_at` itself, and the custom class
is gone. `TwoFactorConfirmationFlowTest` pins both halves (an unconfirmed secret logs straight in, a
confirmed one is challenged).

### Custom action bindings

- Create user: `App\Actions\Fortify\CreateNewUser`
- Update profile: `App\Actions\Fortify\UpdateUserProfileInformation`
- Update password: `App\Actions\Fortify\UpdateUserPassword`
- Reset password: `App\Actions\Fortify\ResetUserPassword`
- 2FA provider override: `App\Actions\Fortify\TwoFactorAuthenticationProvider` (v1-patch H, TOTP replay - see the version pin section below)

### Rate limiters

Defined in provider:

- `login`: two limits since v1-patch H - 5 attempts / minute by
  `strtolower(trim(email)) . "|" . ip`, plus 20 attempts / minute by `ip` alone.
  The key used to be the raw `email . ip` concatenation: MySQL's default
  collation is case-insensitive, so `User@x.hu` and `user@x.hu` resolve to the
  same account while the limiter saw two separate buckets - the 5/minute cap
  could be multiplied at will by varying the letter case. The missing separator
  was the second half: `bob@x.hu` + `1.2.3.41` and `bob@x.hu1` + `.2.3.41`
  produced the same key. The IP-only limit closes email rotation, which
  otherwise opened a fresh bucket per address.
- `two-factor`: 5 attempts / minute by session login id

### Additional middleware hardening

`routes/fortify.php` adds `checkRecaptcha` middleware to three endpoints, each
with the **expected reCAPTCHA action as a parameter** (v1-patch H). Google's v3
documentation asks for that check server-side; without it a token harvested on
one form is usable on any of the others. All three Blade forms used to request
the hardcoded `register` action and the server never looked at the field.

| Endpoint | Middleware | Route throttle |
|---|---|---|
| `POST /login` | `checkRecaptcha:login` | `config('fortify.limiters.login')` |
| `POST /register` | `checkRecaptcha:register` | `throttle:5,1` (new) |
| `POST /forgot-password` | `checkRecaptcha:password_reset` | `throttle:5,1` (new) |

`POST /forgot-password` and `POST /reset-password` additionally carry
**`strictEmail`** (`App\Http\Middleware\EnsureWellFormedEmail`). Both controllers
are vendor code validating `required|email`, and the framework's default `email`
rule accepts CR/LF inside the address (GHSA-5vg9-5847-vvmq, high) - from there it
reaches a mail header, where a line break opens a new one. Fixed only in 12.60.0,
so **Laravel 10 is affected exactly as 9 and 8 were** (re-measured at TODO 39:
`composer audit` still reports the advisory against 10.50.3, it is still on the
ignore list, and it still has no backport below 12.60.0), and the
vendor rule cannot be edited durably. The
middleware re-validates with the same `email:filter` the rest of the application
uses, and runs before `checkRecaptcha`.

The two new throttles matter because `CheckRecaptcha` **fails open** on a
connection error by design (v1-patch D3). Without a route limit, a Google outage
left `/register` and `/forgot-password` with no bot protection at all.

## Fortify Route Endpoints (`routes/fortify.php`)

## Authentication

| Method | URI | Name | Notes |
|---|---|---|---|
| GET | `/login` | `login` | View route (when Fortify views enabled). |
| POST | `/login` | `login.store` | Includes login limiter + `checkRecaptcha:login`. |
| POST | `/logout` | `logout` | Session logout. |

## Password Reset

| Method | URI | Name | Notes |
|---|---|---|---|
| GET | `/forgot-password` | `password.request` | View route. |
| POST | `/forgot-password` | `password.email` | Includes `throttle:5,1` + `strictEmail` + `checkRecaptcha:password_reset`. |
| GET | `/reset-password/{token}` | `password.reset` | View route. |
| POST | `/reset-password` | `password.update` | Password reset submit. Includes `strictEmail`. |

## Registration

| Method | URI | Name | Notes |
|---|---|---|---|
| GET | `/register` | `register` | View route. |
| POST | `/register` | `register.store` | Includes `throttle:5,1` + `checkRecaptcha:register`. |

## Email Verification

| Method | URI | Name | Notes |
|---|---|---|---|
| GET | `/email/verify/{id}/{hash}` | `verification.verify` | Signed + throttled + auth middleware. **This is the definition that actually serves the request**, overwriting the identical route in `routes/web.php` (see Important Notes). |
| POST | `/email/verification-notification` | `verification.send` | Resend verification notification (throttled). |

## Profile & Password Management

| Method | URI | Name |
|---|---|---|
| PUT | `/user/profile-information` | `user-profile-information.update` |
| PUT | `/user/password` | `user-password.update` |
| GET | `/user/confirmed-password-status` | `password.confirmation` |

## Two-Factor Authentication

| Method | URI | Name | Notes |
|---|---|---|---|
| GET | `/two-factor-challenge` | `two-factor.login` | Challenge view route. |
| POST | `/two-factor-challenge` | `two-factor.login.store` | Challenge verification. |
| POST | `/user/two-factor-authentication` | `two-factor.enable` | Requires auth (+ password confirmation depending on feature option). |
| POST | `/user/confirmed-two-factor-authentication` | `two-factor.confirm` | Confirms the second factor with a code (Fortify's `ConfirmedTwoFactorAuthenticationController`, since TODO 69). Errors go to the `confirmTwoFactorAuthentication` bag; the settings page shows them. |
| DELETE | `/user/two-factor-authentication` | `two-factor.disable` | Same middleware profile as enable. |
| GET | `/user/two-factor-qr-code` | `two-factor.qr-code` | Returns QR code payload. |
| GET | `/user/two-factor-secret-key` | `two-factor.secret-key` | Returns the secret as JSON for manual entry (TODO 69). The settings page shows the same key under the QR code. |
| GET | `/user/two-factor-recovery-codes` | `two-factor.recovery-codes` | Recovery code list. |
| POST | `/user/two-factor-recovery-codes` | `two-factor.regenerate-recovery-codes` | Regenerate recovery codes. |

## Passkeys (since roadmap TODO 69)

Fortify 1.41's `Features::passkeys(['confirmPassword' => true])`, on top of
`laravel/passkeys` 0.2.x (`web-auth/webauthn-lib` 5.3). Fortify configures that
package from `config/fortify.php` and calls its `ignoreRoutes()`, so the seven
routes below in `routes/fortify.php` are the only passkey routes.

| Method | URI | Name | Middleware |
|---|---|---|---|
| GET | `/passkeys/login/options` | `passkey.login-options` | `guest`, `throttle:passkeys` |
| POST | `/passkeys/login` | `passkey.login` | `guest`, `throttle:passkeys` |
| GET | `/passkeys/confirm/options` | `passkey.confirm-options` | `auth`, `throttle:passkeys` |
| POST | `/passkeys/confirm` | `passkey.confirm` | `auth`, `throttle:passkeys` |
| GET | `/user/passkeys/options` | `passkey.registration-options` | `auth`, `password.confirm`, `throttle:passkeys` |
| POST | `/user/passkeys` | `passkey.store` | `auth`, `password.confirm`, `throttle:passkeys` |
| DELETE | `/user/passkeys/{passkey}` | `passkey.destroy` | `auth`, `password.confirm` (the controller answers 403 for another user's passkey) |

- **The developer's decisions:** a passkey login does **not** ask for the TOTP
  code - a passkey is two factors on its own; passkeys are managed on the 2FA
  settings page (`user.partials.passkeys-card`); the password-confirmation page
  offers "confirm with a passkey" to a user who has one (`passkey.confirm` marks
  the session password-confirmed exactly as the form does).
- **The `passkeys` rate limiter** is Fortify's own stub (10 a minute per credential
  id - or session, before one exists - and IP), defined in `FortifyServiceProvider`.
  Without `fortify.limiters.passkeys` the routes would carry no throttle at all.
- **Relying party and origin** come from `APP_URL` (`config/fortify.php`
  `passkeys`). WebAuthn needs a secure context, so a host reached over plain
  http (anything but `localhost`) or under a second host name cannot use
  passkeys; the buttons still appear and report the domain error.
- **The user handle is derived from `APP_KEY`** (no `user_handle_secret` is set),
  so rotating the key invalidates every passkey - next to the encrypted columns,
  one more reason the key must survive a deploy.
- **The browser client** is the official `@laravel/passkeys` with
  `@simplewebauthn/browser`, vendored as ES modules under `public/vendor/passkeys`
  (see `.docs/assets.md`), driven by `resources/views/partials/passkeys-client.blade.php`.
- **Data:** the `passkeys` table (public key + the user's own label),
  `App\Models\User` implements `PasskeyUser`; anonymizing a user deletes their
  passkeys, the GDPR export lists them by name without the credential, deleting
  a user cascades.
- Covered by `tests/Feature/Auth/PasskeyTest.php` (routes, options, login with the
  signature check replaced, confirmation, deletion, GDPR, the three pages) and
  `FortifyRouteParityTest`.

## Important Notes

- **`routes/fortify.php` is reconciled with Fortify's own route file** (TODO 69): same paths, controllers
  and names, plus this application's middleware. `tests/Feature/Auth/FortifyRouteParityTest.php` reads
  `vendor/laravel/fortify/routes/routes.php` and fails when Fortify defines a route this file neither
  registers nor lists as deliberately absent - the three absent ones are `verification.notice`,
  `password.confirm` and `password.confirm.store`, each replaced by a `routes/web.php` route of the same
  name. Middleware is not compared. One middleware difference is kept on purpose: Fortify puts `auth`
  on `POST /logout`, this file does not.
- Fortify's email verification prompt route is deliberately absent from `routes/fortify.php`; `routes/web.php` defines `verification.notice`.
- An identical `/email/verify/{id}/{hash}` route is also defined in `routes/web.php` as an inline
  closure. Because `RouteCollection` keys routes by `method + domain + uri`, only one survives, and
  `routes/fortify.php` loads after `routes/web.php`, so **the Fortify definition above wins and the
  `routes/web.php` closure is dead code** (it was deleted in v1-patch A4). This is verified by
  `tests/Feature/RouteContractSnapshotTest.php`, which also guards the load order the outcome
  depends on. **Where that order comes from changed at TODO 56:** it used to be the provider order
  in `config/app.php` (`RouteServiceProvider` before `FortifyServiceProvider`, whose `boot()` loaded
  the file); now `bootstrap/app.php` loads `routes/fortify.php` in `withRouting(then: ...)`, which runs
  after `web:`. Loading it from the provider again would put it BEFORE `routes/web.php`, because
  Laravel 11's own route provider boots last.
- The default Fortify confirm-password GET view route is disabled in `routes/fortify.php`; this app uses a custom `/confirm-password` flow in `routes/web.php`.
- **`POST /user/confirm-password` was removed in v1-patch H.** It carried `auth:web`
  and no rate limit at all, so a stolen session allowed unlimited guessing of the
  user's password, while the application's own branch (`password.confirm.store`)
  is throttled `6,1`. Nothing posted to it. Fortify 1.11.2 additionally moved the
  `password.confirm` **name** onto this POST definition, so adopting the vendor
  file verbatim would now collide with the GET route in `routes/web.php` and break
  `route:cache` with the same `LogicException` TODO 26 closed.

## Fortify version pin

**Lifted at TODO 69 (2026-10-24): the constraint is `^1.41` (1.41.0 installed).**
The developer decided to take the passkey feature now, which is what the ceiling
below existed to hold back. The rest of this section is the pin's history.

`composer.json` constrained `laravel/fortify` to `>=1.19.1 <1.37.0`, not `^1`.
Both ends were decisions, and the file stated them so a reviewer did not have to
reconstruct either.

- **1.19.1 is the floor** because it is the lowest release admitting Laravel 10
  (`illuminate/support ^8.82|^9.0|^10.0`). Re-measured at TODO 39.2 against
  Packagist with Composer's own `Semver::satisfies()`; the rest of the ladder is
  1.21.0 for Laravel 11, 1.31.3 for 12 and 1.36.2 for 13, all inside this range,
  so the remaining framework hops need lock movement and no edit here.
- **1.37.0 is the ceiling** because it adds `laravel/passkeys` as a hard
  requirement and registers passkey routes that `routes/fortify.php` - a
  hand-maintained copy - would have to absorb. It also raises its own floor to
  `illuminate ^11` / `php ^8.2`. Taking 1.37+ is a feature decision for TODO 69,
  not a side effect of a version bump.
- **The ceiling used to be `~1.11.2`**, and the reason it moved is worth keeping.
  It was set because 1.12.0 introduces the `two_factor_confirmed_at` column and
  Fortify's own confirmation flow, which collided with this application's own
  `two_factor_confirmed` boolean. That reading held until it was measured: the
  boolean would not resolve Laravel 10 at all, so TODO 39.2 lifted the ceiling on
  Laravel 9 and moved the confirmation onto `two_factor_confirmed_at`
  (`database/migrations/2026_09_08_120000_move_two_factor_confirmation_onto_a_timestamp.php`).
- **Superseded at TODO 69** - Fortify owns the confirmation now (`'confirm' => true`, its
  `two-factor.confirm` route and action), and the application's `DisableTwoFactorAuthentication`,
  `RedirectIfTwoFactorConfirmed`, `User::confirmTwoFactorAuth()` and `/user/2fa-confirm` are gone. The
  bullet below is the TODO 39.2 measurement as it was.
- **What the bump does NOT change, measured on 1.19.1 rather than assumed.**
  Every package path that touches `two_factor_confirmed_at` is gated on
  `Fortify::confirmsTwoFactorAuthentication()`, and `config/fortify.php` passes
  only `'confirmPassword'` - so it is **false** here.
  `EnableTwoFactorAuthentication` writes only the secret and the recovery codes,
  the package's own disable action skips the timestamp,
  `hasEnabledTwoFactorAuthentication()` does not read it, and
  `ConfirmTwoFactorAuthentication` is never routed. The application keeps its own
  `App\Actions\Fortify\DisableTwoFactorAuthentication`,
  `RedirectIfTwoFactorConfirmed`, `User::confirmTwoFactorAuth()` and its own
  `two-factor.confirm` route, and none of them competes with the package.
- **All 15 controllers `routes/fortify.php` names by FQCN exist in 1.19.1**,
  checked rather than assumed. The package ships two the file does not name -
  `ConfirmedTwoFactorAuthenticationController` and `TwoFactorSecretKeyController`
  - and neither was registered, because `Fortify::ignoreRoutes()` means the route
  table is entirely this file's. **Both are registered since TODO 69.**
- 1.11.2 also added `POST /user/confirmed-two-factor-authentication` named
  `two-factor.confirm` to its vendor route file, and 1.19.1 still has it.
  `routes/fortify.php` deliberately did **not** carry it - the name was taken by
  the application's own `/user/2fa-confirm`. **It carries it since TODO 69**, and the
  application's route is gone.

### Credential-scoped, atomic replay protection

`Laravel\Fortify\TwoFactorAuthenticationProvider::verify()` caches the timestamp
of a used code and then demands a strictly newer one via `verifyKeyNewer()`. On
the **first** call there is no cached value, so `$oldTimestamp` is null - and
`PragmaRX\Google2FA::findValidOTP()` returns `true` rather than a counter in that
case. Fortify stores that `true`; the next call passes it back as `$oldTimestamp`,
`max($timestamp - $window, true + 1)` evaluates to the unchanged starting
timestamp, and the same code verifies again. Later Fortify 1.x releases added the
one missing branch that normalizes `true` to `getTimestamp()` - checked in 1.36.2 at
TODO 69, which keeps the override anyway: the package still keys the entry by the
code alone (`md5($code)`), and reads before it writes.

`App\Actions\Fortify\TwoFactorAuthenticationProvider` always supplies a
non-null old timestamp (`0`), so Google2FA returns the actual matched counter.
The replay key is a SHA-256 hash of the credential secret plus that counter;
the raw secret and submitted code are never stored, and two credentials that
happen to produce the same six-digit value cannot block each other.

Redemption uses the cache repository's atomic `add()` operation rather than a
`get()` / `put()` sequence. Exactly one concurrent request can claim a given
credential-counter pair. The key remains present for
`(2 * window + 1) * keyRegeneration` seconds, covering the full acceptance
window. Cache injection is mandatory, and a cache failure is reported without
secret or code data and rejects the authentication attempt (fail closed).

`FortifyServiceProvider::boot()` rebinds the contract to this provider (Fortify
binds its own in `register()`, so `boot()` wins). Both verification paths -
Fortify's `TwoFactorLoginRequest::hasValidCode()` and its `ConfirmTwoFactorAuthentication`
action (the application's own `User::confirmTwoFactorAuth()` until TODO 69) - resolve the
contract, so both are covered.
`tests/Feature/Auth/TwoFactorReplayTest.php` pins the replay, isolation, atomic
cache, TTL, mandatory dependency, and fail-closed behaviour.
