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
- Two-factor authentication (with `confirmPassword` option enabled)

## Custom Fortify Behavior

### Views

Fortify view endpoints are bound to custom Blade views:

- login -> `auth.login`
- register -> `auth.register`
- forgot password -> `auth.forgot-password`
- reset password -> `auth.reset-password`
- 2FA challenge -> `auth.two-factor-challenge`

### Authentication pipeline customization

Custom authenticate pipeline (`Fortify::authenticateThrough`) includes:

1. `EnsureLoginIsNotThrottled` (conditional)
2. `App\Actions\Fortify\RedirectIfTwoFactorConfirmed`
3. `AttemptToAuthenticate`
4. `PrepareAuthenticatedSession`

### Custom action bindings

- Create user: `App\Actions\Fortify\CreateNewUser`
- Update profile: `App\Actions\Fortify\UpdateUserProfileInformation`
- Update password: `App\Actions\Fortify\UpdateUserPassword`
- Reset password: `App\Actions\Fortify\ResetUserPassword`
- Disable 2FA override: `App\Actions\Fortify\DisableTwoFactorAuthentication`
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
so **Laravel 9 is affected exactly as Laravel 8 was** (TODO 34 re-measured this:
the advisory is still on the ignore list and still has no backport), and the
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
| POST | `/login` | - | Includes login limiter + `checkRecaptcha:login`. |
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
| POST | `/register` | - | Includes `throttle:5,1` + `checkRecaptcha:register`. |

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
| POST | `/two-factor-challenge` | - | Challenge verification. |
| POST | `/user/two-factor-authentication` | `two-factor.enable` | Requires auth (+ password confirmation depending on feature option). |
| DELETE | `/user/two-factor-authentication` | `two-factor.disable` | Same middleware profile as enable. |
| GET | `/user/two-factor-qr-code` | `two-factor.qr-code` | Returns QR code payload. |
| GET | `/user/two-factor-recovery-codes` | `two-factor.recovery-codes` | Recovery code list. |
| POST | `/user/two-factor-recovery-codes` | - | Regenerate recovery codes. |

## Important Notes

- Fortify email verification prompt view route is intentionally commented out in `routes/fortify.php`.
- An identical `/email/verify/{id}/{hash}` route is also defined in `routes/web.php` as an inline
  closure. Because `RouteCollection` keys routes by `method + domain + uri`, only one survives, and
  `FortifyServiceProvider` is listed after `RouteServiceProvider` in `config/app.php`, so **the
  Fortify definition above wins and the `routes/web.php` closure is dead code**. This is verified by
  `tests/Feature/RouteContractSnapshotTest.php`, which also guards the provider order the outcome
  depends on.
- The default Fortify confirm-password GET view route is disabled in `routes/fortify.php`; this app uses a custom `/confirm-password` flow in `routes/web.php`.
- **`POST /user/confirm-password` was removed in v1-patch H.** It carried `auth:web`
  and no rate limit at all, so a stolen session allowed unlimited guessing of the
  user's password, while the application's own branch (`password.confirm.store`)
  is throttled `6,1`. Nothing posted to it. Fortify 1.11.2 additionally moved the
  `password.confirm` **name** onto this POST definition, so adopting the vendor
  file verbatim would now collide with the GET route in `routes/web.php` and break
  `route:cache` with the same `LogicException` TODO 26 closed.

## Fortify version pin

`composer.json` constrains `laravel/fortify` to `>=1.19.1 <1.37.0`, not `^1`.
Both ends are decisions, and the file states them so a reviewer does not have to
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
  - and neither is registered, because `Fortify::ignoreRoutes()` means the route
  table is entirely this file's.
- 1.11.2 also added `POST /user/confirmed-two-factor-authentication` named
  `two-factor.confirm` to its vendor route file, and 1.19.1 still has it.
  `routes/fortify.php` deliberately does **not** carry it - the name is already
  taken by `routes/web.php:213`.

### Credential-scoped, atomic replay protection

`Laravel\Fortify\TwoFactorAuthenticationProvider::verify()` caches the timestamp
of a used code and then demands a strictly newer one via `verifyKeyNewer()`. On
the **first** call there is no cached value, so `$oldTimestamp` is null - and
`PragmaRX\Google2FA::findValidOTP()` returns `true` rather than a counter in that
case. Fortify stores that `true`; the next call passes it back as `$oldTimestamp`,
`max($timestamp - $window, true + 1)` evaluates to the unchanged starting
timestamp, and the same code verifies again. Later Fortify 1.x releases added the
one missing branch that normalizes `true` to `getTimestamp()`.

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
Fortify's `TwoFactorLoginRequest::hasValidCode()` and the app's own
`User::confirmTwoFactorAuth()` - resolve the contract, so both are covered.
`tests/Feature/Auth/TwoFactorReplayTest.php` pins the replay, isolation, atomic
cache, TTL, mandatory dependency, and fail-closed behaviour.
