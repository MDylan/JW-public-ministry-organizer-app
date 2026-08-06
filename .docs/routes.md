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

#### Static page status matrix (`StaticPageController::render`)

`/page/{slug}` carries **no auth middleware**, so every branch below is reachable by a guest.
`/` routes the `home` slug through the same method behind `guest` middleware, where the visitor is
always unauthenticated.

| `status` | Guest | Authenticated non-admin | `mainAdmin` |
|---|---|---|---|
| 0 - draft | 403 | 403 | `layouts.staticpage` |
| 1 - public | `main` | `layouts.staticpage` | `layouts.staticpage` |
| 2 - guest only | `main` | 403 | 403 |
| 3 - login only | 403 | `layouts.staticpage` | `layouts.staticpage` |

- Only the `is-admin` gate is consulted; `translator` gets no draft access.
- The `home` slug never 403s or 404s: every rejecting branch renders the `home-404` view instead.
  A missing page under any other slug is a 404.
- New pages are created as **draft** (`Admin\StaticPageEdit` initialises `status = 0`), so status 0
  is the default state, not an edge case.
- Covered by `tests/Feature/StaticPageAccessTest.php`.

### Signed Registration-Finish Flow

All routes below are wrapped in `middleware(['signed'])`.

| Method | URI | Name | Action |
|---|---|---|---|
| GET | `/finish-registration/{id}` | `finish_registration` | `FinishRegistration::index` (+ `setGuestLanguage`) |
| POST | `/finish-registration/{id}` | `finish_registration_register` | `FinishRegistration::register` |
| GET | `/finish-registration/{id}/cancel` | `finish_registration_cancel` | `FinishRegistration::cancel` |

#### GDPR routes (`gdpr` prefix, `web` + `auth`)

Registered by `Dialect\Gdpr\GdprServiceProvider`, served by the published
`App\Http\Controllers\GdprController`. Covered by `tests/Feature/Gdpr/`.

| Route | State |
|---|---|
| `gdpr-download` | Works. Re-checks the password via `Auth::attempt()` (403 on mismatch, 302 + validation error when omitted), returns `portable()` as a JSON attachment. |
| `gdpr-terms-accepted` / `-denied` | Work. Each writes `users.accepted_gdpr`; a denial has no further consequence. |
| `gdpr-terms` | **Broken - returns 500.** The published view extends a `base` layout that does not exist in this project, and its text is still the package's Lorem ipsum. Nothing in the application links to it; only the unregistered `RedirectIfUnansweredTerms` would. |

The consent feature as a whole is therefore unfinished rather than regressed:
no middleware drives users to it, and the page it would show does not render.

#### User-initiated deletion (`user.askToDelete`, `user.deletepersonaldata`)

Two steps: `asktodelete` mails a signed link valid for 60 hours,
`deletepersonaldata` anonymizes the owner and detaches every membership.

Both steps first ask `App\Support\Gdpr\AnonymizationPolicy` whether the user may
be anonymized at all (the succession rule - see `.docs/commands.md`). When
blocked, nothing happens: no mail, no anonymization, no detach, no logout. The
user is redirected to `user.profile` with a `profile_message` naming what to do
(appoint another main administrator, or hand over the listed groups). The second
step re-checks because the link stays valid long enough for the situation to
change.

The allowed path anonymizes **before** detaching. That ordering suppresses
`GroupUserLogoutNotification`, since `GroupUserMoves::detach()` only notifies
non-anonymized users - correct, because the address is already a token by then.

The export omits `$gdprHidden` fields but, because `setHidden()` **replaces**
the model's `$hidden` list, it exposes `language`, `created_at`, `updated_at`
and `isAnonymized`, which the normal model output hides. Encrypted columns
(`name`, `phone_number`, `congregation`) are exported in clear text. The
`groups_accepted` key is renamed to `groups` only when the user has groups, so
the export's shape is data-dependent.

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

Behaviour of the group, covered by `tests/Feature/Setup/`:

