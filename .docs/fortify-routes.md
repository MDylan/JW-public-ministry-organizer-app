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
are vendor code validating `required|email`, and Laravel 8's default `email` rule
accepts CR/LF inside the address (GHSA-5vg9-5847-vvmq, high) - from there it
reaches a mail header, where a line break opens a new one. Fixed only in 12.60.0,
no Laravel 8 backport, and the vendor rule cannot be edited durably. The
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

`composer.json` pins `laravel/fortify` to `~1.11.2` (>=1.11.2 <1.12.0), not `^1`.

- **1.11.2 is the floor** because CVE-2022-25838 (GHSA-6w4v-qr4m-97gg, TOTP
  replay) is fixed there; v1.10.2 was installed until v1-patch H.
- **1.12.0 is the ceiling** because it introduces the `two_factor_confirmed_at`
  column and enables Fortify's own 2FA confirmation flow by default. This app has
  its own `two_factor_confirmed` boolean
  (`database/migrations/2022_03_03_121545_add_two_factor_confirmed.php`), its own
  `App\Actions\Fortify\DisableTwoFactorAuthentication`,
  `RedirectIfTwoFactorConfirmed`, `User::confirmTwoFactorAuth()` and a
  `two-factor.confirm` route name that 1.12+ also claims for its own controller.
  Lifting the ceiling is a schema plus flow migration, not a lock bump.
- 1.11.2 also adds `POST /user/confirmed-two-factor-authentication` named
  `two-factor.confirm` to its vendor route file. `routes/fortify.php` is a
  hand-maintained copy and deliberately does **not** carry it - the name is
  already taken by `routes/web.php:175`.

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
