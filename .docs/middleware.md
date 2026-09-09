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

**This stack is not visible to either route snapshot.** `RouteContractSnapshotTest`
and `RouteMiddlewareRegressionTest` read `$route->gatherMiddleware()`, which returns
route and group middleware only - so until TODO 35 nothing in the suite would have
noticed a class disappearing from here. `CorsHeadersTest` and `TrustProxiesTest`
close that gap for the two entries TODO 35 replaced; the other four are still
unmeasured, which is worth knowing before deleting a line from `$middleware`.

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

## Route Middleware Aliases (`$middlewareAliases`)

| Alias | Class | Purpose |
|---|---|---|
| `auth` | `Authenticate` | Redirect unauthenticated users to login route. |
| `guest` | `RedirectIfAuthenticated` | Redirect already-authenticated users to `RouteServiceProvider::HOME`. |
| `groupAdmin` | `GroupAdmin` | Restrict route to group editors/admins. |
| `groupMember` | `GroupMember` | Restrict route to accepted group members. |
| `profileFull` | `ProfileFull` | Require non-empty profile name before protected area access. |
| `setGuestLanguage` | `SetGuestLanguage` | Set locale for signed guest flows (finish registration). |
| `checkRecaptcha` | `CheckRecaptcha` | Validate Google reCAPTCHA token on selected auth endpoints. |
| `strictEmail` | `EnsureWellFormedEmail` | Re-validate the request's email field with `email:filter` before Fortify's own vendor controllers see it. |
| `installer` | `EnsureInstallerToken` | Gate the `setup/*` group on a token written to `storage/app/installer-token.txt`. |
| `password.confirm.impersonation` | `RequirePasswordForImpersonation` | Require recent password confirmation on the impersonation POST while returning to the admin user list instead of replaying the POST URL as GET. |

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
| `TrustProxies` | Laravel override | Extends `Illuminate\Http\Middleware\TrustProxies` since TODO 35; before that `Fideloper\Proxy\TrustProxies`, from a package the framework absorbed in Laravel 9. **`$proxies` is `null` and the application ships no `config/trustedproxy.php`, so no proxy is trusted and every `X-Forwarded-*` header is discarded** - which is correct for a host reached directly, and is what makes the swap a no-op here. The parent still reads `config('trustedproxy.proxies')`, so an operator genuinely behind a reverse proxy can publish that file without editing this class. The `$headers` override was **deleted** rather than ported: both the old and the new parent match `$headers` against *single* `Request::HEADER_*` constants and fall through to a default for anything else, and the value here was a combined bitmask - so it never selected anything under either parent. The two defaults differ only in that the framework's also trusts `X-Forwarded-Prefix`, which cannot surface while nothing is trusted. Pinned by `TrustProxiesTest`. |
| `HandleCors` | Laravel default | `Illuminate\Http\Middleware\HandleCors` since TODO 35, replacing the abandoned `fruitcake/laravel-cors`; both read the same `config/cors.php`. Its `paths` is `['api/*', 'sanctum/csrf-cookie']`, so the **only** covered application path is `routes/api.php`'s `auth:api`-guarded `/api/user` - Sanctum is not installed. With `allowed_origins => ['*']` and `supports_credentials => false` the response header is a static `Access-Control-Allow-Origin: *`, emitted whether or not the request carries an `Origin`; the request's own origin is only consulted when the allowed list is a real list or holds patterns. What decides whether the header appears at all is therefore the path match, nothing else. Pinned by `CorsHeadersTest`. |
| `CheckRecaptcha` | Custom | Optional anti-bot check using Google reCAPTCHA score, gated on `config('security.use_recaptcha')`. **Takes the expected reCAPTCHA action as a middleware parameter** (`checkRecaptcha:login`, `:register`, `:password_reset`) and rejects a response whose `action` does not match - v1-patch H; every form used to request the hardcoded `register` action and the server never read the field back. The client IP is now sent as `remoteip`, the field name the siteverify endpoint expects; it was `ip`, which Google silently discarded, so the address was never checked. **Fails open** on a connection error and uses an explicit 5s timeout (v1-patch D3): the call previously had neither, so a Google outage returned 500 on `POST /login`, `/register` and `/forgot-password`, and a hung endpoint held the PHP worker. Availability was chosen over bot protection; the failure is logged. The flag used to be read straight from `env('USE_RECAPTCHA')`, which `config:cache` would have silently turned off — along with the six Blade views that render the captcha field, so nothing would have looked wrong (TODO 28). |
| `EnsureInstallerToken` | Custom | Guards the `setup/*` group during installation, when no user exists to authenticate. Requires a token the operator reads from `storage/app/installer-token.txt` on the server; deleted once the installer closes. |
| `GroupAdmin` | Custom | Authorizes by membership in `userGroupsEditable`. |
| `GroupMember` | Custom | Authorizes by membership in `groupsAccepted`. |
| `ProfileFull` | Custom | Redirects to profile page if user name is missing. |
| `RequirePasswordForImpersonation` | Custom | Applies `auth.password_timeout` to `POST /admin/users/login/{user}`. Expired HTML requests store `admin.users` as the intended GET destination and redirect to `password.confirm`; JSON requests receive 423. The identity switch is never resumed automatically. |
| `SetGuestLanguage` | Custom | Sets locale from invited user language using route `id` parameter. |
| `SetLocale` | Custom | Resolves locale from query/session/user defaults, handles maintenance logout for non-admin users, and shares static-page side menu via cache. Two TODO 31 changes: the maintenance flag is read from `App\Support\Settings\ApplicationSettings`, not from `config('settings_maintenance')`, so the switch takes effect on the next request rather than the next boot; and the side-menu `catch` no longer swallows its exception — it logs and shares an empty collection. |
| `HttpsProtocol` | Custom | Redirects to HTTPS in production when `config('security.use_https')` is true. Two fixes landed here: the comparison used to be against the literal string `"true"`, so `USE_HTTPS=1` did nothing (v1-patch B14), and the flag was read from `env()` at runtime, so `config:cache` would have silently switched enforcement off on exactly the installations careful enough to cache their configuration (TODO 28). The `.env` variable is unchanged — `config/security.php` reads it with `filter_var()`, accepting `1`, `true`, `on` and `yes`. |
| `SetUserLastActivity` | Custom | Updates authenticated user `last_activity` (at most once per minute). |

