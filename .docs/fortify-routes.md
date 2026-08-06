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

### Rate limiters

Defined in provider:

- `login`: 5 attempts / minute by `email+ip`
- `two-factor`: 5 attempts / minute by session login id

### Additional middleware hardening

`routes/fortify.php` adds `checkRecaptcha` middleware to:

- `POST /login`
- `POST /register`
- `POST /forgot-password`

## Fortify Route Endpoints (`routes/fortify.php`)

## Authentication

| Method | URI | Name | Notes |
|---|---|---|---|
| GET | `/login` | `login` | View route (when Fortify views enabled). |
| POST | `/login` | - | Includes login limiter + `checkRecaptcha`. |
| POST | `/logout` | `logout` | Session logout. |

## Password Reset

| Method | URI | Name | Notes |
|---|---|---|---|
| GET | `/forgot-password` | `password.request` | View route. |
| POST | `/forgot-password` | `password.email` | Includes `checkRecaptcha`. |
| GET | `/reset-password/{token}` | `password.reset` | View route. |
| POST | `/reset-password` | `password.update` | Password reset submit. |

## Registration

| Method | URI | Name | Notes |
|---|---|---|---|
| GET | `/register` | `register` | View route. |
| POST | `/register` | - | Includes `checkRecaptcha`. |

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
| POST | `/user/confirm-password` | - |

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
