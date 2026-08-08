# Middleware Documentation

## Overview

Middleware is defined in:

- `app/Http/Kernel.php` (registration and stacking)
- `app/Http/Middleware/*` (implementation classes)

The app combines Laravel defaults with custom authorization, locale/session behavior, HTTPS enforcement, and anti-bot checks.

## Kernel Stacks

## Global Middleware (`$middleware`)

Executed on every HTTP request:

- `TrustProxies`
- `HandleCors`
- `PreventRequestsDuringMaintenance`
- `ValidatePostSize`
- `TrimStrings`
- `ConvertEmptyStringsToNull`

> `TrustHosts` exists but is currently commented out in global stack.

## Web Group (`$middlewareGroups['web']`)

- `EncryptCookies`
- `AddQueuedCookiesToResponse`
- `StartSession`
- `AuthenticateSession`
- `ShareErrorsFromSession`
- `VerifyCsrfToken`
- `SubstituteBindings`
- `SetLocale` (custom)
- `SetUserLastActivity` (custom)
- `HttpsProtocol` (custom)

## API Group (`$middlewareGroups['api']`)

- `throttle:api`
- `SubstituteBindings`

## Route Middleware Aliases (`$routeMiddleware`)

| Alias | Class | Purpose |
|---|---|---|
| `auth` | `Authenticate` | Redirect unauthenticated users to login route. |
| `guest` | `RedirectIfAuthenticated` | Redirect already-authenticated users to `RouteServiceProvider::HOME`. |
| `groupAdmin` | `GroupAdmin` | Restrict route to group editors/admins. |
| `groupMember` | `GroupMember` | Restrict route to accepted group members. |
| `profileFull` | `ProfileFull` | Require non-empty profile name before protected area access. |
| `setGuestLanguage` | `SetGuestLanguage` | Set locale for signed guest flows (finish registration). |
| `checkRecaptcha` | `CheckRecaptcha` | Validate Google reCAPTCHA token on selected auth endpoints. |
| `installer` | `EnsureInstallerToken` | Gate the `setup/*` group on a token written to `storage/app/installer-token.txt`. |

Other aliases (`auth.basic`, `cache.headers`, `can`, `password.confirm`, `signed`, `throttle`, `verified`) use default Laravel middleware classes.

## Middleware Class Catalog

| Class | Type | Behavior |
|---|---|---|
| `Authenticate` | Laravel override | Custom unauthenticated redirect target (`route('login')` for non-JSON requests). |
| `RedirectIfAuthenticated` | Laravel override | Prevents logged-in users from hitting guest-only pages. |
| `EncryptCookies` | Laravel default | Encrypts cookies; no custom exclusions configured. |
| `VerifyCsrfToken` | Laravel default | CSRF verification enabled; no excluded URIs currently configured. |
| `TrimStrings` | Laravel default | Trims string inputs except password fields. |
| `PreventRequestsDuringMaintenance` | Laravel default | Standard maintenance-mode gate; no custom allowlist. |
| `TrustHosts` | Laravel default | Host trust helper class present but not enabled in kernel stack. |
| `TrustProxies` | Laravel default | Proxy/header trust configuration. |
| `CheckRecaptcha` | Custom | Optional anti-bot check using Google reCAPTCHA score, gated on `config('security.use_recaptcha')`. **Fails open** on a connection error and uses an explicit 5s timeout (v1-patch D3): the call previously had neither, so a Google outage returned 500 on `POST /login`, `/register` and `/forgot-password`, and a hung endpoint held the PHP worker. Availability was chosen over bot protection; the failure is logged. The flag used to be read straight from `env('USE_RECAPTCHA')`, which `config:cache` would have silently turned off — along with the six Blade views that render the captcha field, so nothing would have looked wrong (TODO 28). |
| `EnsureInstallerToken` | Custom | Guards the `setup/*` group during installation, when no user exists to authenticate. Requires a token the operator reads from `storage/app/installer-token.txt` on the server; deleted once the installer closes. |
| `GroupAdmin` | Custom | Authorizes by membership in `userGroupsEditable`. |
| `GroupMember` | Custom | Authorizes by membership in `groupsAccepted`. |
| `ProfileFull` | Custom | Redirects to profile page if user name is missing. |
| `SetGuestLanguage` | Custom | Sets locale from invited user language using route `id` parameter. |
| `SetLocale` | Custom | Resolves locale from query/session/user defaults, handles maintenance logout for non-admin users, and shares static-page side menu via cache. |
| `HttpsProtocol` | Custom | Redirects to HTTPS in production when `config('security.use_https')` is true. Two fixes landed here: the comparison used to be against the literal string `"true"`, so `USE_HTTPS=1` did nothing (v1-patch B14), and the flag was read from `env()` at runtime, so `config:cache` would have silently switched enforcement off on exactly the installations careful enough to cache their configuration (TODO 28). The `.env` variable is unchanged — `config/security.php` reads it with `filter_var()`, accepting `1`, `true`, `on` and `yes`. |
| `SetUserLastActivity` | Custom | Updates authenticated user `last_activity` (at most once per minute). |
| `RedirectIfUnansweredTerms` | Custom (unregistered alias) | Redirects users without GDPR response to `gdpr-terms`; class exists but alias is not registered in kernel. **Reads `Auth::user()->accepted_gdpr` with no null check, so registering it in the `web` group would turn every guest request into a 500.** Its redirect target is broken as well - see `.docs/routes.md`. Covered by `tests/Feature/Gdpr/UnansweredTermsMiddlewareTest.php`. |

## Practical Notes

- Locale and menu sharing are coupled inside `SetLocale`, so menu cache behavior is middleware-dependent.
- `checkRecaptcha` is used by custom Fortify routes (`POST /login`, `POST /register`, `POST /forgot-password`).
- Naming is now consistent: the former lower-case `setUserLastActivity` class was renamed to `SetUserLastActivity` (v1-patch, TODO 33). The old name was valid under PSR-4 on a case-insensitive filesystem but would not autoload on a case-sensitive deploy target.