## Practical Notes

- Locale and menu sharing are coupled inside `SetLocale`, so menu cache behavior is middleware-dependent.
- **The side menu has a failure mode worth knowing about.** Four Blade files `@foreach` over the shared `$sidemenu` (`components/footer`, `components/side-static-pages`, `layouts/partials/footer`, `public.blade.php`). Until TODO 31, a failing menu query was caught and discarded, so `View::share()` never ran and every one of those pages answered **500** on an undefined variable — a second, misleading error on top of an invisible first one. The middleware now logs the original exception and shares an empty collection: the menu disappears, the page survives. `SetLocaleTest::test_a_failing_menu_lookup_is_logged_and_leaves_an_empty_menu` pins both halves, and emptying the `catch` again reproduces the 500.
- **Maintenance mode is now switchable at runtime.** `config('settings_maintenance')` is filled by `AppServiceProvider::boot()`, i.e. before the middleware stack, so writing the settings row could never affect the request that wrote it. The middleware therefore asks `ApplicationSettings` directly; the value is cached and invalidated by `SettingsObserver`. `nav-bar.blade.php` and `public.blade.php` still read the config key, which is fine — they render after the boot that filled it.
- `checkRecaptcha` is used by custom Fortify routes (`POST /login`, `POST /register`, `POST /forgot-password`), each with the expected reCAPTCHA action as a parameter.
- `strictEmail` is used by `POST /forgot-password` and `POST /reset-password`. Both controllers live in `vendor/laravel/fortify` and validate `required|email`, and the framework's default `email` rule (RFCValidation) **accepts CR/LF inside the address** (GHSA-5vg9-5847-vvmq, high). From there the address reaches a mail header, where a line break opens a new one. The fix exists only in 12.60.0, so **Laravel 10 is affected exactly as 9 and 8 were** - re-measured at TODO 39, where `composer audit` still reports it against 10.50.3 and it is still on the resolution's ignore list - and the rule cannot be edited in `vendor/` without the next `composer update` reverting it - hence a middleware. It runs **before** `checkRecaptcha` so an obviously malformed address does not cost a round trip to Google. The application's own validations all use `email:filter` already and need no such layer.
- **The CORS configuration carries two open questions, and neither belongs to the middleware.** `config/cors.php` still lists `sanctum/csrf-cookie` in `paths` although `laravel/sanctum` is not in `composer.json`, and `allowed_origins` is a wildcard on an authenticated API path. TODO 35 deliberately changed neither - a package replacement that also alters policy cannot be shown to preserve behaviour - and recorded both under TODO 71, which owns the API surface itself and is where dropping `routes/api.php` would make `api/*` dead configuration anyway. `CorsHeadersTest` asserts the current values, so a change there reports itself as a configuration change rather than as several unexplained middleware failures.
- Naming is now consistent: the former lower-case `setUserLastActivity` class was renamed to `SetUserLastActivity` (v1-patch, TODO 33). The old name was valid under PSR-4 on a case-insensitive filesystem but would not autoload on a case-sensitive deploy target.
