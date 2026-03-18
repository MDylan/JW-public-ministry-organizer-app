# Routes Documentation

## Route Registration Flow

Route loading is split across providers/files:

- `app/Providers/RouteServiceProvider.php`
  - Loads `routes/web.php` under `web` middleware
  - Loads `routes/api.php` under `api` prefix + `api` middleware
- `app/Providers/FortifyServiceProvider.php`
  - Calls `Fortify::ignoreRoutes()`
  - Manually loads `routes/fortify.php` under Fortify domain/prefix config
- `routes/channels.php`
  - Broadcast channel authorization callbacks

## Middleware Layers

From `app/Http/Kernel.php`:

- `web` group includes session, CSRF, bindings, locale and last-activity tracking.
- `api` group includes API throttle and bindings.
- Custom route middleware used in route files:
  - `groupAdmin`
  - `groupMember`
  - `profileFull`
  - `setGuestLanguage`
  - `checkRecaptcha`

## Web Routes (`routes/web.php`)

### Public / Guest Routes

| Method | URI | Name | Action | Notes |
|---|---|---|---|---|
| GET | `/` | - | `StaticPageController::render('home')` | Guest-only landing page. |
| GET | `/page/{slug}` | `static_page` | `StaticPageController::render` | Static page rendering with status-based access logic. |
| GET | `/email/verify` | `verification.notice` | `Admin\DashboardController@verify` | Verification notice page. |
| GET | `/user/new-email-verified` | `user.new-email-verified` | `User\Profile::redirectAfterNewEmailVerification` | Redirect helper after pending-email verification. |

### Signed Registration-Finish Flow

All routes below are wrapped in `middleware(['signed'])`.

| Method | URI | Name | Action |
|---|---|---|---|
| GET | `/finish-registration/{id}` | `finish_registration` | `FinishRegistration::index` (+ `setGuestLanguage`) |
| POST | `/finish-registration/{id}` | `finish_registration_register` | `FinishRegistration::register` |
| GET | `/finish-registration/{id}/cancel` | `finish_registration_cancel` | `FinishRegistration::cancel` |

### Conditional Setup Routes (only when `storage/installed.txt` does not exist)

| Method | URI | Name | Action |
|---|---|---|---|
| GET | `/setup/start` | `setup.welcome` | `Setup\MetaController::welcome` |
| GET | `/setup/requirements` | `setup.requirements` | `Setup\RequirementsController::index` |
| GET/POST | `/setup/basics` | `setup.basics` / `setup.save-basics` | `Setup\BasicsController@index/configure` |
| GET/POST | `/setup/database` | `setup.database` / `setup.save-database` | `Setup\DatabaseController@index/configure` |
| GET/POST | `/setup/mail` | `setup.mail` / `setup.save-mail` | `Setup\MailController@index/configure` |
| GET/POST | `/setup/account` | `setup.account` / `setup.save-account` | `Setup\AccountController@index/register` |
| GET | `/setup/complete` | `setup.complete` | `Setup\MetaController::complete` |

### Authenticated Routes (`middleware(['auth'])`)

| Method | URI | Name | Action |
|---|---|---|---|
| GET | `/home` | `home.home` | Livewire `Home` |
| GET | `/loginback/{id}` | `admin.loginback` | `Admin\LoginToUserController::loginback` (`signed`) |
| GET | `/email/verify/{id}/{hash}` | `verification.verify` | Inline closure using `EmailVerificationRequest` |
| GET | `/profile/resend-new-email-verification` | `user.resendNewEmailVerification` | `User\Profile::resendNewEmailVerification` |
| GET/POST | `/confirm-password` | `password.confirm` | Inline closure view + password check (`POST` throttled `6,1`) |

### Verified + Profile-Complete Routes

Inside nested `verified` -> `profileFull` middleware:

| Area | Routes |
|---|---|
| User profile/security | `user/profile`, `user/twofactorsettings`, `user/2fa-confirm`, delete-personal-data routes, logout-other-devices |
| Event/group basics | `lastevents`, `calendar/{year?}/{month?}`, `jtc/{group}/{year}/{month}`, `groups`, `newsletters` |
| Admin (strict) | `/admin/users*`, `/admin/settings`, `/admin/staticpages*`, `/admin/newsletter_edit/{id?}` with `can:is-admin` + `password.confirm` |
| Admin stats | `/admin/statistics` with `can:is-admin` |
| Group member routes | `/groups/{group}/users`, `/groups/{group}/news`, `/news_file/{group}/{file}`, `/groups/{group}/logout` |
| Group admin routes | `/groups/{group}/edit`, `/groups/{group}/delete`, group news create/edit/delete, `/groups/{group}/statistics`, `/groups/{group}/history` |
| Translator route | `/admin/translate` with `can:is-translator` + `password.confirm` |

## API Routes (`routes/api.php`)

| Method | URI | Middleware | Action |
|---|---|---|---|
| GET | `/api/user` | `auth:api` | Returns authenticated API user |

## Broadcast Channels (`routes/channels.php`)

| Channel | Authorization Logic |
|---|---|
| `App.Models.User.{id}` | Allows listen only if authenticated user ID equals `{id}` |

## Route Observations

- Setup routes are runtime-conditional based on `Storage::exists('installed.txt')`.
- Role and membership checks are strongly middleware-driven (`can:*`, `groupMember`, `groupAdmin`).
- There is overlap for `verification.verify` with Fortify route definitions (`routes/fortify.php` also defines `/email/verify/{id}/{hash}`), so route precedence should be reviewed when changing auth flows.
