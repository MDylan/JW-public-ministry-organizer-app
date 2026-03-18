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
- `setUserLastActivity` (custom)
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
| `CheckRecaptcha` | Custom | Optional anti-bot check using Google reCAPTCHA score (`USE_RECAPTCHA` env flag). |
| `GroupAdmin` | Custom | Authorizes by membership in `userGroupsEditable`. |
| `GroupMember` | Custom | Authorizes by membership in `groupsAccepted`. |
| `ProfileFull` | Custom | Redirects to profile page if user name is missing. |
| `SetGuestLanguage` | Custom | Sets locale from invited user language using route `id` parameter. |
| `SetLocale` | Custom | Resolves locale from query/session/user defaults, handles maintenance logout for non-admin users, and shares static-page side menu via cache. |
| `HttpsProtocol` | Custom | Redirects to HTTPS in production when `USE_HTTPS=true`. |
| `setUserLastActivity` | Custom | Updates authenticated user `last_activity` (at most once per minute). |
| `RedirectIfUnansweredTerms` | Custom (unregistered alias) | Redirects users without GDPR response to `gdpr-terms`; class exists but alias is not registered in kernel. |

## Practical Notes

- Locale and menu sharing are coupled inside `SetLocale`, so menu cache behavior is middleware-dependent.
- `checkRecaptcha` is used by custom Fortify routes (`POST /login`, `POST /register`, `POST /forgot-password`).
- Naming style is mixed (`setUserLastActivity` lower-case class name), which is valid in PHP but worth normalizing in future cleanup.