- **The condition is evaluated once, at route-registration time.** With
  `storage/app/installed.txt` present the routes do not exist at all - the URLs
  are 404, not 403, and `Route::has('setup.welcome')` is false. This is also why
  the route-contract fixture contains no `setup.` entry.
- **No route in the group carries `auth`, a gate, or a signature.** While the
  installer is open, any visitor can complete it: `setup.save-account` creates a
  `mainAdmin` and logs in as it, and it can be called repeatedly, creating one
  admin per call.
- **`setup.complete` closes the installer on a GET**, by writing the sentinel.
  Anyone can therefore end the install window early.
- `Setup\MailController::configure` is the only place where the `languages` and
  `default_language` settings rows are created, and it advances only if the test
  message sends successfully.
- `Setup\DatabaseController::configure` runs `migrate:fresh` on the submitted
  credentials, so its success path is destructive by design and is deliberately
  not exercised by tests. Its `databaseHasData()` guard calls
  `getDoctrineSchemaManager()`, which Laravel 11 removes (roadmap TODO 66).
- `Exceptions\Handler` redirects any `QueryException` to `setup.welcome` while
  the sentinel is missing - this is what routes a freshly unpacked copy into the
  installer. With the sentinel present it returns `null`, so the exception falls
  through to the framework's own handling: it is reported through the normal log
  stack and rendered as `errors/500`. The same shape applies to
  `MissingAppKeyException`, whose bootstrap branch (copy `.env.example`, run
  `key:generate`, go to the installer) only runs when `.env` does not exist.
  Covered by `tests/Feature/Setup/InstallerExceptionHandlerTest.php` and
  `InstalledExceptionHandlerTest.php`.

### Authenticated Routes (`middleware(['auth'])`)

| Method | URI | Name | Action |
|---|---|---|---|
| GET | `/home` | `home.home` | Livewire `Home` |
| GET | `/loginback/{id}` | `admin.loginback` | `Admin\LoginToUserController::loginback` (`signed`) |
| GET | `/email/verify/{id}/{hash}` | `verification.verify` | **Inactive.** An inline closure using `EmailVerificationRequest` is defined here, but it is shadowed by the identical Fortify route (see Route Observations); the request is served by `Laravel\Fortify\Http\Controllers\VerifyEmailController` |
| GET | `/profile/resend-new-email-verification` | `user.resendNewEmailVerification` | `User\Profile::resendNewEmailVerification` |
| GET | `/confirm-password` | `password.confirm` | Inline closure returning the `auth.confirm-password` view |
| POST | `/confirm-password` | `password.confirm` | Inline closure verifying the password, throttled `6,1`. **Two distinct routes share this name**; URL generation resolves the name to this POST definition |

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
- **Two route names are defined twice, and the precedence of both is measured and pinned by
  `tests/Feature/RouteContractSnapshotTest.php`.**
  - `verification.verify` is defined in `routes/web.php` (inline closure) and in
    `routes/fortify.php` (`VerifyEmailController`). Both use the same method and URI, and
    `RouteCollection` keys routes by `method + domain + uri`, so the later registration
    overwrites the earlier one. `RouteServiceProvider` (which loads `routes/web.php`) is listed
    before `FortifyServiceProvider` in `config/app.php`, therefore **the Fortify definition wins
    and the closure in `routes/web.php` never executes**. The effective middleware stack is
    `web, auth:web, signed, throttle:6,1`. Note that this outcome depends purely on service
    provider order.
  - `password.confirm` is defined twice in `routes/web.php`, as a GET and as a POST route. They
    differ by HTTP method, so **both survive**, but the name look-up table keeps the last
    registration, so `route('password.confirm')` resolves to the POST definition. The
    `password.confirm` middleware alias (`app/Http/Kernel.php`) redirects to that name.
- The named Livewire routes (`livewire.message`, `livewire.upload-file`, `livewire.preview-file`)
  are snapshotted separately in `tests/Fixtures/vendor-route-contracts.json`. The two Livewire
  asset routes (`livewire/livewire.js`, `livewire/livewire.js.map`) are unnamed and therefore
  outside any name-keyed snapshot.
