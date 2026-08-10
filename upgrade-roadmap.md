# Laravel 8 -> Laravel 13 Upgrade Roadmap

This roadmap is designed for multi-step execution by AI agents.
Each item is intentionally small enough to complete and mark independently.

## Execution Environment (Important)

- The default `php` on this machine points to **PHP 8.3**, which cannot boot the current Laravel 8 baseline.
- **Today, run all Laravel commands with `php81 artisan ...`.**
- The required runtime changes as the upgrade progresses. After each framework hop, switch the interpreter according to the *Runtime and Version Matrix* below and update this section.
- Locally installed PHP runtimes (Laragon): `php-8.1.30-nts-Win32-vs16-x64`, `php-8.3.16-Win32-vs16-x64`. PHP 8.0 is not installed at all.
- **No additional PHP runtime needs to be installed.** Laravel 11 and 12 declare `php: ^8.2`, which the installed PHP 8.3 satisfies, and Laravel 13 declares `^8.3`. The two installed runtimes cover the entire upgrade path: `php81` for Laravel 8 and 9, the default `php` (8.3) from Laravel 10 onward.
- Composer is 2.10.2 and enforces `config.allow-plugins`, which `composer.json` does not currently declare.

## Status Convention

- `[ ]` not started
- `[x]` completed

## Runtime and Version Matrix

| Phase | Laravel | PHP required | Interpreter to use | PHPUnit | Collision | Node |
| --- | --- | --- | --- | --- | --- | --- |
| Current baseline | 8.83.1 | `^8.0` (pinned to 8.0.9) | `php81` | 9.5 | 5.x | 24.18 (unused) |
| Phase 4 | 9.x | `^8.0.2` | `php81` (see note) | 9.5 | 6.x | 24.18 (unused) |
| Phase 5 | 10.x | `^8.1` | `php81`, then switch to `php` (8.3) | 10.x | 7.x | 24.18 (unused) |
| Phase 7 | 10.x | `^8.1` | `php` (8.3) | 10.x | 7.x | 24.18 (only if TODO 52 keeps a build) |
| Phase 8 | 11.x | `^8.2` | `php` (8.3) OK | 10.x/11.x | 8.x | 24.18 |
| Phase 9 | 12.x | `^8.2` | `php` (8.3) OK | 11.x | 8.x | 24.18 |
| Phase 10 | 13.x | `^8.3` | `php` (8.3) OK | 12.x | current major | 24.18 |

- **Node is listed for completeness only.** No phase before 7 uses it: the Mix pipeline is dead (zero `mix()` calls, empty entrypoints, no outputs), and the assets are served by the `pwbs_asset()` helper since TODO 33.8 removed `eusonlito/laravel-packer`. Whether Node is ever needed is the open question in the Phase 7 preamble.
- **Both required runtimes are already installed.** `^8.2` is a caret constraint, so PHP 8.3 satisfies Laravel 11 and 12; no PHP 8.2 install is needed.
- **Note on Phase 4:** although Laravel 9 declares `^8.0.2`, its officially tested ceiling is PHP 8.2, and the current `nesbot/carbon` line already fatals on PHP 8.3. Stay on `php81` for Laravel 9 and only move to PHP 8.3 once Laravel 10 is in place (Laravel 10.x supports PHP 8.1-8.3).
- PHP 8.4 is not installed. Laravel 13 accepts `^8.3`, so 8.4 is forward-looking only (TODO 68).
- Do not skip intermediate majors. Each hop gets its own composer resolution, its own test run, and its own PR.

## Project-Specific Baseline (Current State, verified)

### Framework and dependencies

- Laravel **`8.83.29`** on branch `v2-dev`, the last 8.x tag; `composer.json` requires `laravel/framework: ^8.12`. (This line read `8.83.1` until the 2026-08-09 fast-forward brought the v1-patch H security refresh onto `v2-dev` - the two lines are now the same lock.)
- PHP constraint is `^8.0` with `config.platform.php = 8.0.9`, which artificially holds back every dependency resolution - and is three patch lines below the runtime that actually executes the code (`php81` = 8.1.30). See the correction in TODO 24: the fix is to raise the pin, not to drop it, because Composer here runs on 8.3.
- `minimum-stability: dev` with `prefer-stable: true` - risks pulling unstable packages during the upgrade. **Fixed on `v1-patch H`** after it demonstrably pulled `laravel/framework: 8.x-dev`; see TODO 24.
- `composer.lock` was resolved in early 2022 and is roughly four years stale. **Partly refreshed on `v1-patch H`** - every package with a security advisory was bumped inside its existing constraint (50 advisories -> 3). The remaining staleness is upgrade-preparation work, not a security question.
- `config/app.php` hard-registers the dev-only `Barryvdh\Debugbar\ServiceProvider`, which breaks `composer install --no-dev`. It also registered `Eusonlito\LaravelPacker\PackerServiceProvider` as a plain string instead of `::class`, with a matching `Packer` alias; **TODO 33.8 deleted both lines**, which is why the TODO 25 bullet about converting them was dropped rather than executed.

### Test suite (this is the main upgrade asset)

- 20 test files, 134 `test_*` methods, **179 executed cases** after data providers. **Verified green on 2026-08-05: `OK (179 tests, 593 assertions)` in 36.5s** (TODO 03).
- Strong coverage: 70-route contract snapshot including middleware stacks (`tests/Feature/RouteContractSnapshotTest.php`), route/middleware regression, all 8 observers, mail contract for all notifications (26 at the time; **25 since TODO 15** deleted an unused stub, and the provider list now fails if a class is added without a contract).
- The 70-entry route fixture covers **every application-owned named route**; the 8 named routes it omits are all vendor-provided (5 Debugbar, 3 Livewire). That is a stronger safety net than 70-of-78 suggests. **TODO 14 added the 3 Livewire routes in a second fixture**, so only the 5 dev-only Debugbar routes now sit outside the snapshot.
- Runs against a real MySQL schema `kozter_testing` via `RefreshDatabase`; `phpunit.xml` and `.env.testing` are configured with test-safe drivers.
- **Known gaps** (addressed in Phase 1): no job `handle()` body is ever executed (all `Bus::fake()`), the ~200 lines of inline scheduler closures are only tested at registration level, 19 Livewire components are smoke-only, ~~5 middleware are untested~~ (closed by TODO 09), 23 of 30 models have no factory.
- ~~**The single largest gap: the core scheduling domain has zero coverage.**~~ **Closed by TODO 07.1 and TODO 07.2.** Per-slot publisher capacity, the raised limit for approval-based groups, time-range overlap, the cross-group "publisher busy" check and in-group role assignment (`Groups\ListUsers::updateUser()`, not `saveUser()`) now have dedicated test files. Suite as of TODO 10: **`OK (677 tests, 1995 assertions)` in ~135s**, up from the 179-test baseline. (The jump from ~107s came with TODO 10's fuller audit trail - see that entry.)
- `phpunit.xml` uses the PHPUnit 9 schema. **Six** `@dataProvider` annotations use **non-static** provider methods, which PHPUnit 11 forbids (measured in TODO 15; this line previously said two - see TODO 40 for the list).
- `.phpunit.result.cache` contains stale defect entries for tests that no longer exist. It must be deleted before recording a baseline.

### Code-level upgrade risks

- `app/Http/Middleware/TrustProxies.php:5` extends `Fideloper\Proxy\TrustProxies`; `app/Http/Kernel.php:23` uses `Fruitcake\Cors\HandleCors`. Both packages are removed in Laravel 9.
- Duplicate route names: `verification.verify` at `routes/fortify.php:89` **and** `routes/web.php:122`; `password.confirm` at `routes/web.php:128` **and** `routes/web.php:138`.
- `routes/fortify.php` is a vendored, hand-modified copy of Fortify's own route file (161 lines), loaded through `Fortify::ignoreRoutes()` plus a `Route::group(['namespace' => ...])`.
- `app/Actions/Fortify/PasswordValidationRules.php:5` and `app/Http/Controllers/FinishRegistration.php:14` use the removed `Laravel\Fortify\Rules\Password`.
- `protected $dates` at `app/Models/Group.php:16` and `app/Models/GroupUser.php:29` - removed in Laravel 10.
- **9 `encrypted` cast columns across 6 models** (`User`, `Group`, `Event`, `GroupMessage`, `GroupPosters`, `GroupUser`). Any `->change()` migration or `APP_KEY` handling mistake is silently data-destructive.
- 16 `->change()` calls across 8 migrations, backed by `doctrine/dbal`. Laravel 11's native `change()` **drops any attribute not re-declared** (nullable, default, charset). 97 migrations total.
- `app/Http/Controllers/Setup/DatabaseController.php:124` calls `getDoctrineSchemaManager()`, removed in Laravel 11.
- **25 `env()` calls outside config**, including 7 in Blade views and 6 in notifications. Under `config:cache` these return `null`; Symfony Mailer (Laravel 9) **throws** on a null `replyTo`/`bcc` where SwiftMailer silently ignored it.
- `config/filesystems.php` `web` disk uses a bare relative root `'public'` instead of `public_path()`, which is fragile under Flysystem 3. No disk declares `'throw'`.
- `app/Providers/AppServiceProvider.php` runs a DB query (`ModelsSettings::all()`) during boot inside a bare `catch (\Exception $e) {}`, which will mask upgrade failures.
- `app/Providers/BladeComponentServiceProvider.php:17` uses the legacy `Blade::component()` view alias, called from `register()` instead of `boot()`.
- `resources/lang/` must move to `lang/` in Laravel 9: **22 locale directories plus `vendor/`, 130 files, 906 KB**, of which only 7 locales hold more than the installer stub. ~~`joedixon/laravel-translation` hardcodes assumptions about that path.~~ **Corrected by TODO 17:** the package resolves its path through `$this->app['path.lang']`, which follows the move; only its `vendor:publish` target was a literal, and everything is already published. The package is removed in TODO 33.3 regardless.
- `app/Http/Middleware/setUserLastActivity.php` has a lowercase class name (risky on case-sensitive deploy targets). `RedirectIfUnansweredTerms` exists but is never registered.
- ~~`app/Notifications/GroupPriorityMessageNotificationTest.php` is a production class with a `Test` suffix, which some PHPUnit discovery configurations will pick up.~~ **Closed by TODO 15**, which deleted it. It was an accidental `make:notification` stub with no dispatch site, and it was inert under today's `phpunit.xml` - the hazard was the PHPUnit 10 schema rewrite in TODO 40. A guard now rejects any `*Test.php` under `app/`.

### Livewire 2 -> 3 migration size (this is the largest single item)

| Metric | Count |
| --- | --- |
| Component classes (`app/Http/Livewire/**`) | 28 |
| Livewire Blade views | 30 |
| Blade files with any `livewire`/`wire:` directive | 22 |
| `emitTo()` / `emitUp()` in PHP | 21 / 6 |
| `$emit*` in Blade | 13 |
| `Livewire.emit*` in JS | 9 |
| `dispatchBrowserEvent()` | 93 |
| `$listeners` arrays | 19 components |
| `wire:model.defer` / plain `wire:model` / `.lazy` | 85 / 28 / 8 |
| `wire:ignore` / `wire:ignore.self` | 24 / 31 |
| `@this.set()` / `@this.call()` | 12 |

Critical hotspots: `public/js/modal.js` (the generic modal bridge driving the 93 browser events), `resources/views/layouts/app.blade.php:113,125` (`livewire:load` -> `livewire:init`, `Livewire.emit` -> `Livewire.dispatch`, `onPageExpired`), the dynamic emit target in `app/Http/Livewire/Events/Modal.php:60,419`, `AppComponent`'s `$paginationTheme`, `Groups\NewsEdit`'s `WithFileUploads`, and camelCase attributes on unclosed `<livewire:...>` tags.

### Frontend

- **The real asset pipeline was `eusonlito/laravel-packer`, not Mix** - 16 call sites across `layouts/app.blade.php`, `layouts/setup.blade.php` and `livewire/groups/poster-edit-modal.blade.php`, concatenating hand-placed files under `public/` at request time. Measured in **TODO 21**, which decided to remove it; **executed in TODO 33.8**, so today the same three files carry 21 `pwbs_asset()` tags and nothing is generated at request time.
- **The Mix pipeline is dead.** `webpack.mix.js` and `laravel-mix ^6.0.6` are present, but there are **zero `mix()` calls** in the project, `resources/css/app.css` is 0 bytes, `resources/js/app.js` is the 25-byte default, and the outputs `public/js/app.js` / `public/css/app.css` do not exist. No `vite.config.js`, no `package-lock.json`, no `node_modules/`.
- Local Node is **24.18.0**, and Mix 6 / webpack 5 will not build on it - but since nothing builds today and nothing consumes the output, **that is not what makes a Vite migration necessary**. See the Phase 7 preamble.
- `composer.json` `post-autoload-dump` publishes Livewire assets, which breaks under Livewire 3.

---

## Phase 0 - Safety and Baseline

- [x] **TODO 01: Create a regression test baseline before any upgrade**
  - Delivered: route/middleware coverage, Livewire route-mounted and nested component tests, all 8 observers, all 26 notification mail contracts, critical user flows, domain unit checks.
  - Files: `tests/Feature/*`, `tests/Unit/*`, `tests/Concerns/BuildsDomainFixtures.php`, `tests/Fixtures/route-contracts.json`.

- [x] **TODO 02: Stabilize test environment configuration**
  - Delivered: dedicated MySQL test schema `kozter_testing`, test-safe drivers for cache/mail/queue/session/filesystem, `.env.testing` defaults.
  - Files: `phpunit.xml`, `.env.testing`.

- [x] **TODO 03: Freeze a verified behavior snapshot**
  - Delivered on 2026-08-05. Full report: `upgrade-notes/baseline-report.md`.
  - **The suite is green: `OK (179 tests, 593 assertions)` in 36.5s on PHP 8.1.30 / Laravel 8.83.1**, with no failures, errors, skips, risky tests or deprecation notices. The stale `.phpunit.result.cache` (2026-03-19, 110 defect entries referencing tests that no longer exist) was deleted first and was **not** evidence of real failures.
  - Artifacts in `upgrade-notes/`: `baseline-phpunit.txt`, `baseline-routes.json` (91 routes), `baseline-routes.txt`, `baseline-schedule.txt`, `baseline-composer-tree.txt`, `baseline-composer-direct.txt`, `baseline-versions.txt`.
  - Findings folded into later TODOs: the `verification.verify` winner is now known empirically (TODO 26), the route-contract fixture's true coverage is quantified (TODO 14), and `schedule:list` visually confirms the 8 opaque closures (TODO 06).
  - Not available on this baseline: `artisan about` (Laravel 9+) and `artisan config:show` (Laravel 11+). Re-capture both after the relevant hop for a richer after-picture.
  - Practical note for every later phase: **run the suite with `composer test`.** It clears the build caches, then calls PHPUnit directly. Extra arguments pass through: `composer test -- --filter SomeTest`.
    - `vendor/bin/phpunit` is a POSIX shell wrapper - invoking it through `php81` merely prints the script. The script therefore uses `php81 vendor/phpunit/phpunit/phpunit`. `php81` resolves via `C:\scripts\php81.bat` and is reachable **only from PowerShell**, not from a POSIX shell. It is hardcoded because Composer itself runs on PHP 8.3 here, and the suite does not survive 8.3 (Carbon's `setLastErrors()` throws a `TypeError` immediately) - so Composer's `@php` cannot be used.
    - **`optimize:clear` first, and that is not cosmetic.** A leftover `bootstrap/cache/config.php` makes the tests read the APPLICATION configuration - the `kozter_live` database included - and a leftover `routes-v7.php` freezes the `setup/*` group's `Storage::exists('installed.txt')` condition. The latter once produced 110 false failures in a single run.
    - `tests/CreatesApplication.php` carries two guards for whoever bypasses the script: it refuses to build the application when a build cache is present, and when the configured database matches the one in the `.env` file. The first one fired on its very first run.

---

## Phase 1 - Test Coverage Completion

Full coverage is required **before** any framework change. Every item here is Laravel 8 compatible and is the safety net for Phases 4 through 10.

**Phase 1 is complete as of 2026-08-06 (TODO 04 through TODO 15).** The suite went from the 179-test TODO 03 baseline to **957 tests / 2867 assertions**, green on PHP 8.1.30 / Laravel 8.83.1 in roughly 155s. The next work is Phase 2, the dependency decisions.

- [x] **TODO 04: Add factories for uncovered models and make seeding usable**
  - Delivered on 2026-08-05. **Suite: 179 -> 249 tests, 777 assertions, green.**
  - All 23 missing factories written under `database/factories/`, each with domain-meaningful states. Every model now has a factory.
  - `tests/Feature/Factories/ModelFactoryTest.php` exercises all 30 factories through a data provider (persist + repeat-create), plus targeted checks for the non-trivial ones: translatable rows, `encrypted` round-trip with a raw-column assertion, `array`/`json` casts, and the `LogHistory` morph binding. The provider list doubles as an assertion - adding a model without a factory makes it fail.
  - `database/seeders/CoreSettingsSeeder.php` added (idempotent, `firstOrCreate`) and wired into `DatabaseSeeder`, so `artisan db:seed` now does something useful. `StaticPagesSetupSeeder::run()` argument made optional with a first-`mainAdmin` fallback, so it is invocable via `db:seed --class=...`; the `Setup\AccountController` `callWith(['user_id' => ...])` path is unchanged. Covered by `tests/Feature/Seeders/SeederTest.php`.
  - **Three latent bugs found and fixed** - `DayStat`, `GroupPosterRead` and `Statistics` lacked `public $timestamps = false` while their tables have no `created_at`/`updated_at` columns. Any Eloquent `create()`/`save()` on them throws `SQLSTATE[42S22] Unknown column 'updated_at'`. It never surfaced because the app writes these tables exclusively through `DayStat::insert()`, `Statistics::insert()` and `DB::table('group_poster_reads')->insert()`, all of which bypass timestamp handling - and bypass casts and observers too. Worth revisiting those call sites in a later phase.
  - **Observation for TODO 10:** several observers (`GroupLiteratureObserver:28`, `GroupNewsTranslationObserver:36`, and others) read `auth()->user()->id` unconditionally, so any model write outside an authenticated context fatals. `ModelFactoryTest` works around it with `actingAs()` in `setUp()`, which mirrors real application usage - but a queue job or console command writing these models would hit the same fatal.
  - Not done, deliberately: `tests/Concerns/BuildsDomainFixtures.php` still uses its ad-hoc builders. Rewriting them onto the new factories touches all 16 existing feature test files, so it belongs in its own change set rather than bundled here.

- [x] **TODO 05: Execute job `handle()` bodies in tests**
  - Delivered on 2026-08-05. **Suite: 249 -> 304 tests, 875 assertions, green.** All 8 jobs now execute for real; previously not a single `handle()` body ran.
  - New files under `tests/Feature/Jobs/`: one per job, plus `JobSerializationTest.php` covering the serialize/unserialize round trip for all 8, the `ModelIdentifier` payload shape, and a full dispatch through the sync queue. Laravel 11 reworked queue serialization, so this is the guard against a worker picking up a payload written by the previous release.
  - **Job activity status, established while writing the tests** - this materially changes what needs attention later:
    - *Live:* `CalulcateUserNameIndexProcess` (dispatched from `UserObserver` on every user write), `GenerateStatProcess`, `CalculateDateProcess`, `UserLogoutFromGroupProcess`, `DeleteGroupDataProcess`.
    - *Inactive:* `GroupDayUpdatedProcess` and `GroupDayDeletedProcess` are dispatched only from `GroupDayObserver`, which is not registered in `EventServiceProvider`. They remain covered as dormant legacy behavior; TODO 10.1 decided not to activate the observer because their cleanup is already provided by the active `GroupDateHelper` chain. **`GroupDayDeletedProcess` was subsequently deleted in TODO 10.2**, so this list is 7 jobs today.
    - *Dead:* `EventAutoCheck` - both dispatch sites in `EventObserver` (`:58`, `:144`) are commented out.
  - **Four latent bugs found, all documented with characterization tests rather than fixed** (fixing them changes behaviour and belongs to the phase that owns the code):
    1. `GroupObserver::deleted()` reads `$group->group_id`, a field the `Group` model does not have (the key is `id`), so it is always null while `log_histories.group_id` is NOT NULL. **Any `$group->delete()` through Eloquent fatals.** Hidden today because `GroupDelete` uses a mass delete, which fires no model events. -> TODO 10.
    2. `GenerateStatProcess::handle()` calls `->first()->delete()` unconditionally in its `forceReset` branch, so a missing `GroupDate` is a null-pointer fatal. Current callers always pass an existing date.
    3. `EventAutoCheck` is not merely unfinished (it carries an explicit "THIS IS NOT FINISHED YET" marker, an empty `foreach`, and an invalid `'=<'` SQL operator) but **unrunnable**: line 115 reads `$event->id` on an array element while the same loop uses `$event['end']`. It fatals as soon as it passes its guard clauses - proof it has never executed in production.
    4. `DeleteGroupDataProcess` only anonymizes members if the group is **already soft-deleted** when it runs, because its `count($user->user->userGroups) == 0` check joins the `groups` table. The real `GroupDelete` flow deletes first and dispatches second, so it works - but the job is silently order-dependent, and calling it on a live group skips anonymization entirely.
  - Test-writing notes worth carrying forward: `Notification::fake()` also captures the `EventObserver`-driven notification fired when a test creates an event, so job assertions must be targeted (`assertNotSentTo`) rather than `assertNothingSent`. Jobs touching events need `actingAs()` for the same observer reason as TODO 04.

- [x] **TODO 06: Extract scheduler closures into testable commands**
  - **Follow-up shipped on `v1-patch`:** the `newsletters:send-due` defect this TODO preserved verbatim is fixed. An unknown `send_to` used to `return`, skipping every REMAINING newsletter in the batch, and since `sent_time` was never stamped it retried every minute and sat at the head of the queue indefinitely - one mistyped recipient value blocked every later newsletter for good. The row is now skipped and reported, the rest are delivered, and the command exits non-zero. `sent_time` deliberately stays null on the skipped row.
  - Delivered on 2026-08-05. **Suite: 304 -> 342 tests, 945 assertions, green.**
  - The ~200 lines of anonymous closures in `app/Console/Kernel::schedule()` became **11 named Artisan commands** under `app/Console/Commands` (a directory `commands()` was already loading but which did not exist). `schedule()` is now a 12-line list of `$schedule->command(...)` calls.
  - Two closures were split because they bundled unrelated work: the daily cleanup closure became `maintenance:daily-cleanup` + `statistics:record-daily-users`, and the every-minute closure became `groups:apply-future-changes` + `newsletters:send-due`. Every original cron expression is preserved; verified against `upgrade-notes/baseline-schedule.txt`.
  - `schedule:list` now shows 13 named entries instead of 1 named plus 9 blank rows.
  - `tests/Unit/Scheduler/SchedulerRegressionTest.php` rewritten: it pins every command name to its cron expression, asserts the total task count, asserts the `queue:work` overlap guard, and **fails if an anonymous closure is ever reintroduced**.
  - New per-command coverage in `tests/Feature/Commands/`: `MaintenanceCommandsTest` (14), `GdprCommandsTest` (13), `NewsletterAndGroupChangeCommandsTest` (9). The GDPR pair was the largest untested surface in the app - roughly 90 lines including a four-way raw join that decrypts `users.name` and `groups.name` by hand - and it deletes or anonymizes real user data daily.
  - **One more latent bug found, pinned rather than fixed:** `newsletters:send-due` returns early on an unknown `send_to` value, which skips **every remaining newsletter in the batch**, not just the bad one. Since `sent_time` is never stamped, it retries every minute and blocks the queue behind it indefinitely. Characterization test in `NewsletterAndGroupChangeCommandsTest`.
  - Two quirks deliberately preserved and documented: the `dialy_users` statistics type is a typo that existing data and `Admin\Statistics` both depend on, and the statistics commands use `Statistics::insert()` rather than `create()`, bypassing casts, observers and timestamps.
  - `.docs/commands.md` rewritten in the same change set, per `AGENTS.md`.

- [x] **TODO 07: Add interaction tests for the 19 smoke-only Livewire components**
  - Delivered on 2026-08-05. New files under `tests/Feature/Livewire/`: `GroupMessagesTest` (20), `GroupNewsEditTest` (14), `GroupComponentsTest` (16), `AdminComponentsTest` (19), `AdminSettingsTest` (15), `PartialComponentsTest` (17), `ListGroupsTest` (10). **111 new tests.**
  - Every previously smoke-only component now has behavioural coverage of its public methods, validation rules, authorization boundaries and emitted events. `Groups\ListUsers` is intentionally left to TODO 07.2, whose subject it is.
  - `Groups\NewsEdit` has full `WithFileUploads` coverage: allowed/disallowed MIME types, the `max:2048` size rule, storage on the private `news_files` disk, and removal of both freshly-uploaded and already-stored attachments. This is the single highest-risk component for Phase 6.
  - **A latent bug found and pinned:** `Groups\Statistics` has its `public $months` declaration commented out (`Statistics.php:16`) while `getMonthListFromDate()` and `setMonth()` still use it. It therefore becomes a **dynamic property**, which Livewire does not persist between requests - so `setMonth()`'s `isset()` check is always false and **the month selector silently does nothing**. The identical code works in `Groups\History`, where `$months` is declared. Extra upgrade risk: dynamic properties are deprecated from PHP 8.2, so this will start emitting notices in Phase 8.
  - **A factory defect found and fixed:** `GroupUserFactory` defaulted `message_use` to `1`, which in `Groups\Messages::checkPrivilege()` means "may not write" - while the database default is `0`. Its `withMessaging()` / `withoutMessaging()` states were also inverted. Corrected, and the semantics documented in the factory.
  - **Two components proved untestable in isolation**, both worth noting for Phase 8:
    - `Admin\Settings::saveOthers()` and `languageSetDefault()` call `setEnvironment::setEnvironmentValue()`, which rewrites the real file behind `app()->environmentFilePath()` - under `APP_ENV=testing` that is `.env.testing`, so a test would corrupt its own configuration. Everything else in the component is covered; these two are not. Writing `.env` at runtime is the root problem and ties into TODO 28.
    - `Partials\SideMenu` renders a `$sidemenu` variable it never sets: it comes from `View::share()` inside the `SetLocale` middleware (`SetLocale.php:76`). The component test has to supply it manually. This implicit coupling is easy to lose when middleware moves in the Laravel 11 skeleton migration (TODO 56).
  - Also confirmed: `ListGroups::confirmGroupRemoval()` and `deleteGroup()` are commented out - group deletion runs through `Groups\DeleteGroup` instead. Pinned by a test so re-enabling them is a deliberate decision.
  - **Test-suite hygiene note:** `Admin\Settings::run()` executes real Artisan commands, and calling `view:clear` from a test wipes the compiled Blade cache - which made every subsequent test recompile its views and stretched the suite from ~50s to over 10 minutes. The Artisan facade is mocked in these tests instead, which also asserts the whitelist key maps to the right command. Watch for the same trap in any future test that triggers cache-clearing commands.
  - **This was a hard prerequisite for Phase 6**, and it is now met: the Livewire 3 migration has a behavioural safety net. **Suite: 342 -> 454 tests, 1303 assertions, 71s, green.**

- [x] **TODO 07.1: Cover event scheduling capacity and overlap rules**
  - Delivered on 2026-08-05. **Suite: 454 -> 502 tests, 1473 assertions, 99s, green.** 48 new tests across four files.
  - Context: this was the core domain logic of the application and it was **entirely untested**. `tests/Feature/CalendarEventEditTest.php` set up `date_max_publishers => 3` and `date_min_time => 60` in its fixture, but not one of its 15 tests ever filled a slot, so the capacity branch in `saveEvent()` never executed.
  - New files: `tests/Unit/Events/GenerateSlotsTest.php` (11), `tests/Feature/Events/EventCapacityTest.php` (12), `EventOverlapTest.php` (15), `EventModalSlotTableTest.php` (10). `tests/Concerns/BuildsDomainFixtures.php` gained `createEventDate()`, `createEventInRange()`, `fillSlotRange()`, `slotKey()` and `timestampFor()`; `CalendarEventEditTest`'s private `makeGroupDate()` was folded into the shared helper.
  - **The user's original three rules are each a named test:** the per-slot maximum boundary (Nth booking succeeds, N+1th fails), approval-group overbooking, and the 08:00-10:00 / 09:00-12:00 overlap example - the last one at both 60- and 30-minute granularity.
  - **CORRECTION to this roadmap's own earlier claim.** The previous version of this entry stated that under `need_approval` the effective per-slot limit is `max_publishers + config('events.max_columns')` while `accepted < max_publishers`. **That is not what `saveEvent()` enforces.** `getInfo()` (`EventEdit.php:288-291`) increments `publishers` and `accepted` **only** when `status == 1`, and always together - so in this component the two counters are **always equal**. The capacity ternary at `:477-482` therefore collapses algebraically to plain `accepted >= max_publishers`: when `accepted < max` the limit is `max + max_columns` but `publishers` (= `accepted`) is below `max`, so it can never trigger. **The `+ config('events.max_columns')` branch is unreachable dead code, and pending applications consume no capacity at all in this check.**
  - The overbooking ceiling *is* enforced, but through a different gate: the `$slots` array (`:286`) counts **every** event including pending ones, and the filter at `:300-306` removes saturated slots from `day_selects`, which `saveEvent():487-490` then validates against. The observable consequence, now pinned by tests:
    - hitting the raised ceiling in an approval group produces **`event.invalid_value`**, not `event.reach_max_publisher`;
    - once `accepted` reaches `max_publishers`, both errors appear together.
  - **Two latent bugs found, both pinned rather than fixed** (fixing changes behaviour and belongs to TODO 77, which owns this code):
    1. The `publishers` counting divergence above. `Events\Modal.php:352` increments `publishers` for **every** event while `EventEdit.php:288` restricts it to accepted ones - so the same data yields different numbers in the calendar view and in the save check. `EventModalSlotTableTest::test_the_modal_counts_pending_events_as_publishers_but_the_editor_does_not` measures the gap directly and is the reference point for the TODO 77 extraction.
    2. ~~**Lowering `date_max_publishers` below the number of existing events on a slot makes the day unopenable.**~~ **FIXED on `v1-patch`.** `getInfo()` allocated `max_publishers + events.max_columns` cells per slot and consumed one per event via `min(array_keys(...))`; once the cells ran out, PHP 8 threw a `ValueError` from `min([])` and the whole day became unopenable, not merely misdrawn. Reachable in production: events created under a high maximum, then an admin lowers it (or a scheduled future group change overwrites it), and nobody deletes the existing events. Overflowing events now get a column of their own; the pinning test was inverted. **Finding 1 above still stands** - it needs a product decision and stays with TODO 77.
  - Also covered: capacity is per-slot not per-day; a member cannot book the same slot twice in the same group (`getInfo():292-294` marks the applicant's own slots disabled); `disabled_slots` suppress slots with zero events; the `ready` status requires **accepted** events, so a pending-only slot stays `free`; the full cross-group `busy` matrix (overlap rejected, exact touch allowed, pending / soft-deleted event / soft-deleted group all ignored, and same-group overlaps deliberately out of its scope).
  - `Events\Modal` got its **first direct test coverage** - `CalendarEventsComponentTest` only exercises `Events\Events`. Its state is private (`day_data`, `date_data`, `day_events`), so `assertSet()` does not work; the tests use `viewData()` instead, since `render()` passes those arrays to the view (`Modal.php:551-558`). Worth remembering for Phase 6.
  - `GenerateSlots` notes now pinned: the `$max_hour` correction (`GenerateSlots.php:16,26-29`) is **dead code** since the rewrite to a `while` loop; a half-hour start keeps its offset across whole-hour steps while a half-hour end leaves the last slot hanging over the closing time; `ceil()` makes the Modal's `height` a **float** that reaches the view's `rowspan`. DST is covered under `Europe/Budapest` (the test timezone is UTC, so it needs an explicit switch): spring-forward is absorbed by the array-key deduplication, while **fall-back silently skips the repeated hour**, making that day appear an hour shorter in the calendar.

- [x] **TODO 07.2: Cover group creation and role assignment authorization** - DONE
  - Delivered: `tests/Feature/Auth/AuthorizationGateTest.php` (22 tests), `tests/Feature/Groups/GroupCreationTest.php` (12), `tests/Feature/Groups/GroupRoleAssignmentTest.php` (27), plus two `can:is-groupservant` route tests in `RouteMiddlewareRegressionTest` and two new fixture helpers (`createChildGroup()`, `editUserState()`). Suite: **502 -> 565 tests, 1670 assertions, ~96s, green**. No application code was changed.
  - **CORRECTIONS to this roadmap's own earlier claims** (the original text is kept below for the record):
    - The method is **`updateUser()`**, not `saveUser()` - there is no `saveUser()` in `Groups\ListUsers`; editing runs through the `editUser()` -> `updateUser()` pair (`:167`, `:201`). `maxRoles()` is at `:808-816`, not `:809-813`.
    - `requestGroupCreatorPrivilege()` does **not** call `env()`; it reads `config('mail.from.address')` (`ListGroups.php:76`), so it is not a TODO 28 item. The real Phase 4 risk there is `->replyTo(auth()->user()->email)` - a user-supplied address under the stricter Symfony Mailer (TODO 36). Its validation was already covered by TODO 07 (`ListGroupsTest:131-177`); what this TODO added is the **mail contract**: recipient, `replyTo`, subject and the `strip_tags()` effect.
    - **Rule 4 (`group.error_no_right`) is dead code; rule 3 is not.** Both were assumed reachable. Rule 3 fires when a `roler` demotes an `admin` to `roler`, because the target role is inside the actor's own `maxRoles()` scope, so the silent reset does not intercept it. Rule 4 requires a non-admin to submit `admin`, which is *always* outside their scope - the reset at `:211-214` rewrites the value first, `:236` (`pivot != state`) then evaluates false, and the whole `after()` block is skipped. Same shape as the TODO 07.1 approval ceiling: the protection holds, but not through the gate the code intends, and with no error message at all. Pinned by `test_the_no_right_to_grant_admin_rule_is_unreachable`.
  - **Five latent bugs found, all pinned rather than fixed** (the user chose characterization over fixing, consistent with TODO 04-07.1):
    1. **A `groupCreator` never receives the newsletters targeted at them.** `helpers.php:68` asks for `can('is-groupCreator')` while the gate is defined as `is-groupcreator` (`AuthServiceProvider.php:37`). Laravel keys abilities in an array, so gate names are case-sensitive and the condition is **always false** - only `mainAdmin` gets through, via the `is-admin` branch. Affects `Admin\AdminNewsletters`, `Partials\NavBar`, `Partials\SideMenu`. Fix belongs to TODO 33.
    2. **An outsider gets a fatal error instead of a 403.** `getRole()` (`:800-806`) ends in `->first()->toArray()`; with no `group_user` row the `first()` is null and the call fatals **before** `isNotHelper()` can abort with 403. It happens during `render()`, so the component never opens. Every `getGroupInfo()` caller is affected. Assigned to TODO 10.
    3. **`isNotHelper()` and `isNotEditor()` are literally identical** (`:792-798`), both allowing only `['admin','roler']`. Consequence: `maxRoles()` can only ever return `['member','helper','roler']` (roler) or all four (admin) - its `member` and `helper` branches are unreachable. Assigned to TODO 33.
    4. **`finish_guest_registration` is not a database column but is passed into `syncWithoutDetaching()`** (`:257-259`; `editUser():193` always sets it). It survives only because `GroupUser` is a custom `Pivot` with a `$fillable` list, so Laravel takes the `updateExistingPivotUsingCustomClass()` path and `fill()` silently drops the unknown key. A framework-version-dependent path - exactly what an 8->13 hop disturbs. Assigned to TODO 29/34.
    5. **The `function_exists()` guard protects the wrong name**: `helpers.php:23` checks `pwbs_check_group_admins` but defines `pwbs_check_group_other_admins`, so the guard never matches and a second load would fatal. Assigned to TODO 33.
  - Also covered and worth remembering: the `updateUser()` **save is not transactional** - pivot data is written at `:259` while the profile fields are validated separately at `:265-269`, so an invalid name leaves a partially saved record (`test_the_profile_validation_runs_after_the_pivot_data_is_already_saved`); `pwbs_check_group_other_admins()` walks child groups and demands an admin who covers **every** group, so a second admin present only in the parent does not unblock a demotion; `groups.name` is `encrypted`, so `assertDatabaseHas(['name' => ...])` can never match and duplicate group names are allowed; `strip_tags()` removes tags but keeps their text content, so it is not sanitization; and the group gates read the **lazily loaded relation** (`$user->userGroupsEditable`, not the query), so a membership created within the same request is invisible until the model is refreshed.
  - Original specification, unchanged:
  - Context: group creation has a happy-path test (`tests/Feature/LivewireComponentInteractionTest.php:114`), but the authorization boundaries around it are untested, and **in-group role assignment has no coverage at all** - `Groups\ListUsers::saveUser()` is roughly 100 lines of authorization logic reached only by a route-returns-200 smoke test.
  - Needed:
    - **The `is-groupcreator` gate.** `app/Providers/AuthServiceProvider.php` grants it to `mainAdmin`, `groupCreator` **and** `translator`. Only the `groupCreator` path is exercised today. Test all three grants plus the denial for a plain `activated` user. The sibling gates `is-groupservant` and `is-groupadmin` derive from `userGroupsEditable` / `userGroupsDeletable` counts and are also untested.
    - **Granting the group-creator privilege.** `Admin\Users\ListUsers` assigning `role => groupCreator` is covered (`:29,35`), and `UserRoleIsGroupCreatorNotification` has a mail-contract test - but the request side is not: `Groups\ListGroups::requestGroupCreatorPrivilege()` validates `congregation`/`reason`/`phone` and sends a raw `Mail::send()` with a `replyTo`. **That mail call is one of the `env()`/address-strictness risks from TODO 28**, so it needs a test before Phase 4.
    - **Group creation boundaries.** `Groups\ListGroups::createGroup()` calls `abort(403)` when the gate denies - untested. Also untested: the `name` validation (`min:2`, `max:50`), and that a *denied* user cannot create a group by calling the component method directly. The existing test already asserts the creator gets `group_role => admin` with `accepted_at` set - keep that.
    - **In-group role assignment hierarchy.** `Groups\ListUsers::saveUser()` enforces four distinct rules, none of them tested:
      1. `maxRoles()` (`:809-813`) walks `$group_roles = ['member','helper','roler','admin']` and stops at the current user's own role, so nobody can grant a role above their own. **Note the failure mode: an out-of-range role is silently reset to the target's existing role (`:211-214`), not rejected with an error.** Pin that behavior down before the upgrade, because a silent reset is easy to break unnoticed.
      2. `pwbs_check_group_other_admins()` (`app/Helpers/helpers.php:24`) blocks demoting the last remaining admin (`group.error_no_admin_user`).
      3. A non-admin cannot remove an existing admin's role (`group.error_no_right_to_remove_admin`).
      4. A non-admin cannot grant the admin role (`group.error_no_right`).
    - **Guest activation side effect.** `finish_guest_registration == 1` on a `registered` user generates a random password, flips `role` to `activated` and sets `email_verified_at` (`:294-300`). Also assert the `UserProfileChangedNotification` dispatched when name/phone/congregation change - all three are `encrypted` columns, which ties into TODO 13.
    - Cover the `hidden`, `note` (`max:50`), `message_use` and `message_send_priority` fields in the same validator.
  - Expected changes:
    - New `tests/Feature/Groups/GroupCreationTest.php` and `tests/Feature/Groups/GroupRoleAssignmentTest.php`.
    - Gate coverage added to `tests/Feature/RouteMiddlewareRegressionTest.php`, which currently only exercises `can:is-admin` and `can:is-translator`.

- [x] **TODO 08: Cover `AppComponent` pagination behavior** - DONE
  - Delivered: `tests/Feature/Livewire/PaginationBehaviorTest.php` (10 tests), `AdminUserListPaginationTest.php` (9), `GroupListPaginationTest.php` (9), `GroupUserListPaginationTest.php` (16), plus two fixture helpers (`attachManyUsersToGroup()`, `attachUserToManyGroups()`). Suite: **565 -> 609 tests, 1842 assertions, ~102s, green**. No application code was changed.
  - **CORRECTION to this roadmap's own component list.** The original entry named five components; **four of them do not paginate**, and three are not even `AppComponent` subclasses:

    | named | base class | paginates? |
    |---|---|---|
    | `Admin\StaticPages` | `Livewire\Component` | no |
    | `Admin\AdminNewsletters` | `Livewire\Component` | no |
    | `Groups\NewsList` | `Livewire\Component` | no |
    | `Groups\History` | `AppComponent` | no - month navigation |
    | `Groups\ListUsers` | `AppComponent` | **yes, by hand** |

    The complete set of paginating components, from the `paginate()` and `->links()` call sites: **`Admin\Users\ListUsers`** (`:134`, `paginate(20)`), **`Groups\ListGroups`** (`:229`, `paginate(20)`) and **`Groups\ListUsers`** (`:915-921`, a hand-built `LengthAwarePaginator` with `per_page = 10`). `Events\Events` uses the `pagination` CSS class for its year/month navigation but is not a Laravel paginator and is unaffected by `$paginationTheme` - TODO 48 should not look for it there.
  - **The main finding, and the reason TODO 48 is riskier than it looks: `Groups\ListUsers` will break silently under Livewire 3.** Its `render()` reads `$current_page = $this->page` (`:915`) - the Livewire 2 `WithPagination` trait's `public $page`, which `setPage()` keeps in step with `$paginators` by writing **both** (`$this->paginators[$pageName] = $page; $this->{$pageName} = $page;`). Livewire 3 drops the trait property and the second write. The component declares its own `public $page = 1` (`:36`), so the property survives - but nothing updates it: the pagination buttons call `gotoPage()`, which sets `paginators`, while `render()` still reads `$page`. **The list would sit on page 1 forever, with no error.** The other two components go through the framework's `paginate()`, which the `Paginator::currentPageResolver` binds to `paginators`, so they migrate cleanly. Pinned by `GroupUserListPaginationTest::test_going_to_the_second_page_actually_shows_the_second_ten` and `test_goto_page_writes_both_the_page_property_and_the_paginators_array`.
  - **One latent bug found and pinned: two filters change the result set without resetting the cursor.** `updatedSearchTerm()` (`:382`), `filterMyself()` (`:393`), `filterIcon()` (`:405`) and `filterOff()` (`:409`) all call `resetPage()`; `filterOnline()` (`:413-419`) and `filterInactive()` (`:421-427`) do not, although they narrow the list just as much. Standing on page 3 and clicking the online filter therefore yields an empty list even when there are matches. Pinned by `test_the_online_filter_narrows_the_list_without_resetting_the_cursor`.
  - Also pinned: `paginationView()` resolves to `livewire::bootstrap` (and `livewire::simple-bootstrap`), which is exactly what TODO 48 must reproduce through a `paginationView()` override; the rendered markup is bootstrap and **not** tailwind (both directions asserted, so a silent fallback to the v3 default is caught); the vendor template's `wire:click="nextPage('page')"` / `gotoPage(N, 'page')` handlers make those method names part of the view contract; the relation filters run before pagination, so withdrawn memberships and soft-deleted groups do not leave gaps, while **pending invitations do count toward the page total**; and `Groups\ListUsers` is the only `->links()` call site taking an argument (`onEachSide(1)`).
  - **Test-fixture note worth remembering:** the member list is ordered by `name_index, email`, and `name_index` is rewritten by `CalulcateUserNameIndexProcess`, which `UserObserver` dispatches on **every** user write and which renumbers *all* users by name. With identical names the order is effectively arbitrary, so any ordering-sensitive test must give its users distinct names - `attachManyUsersToGroup()` now does.
  - Expected changes: none in application code; the assertions above are the verification for TODO 48.

- [x] **TODO 09: Test the 5 uncovered middleware directly** - DONE
  - Delivered: `tests/Feature/Middleware/` with `SetLocaleTest.php` (18 tests), `CheckRecaptchaTest.php` (10), `SetUserLastActivityTest.php` (8), `SetGuestLanguageTest.php` (8), `HttpsProtocolTest.php` (7), plus a `withEnvValue()` fixture helper. Suite: **609 -> 660 tests, 1958 assertions, ~107s, green**. No application code was changed.
  - Where each one actually runs, which the original entry did not record: `SetLocale`, `setUserLastActivity` and `HttpsProtocol` are in the **`web` group**, so they run on every web request; `SetGuestLanguage` is on the signed `finish_registration` route only; and **`CheckRecaptcha` sits on `POST /login`, `POST /register` and `POST /forgot-password`** (`routes/fortify.php:34-75`) - the three most sensitive public endpoints, which is why its enabled state needed covering.
  - **Two latent bugs found, both pinned rather than fixed** (the user chose characterization, consistent with TODO 04-08):
    1. **A Google outage would lock everyone out of the site.** `CheckRecaptcha:22` calls `Http::asForm()->post()` with **no try/catch**. An HTTP error code returns a `Response` and is handled, but a *connection* failure throws `ConnectionException`, which nothing catches - so login, registration and password reset all return 500. Dormant today because `USE_RECAPTCHA=false`. Pinned by `CheckRecaptchaTest::test_a_connection_failure_escapes_the_middleware_as_a_fatal_error`; fix tracked as **TODO 33.1**, mandatory before recaptcha is ever switched on.
    2. **A static page created outside the editor never reaches the menu.** `SetLocale:68-74` uses `Cache::rememberForever('sidemenu_guest' / 'sidemenu_auth')`, and those keys are cleared in **exactly two places** - `Admin\StaticPageEdit:91-92` and the setup `AccountController:50-51`. Anything created by a seeder, a console command, a direct model write or a data import stays invisible until somebody happens to edit a page in the UI. There is no expiry. Pinned by `SetLocaleTest::test_a_page_created_outside_the_editor_never_reaches_the_cached_menu`.
  - **Behaviour now pinned that was previously undocumented:** the menu visibility rule (guests see `status IN (1,2)`, authenticated users see `(0,1,3)`, so status 2 is guest-only and 0/3 are login-only); maintenance mode logs out **everyone except `mainAdmin`** - a `translator` is not exempt - and does not touch guests at all; the `?lang=` branch validates against `available_languages` and hides invisible languages from all but `mainAdmin`/`translator`, **while the `session('language')` branch validates nothing**, so a previously granted hidden language survives losing the privilege; a visible language choice is also written to `users.language`, which fires `UserObserver` and its name-index job on every switch; and `SetGuestLanguage` feeds that same session key, which is the only guest-side source for the `SetLocale` session branch - the two middleware are coupled and this was recorded nowhere.
  - `CheckRecaptcha` details: the score test is strict (`score > min_score`, so a score exactly at the threshold is rejected), a 500 from Google counts as a bot, and the rejection path is a `back()` redirect carrying a `status` message rather than an error bag. **`Http::fake()` appears here for the first time in this suite.**
  - `setUserLastActivity` details: the write is a mass `update()`, so **no model events fire** (deliberate - an Eloquent save would trigger the name-index job on every request) but the Eloquent builder still **bumps `updated_at`**, meaning every active user's row is touched once a minute.
  - Expected changes: none in application code.

- [x] **TODO 10: Fix observer defects and cover `GroupDayObserver`** - DONE
  - **The first TODO that changes application code.** Deliberately placed here: the fixes only come once there is a net underneath them.
  - Delivered: new `app/Observers/Concerns/ResolvesCauser.php`; all **eight** observers switched onto it; the `GroupObserver::deleted()` and `Groups\ListUsers::getRole()` defects fixed; four pinning tests rewritten to the new behaviour; new `tests/Feature/Observers/ObserverCauserTest.php` (11 tests); `.docs/observers.md` rewritten. Suite: **660 -> 671 tests, 1985 assertions, green**.
  - **The causer contract, now uniform.** `causerId()` returns the acting user's id or **`0` = "the system"**; `causerName()` returns the name or `'SYSTEM'`. Before this, three behaviours coexisted for the same situation: `GroupObserver::updated` and `GroupUserObserver::updated` **skipped** the audit record, `EventObserver::updated` wrote `0`, and eight call sites **fataled**. `log_histories.causer_id` has no foreign key, so `0` is safe.
  - **Two intentional behaviour changes, both user-visible:**
    1. `GroupObserver::updated()` and `GroupUserObserver::updated()` now log system-driven changes instead of skipping them - notably everything `ApplyGroupFutureChanges` does, which previously happened with no trace at all.
    2. `EventObserver::deleted()` used to pass `false` as `userName` for a system deletion, which rendered as an **empty name in the outgoing email**. It now says `SYSTEM`, matching what `updated()` has always sent.
  - **Cost of the fuller audit trail: the suite went from ~107s to ~150s.** The extra time is the additional `log_histories` inserts. The production impact is much smaller than that ratio suggests: in tests most writes are unauthenticated, whereas in production almost all group and membership updates happen inside an authenticated request, where a record was already being written. The genuinely new rows are the scheduler/queue/console ones - which is the point of the change.
  - **`GroupDayObserver` stays unregistered - a decision, not an oversight.** It is the **sole dispatcher** of `GroupDayUpdatedProcess` and `GroupDayDeletedProcess`. It was also unregisterable as written: `GroupDay` rows are written only by `updateGroupFutureChanges` (through Eloquent, so the events would fire), and that class is called by the **`ApplyGroupFutureChanges` scheduled command** with no authenticated user - registration would have fataled the scheduler on the first run. The remaining question is a product one, tracked as TODO 10.1.
    - **Correction, made in TODO 10.1:** this entry originally added that because the observer is the sole dispatcher, the "delete future events that no longer fit the day template" cleanup **never runs**, and called that *a missing feature, not dead code*. **That was wrong.** The cleanup does run, on a different chain - `UpdateGroupForm` -> `GroupDateHelper::recalculateDates()` -> `CalculateDateProcess` -> `CalculateDatesEvents::generate()`, which is the very method `GroupDayUpdatedProcess` calls. See TODO 10.1 for the evidence.
  - **Follow-up, found by an audit of this TODO: the first version of the fix only moved the fatal downstream.** The entry originally claimed the causer work had removed the obstacle in front of registration. It had not. Passing `0` where a real id used to go simply relocated the failure into the consumers, both of which read the causer's *name* straight off a model:
    - `GroupDayDeletedProcess::handle()` did `User::find($this->user_id)` and then `->name`. `User::find(0)` is `null`.
    - `CalculateDatesEvents::generate()` guarded with `if($user_id)`, which is **false for `0`**, so it fell through to `auth()->user()` - and that is always `null` in a queue worker.

    Laravel turns PHP warnings into `ErrorException`, so both were genuine failures, not silent nulls. Fixed by giving `ResolvesCauser` a static `causerNameFor($userId)` that maps `0`/`false`/`null` - and a since-deleted user - onto `SYSTEM`, and the trait moved to `App\Support\Concerns` because jobs and service classes now use it too.

    **Why no existing test caught it:** the job tests always pass a real, existing causer, and `phpunit.xml` sets `QUEUE_CONNECTION=sync`, so jobs run *inside* the authenticated request where `auth()` still answers. Production uses `QUEUE_CONNECTION=database`, i.e. a separate worker with no session. New `tests/Feature/Jobs/SystemCauserJobsTest.php` (6 tests) covers both paths with a system causer and was verified to fail with `Attempt to read property "name" on null` before the fix. Suite: **671 -> 677 tests, 1995 assertions**.

    Neither path is reachable in production today - `GroupDayDeletedProcess` only via the unregistered observer, and `CalculateDatesEvents` only ever receives a real id from the authenticated Livewire callers - so this was a latent defect, not a live one. But it was exactly the obstacle TODO 10.1 would have hit.
  - **Correction to the TODO 07.2 note on `getRole()`:** it was described as affecting every entry point. The `groups.users` route carries the `groupMember` middleware (`routes/web.php:177`), which requires an accepted membership, so this was **not an active security hole** - it was missing defence in depth, with a 500 as the only thing holding the component shut. The fix is `abort(403)`. Leaving `role` as `null` instead would have been *worse*: `render()` performs no authorization, it only computes `$editor`, so an outsider would have rendered the member list as a non-editor.
  - Original specification, unchanged:
  - Needed:
    - **Fix `GroupObserver::deleted()`**: it reads `$group->group_id`, which does not exist on the `Group` model (the key is `id`), so `log_histories.group_id` receives null against a NOT NULL column. Any `$group->delete()` through Eloquent fatals today; only the mass-delete in `GroupDelete` keeps this hidden. Found by TODO 05, pinned by a characterization test in `tests/Feature/Jobs/DeleteGroupDataProcessTest.php` that will fail once fixed.
    - **Decide how observers should behave without an authenticated user.** `GroupLiteratureObserver:28`, `GroupNewsTranslationObserver:36`, `EventObserver:31` and others read `auth()->user()->id` unconditionally, so any write from a queue worker, console command or seeder fatals. Either guard the call or make the causer explicit.
    - `GroupDayObserver` is still not registered. Decide and document whether to activate it; its two jobs are now covered by `tests/Feature/Jobs/GroupDayJobsTest.php`, so activation is verifiable.
    - **Fix `Groups\ListUsers::getRole()`** (`:800-806`), found by TODO 07.2 - same family of defect: a missing precondition check at the head of the call chain. It ends in `->first()->toArray()`, so a user with no `group_user` row for the group fatals during `render()`, **before** `isNotHelper()` can return 403. Pinned by `GroupRoleAssignmentTest::test_an_outsider_hits_a_fatal_error_instead_of_a_403`, which will need updating once a proper 403 is returned.
  - Expected changes:
    - Observer fixes, updated characterization tests, and an updated `.docs/observers.md`.

- [x] **TODO 10.1: Decide whether to activate `GroupDayObserver`** - DONE
  - Context: split out of TODO 10, which fixed the technical obstacle but deliberately left the product decision open. The framing was: registering the observer turns on an audit trail for day-template changes **and** a cleanup that deletes users' already-booked future events - so it needed a product decision.
  - **The premise turned out to be false, and the decision changed with it.** The cleanup is not missing. Reading the two write paths end to end:
    - `Groups\UpdateGroupForm::updateGroup()` saves the `GroupFutureChange` **first**, then walks every future `group_dates` row through `GroupDateHelper::generateDate()`, which rewrites them against the *pending* template (`GroupDateHelper.php:58-84`). A removed weekday becomes `date_status = 0` and is queued for deletion; a narrowed one gets new `date_start`/`date_end`.
    - The closing `recalculateDates()` dispatches `CalculateDateProcess`, which calls **`CalculateDatesEvents::generate()`** - the exact method `GroupDayUpdatedProcess::handle()` calls. That job's own body is entirely commented out and replaced by a single call to it.
    - So the `group_dates` rows are already rewritten to the future template at save time; the scheduled `initChanges()` only syncs the `group_days` template itself.
  - **Decision (the user's): leave it unregistered, and document why.** The observer's cleanup dispatches and their two jobs are a **superseded implementation**, not a missing feature: activating them would run the cleanup a second time, on a different schedule. The observer's audit-history hooks are separate and are **not** duplicated elsewhere, so leaving the observer unregistered also deliberately leaves `GroupDay` changes without their own audit records. `app/Providers/EventServiceProvider.php` is unchanged; the observer and both jobs stay in place with their existing coverage.
  - Delivered: new `tests/Feature/Groups/GroupDayTemplateCleanupTest.php` (10 tests) proving the cleanup runs *without* the observer - narrowing deletes the events outside the new window and pulls partially overlapping ones inside, removing a day deletes its events, its `group_dates` row and its `day_stats`, past dates and widened days are untouched; two new tests in `tests/Feature/Commands/NewsletterAndGroupChangeCommandsTest.php` for the scheduled path (the existing ones ran with an **empty** `days` payload, so `initChanges()`'s Eloquent writes were never exercised at all), one of them explicitly unauthenticated and asserting `causer_id = 0`. Suite: **677 -> 689 tests, 2052 assertions, green**.
  - **The tests were verified to measure the right thing.** Commenting out the `recalculateDates()` call in `UpdateGroupForm.php:395` makes **six of the ten** cleanup tests fail; the four that survive are the ones asserting what `generateDate()` and `initChanges()` do on their own, plus the two negative cases. Without that check we would only have known that *something* deletes the events.
  - Loose end for anyone who revisits this: `GroupDayObserver::forceDeleted()` calls `GroupDayDeletedProcess::dispatch([...])` with an **array as a single argument** where six are expected - an `ArgumentCountError`. Unreachable today, because `GroupDay` has no `SoftDeletes` and therefore no `forceDelete()`. **Closed by TODO 10.2**, which deleted the job and the call.
  - Expected changes: none in `app/` - this TODO is evidence and documentation.

- [x] **TODO 10.2: Delete `GroupDayDeletedProcess`** - DONE
  - Context: commit `e3633a0b` ("TODO 10.1 fix") commented out the last two live statements in `GroupDayDeletedProcess::handle()` - `GroupDate::...->delete()` and `DayStat::...->delete()` - leaving the enclosing `foreach` with an empty body. **`GroupDayJobsTest::test_deleted_process_purges_group_dates_and_stats_for_the_weekday` failed as a direct result** and was the suite's only red test.
  - **Decision (the user's): delete the job rather than reconcile it with its test.** The condition was checked before acting, not assumed: its **only** reference in `app/` was `GroupDayObserver` (`deleted()` and `forceDeleted()`), which is not registered, so the job has never run in production and no serialized instance can be sitting in the `jobs` table either. Its work is done by the live `GroupDateHelper` -> `CalculateDateProcess` -> `CalculateDatesEvents` chain, which TODO 10.1 proved with `GroupDayTemplateCleanupTest`.
  - Delivered: `app/Jobs/GroupDayDeletedProcess.php` deleted; both dispatch calls removed from `GroupDayObserver`, each replaced by a comment saying where the work happens now; `GroupDayJobsTest` reduced to the two `GroupDayUpdatedProcess` tests; `SystemCauserJobsTest` lost its three job-based tests; `JobSerializationTest` covers 7 jobs instead of 8; `.docs/jobs.md`, `.docs/observers.md` and `.docs/notifications.md` updated.
  - **The `forceDeleted()` bug is gone as a side effect.** That method dispatched `GroupDayDeletedProcess::dispatch([...])` with an array as a single argument where six were expected - an `ArgumentCountError` that was only unreachable because `GroupDay` has no `SoftDeletes`. The loose end recorded in TODO 10.1 is closed.
  - **One piece of coverage had to be moved rather than dropped.** `SystemCauserJobsTest::test_a_deleted_causer_falls_back_to_the_system_name` was the only test for `causerNameFor()`'s "the user has since been deleted -> SYSTEM" branch, and it drove that branch through this job. It now drives it through `CalculateDatesEvents::generate()`, which reaches the same trait method on the path that actually runs in production.
  - **What survives and why:** `GroupDayUpdatedProcess` stays. It is dispatched from the same unregistered observer, but its `handle()` is a thin delegation to `CalculateDatesEvents::generate()` - live code - rather than a second implementation of it. `GroupDayObserver` also stays: TODO 10.1 established that its audit-history hooks are its one capability not duplicated elsewhere.
  - Expected changes: as delivered. Suite: **734 -> 725 tests, 2183 assertions, green** - seven tests removed from `GroupDayJobsTest`, three from `SystemCauserJobsTest`, one added back there for the moved coverage.

- [x] **TODO 11: Fill notification trigger coverage gaps** - DONE
  - **The list in this entry was stale, and by a wide margin.** It named 13 classes, then struck 2 in the TODO 07.2 follow-up and said "eleven classes remain". **Five remained.** The intervening TODOs had quietly closed the rest: `GroupPriorityMessageNotification` (`GroupMessagesTest`), `Newsletter` (`NewsletterAndGroupChangeCommandsTest`, TODO 06), `TestNotification` (`AdminSettingsTest`), and both anonymization notifications (`GdprCommandsTest`). `GroupPriorityMessageNotificationTest` has **no dispatch site anywhere**, so no trigger test is possible for it at all - **TODO 15 deleted it**, which is why the count of notifications is 25 from that point on. The lesson is the same one TODO 10.1 produced: a roadmap claim written early is evidence of nothing by the time it is executed.
  - **All five survivors lived in one component, `Groups\ListUsers`** - `FinishRegistration` (`createUser`), `UserProfileRenewalNotification` and `UserProfileRenewalAdminNotification` (`userRenewal`), `GroupParentGroupAttachedNotification` (`linkToGroup`) and `GroupParentGroupDetachedNotification` (`detachParentGroup`). So this was not eleven scattered tests but **two entirely uncovered features**: guest invitation/profile renewal, and the parent-child group link.
  - Delivered: new `tests/Feature/Groups/GroupUserInviteTest.php` (14 tests), new `tests/Feature/Groups/GroupHierarchyLinkTest.php` (17 tests), new `tests/Unit/Notifications/NotificationEnvFallbackTest.php` (14 tests); `.docs/notifications.md` gained a "Covered by" column and an "Environment dependencies" section. Suite: **689 -> 734 tests, 2197 assertions**. All 45 new tests pass; the one red test at the time was **pre-existing and unrelated**, and TODO 10.2 settled it.
  - **The biggest thing this uncovered was not a notification.** `linkToGroup()` does not merely set `parent_group_id`: it runs `$group->groupUsersAll()->sync()` against the *parent's* member list (`ListUsers:585-610`), so **anyone who was only in the child group is removed from it** and gets a `GroupUserLogoutNotification`. Linking two groups is a membership operation, and that was recorded nowhere.
  - Other behaviour now pinned: an unverified invitee gets only `FinishRegistration`, never also `GroupUserAddedNotification` (`GroupUserMoves` gates the second on `email_verified_at`); the renewal threshold is `last_activity < now() - gdpr.settings.ttl months + 14 days`, and below it nothing is written and nothing is sent; the admin-side renewal notice goes to `group->editors`, i.e. `roler` and `admin` only; and the invite field is split on `\R`, so CRLF from the browser works and blank lines are skipped.
  - **Two asymmetries recorded as characterization, not fixed:**
    1. `detachChildGroup()` breaks the *same* link as `detachParentGroup()` from the other side, but sends **no notification at all** - and uses a mass `update()`, so no model event fires and the `GroupObserver` audit record is missing too.
    2. Linking to a group that is itself a child produces **two** error messages, because `user_admin_groups()` already filters out child groups - so the user is also told, misleadingly, that they are not a member of it.
  - One more UX defect pinned rather than fixed: inviting into a **child group** hits a bare `return` (`ListUsers:93-95`) - no error, no modal feedback - so the admin believes the invitation went out. Members of a child group come from the parent, so the block itself is correct; only the silence is not.
  - **The `env()` work was measured, not assumed.** Six sites, in two severity classes: the four `Event*Notification` `replyTo()` calls and `UserRoleIsGroupCreatorNotification`'s `bcc()` read the *address itself* from `env()`, while `UserWillBeAnonymizeNotification` only puts `APP_NAME` in the body. A real send with the variable cleared produces `Swift_RfcComplianceException: Address in mailbox given [] does not comply with RFC 2822, 3.6.2.` - so the first class is a genuine hard failure, the second is cosmetic. The test asserts the exception class by *suffix*, because Phase 5 renames it to Symfony's `RfcComplianceException`. Note the failure is data-dependent: the fallback only applies when the group's own `replyTo` is blank.
  - **Control step.** Six of the negative assertions were verified to be non-vacuous by removing the production guard they measure: commenting out the child-group return in `createUser()`, the `continue` for existing members, and the renewal threshold made those three tests fail; forcing `linkToGroup()` past its error count and dropping the `whereNotNull('email_verified_at')` filters made another three fail. `test_detaching_the_same_link_from_the_parent_side_notifies_nobody` needs no such check - it asserts in the same test that the link really was broken, so it cannot pass without reaching the code.
  - Expected changes: none in `app/` - this TODO delivers tests and documentation only.

- [x] **TODO 11.1: Build the `detachParentGroup()` notification payload after the authorization check** - DONE
  - Found by TODO 11. `ListUsers::detachParentGroup()` (`:684-692`) assembles `$data` - including `auth()->user()->name` - **before** the `groupAdmins()` guard that would `abort(403)`. An unauthenticated call therefore fatals where it should return 403. Same family as the `getRole()` defect TODO 10 fixed: a missing precondition check at the head of the call chain.
  - Not an active hole: the method is in `$listeners` so it is reachable on its own, but the route carries the `groupMember` middleware. Deliberately left unfixed here because Phase 1 characterizes rather than fixes; `GroupHierarchyLinkTest` pins the current 403-for-members behaviour.
  - Delivered: the `$parent_group` / `$data` block moved below both `abort(403)` guards in `app/Http/Livewire/Groups/ListUsers.php` (now `:697-703`), plus one new regression test. Suite: **725 -> 726 tests, 2187 assertions, ~93s, green.**
  - **The move is bounded on both sides, and the lower bound matters as much as the upper one.** The payload must stay **above** `$group->update(['parent_group_id' => null])`: afterwards the `parentGroup` relation no longer resolves, so the mail would name a null parent. That constraint was already guarded by `GroupHierarchyLinkTest::test_the_detach_notification_still_names_the_former_parent`, which is why this change needed no new coverage on the happy path - only on the guard path. A code comment now records both bounds.
  - **The fix aligns the method with its own class rather than introducing new behaviour.** Every sibling starts with the same guard - `detachChildGroup()` (`:642`), `confirmParentDetach()` (`:663`), `setCopyInfo()` (`:845`). An audit of all `auth()->user()` reads in the file confirmed `detachParentGroup()` was the **only** one preceding its guard; `:140`, `:286`, `:305`, `:468`, `:555` and `:587` all sit behind an `abort(403)`.
  - **The reachable production path is not an expired session** - that fails CSRF first and Livewire 2 answers 419. It is a *live* session with no authenticated user: the user logs out in one tab and clicks detach on another tab's stale page. The Livewire message endpoint then calls the method directly, since it is in `$listeners`.
  - New test: `GroupHierarchyLinkTest::test_an_unauthenticated_call_is_rejected_with_403_not_a_fatal_error` - builds the component as an admin (which also sets `detachId` via `confirmParentDetach`), then drops the guest state with `$this->app['auth']->forgetGuards()` and calls the method. It asserts 403, that the link survives, and that nothing is sent. `forgetGuards()` is enough because `Livewire::actingAs()` only does `auth()->guard()->setUser()` (`LivewireManager.php:130-142`).
  - **Control step, run before the fix:** the new test errored with exactly `ErrorException: Attempt to read property "name" on null` at `ListUsers.php:687`. Livewire 2's `TestableLivewire` posts a real request under `withoutExceptionHandling([HttpException::class, AuthorizationException::class])` (`MakesHttpRequestsWrapper.php`), so `abort(403)` becomes a proper 403 **response** while every other exception is rethrown - which is what makes `assertForbidden()` a real discriminator here rather than a formality.
  - `.docs/` unchanged: `components.md:36` and `notifications.md:50` describe the trigger point and the notification, both of which are identical. Only the failure mode of an unauthenticated call changed, and that was documented nowhere.
  - **Audit of this TODO, three results.**
    1. **The 403 is guaranteed, not incidental.** The guard rests on `wherePivot('user_id', Auth::id())` counting zero for a guest - but with a null id Laravel rewrites that to `whereNull('group_user.user_id')`, which *could* match. The live schema says `group_user.user_id` is `NOT NULL`, so it cannot.
    2. **The lower bound is now measured, not asserted.** Moving the payload below `$group->update()` was tried in the working tree: `test_the_detach_notification_still_names_the_former_parent` fails, confirming the claim this entry makes above.
    3. **A coverage gap around the fix itself, closed.** The mail renders `userName` (`GroupParentGroupDetachedNotification:68-70`), but no test asserted it - so a null-safe "fix" (`auth()->user()?->name`) would have kept the suite green while sending an **empty name**, which is exactly the defect TODO 10 fixed in `EventObserver::deleted()`. The payload assertion now covers `userName`, with an `assertNotEmpty()` on the fixture name so it cannot go vacuous; verified to fail when the payload value is nulled. Suite: **726 tests, 2188 assertions**.
    - The audit's detector (first `auth()->user()->` read vs. first `abort(4xx)` per method, validated against the pre-fix file before being trusted) found no further instance in `app/Http/Livewire`, and exactly one elsewhere - now **TODO 11.2**.

- [x] **TODO 11.2: Guard the guest branch in `StaticPageController::render()`** - DONE
  - Found by the TODO 11.1 audit, which ran the same detector over all of `app/` instead of just the component that was being fixed: *first `auth()->user()->` read vs. first `abort(4xx)` per method*. The detector was validated against the pre-fix `detachParentGroup()` before being trusted, so its single remaining hit is evidence rather than noise.
  - `app/Http/Controllers/StaticPageController.php:15` reads `Auth::user()->can('is-admin')` on the **draft** branch (`status === 0`). `Auth::user()` is null for a guest, so this is a `Call to a member function can() on null` fatal - a 500 where 403 (or the `home-404` view) is due. Same family as TODO 10's `getRole()` and TODO 11.1's `detachParentGroup()`: a missing precondition at the head of the call chain.
  - **This one is on a fully public route, which the other two were not.** `routes/web.php:62` registers `/page/{slug}` with **no auth middleware at all**, so any visitor hitting a draft page's slug gets the fatal. `routes/web.php:57-59` is sharper still: `/` calls `StaticPageController::render('home')` behind the **`guest`** middleware, which *guarantees* `Auth::user()` is null - so if the `home` page is ever set to draft, the site root 500s for every visitor with no way to see why.
  - **Not theoretical: draft is the default state of every new page.** `Admin\StaticPageEdit:20` initialises `state['status'] = 0`, and the editor offers all of `0..3` (`:98`). The seeder ships `home` as status 2, `contact`/`terms` as 1 and `help` as 3 (`StaticPagesSetupSeeder`), and the current dev database holds no status-0 row - so the branch is dormant *today* and turns live the moment an admin creates a page.
  - No coverage exists: every test that touches this route uses the `home` slug on its seeded status (`RouteMiddlewareRegressionTest:13`, `SetLocaleTest:49`, `SetGuestLanguageTest:93`, `SetUserLastActivityTest:25`). The draft branch has never been executed by a test.
  - Needed:
    - Guard the call - `Auth::check() && Auth::user()->can('is-admin')` - so a guest on a draft page falls through to the existing `else`, i.e. `abort('403')`, or the `home-404` view for the `home` slug. Confirm that is the intended outcome for a draft page rather than a 404.
    - Cover all four statuses from both sides (guest / authenticated, admin / non-admin). That closes the last untested branch of a public controller and pins the status matrix, which `SetLocale`'s cached menu (TODO 09) already depends on from the other direction.
    - Minor, while in the file: `abort('403')` at `:29` and `:42` passes the status as a **string**. It survives on PHP's coercion into `HttpException`'s int parameter - fragile under the stricter typing later versions bring, and free to fix here.
  - Expected changes: `app/Http/Controllers/StaticPageController.php`, new `tests/Feature/StaticPageAccessTest.php`.
  - Delivered: the `Auth::check()` guard added, both `abort('403')` string literals turned into `abort(403)`, new `tests/Feature/StaticPageAccessTest.php` (9 tests), and a **status matrix** added to `.docs/routes.md` - the doc previously said only "status-based access logic", which is exactly the kind of file-name-level statement `AGENTS.md` asks contributors not to settle for. Suite: **726 -> 735 tests, 2208 assertions, green**.
  - **Control step:** the two regression tests were run before the fix and failed with `Error: Call to a member function can() on null` at `StaticPageController.php:15`. **The other seven passed unchanged**, which is the more informative half of the result: the rest of the matrix was already behaving correctly, so this fix is one guarded branch and not a behaviour rewrite.
  - **The matrix turned out to have a shape worth recording.** Status 2 is the only value where **logging in takes access away**, and the `home` slug never 403s or 404s - every rejecting branch renders `home-404` instead. So the site root now degrades to "page not found" rather than a 500 when home is left in draft, which is the correct outcome without any special-casing: the existing `else` already handled it.
  - `translator` gets no draft access - only the `is-admin` gate is consulted. Left as is: it matches `Admin\StaticPages`, where drafts are edited.

- [x] **TODO 12: Add end-to-end tests for the GDPR and setup flows** - DONE
  - Delivered: `tests/Feature/Gdpr/` (5 files, 42 tests) and `tests/Feature/Setup/` (6 files including the `SetupTestCase` infrastructure, 30 tests), plus a tightened `RouteAdditionalBehaviorRegressionTest`. `.docs/routes.md`, `.docs/commands.md` and `.docs/middleware.md` updated. Suite: **735 -> 813 tests, 2544 assertions, ~160s, green. No application code was changed.**
  - **The starting premise was wrong, and measuring first is what caught it.** This entry planned to fix a defect found by reading the code: `$gdprAnonymizableFields` opens with a keyless `'email'`, which the package's `parseValue()` turns into the literal string `email` against a `unique` column - so the second user in a daily batch would fail with `SQLSTATE[23000]` and GDPR retention would stop permanently after the first anonymization. **The mechanism is real but the application already defends against it**, in a place the audit had not reached: `User::getAnonymizedEmail()` (`:247`) hooks the trait's `getAnonymized{Column}` extension point and returns `Str::random(10)`. Verified by removing the method: the second anonymization then fails with exactly the predicted duplicate-key error. **Nothing needed fixing; the deliverable became evidence and documentation.**
  - **Two undocumented, load-bearing guards found - the main thing TODO 16 must not lose.**
    1. `User::getAnonymizedEmail()` is the only reason anonymized addresses are unique.
    2. `User::routeNotificationFor()` (`:310`) returns `null` for any anonymized user **and** for any address failing `FILTER_VALIDATE_EMAIL`. This is what stops mail from ever reaching an anonymized user - because the anonymized value is a bare 10-character token, not an address.
  - **The two anonymizers diverge, and the safer one loses.** `Dialect\Gdpr\Commands\AnonymizeInactiveUsers` (`gdpr:anonymizeInactiveUsers`, scheduled by the package's own provider at `00:00`) has **no role filter** and leaves group memberships intact; the project's `gdpr:anonymize-inactive` (`07:00`) skips `mainAdmin`/`groupCreator` and detaches memberships first. The package command runs seven hours earlier, so **an inactive `mainAdmin` is anonymized and demoted to `registered` before the project's protection ever runs**. Because it also keeps memberships, the anonymized user stays in `newsletters:send-due`'s recipient list - `User::userGroupsEditable()`/`userGroupsDeletable()` do not filter `isAnonymized`, while `Group::groupUsers()`/`users()` do. Only guard 2 above stops the mail. Pinned by `AnonymizeCommandDivergenceTest`.
  - **The tolerant test was hiding a broken page.** `RouteAdditionalBehaviorRegressionTest:141` asserted `assertContains($status, [200, 500])` for both `gdpr-terms` and `gdpr-download`, so it passed either way. Tightening it to `assertStatus(200)` immediately exposed that **`gdpr-terms` really does return 500**: the published view extends a `base` layout that does not exist here, and its body is still the package's Lorem ipsum. Combined with the unregistered `RedirectIfUnansweredTerms`, the consent feature is **unfinished, not regressed** - nothing links to the page and nothing drives users to it. Pinned by `ConsentTermsTest`, including a check that no other file references the route.
  - **Method note worth carrying forward: `Notification::fake()` hides `routeNotificationFor()`.** The fake records the *notifiable*, never resolving the address, so a faked assertion can "prove" a send that would never leave. The divergence test therefore measures the recipient list under a fake and the actual delivery without one.
  - **Setup: the group did not exist in tests at all.** `storage/app/installed.txt` is present in the working copy, so `routes/web.php:78` never registered the routes - which is also why the route-contract fixture has no `setup.` entry. The real sentinel is deliberately untouched: `storage/app/.gitignore` excludes everything, so an interrupted test that deleted it would leave the development app stuck in "not installed" with no way to restore it from git. `SetupTestCase` instead overrides `createApplication()` and calls `useStoragePath()` on a temporary directory **before** `bootstrap()`, then restores `view.compiled` to the real path so the Blade cache is not rebuilt (TODO 07 measured that cost: 50s -> 10 minutes). Both directions are proven - `SetupFlowTest` sees the routes, `SentinelGuardsTheInstallerTest` (on the normal `FeatureTestCase`) sees 404s and asserts the fixture agrees.
  - `SetupTestCase::withTemporaryEnvFile()` redirects `setEnvironment::setEnvironmentValue()` away from `.env.testing` - which is what `app()->environmentFilePath()` resolves to under `APP_ENV=testing`, so a naive test would rewrite its own configuration. **This helper is also the missing piece for the two `Admin\Settings` methods TODO 07 recorded as untestable.**
  - **Installer findings, all characterized:** no route in the group carries `auth`, a gate or a signature, so during the install window any visitor can create a `mainAdmin` - repeatedly, one per call - and anyone can close the installer early, because `setup.complete` writes the sentinel on a **GET**. `MailController::configure` is the only place the `languages`/`default_language` settings rows are ever created.
  - **`DatabaseController` was covered without ever running a migration.** Its success path executes `migrate:fresh` on submitted credentials and rewrites `.env`, so it is destructive by design and is deliberately left untested; the roadmap's planned control step for it (commenting out the guard) was **also skipped deliberately, because it would have wiped the test database**. What is covered is the valuable part: the `databaseHasData()` branch, which is the `getDoctrineSchemaManager()` call that Laravel 11 removes (TODO 66). A separate test asserts that call directly, so it fails exactly at the hop that breaks it.
  - `Handler`'s installer branch is covered with a temporary `QueryException`-throwing route. **Its other branch cannot be tested**: with the sentinel present the handler calls `dd()`, which exits and would kill the PHPUnit process. Recorded as its own item, TODO 12.1.
  - Expected changes: as delivered - tests and documentation only.

- [x] **TODO 12.1: Replace the two `dd()` calls in the exception handler** - DONE
  - Found by TODO 12. `app/Exceptions/Handler.php:52` and `:60` called `dd()` inside `renderable()` callbacks. On an installed site, **any** `QueryException` therefore printed the raw database message to the browser instead of an error page, and the message may disclose schema or SQL. The `MissingAppKeyException` branch did the same whenever `.env` already exists.
  - Delivered: both `else` branches return `null`, so the exception falls through to the framework's own handling (`renderViaCallbacks()` only accepts a non-null response, `Foundation/Exceptions/Handler.php:334-344`), which renders `errors/500`. **The installer branches are unchanged** - the sentinel-less redirect and the `.env` bootstrap copy both still work. New `tests/Feature/Setup/InstalledExceptionHandlerTest.php` (6 tests); `.docs/routes.md` updated. Suite: **813 -> 819 tests, 2554 assertions, green.**
  - **CORRECTION to this entry's own original claim.** It said the `dd()` meant "no error page, **no logging, no reporting**". The logging half was wrong: `Illuminate\Routing\Pipeline::handleException()` calls `report()` **before** `render()` (`:49-51`), and the empty `reportable()` callback returns `null` rather than `false`, which is the only value that would stop the default log stack (`Foundation/Exceptions/Handler.php:233-239`). Exceptions were being logged all along - what `dd()` hijacked was only the response. `test_the_exception_is_still_reported` now pins that, so the correction cannot silently rot.
  - The real cost of `dd()`, restated accurately: a raw `var_dump` reaches the browser **regardless of `APP_DEBUG`**; `exit` skips the rest of the request lifecycle (terminate middleware, session write, queued cookies); API clients receive an HTML dump; and it is untestable.
  - **Control step:** the new test file was run before the fix. PHPUnit printed one dot, then the raw message `"SQLSTATE[42S02]: Base table or view not found: nem_letezo_tabla (SQL: select * from nem_letezo_tabla)"`, and **the process exited with no summary line** - which is simultaneously the proof that the test measures the defect and the demonstration of why this branch could never be covered before.
  - `errors/500.blade.php` was checked for safety on this path before relying on it: `errors::illustrated-layout` inlines its CSS, calls no `mix()`, touches no database, and guards its "Go Home" link with `app('router')->has('home')`. It therefore renders during a database outage, which is exactly when it is needed. `.env.example` ships `APP_DEBUG=false`, so a real deployment gets that page rather than a debug trace.
  - The `MissingAppKeyException` test asserts `.env` exists **as a precondition**, so it can never fall into the branch that would create the file and regenerate a key in a developer's working copy.
  - Expected changes: as delivered.

- [x] **TODO 12.2: Make anonymization conditional on succession, not on role** - DONE
  - Delivered: new `app/Support/Gdpr/AnonymizationPolicy.php` and `app/Console/Commands/PackageAnonymizeInactiveUsers.php`; `Group::activeAdmins()`; the guard in `User::anonymize()`; both GDPR commands and `deletePersonalDataController` switched onto the policy; `pwbs_check_group_other_admins()` re-pointed at the new relation; new `tests/Feature/Gdpr/AnonymizationSuccessionTest.php` (26 tests); `AnonymizeCommandDivergenceTest` rewritten; `.docs/commands.md`, `.docs/models.md`, `.docs/routes.md` updated. Suite: **819 -> 848 tests, 2619 assertions, ~128s, green.**
  - **Control step:** the new test file was run before any application change - **16 failures and 2 errors**, and every one of them a succession case (the last `mainAdmin`, the sole group admin, the anonymized or pending "successor", the child-group rule, all three call paths). The cases that pass today are exactly the ones where nothing should block. That is the proof the tests measure the real gap and not the role list.
  - **Where the rule lives, and why.** `AnonymizationPolicy` is enforced from `User::anonymize()`, which overrides the trait method through a trait alias (`Anonymizable::anonymize as protected anonymizeAttributes`). Three code paths anonymize users - the project command, the package command and the profile page - so a rule in any one command is bypassed by the other two. `anonymize()` now returns `bool`; callers that need a consequence (a message, a skipped detach) ask the policy first.
  - **The `groupCreator` role stopped being a condition.** Creating a group makes the creator an `admin` of it (`ListGroups::createGroup()`), so rule 2 already covers every group they own. A `groupCreator` with no groups is now anonymizable - a deliberate behaviour change, pinned by `test_a_group_creator_without_groups_is_now_anonymizable`.
  - **A third quirk of `pwbs_check_group_other_admins()`, not in the original list: `Group::groupAdmins()` filters neither `isAnonymized` nor `accepted_at`.** So an already-anonymized row and an unaccepted invitation both counted as successors. This is not theoretical: the package command anonymizes but **leaves memberships in place**, so it manufactures exactly such rows - and a group's real admins could then be anonymized one after another, each one "handing over" to the previous ghost. New `Group::activeAdmins()` closes it; `groupAdmins()` itself is untouched, because its ten other call sites check the caller's own membership (`wherePivot('user_id', Auth::id())`). The tightening also applies to the group-leave and role-removal flows, which is correct and which **no existing test noticed** - their fixtures never built an anonymized or pending admin.
  - **The model guard alone was not enough, and measuring caught it.** The package's `handle()` runs `$user->anonymize()` **and then** `$user->update(['isAnonymized' => true])`. The guard stops the first call, not the second - so a protected user would keep all their data yet be flagged anonymized, which means disappearing from every group listing (`Group::groupUsers()`/`users()` filter the flag) and never receiving mail again (`User::routeNotificationFor()`). For a sole `mainAdmin` that is arguably worse than the anonymization it was meant to prevent. Fixed by `PackageAnonymizeInactiveUsers`, a project subclass with the same signature registered from `Kernel::$commands`; it wins because the package registers through an `Artisan::starting()` callback while `$commands` is resolved after the console application is built. `test_the_package_command_is_served_by_the_project_subclass` asserts that resolution order directly - it is exactly the kind of framework-internal path an 8 -> 13 hop disturbs.
  - **Full removal of the package command is decided in TODO 16 and executed in TODO 33.2.** `GdprServiceProvider::boot()` schedules it inside an `app->booted()` callback, which runs *after* `Kernel::schedule()`, so it cannot be filtered out of the schedule; only disabling auto-discovery would remove it. `schedule:list` still shows the same 13 entries and `SchedulerRegressionTest` is unchanged. What remains as divergence is the timing (00:00 vs 07:00) and the missing membership detach - both still pinned.
  - **CORRECTION, measured by TODO 16: there are five anonymization call paths, not three.** This entry, `.docs/commands.md` and `.docs/models.md` all counted the two commands plus the profile page. `app/Jobs/DeleteGroupDataProcess.php:73` (group deletion, anonymizes a member left with no other group) and `database/migrations/2024_12_01_223022_anonymize_old_data.php:20` (a one-off backfill) also call `anonymize()`. Both are covered by the model guard - which is the argument for its placement, only stronger than it was written.
  - **`gdpr:notify-anonymization` follows the same rule now.** Without it a `groupCreator` would be anonymized with no 15-day warning, and - the less obvious half - a *blocked* user would be mailed **every day**, because that query is a threshold (`last_activity <= ttl - 15 days`), not a window, and a blocked user never leaves it. The raw four-way join in `notifyGroupEditors()` cannot express the rule in SQL, so it post-filters with one extra model load; the window there is a single day, so the set is small.
  - **The profile path checks in both steps.** `asktodelete` decides before sending the signed link, `deletePersonalData` again when it is opened - the link is valid for 60 hours and the situation can change. A blocked request changes nothing at all (no mail, no anonymization, no detach, no logout) and flashes a `profile_message` naming what to do; `profile.blade.php:44` already renders that key. The test asserts on the group name inside the message rather than its mere presence, because the `profileFull` middleware uses the same key for the same redirect.
  - Two new language keys (`user.delete.no_successor_admin`, `no_successor_group`) in `hu` and `en`; the fallback locale is `en`, so the remaining 23 languages can be filled in through the translation UI.
  - Original specification, unchanged:
  - **A product decision by the user**, prompted by the TODO 12 findings. Today the rule is a blunt role list: `gdpr:anonymize-inactive` skips `mainAdmin` and `groupCreator` outright (`whereNotIn('role', [...])`) and lets every group admin through without any check. The intended rule is about **succession** - whether somebody is left to take over - not about which role the user holds.
  - Rules to implement:
    1. **A `mainAdmin` may be anonymized only if at least one other `mainAdmin` remains who is not anonymized.** The last one must never be anonymized; the site would be left with no administrator, and `role` is in `$gdprAnonymizableFields`, so anonymization also demotes them to `registered`.
    2. **A `groupCreator`, and any user holding `group_role = 'admin'` in a group, may be anonymized only if each of their groups has somebody to hand over to.** This is the **same condition the group "leave" function already enforces**: `pwbs_check_group_other_admins()` (`app/Helpers/helpers.php:24`), called from `Groups\ListGroups::confirmLogout()` (`:164`) and `logoutConfirmed()` (`:181`). Reuse that helper rather than writing a second rule - the two must not be able to drift apart.
  - Notes for whoever implements it, all established by TODO 12:
    - **The rules must not live only in the project's command.** `Dialect\Gdpr\Commands\AnonymizeInactiveUsers` is scheduled by the package's own service provider at `00:00`, seven hours earlier, with **no filtering at all** - so any rule added to `gdpr:anonymize-inactive` is bypassed nightly. The package command has to be unscheduled (or the check moved into `User::anonymize()`) or the work is cosmetic. Ties into TODO 16.
    - **The user-initiated path needs the same treatment.** `deletePersonalDataController::deletePersonalData()` calls `$user->anonymize()` directly, so today a sole `mainAdmin` can anonymize themselves through the profile page. If a user is blocked, they need a message explaining what to do (hand over the group, appoint another admin), not a silent skip - the GDPR request must not simply vanish.
    - `pwbs_check_group_other_admins()` has two known quirks (TODO 07.2): it walks child groups and demands an admin covering **every** group, so a second admin present only in the parent does not unblock; and its `function_exists()` guard checks the wrong name, which TODO 33 fixes. Both are worth settling before this rule leans on the helper harder.
    - Placing the check inside `User::anonymize()` (overriding the trait method) would cover every path at once - the two commands and the profile page - and would survive the package replacement. Weigh that against keeping the model free of policy.
  - Expected changes: `app/Console/Commands/AnonymizeInactiveUsers.php`, `app/Models/User.php`, `app/Http/Controllers/deletePersonalDataController.php`, `app/Console/Kernel.php` (unscheduling the package command), new tests under `tests/Feature/Gdpr/`, and updates to `.docs/commands.md`. The existing `AnonymizeCommandDivergenceTest` encodes today's behaviour and will need rewriting in the same change set.

- [x] **TODO 13: Add round-trip tests for all encrypted cast columns** - DONE
  - Delivered: new `tests/Feature/Models/EncryptedAttributeTest.php` (96 tests through a 9-row data provider) and `EncryptedColumnSchemaTest.php` (3 tests, 54 assertions); the overlapping `ModelFactoryTest` case narrowed to what it is actually about; `.docs/models.md` gained a dedicated encrypted-columns section. Suite: **848 -> 947 tests, 2841 assertions, ~134s, green. No application code was changed.**
  - The provider list doubles as an assertion: `test_every_encrypted_cast_is_covered` walks the six models' `getCasts()` and fails if an `encrypted` column is added without being listed. Same shape as TODO 04's factory list. Providers are **static**, as PHPUnit 11 requires.
  - **CORRECTION to this entry's own planning assumption - the ciphertext is smaller than expected, and that changes which claim is worth making.** The plan asserted that any encrypted value overflows a `varchar(255)`. Measured, a 5-character input produces a **200-character** payload, which still fits. The real thresholds: a `varchar(255)` holds roughly **30 characters** of plain text, and a 100-character value already passes 255. The sharper fact is about `events.comment`, which was `varchar(100)` before its `->change()`: the MAC alone is 64 characters, so that column **could not have held a single encrypted value**. Both bounds are now pinned rather than the vague "too small".
  - **CORRECTION to the pre-work code reading on the pivot path.** The plan predicted that a newly attached `group_user.note` would bypass the cast, because `attachUsingCustomClass()` ends in `Pivot::fromRawAttributes()` -> `setRawAttributes()`. **Measured: it encrypts.** The step the reading missed is `formatAttachRecord()` -> **`castAttributes()`** (`InteractsWithPivotTable:656`), which pushes the attach payload through `newPivot()->fill()` - but **only when the relation declares `using()`**:
    ```php
    return $this->using ? $this->newPivot()->fill($attributes)->getAttributes() : $attributes;
    ```
    So there is no attach/update asymmetry, and `GroupUserMoves::attach()` is safe. **The load-bearing detail is `using()` itself**, which was nowhere recorded. `Group::groupUsers()`, `Group::groupUsersAll()` and `User::userGroups()` declare it; **`User::groupsAcceptedFiltered()` does not**, so `note` read through that relation comes back as raw base64. Harmless today - no caller reads it there - but a *writing* relation losing `using()` would put plain text into the column with no symptom. Pinned as a pivot-class matrix plus a behavioural test. **No TODO 13.1 is needed**; this ties into the TODO 29/34 pivot work instead.
  - **The schema test is the actual deliverable for TODO 32 and TODO 66.** It reads `information_schema.COLUMNS` and pins type, nullability, character maximum and charset for all nine columns, measured from `kozter_testing` rather than assumed. Four of the nine sit behind a `->change()` migration (`groups.name`, `events.comment`, `group_posters.info`, `group_user.note`) - exactly what Laravel 11's native `change()` truncates by dropping every attribute not re-declared. `groups.name` and `group_posters.info` are **NOT NULL**, which the behavioural suite also checks live by expecting a `QueryException` on a null write.
  - **Behaviour now pinned that was previously undocumented:** the encryption is non-deterministic, so `where()` and `assertDatabaseHas()` can never match on these columns - which is why duplicate group names are possible and why `users.name_index` exists as the only sortable proxy; `null` bypasses the cast in both directions while `''` does not (it is encrypted and read back as `''`); a query-builder write makes the column unreadable, with `DecryptException` on the next model read - the guard rail under every future data migration, and the reason `NotifyUpcomingAnonymization` decrypts by hand off its raw join; and a **wrong `APP_KEY` fails loudly**, not silently. That last one refines this roadmap's own "silently data-destructive" phrasing: the read is loud, the danger is overwriting the row afterwards.
  - Expected changes: as delivered - tests and documentation only.

- [x] **TODO 14: Harden the route contract snapshot** - DONE
  - Delivered on 2026-08-06. `tests/Feature/RouteContractSnapshotTest.php` grew from 1 test to 10; new `tests/Fixtures/vendor-route-contracts.json`; `.docs/routes.md` and `.docs/fortify-routes.md` corrected. Suite: **947 -> 956 tests, 2868 assertions, ~180s, green. No application code was changed.**
  - **Deviation from the plan, deliberately.** The duplicate-name winners are **not** encoded in `route-contracts.json`; they are named test methods with inline expectations. The existing fixture is a *union by name* - merging every definition of a name into one row - which is structurally incapable of expressing "two routes, different middleware". Widening its shape would also have broken `SentinelGuardsTheInstallerTest::test_the_route_contract_fixture_agrees`, which iterates it as a flat array. Named tests also read better as the thing TODO 26 must deliberately rewrite.
  - **`verification.verify`: the Fortify definition wins, and the reason is service-provider order alone.** `RouteCollection::addToCollections()` keys routes by `method + domain + uri`; both definitions are `GET /email/verify/{id}/{hash}`, so the later registration silently overwrites the earlier. `config/app.php:180` lists `RouteServiceProvider` (which loads `routes/web.php`) **before** `:184` `FortifyServiceProvider` (which loads `routes/fortify.php` from `configureRoutes()`), so Fortify registers last. Effective stack: `web, auth:web, signed, throttle:6,1`. **This is the entry's real upgrade value:** TODO 56 moves providers into `bootstrap/providers.php`, and a reordering there would silently flip the route onto the dead closure. `test_the_winner_depends_on_the_service_provider_boot_order` asserts the ordering directly, so that flip cannot happen unnoticed.
  - **The dead closure was proven dead, not merely assumed.** As a one-off check the `routes/web.php:119-122` closure was commented out and the suite re-run: `test_named_route_contracts_match_the_snapshot` **stayed green** while only the two new duplicate-guards failed. TODO 26 can therefore delete it with **zero** runtime change, and the roadmap's earlier "confirm the Fortify middleware stack is the intended one first" is now the only remaining question there.
  - **`password.confirm`: the union hid a real asymmetry.** The fixture row reads `GET, HEAD, POST` + `auth, throttle:6,1, web`, which suggests the throttle applies throughout. It does not: the GET view route (`web.php:126`) carries only `auth, web`; `throttle:6,1` is on the POST route (`:130`) alone. Both survive (different method -> different key), and URL generation resolves the name to the **POST** definition, because `RouteServiceProvider` rebuilds `nameList` from `allRoutes` in an `app->booted()` callback (`refreshNameLookups()`), so insertion order decides. Both facts are now separate named tests.
  - **The duplicate set is guarded at source level, not just at runtime.** `sourceRouteNameCounts()` tokenizes `routes/web.php`, `routes/fortify.php` and `routes/api.php` with `token_get_all()` and counts `->name('...')` calls. A tokenizer rather than a regex, because the commented-out Fortify `verification.notice` (`fortify.php:80-85`) must **not** count - comments are their own tokens, so it falls out for free. `test_the_route_files_contain_exactly_the_known_duplicate_names` pinned the result to exactly `['password.confirm' => 2, 'verification.verify' => 2]`, so TODO 26 had to edit it, and a *new* duplicate would also fail it. (Since TODO 26 it is `test_the_route_files_contain_no_duplicate_names` and expects `[]`.)
    - **Trap recorded for whoever touches this next:** the source scanner must never be compared to the fixture by count. `setup.*` routes sit behind `web.php:78`'s `if (!Storage::exists('installed.txt'))`, so they exist in the source but not in the runtime snapshot. Duplicate detection is unaffected (those names are unique). A companion test (`test_the_route_files_use_no_group_level_name_prefixes`) keeps the scanner's flat-namespace assumption valid by rejecting `Route::name(` and `'as' =>`.
  - **Livewire vendor routes are now snapshotted; Debugbar deliberately is not.** The 3 named routes went into their own fixture, keeping the app fixture regenerable on its own. Measured detail the baseline `route:list` column truncation had hidden: **`livewire.upload-file` carries `throttle:60,1`**, the other two only `web`. Debugbar stays excluded because TODO 25 removes its hard registration from `config/app.php` - snapshotting it would make an unrelated TODO rewrite this fixture.
    - **A limit worth knowing before Phase 6:** `livewire/livewire.js` and `livewire/livewire.js.map` are **unnamed**, so no name-keyed snapshot can ever cover them, even though Livewire 3 changes them too. Recorded in `.docs/routes.md`.
  - `test_every_named_route_is_accounted_for` closes the loop: every named route must appear in one of the two fixtures or carry the `debugbar.` prefix. Today that is 78 named routes = 70 app + 3 Livewire + 5 Debugbar, and a package update sneaking in a new named route now fails the suite instead of hiding behind the prefix filter.
  - **A documentation error found and fixed:** `.docs/routes.md:155` described `verification.verify` as an "Inline closure using `EmailVerificationRequest`" - the definition that never runs. Both route docs now carry the measured precedence.
  - Expected changes: as delivered - tests, one new fixture, and documentation only.

- [x] **TODO 15: Remove the production class with a `Test` suffix** - DONE
  - Delivered on 2026-08-06. `app/Notifications/GroupPriorityMessageNotificationTest.php` **deleted**; new `tests/Unit/ApplicationNamingConventionTest.php`; `NotificationRegressionTest` lost its provider row and gained a completeness guard; `.docs/notifications.md` corrected. Suite: **956 -> 957 tests, 2867 assertions, ~155s, green.** This closes Phase 1.
  - **CORRECTION to this entry's own instruction: deleted, not renamed.** The class was an untouched `make:notification` stub - generic English placeholder text, an empty `__construct()`, an unused `ShouldQueue` import, an empty `toArray()`. `git log` places its birth in commit **60dc8382 "New: Message Board functionality"**, the same commit that added the real `GroupPriorityMessageNotification`, i.e. the generator was run twice by accident. It was never touched again, and it has **no dispatch site anywhere**. There is nothing to preserve and no meaningful name to rename it to - the meaningful one is taken by the real class.
  - **CORRECTION to the risk claim** (repeated at the baseline list, `Code-level upgrade risks`). "Some PHPUnit discovery configurations will pick it up" is not true of this project today: `phpunit.xml` scans only `./tests/Unit` and `./tests/Feature` with `suffix="Test.php"`, so a file under `app/` is never discovered and the class was **inert**. The real exposure was **TODO 40**, which rewrites `phpunit.xml` for the PHPUnit 10 schema (where `processUncoveredFiles="true"` - today's reason every `app/*.php` gets loaded during a coverage run - is removed); PHPUnit 10+ errors on a class the suite picks up that does not extend `TestCase`. The secondary cost was plain confusion: the name reads as "the test for `GroupPriorityMessageNotification`", while that test actually lives in `NotificationRegressionTest`.
  - **Two guards added, both in this suite's established "the list is the assertion" shape** (TODO 04's factory list, TODO 13's cast list):
    - `NotificationRegressionTest::test_every_notification_class_has_a_mail_contract` reads `app/Notifications/*.php` and compares it to the data provider's keys, so a new notification cannot land without a mail contract. The provider is now 25 rows.
    - `tests/Unit/ApplicationNamingConventionTest.php` walks all of `app/` and rejects any `*Test.php`. It lives in its own file because the rule is application-wide, not notification-specific. Both were verified against a temporary `app/Notifications/FooTest.php`, which failed them for the two distinct reasons (the name, and the missing provider row) before being removed.
  - **Three stale claims found in `.docs/notifications.md` and fixed:** the `TestNotification` row still said "the setup-flow dispatch site is not covered, it belongs to roadmap TODO 12" - TODO 12 closed it, `tests/Feature/Setup/SetupMailTest.php:75` asserts it; the "Two kinds of coverage" section still promised "two named exceptions listed under Utility" - with TODO 12 done and this class gone, **there are now zero exceptions**, every notification has both a mail contract and a dispatch trigger test; and the deleted class had its own catalog row.
  - **A finding for TODO 40, recorded there:** the roadmap claimed two non-static `@dataProvider` methods. There are **six**.
  - Expected changes: as delivered - one deletion, two test files, documentation.

---

## Phase 2 - Dependency Decisions

Every blocker gets its own assess-then-decide pair. The **decision** is recorded here; the **execution** happens in the phase where the package first blocks (Phase 8 for Laravel 11 blockers, Phase 10 for Laravel 13 blockers). See Appendix A for the verified compatibility matrix.

**Check the constraint before assuming a package blocks anything.** A package with an *unbounded* framework constraint never fails a Composer resolution - it installs on Laravel 13 and would only misbehave at runtime. When that is the case and the replacement is framework-neutral, the execution should be pulled **forward** into Phase 3 rather than left in a hop, so the hop carries one dependency less. TODO 16 is the worked example, executed as TODO 33.2. **TODO 17 is the second and TODO 18 the third, and all three found the same misclassification** - three of the four packages this roadmap called "blockers" declare no framework constraint that Composer can fail on. Read the vendor `composer.json` **of the version actually installed**, not the Packagist support badge of the latest release, before writing "Blocks Phase N". TODO 18 is the sharpest case: the badge describes 1.0.3.4, while the pinned 1.0.2 requires nothing but `php >=5.4.0`.

**And the rule cuts both ways - TODO 19 proved it.** The fourth package was misclassified in the **opposite** direction: `protonemedia/laravel-verify-new-email` 1.6.0 requires `illuminate/support: ^8.67 || ^9.0`, a real upper bound, so it fails **five phases earlier** than the "Blocks Phase 10" this roadmap recorded - at Phase 5. So the rule is not "the blockers do not block". It is: **read the installed version's `composer.json`, and read both ends of the constraint.** Note also what that measurement did *not* change: the `composer.json` constraint (`^1.6`) already admits every later 1.x, so an early failure of this kind can be a lock bump rather than a decision. Check the declared range before pricing options.

**And there is a third shape - though the example this paragraph used to give was wrong, which is its own lesson.** It read: *"`rakibdevs/openweather-laravel-api` 1.9.0 declares `php: ^7.2|^7.3|^7.4|^8.0`, and Laravel 10 requires `php ^8.1`, so it fails at Phase 5 on PHP."* **TODO 22 re-measured it and that is false.** Composer's `^8.0` means `>=8.0.0 <9.0.0`, so it admits 8.1, 8.3 and 8.4; the pipe-separated list reads as an enumeration but is not one. The package therefore blocks **nothing at all** - no framework constraint, no PHP ceiling below 9 - which puts it back in the TODO 16/17 category: it would resolve silently all the way to Laravel 13 and misbehave only at runtime. **Read a caret as a range, not as a version.**

**The third shape is real all the same, and TODO 22 found the honest example: the breaking change need not be in the package you are bumping.** `laravolt/avatar` has a normal bounded constraint (`illuminate ^6-^9`), so it fails visibly at Phase 5. What the constraint does not say is that every Laravel-12/13-capable line of it requires `intervention/image ^3.4` or `^4.0`, and that the API the project calls disappeared between Intervention 2 and 3. The full rule is therefore: read the installed version's `composer.json`, read **both ends** of each constraint as ranges, read **every** requirement in it - and when a bump crosses a major of a *transitive* library, look at what the project calls through it.

For each package, "assess" means: list every API the project actually consumes, then price out three options - **fork/vendor into the project**, **replace with an alternative or in-house code**, or **drop the feature**.

- [x] **TODO 16: Assess and decide - `dialect/laravel-gdpr-compliance`** - DONE
  - Delivered on 2026-08-06. **Decision: replace the package with in-house code and remove it; drop the consent half.** The execution is **not** Phase 8 work - see the first correction - and is recorded as the new **TODO 33.2** at the end of Phase 3. Suite untouched: **957 tests, 2867 assertions, green.** The only files changed outside this roadmap are `.docs/commands.md` and `.docs/models.md`, which carried a measured undercount (see below).

  - **The three required options, priced:**

    | Option | Cost | Verdict |
    | --- | --- | --- |
    | Fork / vendor the package | The upstream repository has been dead since 2020-01-06. A fork means maintaining the same 158 lines *plus* a repository and a `repositories` entry in `composer.json`, and it keeps the split-brain structure below | rejected |
    | **Replace with in-house code** | Move 158 lines (two traits + one form request) into `app/`, re-register one route, delete three, drop the package | **chosen** |
    | Drop the feature | Not available for the export (GDPR art. 20) or the anonymization - both are legal obligations. **Available for the consent half**, which has never worked | partially taken |

  - **CORRECTION to this roadmap's own classification: the package blocks nothing, and never will.** Both this entry and Appendix A called it "Blocks Phase 8". `composer.lock:351` confirms the requirement is `illuminate/support: >=5.5` with **no upper bound**, so Composer cannot fail on it at any hop, Laravel 11 or 13. It is not a blocker but a *silent* risk: a failure would appear at runtime, not at resolution. The practical consequence is the reason for the reschedule - **nothing forces this into Phase 8**, and the replacement code is framework-neutral, so it can be written and proven green on Laravel 8 today.
  - **CORRECTION to "its code silently breaks".** Not demonstrable. Every framework API the package touches - `Str::studly()`, `loadMissing()`, `setVisible()`/`setHidden()`, `getRelations()`, `Route::group(['namespace' => ...])`, `app()->getNamespace()`, `$this->app->booted()`, `Schedule::command()` - still exists in Laravel 13, and the traits read their `$gdpr*` properties through Eloquent's `__get()`, so PHP 8.2's dynamic-property deprecation does not reach them either. The case for removal rests on the structural findings below, not on a predicted break.

  - **The vendor surface is 158 lines, because four of the package's six publishable pieces already live in the project.** Measured, not assumed:

    | Piece | Where it lives today | Still vendor? |
    | --- | --- | --- |
    | `GdprController` | `app/Http/Controllers/GdprController.php` - byte-identical to the package copy | no |
    | `RedirectIfUnansweredTerms` | `app/Http/Middleware/` - never registered | no |
    | `gdpr/message.blade.php` | `resources/views/gdpr/` - `@extends('base')`, 500 | no |
    | `config/gdpr.php` | `config/` | only the `mergeConfigFrom` default |
    | `add_gdpr_to_users_table` | `database/migrations/2022_02_14_210008_*` | no |
    | `Dialect\Gdpr\Portable` | vendor, **41 lines** | **yes** |
    | `Dialect\Gdpr\Anonymizable` | vendor, **87 lines** | **yes** |
    | `Dialect\Gdpr\Http\Requests\GdprDownload` | vendor, **30 lines** (`password` => `required|string`) | **yes** |
    | `GdprServiceProvider` | vendor - 4 routes, command registration, the 00:00 schedule | **yes**, but see below |
    | `Dialect\Gdpr\Commands\AnonymizeInactiveUsers` | vendor - already overridden by a project subclass (TODO 12.2) | replaced already |
    | `Dialect\Gdpr\EncryptsAttributes` (101 lines) | vendor - **zero usage**, the project uses the native `encrypted` cast | no |
    | `Dialect\Gdpr\console\Kernel` (22 lines) | vendor - never referenced, a documentation example | no |

    So what the project still consumes from `vendor/` is two traits, one form request, and a route group. `diff` confirms the published controller is byte-identical to the vendor copy, and `php81 artisan route:list --name=gdpr` confirms all four routes already dispatch to `App\Http\Controllers\GdprController`, with `web` + `Authenticate`.

  - **The real reasons to remove it:**
    1. **Split responsibility.** The published controller imports a *vendor* form request. Half the feature is in `app/`, half in `vendor/`, and neither half is complete on its own.
    2. **A framework-internal workaround is holding the guard in place.** The package schedules its own command from `GdprServiceProvider::boot()` inside an `app->booted()` callback - after `Kernel::schedule()`, so it cannot be filtered out. `schedule:list` still shows it as the 13th entry at `0 0 * * *`. TODO 12.2 had to neutralize it with a same-named subclass registered from `Kernel::$commands`, which wins **only** because `Kernel::getArtisan()` resolves `$commands` after the `Artisan::starting()` callbacks. That ordering is exactly the kind of internal path an 8 -> 13 hop disturbs, and it is currently load-bearing for a data-protection guarantee.
    3. **The divergence that survived TODO 12.2**: the package command still does not detach group memberships and still runs seven hours earlier, which is how an anonymized user stays on the newsletter recipient list.
    4. **The consent half is dead code** - `gdpr-terms` returns 500, the middleware is unregistered, nothing links to either.

  - **The anonymization call paths were undercounted: there are five, not three.** TODO 12.2, `.docs/commands.md:39` and `.docs/models.md:19` all say three (the two commands and the profile page). A `grep` for `->anonymize(` finds two more:
    - **`app/Jobs/DeleteGroupDataProcess.php:73`** - group deletion anonymizes a member who is left with no other group. This is a live job (TODO 05 confirmed it), so the succession guard is reached from the group-deletion flow too. It happens inside `GroupUser::withoutEvents()`, and the return value is discarded, so a blocked user is silently kept - which is the correct outcome, but it was never a considered one.
    - **`database/migrations/2024_12_01_223022_anonymize_old_data.php:20`** - a one-off backfill that re-anonymizes every `isAnonymized = 1` row. It runs on every `RefreshDatabase` test boot as well (harmlessly, on an empty table). TODO 32 squashes it away.

    Both docs were corrected to five in this change set. **This is the load-bearing fact for the replacement**: the guard must stay on `User::anonymize()`, because five callers is already more than any single command-level rule could cover.

  - **Three defects in the vendor traits, found by reading them for the replacement.** None of them bites today, and the reason each one does not bite is a constraint the replacement must not lose:
    1. **The recursion guard does not work.** `Anonymizable::anonymize()` pushes relation names as *values* (`array_push($modelChecker, $relationName)`) but tests them as *keys* (`array_key_exists($relationName, $modelChecker)`), so the check is never true. The only thing stopping unbounded recursion is that `Group` and `Event` declare no `$gdprWith` - the cascade is exactly one level deep.
    2. **`parseValue()` cannot handle a closure.** Its closure branch is `\call_user_func($item())` - it invokes the closure and then tries to call the *result* as a callable. Any closure in `$gdprAnonymizableFields` would fatal. The project uses none.
    3. **`Portable::setVisible()` is dead here.** No model declares `$gdprVisible`.

    Also worth carrying forward: **`Group` and `Event` carry `Anonymizable` with an empty `$gdprAnonymizableFields` purely so the recursion has a method to call.** Remove the trait from either and `User::anonymize()` fatals with a `BadMethodCallException`. That is not documented anywhere, and it reads like dead code.

  - **What the replacement must reproduce** (the acceptance criteria are the 68 existing tests in `tests/Feature/Gdpr/`):
    - `User::getAnonymizedEmail()` - the `getAnonymized{Column}` extension point. Without it, the keyless `'email'` entry writes the literal string `email` into a `unique` column and the **second** user in a daily batch fails with `SQLSTATE[23000]`, stopping GDPR retention permanently (TODO 12 proved this by removing the method).
    - `User::routeNotificationFor()` returning `null` for anonymized users - the only thing that keeps mail away from them.
    - `Portable::portable()` calls `setHidden()`, which **replaces** the model's `$hidden` rather than extending it, so `language`, `created_at`, `updated_at` and `isAnonymized` appear in the export. Pinned by `DataExportTest::test_the_export_reveals_fields_the_normal_api_hides`.
    - The two distinct rejections on the download: a missing password is a **302 + validation error** (the form request), a wrong one is a **403** (`abort_unless` in the controller).
    - `null` values bypassing the cast in `parseValue()`, and the one-level cascade shape above.

  - **The consent half is dropped** (a decision by the user). The three consent routes, the three controller methods, the published view and the unregistered middleware go; `users.accepted_gdpr` stays as a column so no data migration is needed. The `spatie/laravel-cookie-consent` banner is a different feature and is unaffected. If consent is ever wanted as a real feature, it should be specified on its own, not resurrected from a package's Lorem ipsum.

  - **Effort estimate: one focused change set, comparable to TODO 12.2 and smaller than TODO 07.** The breakdown, all of it in TODO 33.2:
    - ~158 lines moved into `app/` (2 traits + 1 form request), plus a ~10-line route registration keeping `config('gdpr.uri')` and `config('gdpr.middleware')`.
    - 6 imports re-pointed (`User`, `Group`, `Event`, `GdprController`, and the two anonymization commands), 1 `composer.json` line removed, 1 subclass and 1 `Kernel::$commands` entry deleted.
    - Test churn, all mechanical and all already located: `ConsentTermsTest` (6) and `UnansweredTermsMiddlewareTest` (4) deleted; `AnonymizeCommandDivergenceTest` (9) largely moot once there is one command; `SchedulerRegressionTest` 13 -> 12 entries; `route-contracts.json` 70 -> 67 app routes and `test_every_named_route_is_accounted_for` 78 -> 75.
    - 4 `.docs/` files updated in the same change set, per `AGENTS.md`.
    - The risk is low precisely because the 68 GDPR tests were written against *behaviour*, not against the package - only two of them (`ConsentTermsTest`, `UnansweredTermsMiddlewareTest`) name the package's own structure.
  - **Re-check before executing:** whether a maintained fork or alternative appeared on Packagist since this assessment. It would not change the decision - the in-house code is smaller than the integration cost of any package - but it belongs in the same re-check discipline as TODO 22.
  - Expected changes: as delivered - the decision recorded here, the new TODO 33.2, and the Appendix A / TODO 58 / TODO 12.2 pointers updated, plus the two `.docs` undercount corrections.

- [x] **TODO 17: Assess and decide - `joedixon/laravel-translation`** - DONE
  - Delivered on 2026-08-07. **Decision: remove the package now and replace its UI with an in-house Livewire editor; adopt `elegantly/laravel-translator` later as the engine underneath it.** The removal is **not** Phase 8 work - see the first correction - and is recorded as the new **TODO 33.3** at the end of Phase 3; the engine adoption is the new **TODO 66.1** in Phase 10. Suite untouched: **957 tests, 2867 assertions, green.** The only files changed outside this roadmap are `.docs/routes.md` and `.docs/components.md`, both of which under-documented this integration (see below).

  - **The three required options, priced:**

    | Option | Cost | Verdict |
    | --- | --- | --- |
    | Fork / vendor the package | ~1100 lines of actually-consumed PHP (`File` driver 369, `DriverInterface` 128, abstract `Translation` 101, provider 181, 2 controllers 117, manager 47, `Scanner` 62, requests/rules 96) **plus** a Vue 2 + Tailwind 0.6 front-end, in a repository the project would then own. Upstream is dead since 2020-04-13. **Not rejected for incompatibility - see below, a Laravel 13 fork is about half a day's PHP work** | rejected |
    | **Replace with in-house code** | ~350-450 lines: one `LangFiles` repository plus growing the existing 18-line `Admin\Translation` shim into a real editor. Zero new runtime dependencies, framework-neutral, provable green on Laravel 8 today | **chosen** |
    | Drop the feature | Not available. The `translator` role, the `/admin/translate` page and 22 locales depend on it, and the `var_export()` shape of `resources/lang/en/*.php` proves the editor has actually been used to write them | rejected |

  - **Why the fork was rejected, stated precisely, because "unmaintained" is not the same as "incompatible".** A fork **could** be made Laravel 13 compatible, and the PHP side is roughly half a day. The Laravel 13 upgrade guide touches **nothing** the driver depends on - no change to the `path.lang` binding, `Application::langPath()`, translation loader registration or `Illuminate\Translation\Translator` - and the driver's actual work is `Filesystem` plus `Arr::dot`/`Arr::set` and `Str::before`/`after`, all stable API. That is why it installs on Laravel 13 today. The full list of what a fork would have to fix:
    1. Delete `src/InterfaceDatabaseLoader.php` - it implements `Illuminate\Translation\LoaderInterface`, removed in **Laravel 5.4**; only an `interface_exists` guard in `TranslationBindingsServiceProvider.php:48` keeps it from fataling.
    2. Delete `database/factories/` - Laravel 5-style `$factory->define()`, and not autoloaded anyway (the PSR-4 map covers `src` only). Dead already.
    3. Convert the 7 string route actions to `[Controller::class, 'method']`. The `namespace` group attribute still works, so this removes doubt rather than a break.
    4. `src/Rules/LanguageNotExists.php`: `Illuminate\Contracts\Validation\Rule` -> `ValidationRule`. Deprecated since Laravel 10 but **still present in 13** and absent from the 13 removal list, so not even urgent.
    5. Drop the 5 global helpers in `resources/helpers.php`, `str_before()` above all - it redefines a helper Laravel dropped in 6. **Laravel 13 adds `symfony/polyfill-php85`, which defines global `array_first()` / `array_last()`, and the upgrade guide warns explicitly about this class of collision with legacy global-helper packages.** Checked while assessing: the project's own `app/Helpers/helpers.php` is entirely `pwbs_`-prefixed, so the app itself is clear.
    6. Optionally delete `TranslationBindingsServiceProvider` and the database driver. That provider is the one piece that swaps a framework core singleton (`translator`, `translation.loader`), and it is inert here because `driver => 'file'`.
  - **The fork loses on cost, not on compatibility:**
    1. **The front-end is the real liability and a fork inherits it.** Vue 2 (EOL 2023-12-31), axios 0.18, Tailwind 0.6 (2018), laravel-mix 4, shipped as a 302 KB CSS and 114 KB JS prebuilt bundle. Nothing about Laravel 13 breaks it - static assets keep working - but nobody will ever modernize it, and doing so costs more than writing the editor.
    2. **~1100 lines owned to keep a UI that is being replaced anyway**, and it keeps a *second* UI stack (Tailwind/Vue) inside a Bootstrap/AdminLTE/Livewire application. The in-house editor is ~350-450 lines precisely because it reuses the layout, `AppComponent`, the gate and Livewire that already exist.
    3. **It does not close the split brain.** `File::addLanguage()` would still know nothing about `settings.languages`. That is a mismatch between the package and *this* application's architecture, not a package defect, so a fork inherits it by definition.
    4. **Six hops of re-validation** plus a `repositories` entry and a hosted repository, against zero.
    5. **It buys none of what `elegantly/laravel-translator` was wanted for** - missing/dead key detection, CSV export/import, AI translation would all still be separate work.
  - **A fourth option was proposed by the user and priced: `elegantly/laravel-translator`** (v4.2.0, released 2026-07-31). It is adopted, but **not as the replacement for the UI**, because:
    - **It ships no web UI at all.** It is a CLI toolkit (`translator:translate|missing|dead|sort|export|import|proofread|add-locale`) plus a `Translator` facade. The UI advertised in its README is **Laratranslate**, a separate **commercial** product ($29 single-project / $49 unlimited, one-time, one year of updates, licence key plus a private Composer repository).
    - It requires `illuminate/contracts: ^11.0||^12.0||^13.0` and PHP `^8.2`, so it **cannot be installed before Phase 8** under any decision.
    - It pulls **7 runtime dependencies** (`laravel/ai`, `nikic/php-parser ^5.1`, `spatie/laravel-package-tools`, `spatie/simple-excel`, `symfony/finder ^7.0||^8.0`, `symfony/intl ^7.0||^8.0`, `illuminate/contracts`) where the current package pulls **zero**.
    - Laratranslate's own compatibility statement says "Laravel 11 and 12" - it does **not** claim Laravel 13, while the base package already declares `^13`. That is an open risk at this roadmap's end state, and its production authorization hook (a gate or middleware config that would let `translator` and `mainAdmin` in) is **not publicly documented** - it sits behind the licence. Buying it would mean committing before that can be verified.
    - So the UI is built in-house, which keeps translator access behind the project's own already-proven `is-translator` gate, and the package comes in later purely as an **engine**: sorting, missing/dead key detection, CSV export/import, AI translation.
  - **The user asked for the current package to be removed now and the new one introduced later, and that is achievable with no loss of function**, because what is removed is the *UI* and what arrives later is the *engine*. They are not the same thing, so there is no gap in between.

  - **CORRECTION to this roadmap's own classification: the package blocks nothing, and never will.** This entry, `TODO 58` and Appendix A all said "Blocks Phase 8" / "Blocks Laravel 11". `vendor/joedixon/laravel-translation/composer.json:12` reads **`"require": {}`** - the require block is completely empty. No `php` constraint, no `illuminate/*`, nothing. Composer cannot fail on it at any hop; it would install silently on Laravel 13 and only misbehave at runtime. Identical shape to the TODO 16 finding, and the second time this roadmap mistook "unmaintained" for "blocking".
  - **CORRECTION to the stated consumed surface.** This entry named `app/Http/Livewire/Admin/Translation.php`. That file is **18 lines and calls no package API at all** - it reads one `Settings` row, and its view links out to the vendor UI through two **hardcoded URLs** (`resources/views/livewire/admin/translation.blade.php:53,78` -> `/languages/...`, not `route()`). The real surface is elsewhere:

    | Piece | Where | Size |
    | --- | --- | --- |
    | 7 auto-registered routes (`languages.*`) | `vendor/.../routes/web.php`, loaded unconditionally by `loadRoutesFrom` | in the **app** fixture: `tests/Fixtures/route-contracts.json:398-479` |
    | Published views | `resources/views/vendor/translation/` | 12 files, byte-identical to vendor (`diff -r` clean) |
    | Published assets | `public/vendor/translation/` | `main.css` 302 992 B + `app.js` 114 692 B - Vue 2, Tailwind 0.6 |
    | Published lang files | `resources/lang/vendor/translation/` | de/en/fr/nl identical to vendor + **`hu` (52 + 6 lines), project-authored, does not exist upstream** |
    | `config/translation.php` | project | one divergence from the package default: `:26` middleware `['web','auth','can:is-translator','password.confirm']` |
    | 5 global helper functions | `vendor/.../resources/helpers.php`, `require`d at boot | `set_active`, `strs_contain`, `array_diff_assoc_recursive`, `str_before`, `array_undot` |

  - **CORRECTION to the path claim** (repeated at the baseline list `Code-level upgrade risks` and at TODO 38). "Hardcodes assumptions about the `resources/lang` path" is only half true. At **runtime** the driver takes its path from the container: `TranslationManager.php:40` passes `$this->app['path.lang']` into `File`, which follows the Laravel 9 move to `lang/` for free. The single genuine literal is the **publish target**, `TranslationServiceProvider.php:131` -> `resource_path('lang/vendor/translation')`, and that only matters during `vendor:publish` - everything is already published. TODO 38 would therefore not have broken the UI.

  - **The 5 global helpers leave safely with the package.** A grep across the whole project excluding `vendor/` finds `set_active()` **only** in the published `resources/views/vendor/translation/nav.blade.php:7,13`, which is deleted in the same change set. The other four have zero call sites. This mattered: `resources/helpers.php:84` redefines `str_before()`, a global helper Laravel dropped in 6 - had any application code used it, removing the package would have been a fatal.

  - **Two vendor migrations have already run.** `php81 artisan migrate:status` shows `2018_08_29_200844_create_languages_table` and `2018_08_29_205156_create_translations_table` as applied, batch **1**. They are loaded straight from `vendor/` via `loadMigrationsFrom` (`TranslationServiceProvider.php:118`) and run on every `migrate` **and** on every `RefreshDatabase` boot, even though `config/translation.php:14` sets `driver => 'file'` and neither table is ever read. The languages migration also seeds rows through an Eloquent model inside the migration body. Removing the package takes both out of the migration path and leaves the two tables orphaned in the database - TODO 33.3 must decide explicitly what happens to them, and TODO 32 touches them anyway.

  - **Language management is split-brained, and the replacement closes it.** `Admin\Settings::languageAdd()` (`Settings.php:89-112`) writes **only** the `settings.languages` JSON blob and never creates a language directory. The package's `File::addLanguage()` writes **only** the directory and never registers the locale in that blob. Neither half knows about the other, so a locale added in the admin UI has no files, and a locale added in the translation UI never appears in the language switcher. Same shape as TODO 16's "split responsibility", and the single strongest structural argument for owning this code.

  - **What the replacement must reproduce** (the acceptance criteria are the existing tests):
    - The three access assertions in `tests/Feature/RouteAdditionalBehaviorRegressionTest.php:43-73`, the only test that renders the vendor UI today: a `registered` user gets **403**, a `translator` gets redirected to `password.confirm`, and with a confirmed password the page returns **200**. This is the user's stated hard requirement - `translator` and `mainAdmin` must reach the editor in production - and it is inherited for free by keeping the `/admin/translate` route exactly as it is (`routes/web.php:195-197`), already pinned by `LivewireRouteMountedComponentsTest:90`.
    - Reading and writing both file shapes: per-group PHP arrays (`lang/{locale}/{group}.php`) and the 5 root JSON files (`de.json`, `fr.json`, `hu.json`, `ro.json`, `sk.json`).
    - The source-locale reference column, which is what makes translating a 22-locale tree tractable.
  - **Re-check before executing:** whether Laratranslate has published a Laravel 13 line and a documented authorization hook. It would not change the decision - the in-house editor is needed regardless, since it is what keeps the feature alive from Phase 3 to Phase 10 - but it would change whether TODO 66.1 is worth extending. Same re-check discipline as TODO 22.
  - Expected changes: as delivered - the decision recorded here, the new TODO 33.3 and TODO 66.1, the TODO 58 / TODO 38 / Appendix A / baseline pointers updated, plus the two `.docs` corrections.

- [x] **TODO 18: Assess and decide - `pcinaglia/laraupdater`** - DONE
  - Delivered on 2026-08-07. **Decision: keep the self-updater, move it onto the project's own fork, and rename it `mdylan/laraupdater`.** The execution is **not** Phase 8 work - see the first correction - and is recorded as the new **TODO 33.4** at the end of Phase 3, executed in the same change set. Suite: **957 -> 983 tests, 2909 assertions, green.**

  - **CORRECTION to this entry's own context line, and it is the third time. The package blocks nothing.** The installed 1.0.2 declares `"require": {"php": ">=5.4.0"}` and **no `illuminate/*` or `laravel/framework` constraint at all**. Composer cannot fail on it at any hop. The "Blocks Laravel 11" / "Blocks Phase 8" reading came from Packagist's support badge for **1.0.3.4**, a version this project does not use and is not upgrading to. Three of the four Phase 2 "blockers" have now proven misclassified the same way (TODO 16, TODO 17, TODO 18); the rule sits in the Phase 2 preamble.
  - **CORRECTION: "consider whether a self-updater is still needed at all" is answered, and the answer is yes.** It is not deploy convenience, it is the product's distribution model. `https://updates.teruletek.hu/v1` is a live channel, and the **entire `vendor/` tree is committed to git** - force-added past the `/vendor` line in `.gitignore` - precisely so a release zip can carry it. There is no `composer install` on the target host. Moving to git/CI would be a different product, not a refactor.

  - **The three required options, priced:**

    | Option | Cost | Verdict |
    | --- | --- | --- |
    | **Fork / vendor into the project** | The fork already exists (`github.com/MDylan/laraupdater`) and the package is ~300 lines across one controller, one provider and one route file. A `v2` branch closing the gap, plus the Laravel 9-13 hygiene, was one change set | **chosen** |
    | Replace with in-house code | The *read* surface is genuinely small (`getCurrentVersion()` in 3 places, `check()` in 2, `getDescription()` in 1), but the *install* half - zip extraction over a live app root, per-file backup, restore-on-failure, the `upgrade.php` hook - carries all the risk, and rewriting it buys nothing the fork does not already give | rejected |
    | Drop the feature | Not available. See the second correction | rejected |

  - **The real problem was never compatibility, it was the maintenance model.** The vendor copy was **hand-edited in place**. Those edits survived only because `vendor/` is committed; the next `composer update` would have silently restored upstream 1.0.2, with no warning and no test to catch it. Measured against upstream `d19d88f0` (the Packagist 1.0.2 tarball), the local copy carried eight changes, of which the fork's `master` already had three and **five existed nowhere but this working tree**:
    1. `private $cache` plus a cache bypass at the top of `update()` - otherwise an install starts from a manifest up to `version_check_time` minutes stale.
    2. `version_compare()` in the "already updated" early exit. As strings **`"1.1.10" <= "1.1.5"` is TRUE**, so the fork's `master` would refuse to install any release past `x.x.9`. Measured, not reasoned.
    3. `Artisan::call('migrate --force')`. The fork's plain `migrate` prompts for confirmation in production, i.e. the update request hangs.
    4. `Artisan::call('optimize:clear')` after a successful install - see the finding below.
    5. `getLastVersion($file)` plus the `previous_version` chain, so a release that requires an intermediate version installs that one first instead of skipping its migrations.

  - **Finding: the `optimize:clear` edit is load-bearing, and it is what makes a package rename survivable at all.** `optimize:clear` calls `clear-compiled`, and `ClearCompiledCommand::handle()` deletes **`bootstrap/cache/packages.php`** as well as `services.php`. That file is **not** tracked by git (only `bootstrap/cache/.gitignore` is), so it is whatever the target host last generated. Without the clear, a release that changes the installed package set leaves a stale manifest naming a class that no longer exists, and the next request boots into a fatal. **Reproduced live** while executing TODO 33.4: `artisan package:discover` failed with `Class "pcinaglia\laraupdater\LaraUpdaterServiceProvider" not found` until the stale `packages.php` was deleted.
  - **Finding: two of the three updater endpoints were unauthenticated.** `src/Http/routes.php` applied `config('laraupdater.middleware')` to `updater.update` only; `updater.check` and `updater.currentVersion` carried **no middleware at all - not even `web`**, so any anonymous visitor could read `version.txt` through `updater.currentVersion`. `config/laraupdater.php` also sets `allow_users_id => false`, which disables the in-controller id check, so nothing else stood in front of them. They were also **unnamed**, which is why they fell out of every route snapshot this roadmap has taken - `upgrade-notes/baseline-routes.txt:250-252` records them, but `RouteContractSnapshotTest` never could.
  - **A conflict had to be resolved, and the vendor behaviour won.** The fork's `master` merged an upstream PR (`5e6dcb22`) changing `check()` to return the **full manifest array**. `App\View\Components\UpdateNotification:28` expects a **string** and fetches the changelog separately through `getDescription()`. `check()` therefore keeps returning a string in v2, and the package's own sample view was reverted to match.
  - **Other defects found while reading, all fixed in the fork:** the namespace was declared `pcinaglia\laraUpdater` (capital `U`) against a lowercase PSR-4 map, which worked only because PHP class lookups are case-insensitive and which produces a mismatched classmap under `--optimize-autoloader`; `catch(Exception $e)` around the migration was **unqualified inside a namespace**, so it resolved to a class that never exists and caught nothing; `loadTranslationsFrom(__DIR__.'/lang')` pointed at `src/lang`, which does not exist, so the `laraupdater::` namespace resolved to nothing and only the published copies ever worked; `file_get_contents()` had **no timeout**, and `check()` runs on every admin page render; the controller extended the host application's `App\Http\Controllers\Controller`, which the Laravel 11 skeleton no longer ships; and `checkPermission()` would fatal on `Auth::user()->id` with nobody logged in.
  - **What the rename costs, and it is not zero.** `install()` only ever adds and overwrites - it **never deletes**. Every deployed install will therefore keep a dead `vendor/pcinaglia/laraupdater` tree after the release that switches to `vendor/mdylan/laraupdater`. It is inert (the new autoloader does not map it), but the release zip must carry an `upgrade.php` whose `main()` deletes it. That mechanism exists for exactly this.
  - Expected changes: as delivered - the decision recorded here, the new TODO 33.4 executed in the same change set, and the TODO 58 / Appendix A pointers updated.

- [x] **TODO 19: Assess and decide - `protonemedia/laravel-verify-new-email`** - DONE
  - Delivered on 2026-08-07. **Decision: replace the package with in-house code and remove it.** The execution is **not** Phase 10 work - see the phase note below - and is recorded as the new **TODO 33.5** at the end of Phase 3. The characterization suite that every option needed but none had is delivered in the same change set as the new **TODO 19.1**. Suite: **983 -> 1013 tests, 2909 -> 3008 assertions, ~184 s, green.** No application code changed.

  - **The three required options, priced:**

    | Option | Cost | Verdict |
    | --- | --- | --- |
    | Fork / vendor the package | ~350 lines, a repository the project would then own, a `repositories` entry, and six hops of re-validation. The TODO 18 experience applies directly. Decisive against: **a fork inherits the two GDPR defects, the collision defect and the localization gap measured below** - it would have to fix them anyway, at which point it is the in-house option with extra hosting | rejected |
    | **Replace with in-house code** | ~250-300 lines: one `App\Models\PendingUserEmail`, one trait, a thin controller, two Mailables. Stable API only (`Password::broker()->getRepository()->createNewToken()`, `URL::temporarySignedRoute()`, Eloquent, Mailable) - framework-neutral, provable green on Laravel 8 today | **chosen** |
    | Drop the feature | Not available. Without it the profile page would write an unverified address straight into `users.email`, which is exactly the check Fortify's verification exists to make | rejected |
    | *(fourth option, since the roadmap named it)* Wait for upstream | The package is **not abandoned**, but its last release is **v1.13.0, 2025-04-01** - 16 months ago, and it still declares `^10.0||^11.0||^12.0`. Phase 10 cannot depend on a release that may never come. The "Re-check before executing" line below keeps this open | rejected |

  - **CORRECTION to this entry's own context line, and it points the opposite way to TODO 16/17/18.** Those three found packages the roadmap called blockers that block nothing. This one is the reverse: **the installed version blocks five phases earlier than recorded.** `vendor/protonemedia/laravel-verify-new-email/composer.json` shows the installed **1.6.0** requires `illuminate/support: "^8.67 || ^9.0"` - the **first genuine upper-bounded constraint** of the Phase 2 four. Composer fails on it at **Phase 5 (Laravel 10)**, not at Phase 10. That is a version bump, not a decision: `composer.json:31` declares `"^1.6"`, which already admits 1.13.0, so **the constraint needs no edit** - only the lock moves, and it moves with the PHP switch, since 1.9.0+ requires `php ^8.1` while `config.platform.php` is still pinned to `8.0.9`. The real wall stays at Phase 10, where no 1.x line exists. **The roadmap's "latest v1.13.0 supports at most Laravel 12" was accurate** - this is the one of the four where that half needed no correction.
  - **CORRECTION to the stated consumed surface: there is no "interaction with the duplicated `verification.verify` route".** The package registers `pendingEmail.verify` at `pendingEmail/verify/{token}`; the `verification.verify` duplicate (`routes/web.php:119-122` vs `routes/fortify.php:87-89`) is a different name at a different URI. The two flows touch at exactly one point - `PendingUserEmail::activate()` also calls `markEmailAsVerified()` and fires `Verified`. **TODO 26 is not a prerequisite for TODO 19, and TODO 19 is not one for TODO 26.**

  - **The consumed surface, measured.** Four call sites, one trait, one route, two published views:

    | What | Where |
    | --- | --- |
    | `use MustVerifyNewEmail` | `app/Models/User.php:18,22` |
    | `$user->newEmail($input['email'])` | `app/Actions/Fortify/UpdateUserProfileInformation.php:51` - the **only** dispatch site |
    | `getPendingEmail()` + `resendPendingEmailVerificationMail()` | `app/Http/Controllers/User/Profile.php:20-22` |
    | `getPendingEmail()` in Blade | `resources/views/layouts/app.blade.php:45,47`, `resources/views/user/profile.blade.php:93` |
    | `pendingEmail.verify` | vendor `src/routes.php`, loaded **only because** `config('verify-new-email.route')` is `null` |
    | published config / migration / views | `config/verify-new-email.php`, `database/migrations/2022_11_29_095804_*`, `resources/views/vendor/verify-new-email/` |

    What still runs from `vendor/`: `MustVerifyNewEmail` (~110 lines), `PendingUserEmail` (~90), `VerifyNewEmailController` + `VerifiesPendingEmails` (~60), two Mailables (~40 each), `InvalidVerificationLinkException`, `ServiceProvider`, `routes.php` - **~350 lines**. Smaller than TODO 17's ~1100, comparable to TODO 18's ~300.

  - **The load-bearing fact: the feature had zero test coverage.** Of 983 tests, one touched it indirectly (`NotificationTriggerRegressionTest:37-55`, and only for the notification) plus one row in `tests/Fixtures/route-contracts.json`. Nothing covered the signed link, `activate()`, expiry, resend or the mail selection. TODO 16 could lean on 68 existing GDPR tests as its acceptance criteria; here there was nothing to lean on, so **the net had to be built before the decision could mean anything**. That is TODO 19.1, delivered in this change set.

  - **Five defects found by reading the package for the replacement.** All five are pinned by tests today, and all five are why the fork option loses:
    1. **Anonymization leaves the real address behind.** `users.email` is in `$gdprAnonymizableFields` (`User.php:112`) and `getAnonymizedEmail()` returns `Str::random(10)`, but nothing clears `pending_user_emails` - no foreign key, no observer among the eight in `app/Observers/`, and `User::anonymize()` never calls the trait's `clearPendingEmail()`. The address the user asked for survives indefinitely.
    2. **A live link reverses the anonymization.** `PendingUserEmail::activate()` does not look at `isAnonymized`. Until the signed link expires, opening it writes the real address back onto the anonymized user **and marks it verified**, leaving a row that reads as anonymized while carrying real data. **Fix location: TODO 33.2**, since that is where `User::anonymize()` is rewritten anyway and the fix is independent of this package's fate.
    3. **Deleting a user orphans the row.** `morphs('user')` creates no foreign key.
    4. **`activate()` fatals on a collision.** `Rule::unique` runs only at request time in `UpdateUserProfileInformation`; if someone else takes the address while the mail is in flight, `$user->save()` throws `SQLSTATE[23000]` inside a signed-link GET - a 500 with no recovery path.
    5. **`verifyFirstEmail.blade.php` is the package's English stub**, in a 22-locale application. Its sibling `verifyNewEmail.blade.php` is fully `@lang()`-ed. The stub is reachable: `sendPendingEmailVerificationMail()` picks it whenever `hasVerifiedEmail()` is false.
  - **A sixth, milder finding, worth knowing before rewriting:** `InvalidVerificationLinkException extends Illuminate\Auth\AuthenticationException`, so an unknown token does **not** produce an error page - the framework redirects to `route('login')` and the package's own message (`The verification link is not valid anymore.`) never reaches anyone. Pinned as current behaviour, not as a defect to preserve.

  - **Why the execution is pulled forward to Phase 3, even though this constraint really is bounded.** The Phase 2 preamble rule ("pull forward when the constraint is unbounded") does not apply on its face - Composer genuinely fails here, at Phase 5. But the rule's *purpose* does: the replacement code is framework-neutral and provable on Laravel 8 today, so writing it in Phase 3 takes the package out of the Phase 5, 8, 9 **and** 10 resolutions at once, instead of bumping the lock three times and then still having to solve Phase 10. It also lets defects 4 and 5 be fixed in the same motion. **If TODO 33.5 slips**, the fallback is unchanged and safe: bump the lock to 1.13.0 at Phase 5 and do the replacement in TODO 65.
  - **What the replacement must reproduce** (the acceptance criteria are the 30 tests in `tests/Feature/NewEmail/`): the pending row shape and the "one row per user" rule; `users.email` untouched until activation; the verified/unverified Mailable split; `getPendingEmail()` for both Blade call sites; the token rotation on resend and its two flash messages; activation writing the address, marking it verified, firing `Verified`, deleting the row, redirecting to `config('verify-new-email.redirect_to')` with `verified` in the session and **not** logging the visitor in; and the three rejection paths (unsigned 403, expired 403, unknown token 302). The route must keep `web, signed, throttle:6,1` and must **not** gain `auth` - the link is opened on other devices.
  - **Re-check before executing:** whether a 2.x line or a Laravel 13 release appeared upstream since 2025-04-01. It would not change the decision - the in-house code is smaller than the integration cost, and it is the only path that fixes the five defects - but it belongs in the same re-check discipline as TODO 22.
  - Expected changes: as delivered - the decision recorded here, the new TODO 19.1 and TODO 33.5, and the Phase 2 preamble / TODO 39 / TODO 65 / Appendix A pointers updated, plus three `.docs` files that had never documented this feature at all.

- [x] **TODO 19.1: Build the missing characterization suite for the pending-email flow** - DONE
  - Delivered on 2026-08-07 in the TODO 19 change set. **This is not optional scaffolding - it is what makes the TODO 19 decision executable.** Before it, the flow had no acceptance criteria at all, so "replace", "fork" and "wait" were indistinguishable in risk.
  - New `tests/Feature/NewEmail/`, three files, **30 tests**, following the `tests/Feature/Updater/` precedent from TODO 18:
    - `PendingEmailFlowTest` (12) - what `newEmail()` writes, the verified/unverified Mailable split, the one-row rule, the early return, the profile-update path, `getPendingEmail()` and its two Blade call sites, and both resend branches.
    - `PendingEmailVerificationTest` (13) - activation, the `Verified` event, the redirect and its session flag, the guest assertion, the three rejection paths, the route contract (including the deliberate absence of `auth`), the throttle, and `verificationUrl()` round-tripping through the named route.
    - `PendingEmailKnownGapsTest` (5) - **the defects, asserted as they behave today.** Same discipline as TODO 14's duplicate-route tripwires that TODO 26 rewrites: the fix must make these fail, and that failure is the reviewable diff. Each carries the TODO that owns it.
  - **A measurement that shaped the whole suite: both Mailables are `ShouldQueue`, so under `Mail::fake()` they are asserted with `assertQueued()`, not `assertSent()`.** The queue connection is `sync`, so they still go out in the same request in production - but `MailFake::send()` diverts a `ShouldQueue` mailable to `queue()` before anything else happens, and `assertSent()` silently finds nothing.
  - **Control experiment, as run** (the TODO 14 / TODO 15 discipline): `$user->newEmail(...)` was commented out at `UpdateUserProfileInformation.php:51` and the suite re-run. **Exactly one test failed** - and that exposed a hole: `test_the_profile_update_does_not_write_the_new_address_into_the_users_table` stayed green, because "the address is not in `users`" is still true when the feature is simply gone. It gained a `getPendingEmail()` assertion so both profile-path tests now catch the removal. Line restored, verified.
  - No route is added, so `tests/Fixtures/route-contracts.json` and `RouteContractSnapshotTest` are untouched.
  - Expected changes: as delivered - three test files, no application code.

- [x] **TODO 20: Assess and decide - `rakibdevs/openweather-laravel-api`** - DONE
  - Delivered on 2026-08-07. **Decision: replace the package with an in-house `Http::` client, and finish the feature in the same motion.** The execution is split into two Phase 3 items, **TODO 33.6** (the package) and **TODO 33.7** (the feature). The characterization suite is delivered in the same change set as the new **TODO 20.1**. Suite: **1013 -> 1038 tests, 3008 -> 3090 assertions, green.** No application code changed.

  - **The input that reshaped this assessment:** the weather feature is wanted, and it does not work. So the question is not only "what happens to the package" but "what does the feature need in order to run" - and the measurement below shows they are the same piece of work. Every option is therefore priced against a working feature, not against the status quo.

  - **CORRECTION 1, and this package is misclassified in BOTH directions at once.** The roadmap said, in three places: *"installed v1.9.0; latest v2.0.0 supports at most Laravel 12. Blocks Laravel 13."*
    - **The installed v1.9.0 declares no `illuminate/*` or `laravel/*` requirement at all.** Its entire `require` block is `php: ^7.2|^7.3|^7.4|^8.0` and `guzzlehttp/guzzle: ^6.3|^7.0`. It never fails a Composer resolution on the framework version - it would install under Laravel 13 today. That is the TODO 16/17/18 pattern.
    - ~~**What it does bound is PHP**, and `^8.0` excludes 8.1, so the resolution fails at **Phase 5**.~~ **WRONG - corrected by TODO 22 on 2026-08-08.** Composer's `^8.0` is `>=8.0.0 <9.0.0`, so it admits 8.1, 8.3 and 8.4; `^7.2|^7.3|^7.4|^8.0` reads as an enumeration but is a union of ranges. Verified with `Composer\Semver\Semver::satisfies()` against 8.0.9 / 8.1.0 / 8.1.30 / 8.2.0 / 8.3.0 / 8.4.0 - all pass. **The installed 1.9.0 blocks nothing whatsoever**, which moves it into the TODO 16/17 category rather than the TODO 19 one: it would resolve silently through every hop to Laravel 13 and misbehave only at runtime.
    - **This does not change the decision, and it is worth being clear why.** The package is replaced for the six findings below - the feature does not work - not for what it constrains. What the correction changes is the *fallback*: if TODO 33.6 slips there is now **no forcing function at any hop**, so nothing will fail loudly to remind anyone. That argues for doing 33.6 rather than against it.
    - ~~**Unlike TODO 19, this is not merely a lock bump.** `composer.json:32` declares `"^1.9"`, which does not admit 2.0.0.~~ Still true as a statement about the constraint, but moot as a *fallback*: there is no resolution failure to escape, so no constraint edit is forced at Phase 5 either.
    - **v2.0.0 is one month old** - released 2026-07-03 after a 3.5-year gap - and requires `php ^8.0|^8.1|^8.2|^8.3|^8.4` with `illuminate/support ^8.0|^9.0|^10.0|^11.0|^12.0`. So the Phase 10 wall the roadmap describes is real, but it belongs to **v2.0.0**, not to the installed version. The package is not marked abandoned.
    - Net: a genuine **double block - Phase 5 on PHP, Phase 10 on Laravel** - and the roadmap stated neither correctly.

  - **CORRECTION 2: the recorded consumed surface names the wrong things.** `WeatherCity` is a **project** model (`app/Models/WeatherCity.php`); it survives the package's removal untouched and was never package surface. The real surface is **two methods on one class, from one call site**:

    | Consumption | Where |
    |---|---|
    | `new \RakibDevs\Weather\Weather()` | `app/Helpers/helpers.php:111` - the only occurrence of `RakibDevs\` in the whole project |
    | `->get3HourlyByCity($city, $country)` | `helpers.php:112` -> `GET data/2.5/forecast` |
    | `->getCurrentByCity("$city, $country")` | `helpers.php:113` -> `GET data/2.5/weather` |
    | the published `config/openweather.php` | 70 lines, of which four keys matter (`api_key`, `lang`, `temp_format`, two `*_api_version`) |

    Two plain GETs. The package ships **no facade** (no `aliases` block), no routes, migrations, views or commands. Everything else weather-related is project code: the model, the two 2024-12-04 migrations, the two Livewire components, the two Blade widgets, and the 18 self-hosted icons in `public/images/wt_icons/`.

  - **The package's HTTP layer cannot be faked, and that is a load-bearing fact.** `WeatherClient::client()` (`vendor/.../WeatherClient.php:88-96`) constructs `new GuzzleHttp\Client(...)` inline, with no container binding and no injection point; `Weather`'s private methods call `(new WeatherClient)->client()->fetch(...)`. `Http::fake()` intercepts Laravel's own client factory and never touches this. **So the success path of this feature has no test today and cannot get one while the package stays.** The repo already owns the pattern that would work - `tests/Feature/Middleware/CheckRecaptchaTest.php` fakes an outbound API exactly this way.

  - **The feature's missing half - six measured findings.**
    1. **There is no refresh loop.** `weather_cities` is written only from `UpdateGroupForm::updateGroup()` (`:215`) and `checkWeatherSettings()` (`:524`) - only when a group admin saves the group form or presses "check". `app/Console/Kernel.php` schedules 12 named commands, none weather-related; there is no job, observer or queue listener. The calendar (`Events.php:287-345`) is a **pure reader**. So the 59-minute freshness rule at `helpers.php:93` never runs on the display path, and publishers see whatever an admin last cached - arbitrarily old. **This is the half that is missing.**
    2. **`get3HourlyByCity($city, $country)` passes an argument the method does not take.** The signature is `get3HourlyByCity(string $city)` (`Weather.php:61`), and PHP silently discards extra arguments to userland functions. The 5-day forecast is therefore resolved **without** the country code while the current weather (`:113`) includes it - `"Szeged"` and `"Szeged, HU"` can be different cities.
    3. **The payload is JSON-encoded twice.** `helpers.php:116-117` calls `json_encode()`, then the model's `'json'` cast encodes again on write; reads decode once and `helpers.php:96-97` / `Events.php:297,300` decode a second time. Self-consistent, but it makes `$weatherCity->current_weather` a **string**, while `WeatherCityFactory::withWeatherData()` writes arrays - so `ModelFactoryTest:197-204` asserts a shape production cannot hold.
    4. **A failing API call makes the group unsavable.** `UpdateGroupForm.php:216-220` sets `city_id = null` on error and `:251` validates `'city_id' => 'required_if:weather_enabled,1'`. In the current state - no API key configured - an admin who enables weather **cannot save the group at all**, and the validation error lands on a field with no input in the form. Worse, `InvalidConfiguration` is constructed with no message (`WeatherClient.php:61`), so `helpers.php:158` returns `['error' => '']`.
    5. **`Events.php:296` dereferences the relation unguarded** - `$this->cal_group_data['weather']['current_weather']` on a group with `weather_enabled = 1` and `city_id = null`, which is exactly the state finding 4 produces. `:303` fatals the same way on a blob without a `list` key. And the foreign key that would prevent a dangling `city_id` was never created: `2024_12_04_194500_add_city_id_to_groups_table.php:17` calls `->constrained()` on `unsignedBigInteger()`, where it is a silent no-op.
    6. **The feature is English-only and effectively untranslated.** `config/openweather.php:55` defaults `lang` to `'en'` and never ties it to `app()->getLocale()`, so `description` comes back English and both widgets print it raw. And `group.weather.*` exists **only in `hu`**: of the 22 locale directories only `de`, `en` and `hu` carry `group.php` at all, and neither `de` nor `en` has a `weather` block - under those locales the widget renders raw keys.

  - **Two smaller findings worth knowing before rewriting:** the `weather_monthly_call` counter (`helpers.php:130-137`) is written and **never read** anywhere - the quota display was never built; and `WeatherCity::groups()` (`WeatherCity.php:28-31`) points at a `weather_city_id` column that does not exist (the real column is `groups.city_id`), so it is a broken stub that no caller touches.

  - **The options, priced against a working feature:**

    | Option | Cost | Verdict |
    |---|---|---|
    | **Replace in-house and finish the feature** | The package half is ~80 lines (`App\Support\Weather\OpenWeatherClient`); the rest is the refresh command and the six findings. Only stable API (`Http::`, Eloquent, `Schedule`), framework-neutral, provable on Laravel 8 today. **It is the only option that makes the success path testable**, and it clears the Phase 5 and Phase 10 blocks in one move | **chosen** |
    | Upgrade to v2.0.0 | Clears Phase 5 (`php ^8.4`) but not Phase 10 (`illuminate ^12.0`). Costs a constraint edit plus a re-read of the 2.x API, and fixes **none** of the six findings, all of which are in project code. It buys exactly one phase | rejected as an end state, **kept as the fallback** |
    | Fork / vendor | ~250 lines of vendor code for two GETs, own repo plus a `repositories` entry, six hops of revalidation. Fixes nothing, and **preserves the un-fakeable Guzzle client** - that is, the untestability | rejected |
    | Drop the feature | It is wanted. The UI, the icon set, the migrations and the Livewire code all exist already; what is missing is smaller than the removal would be | rejected |
    | Wait for upstream | v2.0.0 proves the project is alive, but v2.0.0 itself does not support Laravel 13, and no upstream release will ever fix the six project-side findings. The "Re-check before executing" line keeps this open | rejected |

  - **Why the execution is pulled forward to Phase 3.** Same argument as TODO 33.5: the replacement code is framework-neutral and provable today, so writing it in Phase 3 takes the package out of the Phase 5, 8, 9 **and** 10 resolutions at once, and `composer.json` never has to be edited for it. The feature is also wanted, and Phase 3 is the next phase. It is split into two items because they fail differently: **TODO 33.6** removes the dependency, **TODO 33.7** makes the feature work. **If both slip**, the fallback is a constraint bump to `^2.0` at Phase 5, which buys everything up to Laravel 12 and nothing beyond it - so Phase 10 would still have to solve it.
  - **What the replacement must reproduce** (the acceptance criteria are the 25 tests in `tests/Feature/Weather/`): the four helper branches - off, cache hit within 59 minutes, the 15-minute throttle, and the failure path - with their exact return shapes; the case-insensitive city lookup; the calendar's per-service-day aggregation, including its two filters and the last-slot-wins rule for description and icon; both suppression gates; and the admin preview. The eight `WeatherKnownGapsTest` assertions are the ones the execution is **required** to break.
  - **Re-check before executing:** whether a Laravel 13 line appeared upstream since 2026-07-03, and - more important to the feature than to the upgrade - whether the free tier still serves `data/2.5/weather` and `data/2.5/forecast`. Neither would change the decision, but both belong in the same re-check discipline as TODO 22.
  - Expected changes: as delivered - the decision recorded here, the new TODO 20.1, TODO 33.6 and TODO 33.7, the Phase 2 preamble / TODO 39 / TODO 65 / Appendix A pointers updated, and two `.docs` files, one of which carried a wrong statement.

- [x] **TODO 20.1: Build the missing characterization suite for the weather feature** - DONE
  - Delivered on 2026-08-07 in the TODO 20 change set. Same reason as TODO 19.1: before it the feature had no acceptance criteria at all, so "replace", "upgrade" and "wait" were indistinguishable in risk. Four tests mentioned weather; none exercised it - a factory smoke test asserting a shape production cannot hold, an admin-settings default, and two fixtures that deliberately set `weather_enabled = 0` to stay off the network.
  - New `tests/Feature/Weather/`, three files, **25 tests**:
    - `WeatherCacheTest` (9) - `pwbs_weather_api_call()` branch by branch: the off gate, the 59-minute cache hit, the case-insensitive lookup, the 15-minute throttle, the missing-key path and its empty error string, the fact that that path never records `last_try` so the throttle never engages, and both group-save outcomes.
    - `WeatherRenderTest` (8) - the read path from a hand-seeded cache row: the current-weather box, the per-service-day aggregation, the last-slot-wins rule for description and icon, the two filters, both suppression gates, and the admin preview.
    - `WeatherKnownGapsTest` (8) - **the findings, asserted as they behave today**, each naming the TODO that owns it. The fix must make these fail; that failure is the reviewable diff.
  - **Nothing in this suite touches the network, and that is enforced rather than hoped for.** Every `setUp()` pins `openweather.api_key` to `''`, which makes `WeatherClient` throw in its constructor before a socket opens - so the files stay offline even if someone later puts a key in `.env`.
  - **A measurement that shaped every read-path fixture: the application runs in `Europe/Budapest`, not UTC** (`config/app.php:72` plus `TIMEZONE` in both `.env` and `.env.testing`), while OpenWeather's `dt_txt` is UTC and `Events.php:305` correctly parses it as such. The service window in `date_start`/`date_end` is local. So an 08:00-12:00 Budapest window matches 06:00-10:00 UTC, and since the API returns 3-hourly steps at 00/03/06/09/12/15/18/21 UTC, such a window catches exactly two slots. Not a defect - but invisible from the code, and it decides every fixture in the file.
  - **Control experiment, as run** (the TODO 14 / 15 / 19 discipline): both dispatch sites - `UpdateGroupForm.php:215` and `:524` - were removed and the suite re-run. **It exposed a hole**: the "a failing call blocks the save" test stayed green, because "the group cannot be saved" is still true when the feature is simply gone and `city_id` was never set in the first place. It was rewritten to give the group a **working** city first, so the assertion is now that a failing call *destroys* it. After that, exactly three tests fail on removal and no others. Lines restored, `git status` clean.
  - No route and no scheduled command is added, so `RouteContractSnapshotTest` and `SchedulerRegressionTest` are untouched.
  - Expected changes: as delivered - three test files, no application code.

- [x] **TODO 21: Assess and decide - `eusonlito/laravel-packer`** - DONE
  - Delivered on 2026-08-07. **Decision: remove the package and replace its 16 call sites with a `pwbs_asset()` versioning helper, without concatenation.** The execution is **TODO 33.8**, at the end of Phase 3. The characterization suite is delivered in the same change set as the new **TODO 21.1**. Suite: **1038 -> 1055 tests, 3090 -> 3151 assertions, green.** No application code changed.

  - **CORRECTION 1: this package blocks nothing, and the roadmap said it did.** The entry read *"v3.0.1 exists with no Laravel constraint at all"* and Appendix A filed it under "likely redundant after Vite". The installed **v2.2.6** (2022-04-22) declares, in full:

    ```json
    "php": ">=5.5",
    "imagecow/imagecow": "^2.4"
    ```

    No `illuminate/*`, and **no upper PHP bound**. It fails neither the Phase 5 resolution (PHP 8.1) nor the Phase 10 one (Laravel 13) - it would install under both today, unchanged. The transitive `imagecow/imagecow` v2.4.1 asks only for `php >=5.5` and is pulled in by **this package alone**. So the honest classification is not "blocker" and not "redundant-once-Vite-lands" but **maintenance debt that happens to be free of Composer risk**. That changes what the decision has to be argued on: runtime behaviour, not resolvability.

  - **CORRECTION 2: the Vite dependency is recorded backwards.** The entry deferred this item behind Phase 7 - *"likely made redundant by the Vite migration - assess after that decision, not before"*. Measured:
    - **Zero `mix()` calls exist in the project**, in the layouts or anywhere else, though TODO 52 instructs replacing them.
    - `resources/css/app.css` is **0 bytes**; `resources/js/app.js` is the untouched 25-byte Laravel default.
    - The Mix outputs `public/js/app.js` and `public/css/app.css` **do not exist**, and nothing references them. There is no `node_modules/` and no `package-lock.json`.

    **The Mix pipeline is dead: it does not run, it never ran here, and nothing consumes its output.** Two consequences. Phase 7's stated reason for being mandatory - "Mix 6 will not build on Node 24" - does not hold, because nothing builds today and therefore nothing breaks. And **Vite cannot make Packer redundant**, because Packer is the application's only working asset pipeline and TODO 52 as scoped touches none of its 16 call sites. The dependency runs the other way: decide Packer first, and Vite's real scope follows from that. Pinned by `AssetPipelineKnownGapsTest`.

  - **The consumed surface: two facade methods, 16 call sites.**

    | File | Calls |
    |---|---|
    | `resources/views/layouts/app.blade.php` | 9 (3 `css`, 6 `js`) |
    | `resources/views/layouts/setup.blade.php` | 6 (3 `css`, 3 `js`) |
    | `resources/views/livewire/groups/poster-edit-modal.blade.php:87` | 1 (`js`) |

    Only `Packer::css()` and `Packer::js()`. **`img()`, `jsDir()` and `cssDir()` are never called** - which means the `imagecow` dependency exists solely to serve dead code. The package ships no routes, migrations, commands or views; the project-side surface is `config/packer.php`, the string-literal provider at `config/app.php:185` and the `Packer` alias at `:238`.

  - **What it actually does here, and it is less than the name suggests.**
    1. **13 of the 16 calls pass a single, already-minified file** (`all.min.css`, `adminlte.min.css`, `jquery.min.js`, `bootstrap.bundle.min.js`, `adminlte.min.js`, `toastr.min.js`, `sweetalert2.all.min.js`, `summernote-bs4.min.js`). For those the package copies the file to a `{filemtime}-` prefixed name and nothing else. `public/cache/js/*-jquery.js` is 89 KB today: `jquery.min.js` with a semicolon in front. Only **3 calls** concatenate more than one file.
    2. **It never minifies.** `config/packer.php` sets `'css_minify' => false` **and** `'js_minify' => false`. In this project the package is a concatenator and a timestamp renamer. The one value it genuinely delivers is **cache busting**, which is a `filemtime()` lookup.

  - **Finding 1 - the CSS rewriter breaks the icons it is handed.** `Providers/CSS.php:25` prefixes **every** `url(` with the asset base plus the source file's directory. Correct for relative paths; destructive for anything else. `toastr.min.css` carries four `data:` URIs, and the served `public/cache/css/*-all_style.css` contains, right now:

    ```
    url(http://kozter.test/plugins/toastr/data:image/png;base64,...)
    ```

    **All four toastr icons are broken in every non-`local` environment** - and fine locally, because `local` skips packing, which is exactly why nobody noticed. The project gains nothing in exchange: both of its own stylesheets contain zero `url(`.

    > **Corrected on 2026-08-09 during TODO 33.8: the count is 181, not 4.** This finding measured `toastr.min.css` and stopped there. `adminlte.min.css` carries **177** `url()`, and **every one of them is a `data:` URI** - Bootstrap's checkboxes, radios, select arrows, close buttons, accordion chevrons and validation icons, all corrupted the same way. Across the whole packed surface: **199 `url()`, 181 of them `data:`, 18 relative.** The 18 relative ones are FontAwesome's `../webfonts/`, and they did not need rewriting either, because the packed output landed in the *same directory* as its source. The rewrite was therefore necessary for **0 of 199** and destructive for 181 - a stronger verdict than this finding recorded, and the closing argument for TODO 33.8.

  - **Finding 1b - the absolute URL baked the request scheme into a cached file.** Not recorded when TODO 21 was written; measured on 2026-08-09, when it took the FontAwesome icons off a workstation running `APP_ENV=production`. The rewrite produced absolute URLs, so whichever request generated the file decided its scheme - and the file was then reused indefinitely, because its name derived from the **source** file's `filemtime`, not from its own contents. A stylesheet generated during one `http` request kept serving `http://` font URLs to every later `https` request: blocked as mixed content, with no self-healing and no way for a cache to notice.

    The trigger was `composer test`. `ignore_environments` lists only `local`, so the suite packs too, and `phpunit.xml` sets `APP_URL=http://kozter.test` - running the tests overwrote the very files the browser was being served. Proven by deleting the artifact, running the suite, and measuring 18 `url(http://` back in it. This is finding 2 (writing into the web root) with a consequence nobody had traced.

  - **Finding 2 - it writes into the web root during a request, and that has already cost something.** `process()` runs `mkdir` + `tempnam` + `fopen` + `rename` + `chmod 0644` under `public/` while rendering. `ignore_environments` lists only `local`, so `production` **and** `testing` both write. Twelve artifacts across six directories today, contained by **nine `.gitignore` lines** written for no other purpose (seven `*-cache_*` globs, `/public/cache`, and a misspelled `/public/storages/cache/*` naming a directory that has never existed).

    The concrete damage: `setup.blade.php` targets `/storage/cache/...` (while `app.blade.php` sends the same jQuery to `/cache/...`), so Packer creates the missing directory - and **`public/storage` is a real directory in this repository, not a symlink**, containing nothing but Packer's two files. That is precisely the state in which `php artisan storage:link` reports *"The [public/storage] link already exists"* and **skips the link** - `--force` does not help, since it only removes an `is_link()`. Every public-disk URL then 404s. In production the installer wizard renders before anyone runs `storage:link`, so a fresh deploy reproduces it.

  - **Finding 3 - dead weight.** `imagecow` is installed for an API with zero call sites. The provider still uses the pre-5.8 `protected $defer = true;` with a `provides()` method but does not implement `DeferrableProvider`, so the deferral has been inert for six major versions and the package registers eagerly on every request. And the provider and alias are registered as **string literals**, which is the other half of TODO 25.

  - **Options, priced:**

    | Option | Cost | Verdict |
    |---|---|---|
    | **Remove; replace with a `pwbs_asset()` helper, no concatenation** | ~10 lines in the already-autoloaded `app/Helpers/helpers.php`, plus 16 one-line blade edits. Deletes the runtime write into the web root, the nine `.gitignore` lines, `config/packer.php`, `imagecow`, the string-literal provider and the alias - and fixes finding 1 outright. Concatenation is dropped deliberately: it affects 3 of 16 calls and buys nothing measurable over HTTP/2 | **chosen** |
    | Keep it | No Composer risk, but findings 1 and 2 are live defects and the package has been untouched since 2022. Keeping it also keeps two entries in `config/app.php`'s `providers`/`aliases` arrays, which the Laravel 11 skeleton change makes awkward | rejected |
    | Upgrade to v3.0.1 | None of the three findings live in the package's version history - one is its own design, two are ours. The web-root writing stays. Buys nothing, costs a re-read of the 3.x API | rejected |
    | Fold into Vite (TODO 52) | Would mean pulling AdminLTE, jQuery, bootstrap, toastr, sweetalert2 and summernote into a build graph: an order of magnitude more frontend work than TODO 52 describes, on a machine with no Node modules installed. It is a legitimate future direction, not a way to retire this package | rejected for now |
    | Drop cache busting entirely | Simplest of all, and wrong: it is the one thing the package genuinely provides | rejected |

  - **Why the execution goes in Phase 3.** Same argument as TODO 33.5 and 33.6: the replacement is framework-neutral and provable on Laravel 8 today, so doing it in Phase 3 takes the package and `imagecow` out of the Phase 5, 8, 9 and 10 resolutions at once, and empties two lines of `config/app.php` before the Laravel 11 skeleton change makes them awkward. Finding 2 is also a live bug, not an upgrade concern. Since nothing blocks, **there is no fallback to record** - if TODO 33.8 slips, every later phase simply carries the package unchanged.
  - **What the replacement must reproduce** (the acceptance criteria are the 17 tests in `tests/Feature/Assets/`): a cache-busting token that follows `filemtime`, `<link rel="stylesheet">` and `<script src>` tags in the positions the layouts expect, and the same set of files reaching the browser. The nine `AssetPipelineKnownGapsTest` assertions are the ones the execution is **required** to break.
  - **Re-check before executing:** whether `public/storage` is still a real directory on each deployed host (the fix is per-install, not per-release), and whether any asset has since been added to a layout without going through the helper.
  - Expected changes: as delivered - the decision recorded here, the new TODO 21.1 and TODO 33.8, the TODO 25 / 52 / 53 / 54 / Phase 1 findings / Phase 7 preamble / Appendix A pointers updated, and a new `.docs/assets.md` for a subsystem that had no documentation at all.

- [x] **TODO 21.1: Build the missing characterization suite for the asset pipeline** - DONE
  - Delivered on 2026-08-07 in the TODO 21 change set. Same reason as TODO 19.1 and 20.1: before it the pipeline had **zero** tests, so "keep", "upgrade" and "remove" were indistinguishable in risk - and every page in the application is served through it.
  - New `tests/Feature/Assets/`, two files, **17 tests**:
    - `AssetPipelineTest` (8) - the container binding and the real `public_path()` it points at; the packed URLs the app layout emits outside `local` and the individual source tags it emits inside it; the concatenation order; the single-file copy; the `filemtime` prefix and its tracking of a changed source; the directory tree creation; and the two tag shapes the layouts depend on.
    - `AssetPipelineKnownGapsTest` (9) - **the findings, asserted as they behave today**, each naming the TODO that owns it. The fix must make these fail; that failure is the reviewable diff.
  - **A design split worth keeping in later work.** The two layout-rendering tests use the **real** `public/` directory, because only that proves what the browser receives - and it adds no new damage, since the suite already writes there (`SetupFlowTest` renders the setup layout) and every artifact is gitignored. Everything else runs against a **temporary `public_path`** with a directly instantiated `Packer` - which is exactly what `PackerServiceProvider` does, `new Packer($this->config())`, minus the container - and cleans up after itself in `tearDown()`.
  - **Two measured facts that decide every assertion in the files**, and neither is visible from the call sites: the JS provider prepends a `;` to **every** packed file, so output is never byte-identical to its source even for a single file; and the CSS provider rewrites every `url(` to absolute, which is how finding 1 was found.
  - **Control experiment, as run** (the TODO 14 / 15 / 19 / 20 discipline): the multi-file `Packer::js` call was deleted from `app.blade.php:77-81` and the suite re-run. **Exactly three tests failed** - the packed-URL test, the local-passthrough test and the call-site count in the gaps file - and nothing else. Line restored, `git diff` empty.
  - No route and no scheduled command is added, so `RouteContractSnapshotTest` and `SchedulerRegressionTest` are untouched.
  - Expected changes: as delivered - two test files, no application code.

- [x] **TODO 22: Confirm the remaining dependencies need only version bumps** - DONE
  - Delivered on 2026-08-08. **Answer: eight of the ten do, two do not** - and one of the two is not a version bump at all, but a forced `intervention/image` migration that breaks the only call site it has. Appendix A is rewritten from measurement, including a per-hop version ladder. The characterization suite the answer needed is the new **TODO 22.1**; the execution is the new **TODO 39.1**, in Phase 5. Suite: **1055 -> 1065 tests, 3151 -> 3183 assertions, green.** No application code changed.

  - **Method, so the numbers can be re-derived rather than trusted.** Three sources: `composer.lock` for the **installed** version and its actual `require` block; `composer.json` for the **declared** constraint, which is what decides lock bump versus constraint edit; and `repo.packagist.org/p2/{package}.json` for every stable release. The matching is done with `Composer\Semver\Semver::satisfies()` loaded out of the installed Composer phar, so it is the resolver's own semantics rather than a caret range read by eye. Every figure below is one of those three lookups.

  - **The eight that really are version bumps** - the declared constraint already admits a Laravel-13-capable release, so each is a lock bump:

    | Package | Installed | Declared | L13-capable release |
    |---|---|---|---|
    | `astrotomic/laravel-translatable` | 11.10.0 | `^11.9` | 11.17.0 |
    | `spatie/laravel-activitylog` | 4.4.0 | `^4.0.0` | 4.12.3 |
    | `spatie/laravel-cookie-consent` | 3.2.0 | `^3.1` | 3.5.0 |
    | `spatie/laravel-failed-job-monitor` | 4.1.1 | `^4.1` | 4.5.0 |
    | `spatie/calendar-links` | 1.7.1 | `^1.6` | n/a - **no framework constraint in any version** |
    | `petercoles/multilingual-country-list` | 1.2.12 | `^1.2` | 1.2.14 |
    | `laravel/fortify` | 1.10.2 | `^1.7` | 1.36.2 |
    | `guzzlehttp/guzzle` | 7.4.1 | `^7.0.1` | 7.15.3 - **and not the 8.x line, see correction 4** |

  - **CORRECTION 1: `laravolt/avatar` fails five phases earlier than this roadmap assumes, and it is not a version bump.** TODO 66 (Phase 10) reads *"`laravolt/avatar` 7.x requires PHP >= 8.3 and Intervention Image 4 - check the avatar generation"*. The installed **4.1.7** declares `illuminate/support: ^6.0|^7.0|^8.0|^9.0`, so **Composer fails at Phase 5**, on the Laravel 10 hop. And `composer.json` declares `^4.1`, which admits **no** release that supports Laravel 10 or later - so this is a constraint edit, not a lock bump, and it cannot be deferred by touching the lockfile.

    The version lines, measured:

    | Line | Laravel | PHP | `intervention/image` |
    |---|---|---|---|
    | 4.1.7 *(installed)* | `^6`-`^9` | >=7.3 | `^2.5` |
    | 5.1.0 | `^8`-`^11` | >=8.0 | `^2.7` |
    | 6.1.2 | `^10`-`^13` | >=8.1 | **`^3.4`** |
    | 6.5.1 | `^10`-`^13` | >=8.2 | **`^4.0`** |
    | 7.0.0 | `^10`-`^13` | >=8.3 | `^4.0` |

    The real work is the **Intervention Image 2 -> 3/4** jump, and it lands on exactly one line of application code:

    ```php
    // app/Http/Livewire/Groups/Messages.php:174-176
    $avatar = Avatar::create($message->user->name);
    $image  = $avatar->getImageObject();
    Storage::disk('web')->put($path.$file, $image->stream("png"));
    ```

    In Intervention Image 2, `stream()` is **not a real method**: it is an `@method` line on the class docblock (`Image.php:53`) dispatched by `__call()` (`Image.php:106`) to `Commands\StreamCommand`. The v4 `Image.php` has no `stream()`, no `__call()` and no `Commands` namespace - it has `encode(EncoderInterface)` and `encodeUsingFormat(Format)`. `getImageObject()` survives the jump (6.5.1 still declares it, returning `\Intervention\Image\Image`), so the call site fails at the *next* hop rather than at the facade. **Decision: go straight to `^6.5` at Phase 5** - 6.5.1 covers Laravel 10 through 13 and needs PHP >= 8.2, which the phase already provides, so it is one jump and one break instead of two. Executed as **TODO 39.1**; pinned by TODO 22.1.

  - **CORRECTION 2: `laravel/tinker` needs a constraint edit at Phase 10.** No 2.x release supports Laravel 13 - the line stops at 2.10.2 / Laravel 12. Support arrives in **3.0.0** (`php ^8.1`, `illuminate ^8.0` through `^13.0`), which `^2.5` does not admit. Appendix A called this "version bump only", which would have surfaced as a resolution failure in the middle of the Laravel 13 hop.

  - **CORRECTION 3: `barryvdh/laravel-debugbar` is the same shape, in `require-dev`.** The 3.x line stops at 3.15.4 / Laravel 12; Laravel 13 needs **4.0.10+**, and `^3.6` does not admit it. Recorded in TODO 66 with correction 2, since both are one-line constraint edits in the same hop.

  - **CORRECTION 4: the guzzle 8 line is a trap, and Appendix A was inviting it.** `laravel/framework` v13.24.0 declares `guzzlehttp/guzzle: ^7.8.2` in **`require`** - not `require-dev`, not `suggest`. Guzzle **8.0.2** exists (2026-08-05), so "bump it like the others" is a resolution failure rather than an upgrade. The correct verdict is **stay on `^7`**; 7.15.3 is the target.

  - **CORRECTION 5: for three packages the newest release is the wrong target.**
    - `spatie/laravel-activitylog` **5.0.0** requires `php ^8.4`, above this roadmap's PHP 8.3 target. **Stay on 4.x** - 4.12.3 covers Laravel 13 and needs only `php ^8.1`.
    - `laravel/fortify` **1.37.0** raises its floor to `illuminate ^11` / `php ^8.2` and adds **`laravel/passkeys` as a hard requirement**. **1.36.2** is the last release covering Laravel 10 through 13 without it. Since `routes/fortify.php` is a hand-patched copy of Fortify's own route file (TODO 69), new passkey routes appear there as a reconciliation problem, not as a feature. **Pin 1.36.2**; 1.37+ is a separate decision.
    - `spatie/calendar-links` **2.0.1** requires `php ^8.3`, and `^1.6` does not admit the 2.x line. It does not matter: the package declares **no `illuminate/*` constraint in any version**, so it never blocks a hop. 1.11.1 is the last 1.x and is admitted today. Moving to 2.x is an optional cleanup, not upgrade work.

  - **CORRECTION 6, and it is this roadmap's own, in six places: a caret was read as a version.** TODO 20 recorded that `rakibdevs/openweather-laravel-api` 1.9.0 *"declares `php ^7.2|^7.3|^7.4|^8.0`, and `^8.0` excludes 8.1"*, and concluded it fails the Phase 5 resolution on PHP. It does not. Composer's `^8.0` is `>=8.0.0 <9.0.0`; the pipe-separated list looks like an enumeration of minor versions but is a union of ranges, and the last one swallows 8.1 through 8.4. Verified with `Semver::satisfies()` against 8.0.9, 8.1.0, 8.1.30, 8.2.0, 8.3.0 and 8.4.0 - all six pass.

    Combined with the fact TODO 20 got right - no `illuminate/*` requirement at all - **the installed 1.9.0 blocks nothing at any hop**, which puts it in the TODO 16/17 category: it resolves silently to Laravel 13 and misbehaves only at runtime. This changes no decision (the package is replaced because the weather feature does not work), but it removes the forcing function TODO 33.6 was assumed to have, and that is worth knowing before relying on a hop to fail loudly. Corrected in the Phase 2 preamble, TODO 20, TODO 33.6, TODO 39, TODO 65 and Appendix A.

  - **A maintenance question answered rather than deferred.** Appendix A carried *"`petercoles/multilingual-country-list` - small/niche - verify maintenance status at Phase 10"*. It is alive: **1.2.14 (2026-04-11)** added `~13` to its `illuminate/support` list. It is a lock bump, and nothing about it needs to wait for Phase 10.

  - **The per-hop ladder is in Appendix A.** For every package that survives the upgrade, the **lowest** release admitting each of Laravel 9 through 13 is measured and tabulated there, so the Phase 4 / 5 / 8 / 9 / 10 items can read a version number instead of estimating one. Two entries in it are worth carrying in the head: `laravolt/avatar` crosses the Intervention boundary between L11 (5.1.0) and L12 (6.1.2), and `laravel/tinker` crosses a major between L12 (2.10.2) and L13 (3.0.0).

  - **Re-check before each hop.** This measurement is dated 2026-08-08 and upstream moves. The cheap re-run is the method above against the three or four packages whose floor rises during that hop; the ladder column tells you which those are. The one thing not worth re-checking is `spatie/calendar-links`, which has never declared a framework constraint.
  - Expected changes: as delivered - the answer recorded here, the new TODO 22.1 and TODO 39.1, the TODO 39 / 66 / 69 pointers updated, Appendix A rewritten with the ladder, and `.docs/components.md`, which had never recorded that the message board writes files.

- [x] **TODO 22.1: Build the missing characterization suite for avatar generation** - DONE
  - Delivered on 2026-08-08 in the TODO 22 change set. Same reason as TODO 19.1, 20.1 and 21.1: correction 1 says a package has to move across a breaking library boundary, and the code it lands on had **zero** tests.
  - New `tests/Feature/Avatar/AvatarGenerationTest`, **10 tests**: the mechanism (`Avatar::create()->getImageObject()` returns an `Intervention\Image\Image`, `->stream('png')` a PSR-7 `StreamInterface`, the bytes carry the PNG signature and the configured 100x100 size, the driver is GD); the integration (rendering `Groups\Messages` with one message writes `avatars/avatar-{user_id}.png` to the `web` disk, an empty board writes nothing, and a second render does not overwrite); the disk-root and view-prefix pairing; and three gap assertions - the `stream()` tripwire, the single call site, and the declared-constraint measurement from correction 1.
  - **The tripwire is directional, which is the point.** It asserts four things that all reverse under Intervention Image 4: `stream()` is *not* a real method today, `__call()` *is* one, `Commands\StreamCommand` exists, and `encodeUsingFormat()` does *not*. The moment the new library lands, that test fails and the failure is the review.
  - **It writes to the real `web` disk**, because only that proves where the file lands - the disk's root is the relative path `'public'` while the view addresses it as `asset('public/avatars/...')`, and that pairing is easy to break during the migration. It adds no new damage: `public/avatars/*` is gitignored (`.gitignore:5`), and `tearDown()` deletes what the test created.
  - **Control experiment, run twice** (the TODO 14 / 15 / 19 / 20 / 21 discipline). Neutralizing the `Storage::exists()` guard at `Messages.php:173` failed **exactly one** test, the "generated once" one. Commenting out the `Storage::put()` at `:176` failed **exactly two**, that one and the write test - and nothing else in either run. Restored, `git diff` empty.
  - No route and no scheduled command is added, so `RouteContractSnapshotTest` and `SchedulerRegressionTest` are untouched.
  - Expected changes: as delivered - one test file, no application code.

---

## Phase 3 - Laravel 8 Cleanup

Everything in this phase is Laravel 8 compatible and shortens every later phase. Nothing here changes the framework version.

### Branch discipline (read this before looking for a branch that does not exist)

- **The `v1-patch` branch is called `dev` in git.** Every section below refers to
  the last Laravel 8 release line as `v1-patch`, which is the name of the *work*,
  not of a ref. There is no `v1-patch` branch: sections A through H2 were committed
  on `dev`, which is 108 commits ahead of `v1` and is the freshest state of the
  repository. `v2-dev` was fast-forwarded onto it on 2026-08-09 - it had no commits
  of its own at that point, so nothing was merged away.
- **`dev` stays alive for v1 hotfixes.** The upgrade work continues on `v2-dev`.
  While `v2-dev` has not diverged, `git merge dev` stays a fast-forward; once the
  first v2-only commit lands, take `dev` -> `v2-dev` as a **merge commit**, not a
  cherry-pick. `vendor/` is committed in this repository, so every duplicated
  commit is a thousand-file diff, and a cherry-picked history makes the next merge
  resolve those files twice. Cherry-pick is the exception - for a hotfix that is
  already moot on v2 - and it should say so in the commit message.
- Baseline on `v2-dev` right after the fast-forward: **`OK (1249 tests, 3726
  assertions)` in 2m28s** on PHP 8.1.30 / Laravel 8.83.29, via `composer test`.
  **TODO 24 then moved the assertion figure to 3311 without removing a single
  check** - the Mockery upgrade stopped counting the Livewire test harness's own
  unconstrained expectation. Compare against that figure, not the 3726, for
  anything measured before TODO 24; the reasoning is in that entry.

### Where Phase 3 stands

The cheap preparation group is done on `v2-dev`: **TODO 23, 24, 25, 27 and 29**,
one commit each, suite **`OK (1270 tests, 3343 assertions)`**. The route table is
unchanged by all five - 91 entries, identical to the fast-forward point; the 8
differences against the TODO 03 baseline all come from the v1-patch line (the
`laraupdater.*` names, `password.confirm.store`, and the impersonation routes
moved GET -> POST by the security audit). `artisan optimize` completes, `composer
audit` still reports exactly the 3 known Laravel 8 advisories.

**TODO 31 shipped on 2026-08-10**, also in one sitting: the boot-time settings
read moved behind a cached repository with observer invalidation, and both silent
catches now log. Suite **1273 -> 1295 tests, 3564 -> 3622 assertions**. Two
findings the entry carries: emptying the `SetLocale` catch reproduces a **500**
on every guest page, so the swallowed exception was a live defect rather than a
tidiness issue; and a single malformed `settings.languages` row used to disable
the *entire* Config population through the same catch.

Still open in this phase, in rough order of size: **TODO 33.2** (GDPR in-house),
**TODO 33.3** (translation editor in-house), **TODO 33.5** (pending-email
in-house) and **TODO 32** (migration squash). Each is multi-day work that
deserves its own plan - unlike the items above, none of them is a one-sitting
edit. One small, well-specified follow-up also sits here unclaimed: converting
`resources/views/public.blade.php` to `pwbs_asset()`, recorded at the end of
TODO 33.8.

**TODO 33.9** is open as well and belongs in a category of its own: translating
the 4569 Hungarian comment lines across 208 files into English, now that
`AGENTS.md` states the rule. It carries no runtime risk at all - and therefore
no test will ever catch a mistake in it - so it is sliceable, but it must never
share a commit with a behaviour change.

> **TODO 33.8 was on that list, and it should not have been.** It shipped on
> 2026-08-09 in two commits, in one sitting. The estimate was wrong because the
> item was priced by its file count (a helper, three blades, `composer.json`, the
> vendor tree, `.gitignore`, the release hook, two test files, two documents)
> rather than by its risk, and the specification TODO 21 left behind - including
> the 17 tests that defined acceptance - had already done the hard part. It was
> pulled forward because one of its defects reached a browser: see finding 1b.
> The lesson is worth keeping for the four items still listed: a large *diff* and
> a large *decision* are not the same thing, and only the second one takes days.

One loose end noticed on the way and not worth its own TODO: `composer validate`
warns that `dialect/laravel-gdpr-compliance` is pinned to the exact version
`1.4.7`. TODO 33.2 removes the package, so the pin disappears with it.

### The `v1-patch` branch - a last Laravel 8 release, cut before the framework moves

Branched from `v2-dev` (which already contains all of `dev`, so the Phase 0-2 test
suite sits directly on top of the production line). The goal was a final Laravel 8
patch carrying the test suite and the measured bug fixes to the `v1` line, so the
value of Phases 0-2 does not have to wait for the whole upgrade.

**Scope decision, taken by the user:** behaviour-neutral fixes plus the real
production defects, the weather feature fixed and finished, and three
behaviour-changing items explicitly approved. Deliberately OUT of scope, and
still open below: TODO 33.8 (packer), TODO 33.2 and 33.5 (GDPR and pending-email
package replacements, though two GDPR-relevant defects were fixed without
replacing anything), and the pure upgrade-preparation items TODO 23, 24, 27, 29
and 32. The `helper` role question in TODO 33 did NOT get a go-ahead and stays as
it is.

Suite: **1065 -> 1199 tests, 3578 assertions, green on PHP 8.1.30 / Laravel 8.83.1**
at the time; **1240 tests / 3675 assertions on Laravel 8.83.29** after the
security audit work in section H. The route table was byte-identical to the
TODO 03 baseline apart from the three `laraupdater.*` names TODO 33.4 added -
section H then moved five entries deliberately, listed there.

The suite is run with **`composer test`**, never `artisan test` - the script
clears the build caches first, because a leftover `bootstrap/cache/config.php`
makes the tests read the APPLICATION configuration (production database
included) and once produced 110 false failures in a single run.
`tests/CreatesApplication.php` carries two guards for anyone who bypasses it.

**Closed on that branch (see the individual TODOs below):**

| Item | What shipped |
|---|---|
| TODO 26 | The dead `verification.verify` closure in `routes/web.php` is gone - zero runtime change, as TODO 03 predicted. The duplicated `password.confirm` was split: the POST definition is now `password.confirm.store`. Both definitions still answer on `/confirm-password` with unchanged middleware, so no URL moved - but the route table is finally cacheable. |
| TODO 30 | The `web` disk root is `public_path()` instead of a CWD-relative `'public'`, the view's URL prefix moved with it, and every disk declares `'throw'`. |
| TODO 33 | `setUserLastActivity` -> `SetUserLastActivity`; the `function_exists()` guard that named the wrong function; the dead `->namespace()` calls in `RouteServiceProvider`; **and the `is-groupCreator` gate typo**, which is why a plain `groupCreator` never received their newsletters. |
| TODO 33.1 | `CheckRecaptcha` now fails **open** on a connection error and carries an explicit 5s timeout. |
| TODO 33.6 + 33.7 | The weather feature rebuilt in-house and finished: `App\Support\Weather\*`, a `weather:refresh` command on `0 */3 * * *`, the country code on both endpoints, no more double JSON encoding, guards in the calendar, a real foreign key, en/de translations, and `OPENWAETHER_` -> `OPENWEATHER_`. `rakibdevs/openweather-laravel-api` removed. |
| TODO 06 | `newsletters:send-due` no longer blocks its whole queue forever on one unknown `send_to`. |
| TODO 28 | Every runtime `env()` call is gone from `app/`, `routes/`, `database/` and `resources/views/` - 24 of them. `config:cache` is finally safe, which matters because A7 made `optimize` reachable in the first place. |
| TODO 77, defect 2 | The `min([])` `ValueError` that made a day unopenable after lowering `date_max_publishers`. Defect 1 (the publishers-counting divergence) still needs a product decision and stays where it is. |
| (new, unrecorded) | **All seven audit loops filtered with `isset($changes[$field])`, which is false for null**, so every change setting a field to null was invisible in the audit trail, application-wide. |
| (new, unrecorded) | The `setup/*` group had no authorization at all. It is now behind an installer-token middleware, and `setup.complete` only writes the sentinel once an administrator exists. |
| (new, unrecorded) | **Nothing in the application deleted old data by age** (see "Data retention" below). `day_stats` had grown to 471,754 rows, live `events` to 164,371 and `group_dates` to 48,033, all back to June 2022; `config/activitylog.php` had declared a 90-day retention since installation, but the Spatie command that applies it was never scheduled, so 7,581 of 7,739 rows sat past their window. |
| (new, unrecorded) | **The self-updater carried no compatibility limit of any kind.** One admin click would have installed a 2.x release - PHP 8.3, a different Laravel major - onto a PHP 8.1 production system, migrations included and unrollbackable (see "Update branch ceiling" below). |
| (new, unrecorded) | **`artisan optimize` has never completed on this codebase - not on `v1` either.** A duplicate route name is not merely untidy: `route:cache` rebuilds the table as a Symfony collection keyed by name and throws `LogicException: Unable to prepare route [confirm-password] for serialization` on the second one. Verified by running `route:cache` against `git show v1:routes/web.php`, which fails identically. Every deployment that ran `optimize` silently fell back to an uncached route table. |
| (new, unrecorded) | **An external security audit found nine holes in the authentication surface** (see "Security audit" below). The headline: the admin impersonation return path was a 12-hour signed URL carrying an arbitrary user id, bound to neither the session nor the `mainAdmin` role - a leaked link was replayable administrator access. Alongside it, `composer audit` went from **50 advisories on 16 packages to 3 on one**, including a high-severity Livewire RCE that had been in range of the existing constraint all along, and a Fortify TOTP-replay CVE whose *official* fix turned out not to work. |
| (new, unrecorded) | **The special-date modal never cleared itself on open.** "Cancel" is a plain `data-dismiss="modal"` - nothing runs server-side - and `openModal()` only touched the state when it received a date, so *edit -> Cancel -> Add* reopened the form holding the previous day: delete button visible, the date field `disabled` by the carried-over `id`, and a save silently overwrote **the other day**. Every open now starts clean, the same `reset()`-on-open the other two modals already do. |

Also fixed there, with no owning TODO: the `ListUsers` pagination/transaction/
notification/silent-error set, `DeleteGroupDataProcess`'s order dependence, the
`Groups\Statistics` month-selector leftovers (which also removes a PHP 8.2
dynamic-property deprecation from Phase 8's path), the side-menu cache that never
expired, `HttpsProtocol`'s literal `"true"` comparison, and two GDPR defects
around pending e-mail addresses. `EventAutoCheck` was deleted - unrunnable, and
provably never dispatched.

#### Data retention (v1-patch E)

Measured before the work, on the live database: `day_stats` 471,754 rows (72%
past 13 months), live `events` 164,371 (73%), `group_dates` 48,033 (71%),
`activity_log` 7,739 (90%). The only age-based deletes in the whole application
were `maintenance:purge-log-history` (`log_histories`, 3 months) and
`maintenance:daily-cleanup`, which only empties the event **trash**. The three
GDPR commands anonymize `users` and delete no rows anywhere.

Three datasets, three separate gates - deliberately not one switch:

| Dataset | Gate | Window |
|---|---|---|
| `events` + cascading `event_service_reports` | `gdpr.enabled` | 13 months (`config/retention.php`) |
| `day_stats` + `group_dates` | new admin setting `group_data_retention` | off / 12 / 24 months |
| `activity_log` | `gdpr.enabled`, via a scheduler `when()` | 90 days, already declared |

`day_stats` and `group_dates` are **not** on the GDPR switch: they hold group,
day, time slot and a count - no personal data - so a size problem must not be
blocked by a privacy switch that is off on this deployment. They get an
admin-facing three-state control instead, with its own save action rather than
`saveOthers()`, which rewrites the whole `.env` and is the one deliberately
untested method in that component.

Four findings that shaped the implementation:

- **`event_service_reports` loses 873 of its 874 rows** on the first run (last
  entry belongs to a 2025-09-04 event). Accepted by the user against that
  number, not against a vague expectation. `--dry-run` on both commands, and a
  database backup, are the safety net.
- **`group_dates` had to go with `day_stats`.** `Groups\Statistics` builds its
  daily rows from `group_dates` (`isset($dates[$key])`), so purging only the
  statistics would not blank the screen - it would render a full table claiming
  the group served 0 hours out of N available, every day, for years.
- **`activitylog:clean` needs `--force`.** Without it the command hits
  `ConfirmableTrait::confirmToProceed()`, gets no TTY from the scheduler, prints
  `Command Cancelled!`, exits 1 and deletes nothing - forever, silently.
- **`GenerateStatProcess` could resurrect purged days as all-zero rows.**
  `GroupDateHelper::generateDate()`'s past-date guard is broken (it evaluates
  `$date_info->toArray()`, discards it and falls through to `updateOrCreate`),
  so a template edit dispatches the job for old days. It now returns early below
  the floor. **The broken guard itself is still there** and is worth its own fix.

All floors come from `App\Support\Retention\RetentionWindow` - the commands and
the four UI clamps read the same source, so they cannot drift. Two traps are
solved there once: the setting value is **whitelisted, never cast** (an `'x'`
cast to int is 0, and a zero-month window deletes everything up to today), and
`subMonthsNoOverflow()` with day-granular comparison, because `subMonths()`
overflows at month end and `day` is a `DATE` column.

Still open, noted but not done: `Statistics` (the `statistics` table, not the
component) grows ~9,000 rows/year while only the last 25 hours and 7 days are
ever read, and nothing purges it.

#### Update branch ceiling (v1-patch F)

The self-updater installs whatever the channel advertises, on exactly one
condition: `version_compare($remote, $local, '>')`. There is no PHP-version,
Laravel-version or major-version check anywhere in it. The moment a 2.x manifest
reaches `https://updates.teruletek.hu/v1`, every 1.x install is one admin click
away from downloading it, dropping into maintenance mode and running
`migrate --force` against a codebase that wants PHP 8.3 and a different Laravel
major. `restore()` brings the overwritten files back; it does **not** roll back
migrations. That is the accident this closes, and `v1-patch` is the last release
where closing it still helps - the guard only protects installs that already run
it.

**The rule: a major is never crossed automatically.** Within the installed major
nothing changes - one click, exactly as before. A higher major is *announced*,
not installed.

- **The ceiling is derived, not configured.** It comes from the major in
  `version.txt`, so there is no config key to maintain and none to empty by
  accident - and the 2.x line will be protected from 3.x by the same code, with
  no edit. `App\Support\Updates\UpdateBranch` is the single source, the same
  discipline as `RetentionWindow` in section E: an unreadable version on either
  side answers **blocked**, because a needless block costs a manual update while
  a needless allow costs a production system.
- **Enforced at the endpoint, not just in the UI.** Hiding the button is not
  protection - `/updater.update` is a plain GET that survives in bookmarks and
  history. `App\Http\Middleware\EnsureUpdateWithinBranch` joins
  `config('laraupdater.middleware')`, so it covers all three updater routes, but
  it only acts on `laraupdater.update`; `check` and `currentVersion` are
  read-only and pass through. It sits **last** in the stack, so the channel is
  queried only after `auth` and `can:is-admin` admitted the request, and it
  **forgets the cache first**: `update()` deliberately reads the manifest fresh,
  and a guard reading a 15-minute-old cache entry could wave through exactly the
  release the installer would then fetch.
- **The `previous_version` chain protects the pre-ceiling installs.** Publishing
  2.0.0 on the `/v1` channel means publishing *two* manifests: the head advertises
  2.0.0 with `previous_version` set to the last 1.x release, and that release gets
  its own manifest. An install still on 1.1.5 then resolves to the last 1.x, takes
  it automatically, and only after that sees 2.0.0 - blocked. No install can reach
  a new major without passing through the release that carries the ceiling. The
  contract is written down in `release/README.md`.
- **The blocked state is a card of its own**
  (`components/update-notification-manual`), warning-coloured, with no update
  button and a link to the project page. Its body is the manifest's `description`
  field verbatim, so the upgrade instructions are edited on the update server, not
  in the application. The Settings status line gained the same third state.
- **Nothing in `vendor/mdylan/laraupdater` changed.** No new fork tag, no
  `composer.lock` churn, no re-packaging of the vendor tree - the ceiling is a
  project-side layer *above* the package, and `UpdaterContractTest` pins that the
  package still reports the blocked version unchanged. It also means no PHP- or
  Laravel-version gate: the manifest carries no such field, and reading one would
  need a vendor change. The major check rules out the same accident.

Tests: `tests/Feature/Updater/UpdateBranchCeilingTest.php`, 14 tests. Three
component tests in `UpdaterContractTest` moved from `9.9.9` to `1.9.9`: under the
ceiling `9.9.9` is a *blocked* release, so they would have been asserting against
the manual card while claiming to test the normal one. The `check()` tests keep
`9.9.9` - they pin the vendor, which the ceiling does not touch. The three
`laraupdater.*` entries in `tests/Fixtures/vendor-route-contracts.json` carry the
new middleware.

#### Security audit (v1-patch H)

An external security audit of the authentication surface produced nine findings:
one high-priority privilege-delegation bug in admin impersonation, two known
CVEs in installed packages, four medium and one low. All nine are closed here.
`v1-patch` is the **last Laravel 8 release**, so anything left open stays open on
the `v1` line until the whole upgrade lands.

Suite: **1199 -> 1225 tests, 3647 assertions, green on PHP 8.1.30 /
Laravel 8.83.29.** `route:cache` and `config:cache` still complete.

##### 1. The impersonation return path was a bearer token

`admin.users.login` minted a **12-hour** `URL::temporarySignedRoute()` carrying
the admin's own id, parked it in the session, and rendered it as a link in the
navigation bar. `loginBack()` checked the signature and nothing else - not that
the `{id}` belonged to *this* session, not that it belonged to a `mainAdmin`.
Anyone who obtained the URL - browser history, a proxy log, a shared screen, a
screenshot - could replay administrator access for twelve hours with **any** user
id in it.

The admin id now lives in the session only
(`LoginToUserController::SESSION_KEY`), the URL carries no identity at all, both
directions are `POST` + CSRF, and the return path is single-use: the keys are
dropped **before** the login, so a half-finished request leaves nothing usable.
Three further guards the old code had none of: the `mainAdmin` role is
**re-checked on the way back** (the account may have lost the role meanwhile),
impersonation cannot be **chained** (a second switch would overwrite the stored
original and point the return path at the intermediate user - and the gate cannot
catch this on its own, because two main admins are possible), and
`auth.password_confirmed_at` is **forgotten on both switches**, so a password
confirmation cannot be inherited across an identity change.

One deliberate loosening, recorded rather than hidden: `admin.users.login` left
the `password.confirm` group for the `can:is-admin`-only one. `RequirePassword`
returns through `redirect()->intended()`, which re-issues the request as a GET -
405 on a POST-only route. Password confirmation still gates `/admin/users`, the
only page that renders the button.

`tests/Feature/Auth/ImpersonationTest.php`, 9 tests.

##### 2. CVE-2022-25838, and the fix that does not fix it

`laravel/fortify` was pinned at `^1.7` and locked at **v1.10.2**, whose
`TwoFactorAuthenticationProvider::verify()` is a bare `verifyKey()`: a TOTP code
stayed valid for the whole window, **any number of times**. The advisory
(GHSA-6w4v-qr4m-97gg, CVSS 8.1) marks 1.11.1 as fixed.

**Measured: bumping to 1.11.2 does not close it.** The 1.11.2 `verify()` caches
the used code's timestamp and then demands a strictly newer one - but on the
first call there is no cached value, `$oldTimestamp` is null, and
`PragmaRX\Google2FA::findValidOTP()` returns `true` instead of a counter in that
branch. Fortify stores that `true`; the next call passes it back as
`$oldTimestamp`, `max($timestamp - $window, true + 1)` leaves the starting
timestamp untouched, and the same code verifies again. A failing test proved it
before the fix and passes after. Later Fortify 1.x releases added exactly one
missing line - normalize `true` to `getTimestamp()` - and
`App\Actions\Fortify\TwoFactorAuthenticationProvider` now carries it, bound in
`FortifyServiceProvider::boot()` the same way `DisableTwoFactorAuthentication`
already was. Both verification paths - Fortify's `TwoFactorLoginRequest` and the
app's own `User::confirmTwoFactorAuth()` - resolve the contract, so both are
covered.

The version is pinned to **`~1.11.2`**, not `^1.11.2`: **1.12.0 introduces the
`two_factor_confirmed_at` column** and turns on Fortify's own 2FA confirmation
flow, which collides with this project's `two_factor_confirmed` boolean, its
overridden actions, `User::confirmTwoFactorAuth()`, and its own
`two-factor.confirm` route name. Lifting the ceiling is a schema plus flow
migration, not a lock bump. 1.11.2's vendor route file also moves the
`password.confirm` name onto `POST /user/confirm-password` and adds
`POST /user/confirmed-two-factor-authentication` as `two-factor.confirm`;
`routes/fortify.php` is a hand-maintained copy and deliberately carries neither.

`tests/Feature/Auth/TwoFactorReplayTest.php`, 3 tests.

##### 3. Fifty advisories, sixteen packages - and three with nowhere to go

`composer audit` reported **50 advisories across 16 packages** on a lock resolved
in early 2022. After this work: **3, on one package.**

| Package | Was | Now | Closed |
|---|---|---|---|
| `laravel/framework` | 8.83.1 | **8.83.29** | CVE-2024-52301 (env manipulation via query string; `register_argc_argv` is on in the local PHP 8.1 config) + 3 more |
| `symfony/http-foundation` | 5.4.3 | **5.4.50** | CVE-2025-64500 (PATH_INFO authorization bypass), CVE-2024-50345 |
| `symfony/http-kernel`, `mime`, `routing`, `process`, `polyfill-intl-idn` | 5.4.3-5.4.4 | 5.4.51-5.4.53 | 8 advisories, incl. two high-severity mail header / SMTP injections |
| `guzzlehttp/guzzle` + `psr7` | 7.4.1 / 2.1.0 | 7.15.3 / 2.13.0 | **20** advisories, 6 of them high |
| `league/commonmark` | 2.2.2 | 2.9.0 | 9 |
| `livewire/livewire` | 2.10.4 | **2.12.8** | CVE-2024-47823, **remote code execution on file uploads** - high, and in range of the existing `^2.10.4` constraint the whole time |
| `laravel/tinker` / `psy/psysh` | 2.7.0 / 0.11.1 | 2.11.1 / 0.12.24 | local privilege escalation via a CWD `.psysh.php` |
| `phpunit/phpunit`, `maximebf/debugbar` | 9.5.14 / 1.18.0 | 9.6.35 / 1.23.6 | 3 (dev-only, but the tree ships - see below) |

Every one of these fit inside the **existing** `composer.json` constraints. The
lock was simply four years stale, and `composer.json` carried `minimum-stability:
dev`, which is not a theoretical hazard: the first update run resolved
`laravel/framework` to **`8.x-dev`**, an untagged branch snapshot, on a release
branch that ships to production installs. `minimum-stability` is now `stable` -
the one bullet of **TODO 24** pulled forward; the platform pin and
`allow-plugins` stay with that TODO.

Two findings worth carrying forward:

- **Laravel 8 has three advisories with no fixed 8.x release**, because the
  branch is EOL and the fixes were never backported: temporary signed URL path
  confusion (fixed in 12.61.1), CRLF injection in the default `email` validation
  rule (12.60.0), and CVE-2025-27515 file validation bypass (10.48.29). Composer
  2.10 **blocks** advisory-affected versions during resolution by default, so
  `composer update` cannot resolve *any* Laravel 8 without saying so out loud.
  They are listed in `config.policy.advisories.ignore-id` with `on-audit: false`,
  which unblocks resolution while keeping them visible in `composer audit`. Each
  entry carries its reason. **These three are the honest argument for Phase 4+**:
  they cannot be fixed on this branch at all.
- **`require-dev` packages are not dev-only here.** `vendor/` is committed and
  shipped in the update archive (`release/README.md`), so Debugbar, PsySH and
  PHPUnit reach every production install. They were bumped rather than triaged
  away. `config/app.php` also hard-registers the Debugbar provider - **TODO 25**.

##### 4-7. The medium findings

- **A second, unthrottled password-confirmation endpoint.** `routes/fortify.php`
  still carried Fortify's `POST /user/confirm-password` with `auth:web` and no
  rate limit, so a stolen session allowed unlimited password guessing - while the
  application's own `password.confirm.store` is throttled `6,1`. Nothing posted
  to it. Removed.
- **The login limiter key was raw `email . ip`.** MySQL's default collation is
  case-insensitive, so `User@x.hu` and `user@x.hu` resolve to the same account
  while the limiter saw two buckets: the 5/minute cap was multipliable by varying
  letter case. The key is now `strtolower(trim(email)) . "|" . ip`, and a second
  limit caps a single IP at 20/minute against email rotation.
- **reCAPTCHA had three separate holes.** The client IP went out as `ip`, which
  the siteverify endpoint silently discards - the address was never actually
  checked; it is `remoteip` now. All three forms requested the hardcoded
  `register` action and the server never read the field back, so a token
  harvested on one form worked on any other; the expected action is now a
  middleware parameter (`checkRecaptcha:login|register|password_reset`) and is
  verified server-side, as Google's v3 documentation asks. And `/register` and
  `/forgot-password` carried **no route throttle at all**, which matters because
  `CheckRecaptcha` fails open on a connection error by design (v1-patch D3): a
  Google outage left both endpoints with no bot protection. Both now carry
  `throttle:5,1`. The fail-open decision itself stands, unchanged.
- **`finish-registration/{id}/cancel` deleted a user row over GET.** A signature
  proves the link came from us, not that the user meant to open it - browser
  prefetch, a mail scanner following links, or a stray navigation all fire a GET.
  It is `POST` + CSRF now, behind the same signature.

##### 8. The low finding

`GET /email/verify` had no `auth`. No authorization bypass followed from it, but
the view renders `<x-admin-layout>`, which dereferences `auth()->user()` - so
every logged-out hit was a 500 and a stack trace in the log. It carries `auth`.

##### Route contract diff

Five entries in `tests/Fixtures/route-contracts.json` moved, all intentionally:
`admin.loginback` (GET+signed -> POST), `admin.users.login` (GET -> POST, minus
`password.confirm`), `finish_registration_cancel` (GET -> POST),
`verification.notice` (+`auth`), `password.email` (+`throttle:5,1`, and
`checkRecaptcha` -> `checkRecaptcha:password_reset`). The Livewire 2.12.8 bump
added `livewire.message-localized` to `vendor-route-contracts.json` - caught by
the snapshot, which is exactly what it is for.

#### Follow-up on the two remaining exposures (v1-patch H2)

Section H closed the audit's nine findings and left three `laravel/framework`
advisories with no fixed 8.x release. Two of them were then measured **against
this codebase** rather than against the version range, and one of the two is
reachable here. That one is closed below, together with an unrelated
authorization hole found while measuring it.

Suite: **1225 -> 1240 tests, 3675 assertions, green.**

##### The email CRLF advisory is reachable - on two vendor endpoints

GHSA-5vg9-5847-vvmq (**high**, fixed in 12.60.0): Laravel's default `email` rule
uses RFCValidation, which **accepts CR/LF inside the address**. From there the
address reaches a mail header, where a line break opens a new one - the attacker
can influence the message, redirect it, or make the mailer send messages of their
own.

**The application's own validations were already safe.** Every user-supplied
address goes through `email:filter` - `CreateNewUser`, `UpdateUserProfileInformation`,
`Admin\Users\ListUsers`, `Groups\ListUsers` - and that variant is
`filter_var(FILTER_VALIDATE_EMAIL)`, which rejects CR/LF outright. That was not
foresight about this advisory; it happens to be the right rule.

Four places were not:

| Site | Why it matters | Fix |
|---|---|---|
| `POST /forgot-password` (vendor) | **Anonymous**, and mails the address it is given | `strictEmail` middleware |
| `POST /reset-password` (vendor) | Resolves the reset token by address | `strictEmail` middleware |
| `UpdateGroupForm::$replyTo` | Goes straight into the **Reply-To header** of the group's mail | `email:filter` |
| `SetupMailRequest::MAIL_FROM_ADDRESS` | Goes into the **From header** | `email:filter` |

The two Fortify endpoints validate `required|email` inside
`vendor/laravel/fortify`, where an edit would not survive the next
`composer update`. `App\Http\Middleware\EnsureWellFormedEmail` (alias
`strictEmail`) re-validates the field with the same `email:filter` rule the rest
of the application uses, so the error message and the return to the form are
unchanged. It sits **before** `checkRecaptcha`, so a malformed address does not
cost a round trip to Google.

Note the mitigating half, recorded so nobody re-derives it: Laravel 8 still uses
**SwiftMailer**, not the Symfony Mailer/Mime pair the advisory names, so the
second half of the chain differs here. The validation side was lax either way,
and the fix is cheap - but the exposure was not the advisory's worst case.

##### The other two advisories, measured

- **CVE-2025-27515, file validation bypass** (medium, fixed in 10.48.29): affects
  the `files.*` wildcard form of file/image validation, and this project has
  exactly one - `Groups\NewsEdit::updatedFiles()`, `'files.*' => 'mimes:...'`.
  Left as is, with the reasoning written down: it needs `groupAdmin`, the
  `news_files` disk is private and rooted outside the docroot, and downloads go
  through `Storage::download()`, which sets `Content-Disposition: attachment`.
  The realistic outcome is a stored file of a disallowed type, not execution.
- **Temporary signed URL path confusion** (medium, fixed in 12.61.1): the
  advisory describes the **local filesystem driver's** temporary URLs
  (`Storage::temporaryUrl()` with the `serve` option, and `temporaryUploadUrl()`).
  Verified against the installed source: `temporaryUploadUrl` does not exist
  anywhere in Laravel 8, the local driver has no `serve` option, and
  `FilesystemAdapter::temporaryUrl()` throws unless the adapter implements
  `getTemporaryUrl` (S3 and friends). This project uses only
  `URL::temporarySignedRoute()` - route signing, a different mechanism - plus one
  Livewire `TemporaryUploadedFile::temporaryUrl()`. **The vulnerable code appears
  not to be in this branch at all**; the `<12.61.1` range is the blanket form
  Laravel advisories use when they do not enumerate per-branch fixes. Recorded as
  a source-level reading, not as an upstream statement.

##### Found while measuring: any group member could read any group's attachments

`GET /news_file/{group}/{file}` is gated by `groupMember`, which reads **only**
the `{group}` route parameter - it proves the caller belongs to *that* group. The
`{file}` was bound by bare id, and `GroupNewsFileDownloadController` never checked
that the file belonged to the group. Passing one's own group id next to a foreign
file id therefore downloaded **another group's private attachment**. The files sit
on the private `news_files` disk outside the docroot, so this controller was the
only route to them, and it was open.

The controller now verifies `$file->new->group_id` against `{group}` and answers
**404** rather than 403, which would confirm the file exists.
`tests/Feature/Groups/NewsFileDownloadScopeTest.php`, 4 tests.

**This is a pattern, not a one-off.** `groupMember` and `groupAdmin` scope exactly
one parameter; every route carrying a group *plus* a second model binding needs
the same check in its controller. Worth a sweep - it was not in the audit's scope
and is not covered by a general test.

- [x] **TODO 23: Remove `laravelcollective/html`** - DONE on `v2-dev`
  - Delivered: the requirement is gone from `composer.json`, the commented-out `Collective\Html\HtmlServiceProvider` line is gone from `config/app.php`, and the lock reports exactly what a dead dependency should: **`0 installs, 0 updates, 1 removal`**. No other package moved. Suite unchanged at **1249 tests, 3726 assertions**.
  - Re-verified before removing, not taken from the note below: no `Form::`, `Html::`, `FormFacade` or `HtmlFacade` reference in `app/`, `resources/`, `routes/`, `database/`, `config/` or `tests/` (the three grep hits are the `UpdateGroupForm` Livewire component), and no `Form`/`Html` entry in the `aliases` array.
  - **The trap in this repository, and it will recur in every composer-touching TODO: `--no-scripts` leaves a stale package manifest behind.** `composer update` has to run with `--no-scripts` here, because `post-autoload-dump` calls `@php artisan`, and Composer's `@php` is PHP 8.3, which this Laravel 8 baseline cannot boot. But skipping the scripts also skips `package:discover`, so `bootstrap/cache/packages.php` and `services.php` keep listing the removed provider - and the next boot dies in `registerDeferredProvider("Collective\Html\HtmlServiceProvider")`. `optimize:clear` cannot rescue it either: clearing the cache needs a boot, and the boot is what fails. **The fix, and the step to repeat after every `--no-scripts` run:** delete `bootstrap/cache/packages.php` and `bootstrap/cache/services.php` by hand, then `php81 artisan package:discover`.
  - Needed:
    - Verified: zero `Form::` or `Html::` usages anywhere, and the provider is already commented out at `config/app.php:167`. Remove the requirement outright, and take the commented-out provider line with it.
    - **`vendor/` is committed in this repository**, so this and every other composer-touching item produces a diff of thousands of files. That is the lock's consequence, not hand editing - say so in the commit message, or the next reviewer will read it as noise hiding a real change.
  - Expected changes: `composer.json` / `composer.lock` / `vendor/`. This eliminates one of the most common hard blockers for Laravel upgrades at zero cost.

- [x] **TODO 24: Clean composer configuration** - DONE on `v2-dev` (was PARTIALLY DONE on `v1-patch`)
  - Delivered: `config.platform.php` raised `8.0.9` -> **`8.1.30`**, an explicit empty `config.allow-plugins` block, and the full re-resolution that the honest platform value makes possible. `laravel/framework` did **not** move - it stays at 8.83.29, the last 8.x tag. Suite green: **1249 tests**, unchanged.
  - **`allow-plugins` is empty on purpose, and that is a finding, not a shortcut.** This project has **zero** `composer-plugin` packages - `grep composer-plugin composer.lock` returns nothing, and `composer install` never asked for an approval. The bullet below said "for the plugins actually in use", and the answer is: none. `{}` records deny-by-default, so the first plugin that an upgrade hop tries to introduce (a Laravel 11 skeleton dependency, say) has to be approved deliberately instead of arriving unnoticed.
  - **35 packages moved.** The user was shown the list and approved it as its own commit, on the roadmap's own principle: doing it here, on Laravel 8 with a green suite, means the TODO 34 hop is about the framework alone and a regression there has one candidate cause.
    - Four major bumps: `ramsey/collection` 1.3 -> 2.1, `doctrine/instantiator` 1.5 -> 2.0, `doctrine/event-manager` 1.1 -> 2.1, `symfony/service-contracts` v2.5 -> v3.7.
    - `doctrine/dbal` 3.3.2 -> 3.10.6 - the engine behind the 16 `->change()` calls TODO 32 and TODO 57 deal with.
    - The whole Symfony layer v6.0.19 -> v6.4.4x, plus `spatie/laravel-activitylog` 4.4 -> 4.12.3 (the audit trail) and the dev tools (`mockery`, `faker`, `sail`, `whoops`).
    - Three packages left, two arrived: `symfony/polyfill-php81` is gone (the platform is 8.1 now, so the polyfill is dead weight), `fruitcake/php-cors` gave way to `asm89/stack-cors`, `doctrine/cache` dropped out, and `symfony/yaml` came in.
  - **The assertion count fell 3726 -> 3311 with the test count unchanged, and that is correct.** Worth writing down, because assertion counts are used as a progress metric throughout this roadmap and a 415-assertion drop reads like lost coverage. It is not.
    - Measured, not guessed: both runs were captured with `--log-junit` and diffed per test case. **No test gained an assertion**, and every test's loss equals the number of `Livewire::test()` / `->test()` calls it makes (`LivewireNestedComponentsTest::test_partial_components_smoke`: 3 calls, 4 -> 1; `AvatarGenerationTest::test_the_avatar_is_generated_once_and_never_overwritten`: 2 calls, 4 -> 2).
    - The cause is `vendor/livewire/livewire/src/Testing/TestableLivewire.php:66-68`: every `Livewire::test()` builds a `Mockery::mock(GenerateSignedUploadUrl::class)` with `shouldReceive('forS3')` so tests never generate real S3 signed URLs. That expectation carries **no call-count constraint** and is never invoked.
    - Mockery 1.5.0 counted it anyway (`ExpectationDirector::getExpectationCount()` was `count($this->getExpectations())`). Mockery 1.6.12 counts only expectations where `isCallCountConstrained()` holds. So 415 phantom assertions disappeared from the tally - none of them ever asserted anything about this application.
    - **New baseline for later phases: `OK (1249 tests, 3311 assertions)`.** Comparisons against the 3726 figure recorded before this TODO are apples to oranges.
  - `composer audit` after the update: the same **3 advisories on `laravel/framework`** as before, all three the known no-8.x-backport entries in the `ignore-id` block. Three abandoned packages are reported and all three are already owned by later TODOs: `swiftmailer/swiftmailer` (TODO 36), `fruitcake/laravel-cors` (TODO 35), `maximebf/debugbar` (a Debugbar dependency).
  - Needed:
    - ~~Remove `config.platform.php = 8.0.9`.~~ **Corrected: raise it to `8.1.30`, do not remove it.** Removing the key would be right on a machine where Composer runs on the target runtime. Here it does not: Composer runs on **PHP 8.3** while the application runs on **PHP 8.1.30** (`php81`), so with no platform key Composer resolves against its own 8.3 and can lock package versions the real runtime cannot execute. The pin is the tool that keeps the resolution honest - `8.0.9` was simply the wrong value, three patch lines below what is installed. **Delete the key only once the two coincide**, i.e. from Phase 5 onward, when both Composer and the application run on `php` 8.3; note it in the *Runtime and Version Matrix* row for that phase.
    - ~~Change `minimum-stability` from `dev` to `stable`.~~ **DONE on `v1-patch H`**, and not as housekeeping: the first security-update run resolved `laravel/framework` to `8.x-dev` - an untagged branch snapshot - on the branch that ships to production installs. The hazard this bullet described is measured, not hypothetical.
    - Add a `config.allow-plugins` block for the plugins actually in use (Composer 2.10 requires it). Take the list from what `composer install` itself reports as awaiting approval, not from memory.
  - ~~Also added on~~ **Already on `v1-patch H`**, and belonging to this TODO's subject: a `config.policy.advisories.ignore-id` block for the **three Laravel 8 advisories that have no fixed 8.x release**. Composer 2.10 blocks advisory-affected versions during resolution by default, so without it `composer update` cannot resolve any Laravel 8 at all. Each entry carries a reason and `on-audit: false`, so `composer audit` still reports them.
    - **The block is temporary and must be re-evaluated after TODO 34.** All three advisories are fixed on later Laravel lines (12.61.1, 12.60.0 and 10.48.29 respectively), so each entry becomes obsolete at a different hop. Leaving them in place past the hop that fixes them would silence a real advisory.
  - **Risk note for whoever executes this:** raising the platform pin re-resolves the whole dependency graph, which makes this the riskiest item in the "cheap preparation" group. Run it on its own, read the `composer.lock` diff before committing, and stop if more packages move than the change explains.
  - Expected changes: honest dependency resolution and no hidden legacy locks.

- [x] **TODO 25: Fix provider registration hygiene** - DONE on `v2-dev`
  - Delivered: the hard-registered `Barryvdh\Debugbar\ServiceProvider` is out of `config/app.php`, the `\Debugbar::enable()` call in `AppServiceProvider::boot()` is behind a container check, and `tests/Feature/DevDependencyIsolationTest.php` (4 tests) guards both halves. Suite: **1249 -> 1253 tests, 3320 assertions**. The packer lines are untouched, as decided above.
  - **Proven on a real `--no-dev` tree, not argued from the code.** After `composer install --no-dev`, `artisan route:list` boots and exits 0, and the two facts that make the guard necessary and sufficient measure out as: `app()->bound('debugbar') === false` (so the guard skips the call) and `class_exists('Debugbar') === false` (so the unguarded call would have raised `Error`). An `Error` is not an `Exception`, so the `catch (\Exception)` wrapping the whole boot would **not** have caught it - the setting turned on would have killed every request on such a host. The dev tree was restored afterwards; `composer install --no-dev --dry-run` alone proves nothing here, because it never boots.
  - The live `debugbar` setting is `0` today, which is the only reason this was latent rather than active.
  - **A test that looks right and measures nothing - worth reading before writing the next one of these.** The first version of the "absent package" test passed with the guard *removed*, i.e. it asserted nothing. Three rounds of control experiment, each one uncovering the next layer:
    1. Unbinding the container's `debugbar` key changed nothing - the facade answered from its own `$resolvedInstance` cache and never reached the container.
    2. Adding `Facade::clearResolvedInstance('debugbar')` changed nothing - `Barryvdh\Debugbar\Facades\Debugbar::getFacadeAccessor()` returns `LaravelDebugbar::class`, not the `debugbar` alias.
    3. Clearing that key too changed nothing - **the class is present in the vendor tree**, so the container simply built it (`__construct($app = null)` is resolvable). And the class being present is exactly what a `--no-dev` host would not have.
    - The conclusion is structural: this failure mode cannot be simulated inside a running process, because it depends on the vendor tree being absent. The suite therefore guards what it can actually measure - that no `require-dev` package's provider is in `config('app.providers')` (dynamic, and it fails correctly when the line is restored), and that the debugbar branch never calls the facade directly (source-level, weak, but it catches the regression). The `--no-dev` boot itself is a manual verification step, recorded above.
    - Second trap in the same test: `config('settings_debugbar')` is already set by the boot that built the test application, so asserting on it after a second `boot()` call passes whether or not the branch threw. The check only means something after the key is explicitly nulled first.
  - Needed:
    - Remove the hard-registered `Barryvdh\Debugbar\ServiceProvider` from `config/app.php` and rely on auto-discovery (or gate it behind an environment check); it is a `require-dev` package and currently breaks `composer install --no-dev`.
    - ~~Replace the `'Eusonlito\LaravelPacker\PackerServiceProvider'` string literal with `::class`.~~ **Ordering resolved: this bullet is dropped, not executed.** TODO 33.8 deletes that line (`config/app.php:185`) and the matching `Packer` alias (`:238`) outright. Since 33.8 is still open and large, this TODO closes the Debugbar half only and leaves both packer lines untouched - so nobody edits the same two lines twice, and a `::class` change does not have to be reviewed on its way to being deleted.
    - Review the `\Debugbar::enable()` call in `app/Providers/AppServiceProvider.php:86`. **This is the real risk in this item, not the removal.** Once the provider is no longer hard-registered, the facade is absent wherever the dev package is - a `composer install --no-dev` host is exactly the case this TODO exists to unbreak - so the call needs a guard (`class_exists`, or the environment check it should have had) or it turns one breakage into another.
  - Verification: `composer install --no-dev --dry-run`, plus a boot under `APP_ENV=production`.
  - Expected changes: `config/app.php`, `app/Providers/AppServiceProvider.php`.

- [x] **TODO 26: Resolve duplicate route names** - DONE on `v1-patch`
  - Delivered in two passes. **First pass:** the `routes/web.php` `verification.verify` closure deleted (zero runtime change - the Fortify definition always won), `verification.notice` left alone, and `password.confirm` left with both definitions on the reasoning that both are live and differ only by HTTP method.
  - **That reasoning was wrong on one point, and the second pass corrects it.** Both definitions being live is true, but sharing a name makes `route:cache` - and therefore `artisan optimize` - impossible: `AbstractRouteCollection::addToSymfonyRoutesCollection()` treats the name as a unique key and throws `LogicException: Unable to prepare route [confirm-password] for serialization. Another route has already been assigned name [password.confirm].` This was **pre-existing, not introduced here**; running `route:cache` against `git show v1:routes/web.php` reproduces it byte for byte. The user hit it while smoke-testing the branch.
    - Fix: the POST definition is renamed `password.confirm.store`, following the Laravel convention that `password.confirm` names the GET form. The form action in `resources/views/auth/confirm-password.blade.php` points at the new name.
    - **No URL moves.** Both definitions keep URI `confirm-password` and their middleware (`auth, web` on the GET; `auth, throttle:6,1, web` on the POST). `route('password.confirm')` still generates `/confirm-password` - it now resolves to the GET definition, which is what the `password.confirm` middleware alias (`app/Http/Kernel.php:68`) always needed, since `RequirePassword` issues a **GET** redirect. It only ever worked because the two URIs are identical.
    - The route fixture gained one row (`password.confirm.store`) and `password.confirm` lost `POST`; `route:list` is otherwise unchanged against the TODO 03 baseline.
  - Tests: `RouteContractSnapshotTest`'s `verification.verify` tripwires inverted in the first pass; in the second, `test_both_password_confirm_definitions_survive_with_different_middleware` and `test_url_generation_resolves_password_confirm_to_the_post_definition` were rewritten, the duplicate guard now expects `[]`, and a new `test_the_route_table_survives_route_cache` calls `toSymfonyRouteCollection()` directly - the same code path as the command, without writing a cache file. **That test is the one that would have caught this originally.**
  - Needed:
    - **`verification.verify`: resolved empirically in TODO 03.** Only one entry reaches the routing table - `Route::get()` overwrites by `method + domain + uri`, and **the Fortify definition wins** (`Laravel\Fortify\Http\Controllers\VerifyEmailController@__invoke`, middleware `web, Authenticate:web, ValidateSignature, ThrottleRequests:6,1`). The closure at `routes/web.php:122` is **dead code that never executes** and can be deleted with zero runtime change - confirm the Fortify middleware stack above is the intended one first.
    - ~~`password.confirm` is defined twice in `routes/web.php`~~ **Answered: not intended, and not merely a style question.** URL generation pointed at the POST route while the `password.confirm` middleware alias (`app/Http/Kernel.php:68`) redirects with GET, and the shared name blocked `route:cache` outright. Split as described above.
    - `verification.notice` at `routes/web.php:74` uses a string callable while Fortify's own is commented out at `routes/fortify.php:82-86` with the note "disabled, it's generate problem" - resolve that properly.
    - **TODO 14 built the tripwires for this; they must be rewritten in the same change set.** `RouteContractSnapshotTest::test_the_route_files_contain_exactly_the_known_duplicate_names` and `test_the_web_php_verification_verify_closure_never_reaches_the_routing_table` will both fail the moment a duplicate definition is deleted - that failure *is* the intended, reviewable diff. TODO 14 also verified by experiment that removing the `verification.verify` closure leaves the main route snapshot green, so that deletion carries **zero** runtime change. The `password.confirm` case is different: both definitions are live, so any change there is a real behaviour change and `test_both_password_confirm_definitions_survive_with_different_middleware` / `test_url_generation_resolves_password_confirm_to_the_post_definition` must be updated to state the new intent. (Both were, in TODO 26's second pass.)
  - Expected changes: `routes/web.php`, `routes/fortify.php`, updated route contract fixture, updated `.docs/routes.md` and `.docs/fortify-routes.md`.

- [x] **TODO 27: Replace the removed Fortify password rule** - DONE on `v2-dev`
  - Delivered: `passwordRules()` returns `['required', 'string', Password::min(8)->mixedCase()->numbers(), 'confirmed']`, the unused import is out of `FinishRegistration`, three Hungarian translations came with it, and `tests/Feature/Auth/PasswordRuleTest.php` (12 tests) pins the rule. Suite: **1253 -> 1265 tests, 3334 assertions**. No pre-existing test failed, so no covered flow used a password the tightening rejects.
  - Written pin-first, and the pin paid for itself: the characterization test was green against the **old** rule, and the replacement turned exactly three cases over. Each one is a decision now stated in the diff instead of a silent change:
    1. **Tightening, approved by the user.** `PASSWORD1` was accepted and now is not. Fortify's `requireUppercase()` failed only on `Str::lower($value) === $value`, i.e. it asked that the password not be *all lowercase* - an all-uppercase password satisfied it. `mixedCase()` requires upper **and** lower. Only new passwords are affected (registration, reset, change, guest activation); stored passwords are untouched.
    2. **A loosening nobody asked for, found by the test.** `Password١` was rejected and now is accepted: Fortify's `requireNumeric()` matched ASCII `[0-9]`, while `Illuminate`'s `numbers()` matches the `\pN` unicode class. Not a risk - the entropy is no worse - but it is a behaviour change that would have shipped unnoticed.
    3. **The error message would have switched to English.** Both classes use the full English sentence as the translation key. Fortify's sentence is translated in `resources/lang/hu.json`; the three `Illuminate` sentences were present there as keys but **untranslated**, mapping to themselves. Translated in this change set.
  - **`ro.json` and `sk.json` are still untranslated for those three sentences** (`de.json` and `fr.json` already carry all three). Those two locales will show the English message. Left rather than machine-translated: the existing entries read as human work, and a wrong translation is worse than the English fallback.
  - The duplicated length check is gone as a side effect: the old list carried `min:8` next to a rule that already enforced `Str::length($value) >= 8`.
  - Needed:
    - `app/Actions/Fortify/PasswordValidationRules.php:5` and `app/Http/Controllers/FinishRegistration.php:14` import `Laravel\Fortify\Rules\Password`, which no longer exists in modern Fortify.
    - **Correction, measured: only one of the two imports is live.** `FinishRegistration.php:14` imports `Password` and never names it - the rules come from the trait, and the controller only calls `$this->passwordRules()` (`:41`). That import is a plain deletion. The single real call site is `PasswordValidationRules.php:16`.
    - Move to `Illuminate\Validation\Rules\Password`. ~~preserving the current `requireUppercase()->requireNumeric()` semantics~~ - **`min(8)->mixedCase()->numbers()` does not preserve them, it tightens them.** Fortify's `requireUppercase()` demands an uppercase letter; `mixedCase()` demands uppercase **and** lowercase. Existing accounts are unaffected until they change a password, but registration and password reset get stricter. Decide explicitly: take the tightening (the Laravel convention), or reproduce the old rule exactly.
    - **`Illuminate\Validation\Rules\Password` exists in Laravel 8.83** (added in 8.39), so this is executable today - it is not gated on the Phase 4 hop.
    - **Nothing in the suite pins the password semantics.** A grep for `requireUppercase` / `mixedCase` / `passwordRules` over `tests/` returns no assertion on rule content, so this is the one behaviour-changing item in the cheap-preparation group with no safety net. Write a characterization test **first** - the same pin-then-change discipline as TODO 04 through 07.1 - and let it be the diff that states the new intent.
    - Consumers to update: `CreateNewUser`, `ResetUserPassword`, `UpdateUserPassword`, `FinishRegistration` - all four go through the trait, so the rule itself changes in one place.
  - Expected changes: small, isolated, high-value. Existing auth tests cover the flows but not the rules.

- [x] **TODO 28: Move `env()` calls out of runtime code into config** - DONE on `v1-patch`
  - **Why it was pulled into this release.** `artisan optimize` had never completed on this codebase (see TODO 26): `route:cache` threw on the duplicate `password.confirm` name before `config:cache` could do any harm. The moment A7 fixed that, `config:cache` became something an operator would realistically run - and every item below turned from theoretical into live. Fixing the two together was not scope creep; shipping A7 without this would have handed operators a command that quietly disables HTTPS enforcement and bot protection.
  - **Measured, not assumed.** `php artisan config:cache` followed by `tinker` shows `env('USE_HTTPS')`, `env('USE_RECAPTCHA')` and `env('MAIL_FROM_ADDRESS')` all returning `NULL`, while `config('security.use_https')` and friends keep their values.
  - Delivered, all 24 occurrences:
    - **New `config/security.php`** with `use_https` and `use_recaptcha`, both parsed with `filter_var()` so `1`, `true`, `on` and `yes` all work. `HttpsProtocol` and `CheckRecaptcha` read the config; so do the six Blade views that render the captcha field. The orphaned `events.use_recaptcha` key - defined in a *calendar* config file and read by nothing - moved here rather than staying as a second home. **No `.env` change is needed on deployed hosts:** the variable names are unchanged.
    - **The six notifications** now read `config('mail.from.address')` / `config('app.name')`.
    - **The `.env`-editing screens read the `.env` FILE.** `Admin\Settings`, the installer's basics form and its database form all edit `.env`, so `env()` was the wrong source twice over: cached configuration would have shown them **blank fields**, and `saveOthers()` writes every `from_env` key back unconditionally - one click would have wiped `APP_NAME`, `APP_URL` and every `MAIL_*` value out of the file. New `setEnvironment::value()` parses the file with Dotenv's array-backed reader (same quoting rules as Laravel's own loader, no `$_ENV`/`putenv` side effects) and is invalidated whenever the file is written.
    - `MailController` and `StaticPagesSetupSeeder` use `config('app.locale')`, which is defined as exactly `env('APP_LANG', 'en')`.
  - **Standing guard: `tests/Feature/ConfigCacheSafetyTest.php`.** It tokenizes every PHP file under `app/`, `routes/`, `database/` and `resources/views/` and fails on any `env()`/`getenv()` call. Tokenizing, not grepping, because the fixes' own comments quote the old `env('USE_HTTPS')` form. **Blade views are compiled first** - a control experiment proved they had to be: an `@if (env('PROBE'))` planted in `auth/login.blade.php` sailed straight through the uncompiled scanner, because everything outside `<?php` is one `T_INLINE_HTML` token.
  - Tests inverted: `NotificationEnvFallbackTest`'s whole "what config:cache causes" section (the hard-failure send now succeeds), plus `HttpsProtocolTest` and `CheckRecaptchaTest`'s `test_the_flag_is_read_from_the_environment_on_every_request` - each replaced by a case proving the environment variable alone no longer moves the middleware. The truthy/falsy spelling coverage moved onto `config/security.php` itself, which is where that parsing now lives.
  - Original notes:
    - 25 occurrences outside `config/`, notably `app/Http/Middleware/HttpsProtocol.php:19`, `app/Http/Middleware/CheckRecaptcha.php:20`, `app/Http/Livewire/Admin/Settings.php:77,78,81`, `app/Http/Controllers/Setup/MailController.php:67`, 7 in Blade views (`auth/login`, `auth/register`, `auth/forgot-password`, `livewire/groups/update-group-form`), and 6 in notifications.
    - **Highest priority: the mailer ones.** `->replyTo(env('MAIL_FROM_ADDRESS'))` in `EventCreatedNotification.php:68`, `EventDeletedNotification.php:66`, `EventStatusChangedNotification.php:71`, `EventUpdatedNotification.php:75`, `UserWillBeAnonymizeNotification.php:62`, and `->bcc(...)` in `UserRoleIsGroupCreatorNotification.php:45`. Symfony Mailer (Phase 4) throws on a null address; SwiftMailer did not.
    - The two middleware occurrences are now **covered and provably runtime-dependent**: `HttpsProtocolTest` and `CheckRecaptchaTest` (TODO 09) toggle `$_SERVER['USE_HTTPS']` / `$_SERVER['USE_RECAPTCHA']` mid-test and get different behaviour from the same request, which is exactly what `config:cache` would freeze. Both files carry a `test_the_flag_is_read_from_the_environment_on_every_request` case that must be rewritten once the value moves into config - treat that rewrite as part of this TODO.
    - Note a second defect in `HttpsProtocol:19` while you are there: the comparison is `env('USE_HTTPS', "false") == "true"`, i.e. against the literal string. `USE_HTTPS=1` therefore does **not** enable the redirect. Pinned by `test_a_truthy_but_non_string_true_value_does_not_enable_the_redirect`.
  - Expected changes: new/extended config keys, `env()` confined to `config/*.php`, `config:cache` becomes safe.

- [x] **TODO 29: Replace `$dates` with `$casts`** - DONE on `v2-dev`
  - Delivered: the property is gone from both models, `GroupUser` carries explicit `datetime` casts for `created_at`/`updated_at`, and `tests/Unit/Models/DateCastingTest.php` (5 tests) pins the behaviour. `.docs/models.md` updated. Suite: **1265 -> 1270 tests, 3343 assertions**.
  - Written pin-first again, and here the pin was the whole point: the edit is two lines, but nothing in the suite asserted that these columns come back as dates. The four behavioural cases were green **before** the change and stayed green after - that, not the diff, is the evidence the swap was neutral.
  - `Group` needed no `$casts` array: `SoftDeletes::initializeSoftDeletes()` already writes `deleted_at` into the casts when it is absent, so the `$dates` line there was redundant before it was removed. `GroupUser` merged into its existing array next to `signs` and the `encrypted` `note` - a test asserts those two still work, because a mangled array would silently store the note in clear text.
  - **The guard test's first version passed the wrong thing.** `assertStringNotContainsString('protected $dates', ...)` over the source file also matched the explanatory comment written next to the removal, so it failed *after* the fix. It now asks reflection whether the model itself declares the property - `$dates` still exists on the `Model` base class in Laravel 8, so only the declaring class is meaningful. Verified to fail when the property is put back.
  - Needed:
    - `app/Models/Group.php:16` (`['deleted_at']` - already handled by `SoftDeletes`, likely just removable) and `app/Models/GroupUser.php:29` (a `Pivot` model, `['created_at','updated_at','deleted_at']`).
    - **The two cases are not symmetric.** `Group` has no `$casts` array at all, so nothing has to be created there. `GroupUser` already has one (`'signs' => 'array'`, `'note' => 'encrypted'`, `:31-34`) - this is a merge into an existing array, and it sits next to an `encrypted` cast, which TODO 13's round-trip tests guard.
    - `GroupUser` is a custom `Pivot` with `SoftDeletes` and `$incrementing = true`. Its timestamp handling comes from `AsPivot`, not from a plain model, so moving `created_at`/`updated_at` into `$casts` there is not a self-evident no-op - assert it, do not assume it. `deleted_at` is covered by `SoftDeletes` on both models.
  - Expected changes: two model edits and `.docs/models.md`; removes a Laravel 10 blocker early.

- [x] **TODO 30: Fix filesystem disk configuration** - DONE on `v1-patch`
  - Delivered: the `web` disk root is `public_path()`, every disk declares `'throw' => false`, and the avatar URL prefix in `livewire/groups/messages.blade.php` moved with it - the pair is only correct together, which is what `AvatarGenerationTest` now pins. **Release note:** deployed hosts carry their generated avatars under `public/public/avatars/`; they are regenerated on first render, so no data migration is needed, but the old directory can be removed by the release hook.
  - Needed:
    - `config/filesystems.php` `web` disk root is the bare relative path `'public'` instead of `public_path()`. It resolves against the PHP working directory and is fragile under Flysystem 3. Used by `app/Http/Livewire/Groups/Messages.php:173,176`.
    - Add an explicit `'throw'` value to every disk, since Flysystem 3 changes `exists()`/`delete()` semantics.
    - Review the `news_files` disk (private visibility, explicit permission maps) used in 8 places.
  - Expected changes: `config/filesystems.php`, possibly the `exists()`-then-`delete()` guards in `GroupNewsDelete.php` and `NewsEdit.php`.

- [x] **TODO 31: Fix boot-time database access and silent exception swallowing** - DONE
  - Delivered on 2026-08-10, one commit. Suite: **1273 -> 1295 tests, 3564 -> 3622 assertions, 2m22s, green.** `artisan optimize` still completes.
  - **The replacement, in full:** `App\Support\Settings\ApplicationSettings`, a container singleton that owns the one and only read of the `settings` table. It caches the `name => value` map under `application_settings` with `rememberForever`, memoizes it for the request, and `App\Observers\SettingsObserver` invalidates both on every `saved`/`deleted`. That observer is the `StaticPageObserver` pattern applied a second time - and it fits even better here, because **every** setting write in the application already goes through `Settings::updateOrCreate()` (`Admin\Settings` in seven places, `CoreSettingsSeeder`, the installer), so one hook covers all of them. `AppServiceProvider::boot()` shrank from ~70 lines to two calls.
  - **The maintenance workaround is gone, which was the point.** `SetLocale` asks the repository instead of `config('settings_maintenance')`, so the switch is effective on the next request rather than the next boot, and the four TODO 09 tests now write a real `Settings` row. A fifth was added that toggles it **on and then off** in one test - the invalidation has to work in both directions or the "off" half hangs. Control experiment: putting `Config::get('settings_maintenance')` back fails exactly the three cases that assert a redirect, and commenting out the observer registration fails 12 tests across the two files.
  - **The strongest single result.** Emptying the `SetLocale` catch again - the state the code shipped in - makes the guest page answer **500**, not 200. That is the second, misleading error the TODO predicted: the `View::share('sidemenu', ...)` never ran, and four Blade files `@foreach` over that variable. So this was not a hypothetical logging improvement; the swallowed exception was converting every menu-query failure into an unexplainable white page. The middleware now logs the original and shares an empty collection.
  - **What the delivery found that the plan had not.** A broken `settings.languages` blob took *everything* with it: `json_decode` returned null, `count(null)` threw a `TypeError`, and the same silent catch ate it - so one bad JSON string in one row disabled the entire Config population, registration flag and all. `languages()` now falls back to the default locale and logs a warning; `test_a_broken_languages_blob_no_longer_takes_the_other_settings_with_it` pins it.
  - **Two decisions worth keeping.** (1) The debugbar branch deliberately stayed in `AppServiceProvider::boot()` rather than moving into the repository: it must sit *outside* the repository's `\Throwable` catch, or a missing class on a `--no-dev` host would fail silently again - exactly the TODO 25 trap. `DevDependencyIsolationTest` greps that file for `bound('debugbar')` and is unchanged. (2) Only the *maintenance* read moved to the repository. `settings_default_language` and `available_languages` stay on `Config`, because `FeatureTestCase` boots before it seeds, so the whole test base is written against the boot-time values - moving those is a separate, much larger job.
  - `'maintenance' => false` joined the defaults. It was never there, so `config('settings_maintenance')` silently resolved to `null` on any install without the row.
  - The failure is **not** cached - only a successful read is - so a transient database error cannot freeze the defaults behind a `rememberForever` entry. `Cache::shouldNotReceive('put')` pins that.
  - Expected changes: as delivered - 2 new files under `app/`, `AppServiceProvider`, `EventServiceProvider`, `SetLocale`, 1 new test file (18 cases), `SetLocaleTest` (+4 cases), and `.docs/middleware.md`, `.docs/observers.md`, `.docs/models.md`.
  - Needed:
    - `app/Providers/AppServiceProvider.php::boot()` runs `ModelsSettings::all()` on every request inside a bare `catch (\Exception $e) {}`, then `Config::set()`s ~10 runtime keys. The silent catch will hide upgrade failures for the rest of this roadmap.
    - At minimum: log the exception instead of swallowing it. Preferably: defer the lookup or cache it.
    - **A second silent catch of the same kind, found by TODO 09:** `SetLocale:65-79` wraps the whole side-menu lookup in `catch (\Throwable) {}`. If the query fails, `View::share('sidemenu')` never happens and the views meet an undefined variable, while the original error vanishes without a trace.
    - **Consequence of the boot-time read, worth stating explicitly:** because `settings_maintenance` is populated from the `Settings` table during `boot()`, **maintenance mode cannot be turned on for the current request** - writing the settings row has no effect until the next one. The TODO 09 tests therefore drive it with `Config::set()`, and that workaround should disappear when this TODO lands.
  - Expected changes: `app/Providers/AppServiceProvider.php`, `app/Http/Middleware/SetLocale.php`.

- [ ] **TODO 32: Squash the migration history**
  - Needed:
    - 97 migrations, 16 `->change()` calls across 8 files, 68 `dropColumn`/`renameColumn` occurrences. Squashing to a single schema dump removes the `doctrine/dbal` dependency risk and the Laravel 11 native-`change()` attribute-loss trap in one move.
    - **Before squashing**, verify the resulting schema against production for the encrypted columns (`text` widening from `2022_04_12_*` and `2022_06_05_*`) - nullable, default, charset, and collation must survive.
    - The TODO 13 encrypted round-trip tests must pass against the squashed schema.
  - Expected changes: `database/schema/*.sql` baseline, archived migrations, green test suite on a freshly migrated `kozter_testing`.

- [x] **TODO 33: Clean up middleware naming and dead code** - MOSTLY DONE on `v1-patch`
  - Delivered: the middleware rename, the `RouteServiceProvider` leftover, the `helpers.php:23` guard, and defect 1 (the `is-groupCreator` gate typo, with the user's go-ahead - `AuthorizationGateTest` inverted, and a new test records that `translator` now receives those newsletters too, since the gate admits it).
  - **Still open: defect 2.** `isNotHelper()` and `isNotEditor()` remain identical - the user did NOT approve giving the `helper` role write access, so `test_a_helper_cannot_open_the_user_editor_either` stays as it is. Revisit as a product decision.
  - Needed:
    - Rename `app/Http/Middleware/setUserLastActivity.php` to `SetUserLastActivity` (PSR-4 tolerates the current name locally, but case-sensitive deploy targets will not).
    - ~~Decide the fate of `RedirectIfUnansweredTerms`, which is never registered in `app/Http/Kernel.php`.~~ **Decided in TODO 16: it is deleted with the rest of the consent feature, in TODO 33.2.**
    - `app/Providers/RouteServiceProvider.php` still passes `->namespace($this->namespace)` where the property is commented out.
    - **Three naming defects found by TODO 07.2, all pinned by tests today:**
      1. **`helpers.php:68` asks for `can('is-groupCreator')` but the gate is `is-groupcreator`.** Gate names are case-sensitive, so the branch is always false and a plain `groupCreator` never receives the newsletters targeted at them - only `mainAdmin` does, through `is-admin`. A one-character fix that **changes production behaviour**, so it needs the user's go-ahead. Affects `Admin\AdminNewsletters`, `Partials\NavBar`, `Partials\SideMenu`; pinned by `AuthorizationGateTest::test_a_group_creator_never_receives_group_creator_newsletters`, which must be inverted when fixed.
      2. **`Groups\ListUsers::isNotHelper()` and `isNotEditor()` are literally identical** (`:792-798`), both allowing only `['admin','roler']`. The `helper` role is therefore not a helper by this check, and `maxRoles()`'s `member`/`helper` branches are unreachable. Decide whether `helper` was meant to have write access; pinned by `test_a_helper_cannot_open_the_user_editor_either`.
      3. **`helpers.php:23` guards on `pwbs_check_group_admins` while defining `pwbs_check_group_other_admins`** - the `function_exists()` check never matches its own function.
  - Expected changes: `app/Http/Kernel.php`, middleware files, `app/Helpers/helpers.php`, `app/Http/Livewire/Groups/ListUsers.php`, `.docs/middleware.md`.

- [x] **TODO 33.1: Make `CheckRecaptcha` survive a Google outage** - DONE on `v1-patch`
  - Policy chosen with the user: **fail-open**. A `ConnectionException` lets the request through and logs it; availability beat bot protection. An explicit 5s timeout was added - there was none, so a hung Google endpoint held the PHP worker on the login path. `CheckRecaptchaTest`'s fatal-error case rewritten.
  - Context: found by TODO 09. `app/Http/Middleware/CheckRecaptcha.php:22` calls `Http::asForm()->post()` with **no try/catch**. HTTP error codes come back as a `Response` and are handled correctly, but a connection failure (timeout, DNS, network) throws `Illuminate\Http\Client\ConnectionException`, which nothing catches. The middleware guards `POST /login`, `POST /register` and `POST /forgot-password`, so **a Google outage returns 500 on all three** - nobody can log in, register or reset a password until Google comes back.
  - Dormant today only because `USE_RECAPTCHA=false`. **This must be resolved before recaptcha is ever switched on**, independently of the upgrade.
  - Needed:
    - Catch `ConnectionException` and decide the policy explicitly: **fail-open** (let the request through, log the failure - availability over bot protection) or **fail-closed** (treat it as a bot, so the user gets the captcha error instead of a 500 - protection over availability). Either is defensible; the current behaviour is neither.
    - Set an explicit timeout on the request; there is none today, so a hanging Google endpoint holds the PHP worker.
    - `CheckRecaptchaTest::test_a_connection_failure_escapes_the_middleware_as_a_fatal_error` pins the present behaviour and must be rewritten to match whichever policy is chosen.
  - Expected changes: `app/Http/Middleware/CheckRecaptcha.php`, one test rewritten.

- [ ] **TODO 33.2: Replace `dialect/laravel-gdpr-compliance` with in-house code**
  - **This is the execution of the TODO 16 decision.** It sits in Phase 3 rather than Phase 8 because the package's `illuminate/support: >=5.5` requirement is unbounded and therefore blocks no Composer resolution, while the replacement code uses only stable Eloquent APIs - it can be written and proven green on Laravel 8 today, which takes one package out of every subsequent hop. Read TODO 16 first; it carries the measurements, the three vendor-trait defects, and the list of behaviour the replacement must reproduce.
  - Needed:
    - Move the two traits into `app/Support/Gdpr/` (`Portable.php`, `Anonymizable.php`, next to the existing `AnonymizationPolicy.php`) and the form request into `app/Http/Requests/GdprDownload.php`. Re-point the imports in `User`, `Group`, `Event` and `GdprController`. **Keep `Anonymizable` on `Group` and `Event`** - `User::anonymize()` recurses into `$gdprWith` and calls `anonymize()` on those models; removing the trait fatals. Add a comment saying so, since the empty `$gdprAnonymizableFields` reads as dead code.
    - Decide explicitly whether to carry the three defects across or fix them: the broken `$modelChecker` recursion guard (pushes values, tests keys), the `parseValue()` closure branch that calls the closure's *return value*, and the dead `setVisible()` branch. Fixing the guard is safe today (the cascade is one level deep); fixing or deleting the closure branch is free (no closures are used). Whatever is chosen, `AnonymizationTest` must state it.
    - Register `gdpr-download` in `routes/web.php` inside a group built from `config('gdpr.uri')` and `config('gdpr.middleware')`, so the route contract is byte-identical (`POST gdpr/download`, `web` + `Authenticate`). `tests/Fixtures/route-contracts.json` must not change for this route.
    - **Drop the consent half** (TODO 16 decision): delete the `gdpr-terms`, `gdpr-terms-accepted` and `gdpr-terms-denied` routes, the three matching `GdprController` methods, `resources/views/gdpr/message.blade.php`, and `app/Http/Middleware/RedirectIfUnansweredTerms.php`. Also delete `GdprController::anonymize($id)` - no route has ever pointed at it. `users.accepted_gdpr` **stays** as a column; no data migration. This closes the `RedirectIfUnansweredTerms` question left open in TODO 33.
    - Delete `app/Console/Commands/PackageAnonymizeInactiveUsers.php` and its `Kernel::$commands` entry. With the package gone, `gdpr:anonymizeInactiveUsers` and its 00:00 schedule disappear, and `gdpr:anonymize-inactive` at 07:00 becomes the only anonymizer - which retires the divergence for good.
    - Remove `dialect/laravel-gdpr-compliance` from `composer.json`. `config/gdpr.php` stays (it is a published, project-owned file); check that nothing relied on the vendor `mergeConfigFrom` default.
    - **The guard must stay on `User::anonymize()`.** TODO 16 measured **five** call paths, not the three previously documented - the two commands, the profile request, `DeleteGroupDataProcess::handle()` and the `2024_12_01_223022_anonymize_old_data` migration.
  - Verification:
    - The 68 tests in `tests/Feature/Gdpr/` are the acceptance criteria and should pass **unchanged**, except the two that name the package's own structure: `ConsentTermsTest` (6) and `UnansweredTermsMiddlewareTest` (4) are deleted with the feature, and `AnonymizeCommandDivergenceTest` (9) is rewritten down to what still exists.
    - `SchedulerRegressionTest` 13 -> 12 entries; the route fixture 70 -> 67 app routes; `RouteContractSnapshotTest::test_every_named_route_is_accounted_for` 78 -> 75.
    - Control step: run the GDPR suite **before** removing the package, with the imports already re-pointed at `app/Support/Gdpr/`. Green there proves the traits were copied faithfully, separately from the removal.
  - Expected changes: 3 new files under `app/`, 5 deletions, `composer.json`, `routes/web.php`, `app/Console/Kernel.php`, the route fixture, 3 test files removed or rewritten, and `.docs/routes.md`, `.docs/commands.md`, `.docs/middleware.md`, `.docs/models.md`.

- [ ] **TODO 33.3: Remove `joedixon/laravel-translation` and build an in-house translation editor**
  - **This is the execution of the TODO 17 decision.** It sits in Phase 3 rather than Phase 8 because the package's `require` block is **empty**, so it blocks no Composer resolution at any hop, while the replacement uses only stable filesystem and Eloquent APIs - it can be written and proven green on Laravel 8 today, which takes one package, 417 KB of Vue 2 / Tailwind 0.6 assets and 5 injected global functions out of every subsequent hop. Read TODO 17 first; it carries the measurements, the corrected consumed surface and the split-brain finding.
  - Needed:
    - **Write the editor first, prove it green, and only then remove the package.** Two independent failure sources otherwise, exactly as TODO 33.2 warns.
    - New `app/Support/Translation/LangFiles.php`, a thin repository next to the `app/Support/Gdpr/` precedent:
      - `locales()` reads the **`settings.languages` JSON blob**, i.e. the application's own registry, not the filesystem. This is what closes the split brain at its source.
      - `groups(string $locale)` lists the `{locale}/*.php` basenames plus a `json` pseudo-group for the root `{locale}.json`.
      - `read()` / `write()` flatten with `Arr::dot()` and write back in `var_export()` shape. **Decide and record whether saving one key reformats the whole file** - `resources/lang/en/*.php` is already `var_export`-shaped (the old UI wrote it), but `resources/lang/hu/*.php` is hand-written with `return [` and four-space indent, and hu is the only complete locale.
      - `ensureLocale()` creates the directory, which `Admin\Settings::languageAdd()` has never done.
      - **Resolve the path through `App::langPath()`, never `resource_path('lang')`.** That is what makes TODO 38 a no-op for this code.
    - Grow `app/Http/Livewire/Admin/Translation.php` from the 18-line shim into the editor: locale and group selectors, search filter, source-locale reference column, per-key save, add key, add locale. Keep it an `AppComponent` subclass and keep the AdminLTE/Bootstrap markup used by every other admin page - the vendor UI's Tailwind chrome is not being reproduced.
    - **Salvage before deleting:** `resources/lang/vendor/translation/hu/translation.php` (52 lines) and `hu/errors.php` (6 lines) are project-authored Hungarian strings that do not exist upstream. Fold the reusable labels into the app's own `resources/lang/hu/` tree; the other four locales there are byte-identical to vendor and can go.
    - Delete: the `joedixon/laravel-translation` line in `composer.json`, `config/translation.php`, `resources/views/vendor/translation/` (12 files), `public/vendor/translation/` (~417 KB), `resources/lang/vendor/translation/`, and the 7 `languages.*` entries from `tests/Fixtures/route-contracts.json`.
    - **Leave `routes/web.php:195-197` untouched.** `admin.translate` keeps its `can:is-translator` + `password.confirm` stack, which is what preserves production access for `translator` and `mainAdmin`.
    - **Decide what happens to the orphaned `languages` and `translations` tables.** Both migrations are applied (batch 1) and load from `vendor/`; once the package is gone they vanish from the migration path and the tables remain in the database with two seeded rows. Either add a drop migration or record deliberately that they stay until TODO 32 squashes.
  - Verification:
    - **The write path is a live hazard in tests.** The editor writes real files under `App::langPath()`, and under `APP_ENV=testing` that is the **real `resources/lang`** - the same trap TODO 07 hit with `Admin\Settings::saveOthers()` rewriting `.env.testing`. `LangFiles` must take an injectable base path and the tests must point it at a temporary directory. Without that, the suite shreds its own language files.
    - `tests/Feature/RouteAdditionalBehaviorRegressionTest.php:43-73` is the only test that renders the vendor UI; rewrite it against the new component and keep all three assertions intact (403 / `password.confirm` redirect / 200).
    - New `tests/Feature/Livewire/AdminTranslationEditorTest.php` for the read/write round trip, both file shapes (PHP group and root JSON), the source-locale column and the add-key / add-locale paths. `AdminComponentsTest:294` and `LivewireRouteMountedComponentsTest:90` stay as they are.
    - Route fixture 70 -> 63 app routes. `RouteContractSnapshotTest::test_every_named_route_is_accounted_for` needs no edit - it is a set difference with no hardcoded count; only the exact-snapshot test forces the fixture edit.
    - `migrate:status` and a `RefreshDatabase` boot both lose two tables; confirm no test asserted on them.
  - Expected changes: 1 new file under `app/Support/Translation/`, 1 component and 1 view rewritten, 4 directories and 1 config deleted, `composer.json`, the route fixture, 2 test files rewritten or added, and `.docs/routes.md`, `.docs/components.md`.

- [x] **TODO 33.4: Move `laraupdater` onto the project's own fork** - DONE
  - **This is the execution of the TODO 18 decision**, and it was done in the same change set. It sits in Phase 3 rather than Phase 8 because the installed 1.0.2 declares no framework constraint, so it blocks no Composer resolution at any hop, and every change is Laravel 8 compatible and proven green there today. Read TODO 18 first; it carries the measurements.
  - The fork is at `github.com/MDylan/laraupdater`, commit `f807b05`, tagged **`v2.0.0`**; `master`, `v2` and the tag all point at it. The project requires it through a `repositories` entry of `{"type": "vcs", "url": "https://github.com/MDylan/laraupdater"}` at `^2.0`, and `composer.lock` records the git ref, not a path install.
  - Delivered on the fork (`v2`):
    - Package renamed `pcinaglia/laraupdater` -> **`mdylan/laraupdater`**; namespace `pcinaglia\laraUpdater` -> **`MDylan\LaraUpdater`**, which also fixes the capital-`U` / lowercase-PSR-4 mismatch.
    - `require` now declares `php ^8.0` and `illuminate/support ^8.12|^9.0|^10.0|^11.0|^12.0|^13.0`. **`^8.0`, not `^8.1`**: `composer.json:78-80` still pins `config.platform.php` to `8.0.9`, so resolution runs against 8.0.9 until TODO 24 removes it.
    - All five working-tree-only hand edits from TODO 18 are now in the repository, plus the three the fork already had.
    - All three routes are behind `config('laraupdater.middleware')` and **named** (`laraupdater.check`, `laraupdater.currentVersion`, `laraupdater.update`). URIs unchanged, so old links keep working.
    - Fixed: unqualified `catch(Exception $e)`; `loadTranslationsFrom()` pointing at a nonexistent `src/lang`; lang publishing to `lang_path()` on Laravel 9+ with a `resource_path('lang')` fallback; the controller's dependency on the host app's `App\Http\Controllers\Controller`; a null-`Auth::user()` fatal in `checkPermission()`; the `/../tmp` default for a path that is resolved against `base_path()`.
    - New `request_timeout` (10s) and `download_timeout` (60s) config keys. **`file_get_contents()` was kept** rather than moved to the `Http` facade: a stream context bounds it in one line, the package stays dependency-free, and a plain directory path as `update_baseurl` remains a working test seam - which is what let the characterization tests be written once instead of twice.
  - Delivered in the app:
    - `composer.json`, `config/app.php:183`, and the 6 FQCN call sites in `app/View/Components/UpdateNotification.php`, `resources/views/components/update-notification.blade.php`, `resources/views/layouts/partials/footer.blade.php` and `resources/views/livewire/admin/settings.blade.php`.
    - The hardcoded `href="/updater.update"` became `route('laraupdater.update')`. The old form broke on any subdirectory deployment.
    - Deleted `resources/views/vendor/laraupdater/laraupdater_check_update.blade.php` - byte-identical to the vendor original and referenced by nothing.
  - Verification, as run:
    - **Control step first.** `tests/Feature/Updater/UpdaterContractTest.php` was written against the **old** vendor code and run before anything changed: **23 tests, 18 green, 5 red**. The 18 prove the characterization is faithful (trim, `version_compare`, the `previous_version` chain, one channel read per render, survival of an unreachable channel, both component render paths). The 5 red are exactly the intended targets - `updater.check` and `updater.currentVersion` accepting a guest **and** a non-admin, and the hardcoded update link. `updater.update` passed both, confirming it alone was guarded.
    - After the switch: **23/23 green.** Two further cases were then added on the new code, because the one-level `previous_version` fixture under-described the real chain: `test_the_previous_version_chain_walks_back_more_than_one_step` (1.1.5 installed, channel offers 1.2.0 -> 1.1.7 -> 1.1.6) and `test_the_chain_stops_at_the_first_step_that_is_already_installed`. Both confirm the recursion walks **backwards** and returns the **earliest still-pending** release, so one `update()` run installs exactly one step and reaching the newest release takes one run per step. File total **25 tests**.
    - `RouteContractSnapshotTest` gained `test_the_laraupdater_vendor_routes_match_the_snapshot` and the three names entered `tests/Fixtures/vendor-route-contracts.json`. Two structural edits were needed and are worth knowing about: `test_the_livewire_vendor_routes_match_the_snapshot` compared the **whole** vendor fixture against `livewire.`-prefixed routes, so a new `fixtureSubset()` helper now slices it per prefix; and `currentRouteContracts(null)` excluded only `debugbar.` and `livewire.`, so `laraupdater.` had to join that list or the app-owned snapshot would have swallowed it.
    - `php81 artisan route:list --path=updater` shows all three with `web`, `App\Http\Middleware\Authenticate`, `Illuminate\Auth\Middleware\Authorize:is-admin`.
    - Full suite **983 tests, 2909 assertions, ~2:48, green** (was 957).
  - **Finding: a Composer VCS repository takes the package name from the DEFAULT BRANCH, not from the tag.** Pushing `v2` and tagging `v2.0.0` was not enough. Composer read the tag - the API calls all returned `[200]`, so this was never a network problem - but registered every version under the name in `master`'s `composer.json`, which was still `pcinaglia/laraupdater`. The resolver then reported `requires mdylan/laraupdater ^2.0, found ... in the lock file but not in remote repositories`, and `composer show -a mdylan/laraupdater` said `Package not found`, both of which point away from the real cause. `-vvv` is what showed it: `Reading composer.json of pcinaglia/laraupdater (v2.0.0)`. Fixed by fast-forwarding `master` onto `v2`. **Consequence to remember:** the pre-rename tags (`1.0`, `1.0.1`, `1.0.2`) still carry the old name in their own `composer.json`, so Composer skips them on a name mismatch. Only the `2.x` line is resolvable, which is the intent.
  - **Environment note, not a project defect: HTTPS verification is broken on this workstation.** **Avast Web/Mail Shield** intercepts TLS (`issuer=CN=Avast Web/Mail Shield Root` on `api.github.com`) and its root CA is absent from `C:\laragon\etc\ssl\cacert.pem`, which is what PHP's `openssl.cafile`/`curl.cainfo` point at. Neither Composer nor git can verify **any** HTTPS source here - Packagist included, not just this fork. **Resolved on 2026-08-07.** The Avast root (thumbprint `E6DC7372C16E9B68D968FB31C7E5A6320124AFC3`, valid to 2040-01-01) was appended to `C:\laragon\etc\ssl\cacert.pem` under a commented header, with the original kept as `cacert.pem.bak-20260807`. This lowers nothing: the root is already in the Windows Root store, so the machine trusted it system-wide already - the PEM bundle was simply out of step. Git does **not** read that bundle (it ships its own at `C:\Program Files\Git\mingw64\etc\ssl\certs\ca-bundle.crt`, which needs elevation to edit), so `git config --global http.sslCAInfo` now points at the Laragon bundle too - one file to maintain instead of two. Verified: `composer diagnose` reports https connectivity to Packagist and the GitHub rate limit OK, PHP's curl and openssl stream wrappers both verify, and `git ls-remote` works, all with no environment variables set. **One caveat: a Laragon PHP or bundle update replaces `cacert.pem` and silently drops the block.** If HTTPS starts failing again after a Laragon upgrade, this is why.
  - **Two operational notes that must not be lost:**
    1. `vendor/` is git-tracked by force-add past `.gitignore`. The new `vendor/mdylan/laraupdater` will therefore **not** be picked up by a plain `git add` - it needs `git add -f`, or it will be missing from the release zip and every updated install will fatal.
    2. The release that ships this must carry an `upgrade.php` whose `main()` deletes `vendor/pcinaglia/`. `install()` never deletes, so the dead tree would otherwise stay on every deployed host forever. **Written and verified: `release/upgrade.php`**, with `release/README.md` documenting the hook contract. It removes `vendor/pcinaglia/`, the orphaned published view at `resources/views/vendor/laraupdater/`, and `bootstrap/cache/packages.php` / `services.php` - the last two belt-and-braces, since `optimize:clear` reaches them a few lines later but a failed Artisan call would otherwise leave a manifest naming a class that no longer exists. Verified against the booted application with the targets planted: every branch exercised (directory tree, plain file, "already gone"), idempotent on a second run, and no collateral damage to `vendor/mdylan/`, the sibling `resources/views/vendor/*` directories or the published `laraupdater` language files. It is a one-off - once 1.1.6 has reached every install, empty `main()` or drop the file so later archives stop carrying it.
  - **Follow-up, same branch: the ceiling on top of it.** The fork inherited the package's one and only update condition - "the channel advertises something newer" - and nothing else. `v1-patch F` adds the major-version limit *above* the package, in project code, and leaves `vendor/mdylan/laraupdater` untouched (no new tag, no `composer.lock` change). See "Update branch ceiling (v1-patch F)" in the branch section above; the ops constraint it introduces - two manifests, chained by `previous_version`, when the new major ships - is written down in `release/README.md`.
  - Expected changes: as delivered.

- [ ] **TODO 33.5: Replace `protonemedia/laravel-verify-new-email` with in-house code**
  - **This is the execution of the TODO 19 decision.** It sits in Phase 3 rather than Phase 10 for a reason that differs from 33.2/33.3/33.4: this package's constraint really **is** bounded (`illuminate/support ^8.67||^9.0` in the installed 1.6.0), so Composer really does fail on it - at **Phase 5**, not Phase 10. But that half is a lock bump, not a decision, and the replacement code is framework-neutral, so writing it here removes the package from the Phase 5, 8, 9 and 10 resolutions in one move. Read TODO 19 first; it carries the measurements and the five defects.
  - **Acceptance criteria already exist: the 30 tests in `tests/Feature/NewEmail/` (TODO 19.1).** They were written against the vendor code, so they are the before-and-after comparison. Twenty-five must stay green untouched; the five in `PendingEmailKnownGapsTest` are the ones the replacement is allowed - and for two of them, required - to break.
  - Needed:
    - **New code in `app/`**: `App\Models\PendingUserEmail` (the `forUser` scope, `activate()`, `verificationUrl()`), a `MustVerifyNewEmail` equivalent under `App\Support\Email\`, a thin controller with `throttle:6,1`, and two Mailables. Register `pendingEmail.verify` in `routes/web.php` with `web, signed` - keeping the name and URI so deployed links in flight keep working.
    - **Keep `config/verify-new-email.php`** and its keys (`redirect_to`, `login_after_verification`, `model`, the two mailable classes), re-pointed at the new classes. The `route` key loses its meaning once the route is app-owned; drop it and delete the branch that read it.
    - Fix defect 4 while rewriting: `activate()` must check that the address is still free and fail into a flash message instead of a `SQLSTATE[23000]` 500.
    - Fix defect 5: give `verifyFirstEmail` the same `@lang()` treatment `verifyNewEmail` already has, and add the missing `email.verifyFirstEmail.*` keys to `hu` and `en`.
    - Fix defect 3: give `pending_user_emails.user_id` a cleanup path - the simplest is the same `UserObserver` that already exists.
    - Drop `protonemedia/laravel-verify-new-email` from `composer.json`. The `pending_user_emails` table and its migration **stay** - no data migration, same shape.
    - Update the import at `app/Models/User.php:18,22`. The four call sites keep their names, so `UpdateUserProfileInformation`, `User\Profile` and both Blade views need no edit - **verify that, do not assume it**.
  - **Defects 1 and 2 are NOT fixed here - they belong to TODO 33.2**, where `User::anonymize()` is rewritten. If 33.2 has already shipped by then, its `clearPendingEmail()` call has to be re-pointed at the new trait in this change set.
  - **The release note that must not be lost**, the same shape as TODO 33.4: `install()` never deletes, so the release carrying this must extend `release/upgrade.php` to remove `vendor/protonemedia/` and `resources/views/vendor/verify-new-email/` from deployed hosts.
  - Expected changes: ~250-300 lines under `app/`, one route registration, `config/verify-new-email.php` re-pointed, `composer.json`, two language files, `release/upgrade.php`, and the `.docs` entries added by TODO 19.

- [x] **TODO 33.6: Replace `rakibdevs/openweather-laravel-api` with a direct `Http::` client** - DONE on `v1-patch`
  - Delivered as described, plus the coverage that was the real reason for the swap: `tests/Feature/Weather/OpenWeatherClientTest.php` (15 cases) finally exercises the SUCCESS path with `Http::fake()`, which the package's inline Guzzle client made impossible. One extra defect found while doing it: a failed refresh used to bump `updated_at`, so an unreachable API marked stale data fresh for an hour.
  - **This is the first half of the TODO 20 decision.** It sits in Phase 3 for the same reason as 33.5: the replacement code is framework-neutral and provable on Laravel 8 today, so writing it here takes the package out of every later resolution at once. **Note the TODO 22 correction to TODO 20**: the installed 1.9.0 does **not** block at Phase 5 - `php ^8.0` admits 8.1 - so it blocks nothing at any hop, and only v2.0.0 has a Laravel ceiling (`illuminate ^12.0`). That removes the forcing function, not the reason: the weather feature does not work today, which is what this item fixes. Read TODO 20 first; it carries the measurements and the six findings.
  - **The consumed surface is two GETs.** `helpers.php:111-113` is the only place the package is touched: `getCurrentByCity()` -> `data/2.5/weather` and `get3HourlyByCity()` -> `data/2.5/forecast`. Nothing else in the project references `RakibDevs\`.
  - **Acceptance criteria already exist: the 25 tests in `tests/Feature/Weather/` (TODO 20.1)**, written against the vendor code, so they are the before-and-after comparison. **Plus one criterion those tests cannot express today:** the replacement must come with `Http::fake()` coverage of the success and failure branches - the coverage that is impossible while `WeatherClient` hard-wires its own Guzzle client. Follow `tests/Feature/Middleware/CheckRecaptchaTest.php`.
  - Needed:
    - **New code in `app/`**: `App\Support\Weather\OpenWeatherClient` - `Http::baseUrl('https://api.openweathermap.org')->timeout(10)`, `appid` / `units` / `lang` query parameters, two methods (`currentByCity()`, `forecastByCity()`), and a project exception that carries a **real** message.
    - Move the cache and throttle logic into `App\Support\Weather\WeatherCache` and leave `pwbs_weather_api_call()` as a one-line shim, so the two Livewire call sites do not move in the same commit.
    - **Fix finding 2 here:** pass the country code to *both* endpoints, not just to the current-weather one.
    - **Fix finding 3 here:** drop the manual `json_encode` and let the `json` cast own the shape. That means migrating both readers (`helpers.php:96-97`, `Events.php:297,300`) and re-aligning `WeatherCityFactory::withWeatherData()` with what production actually stores.
    - **Fix finding 4 here:** give the misconfigured-key path a real message, and stop a failed lookup from nulling `city_id` and blocking the whole group save. Enabling weather must not be able to make a group unsavable.
    - Shrink `config/openweather.php` to the four keys actually used, and derive `lang` from `app()->getLocale()` instead of hard-wiring `en`.
    - Drop `rakibdevs/openweather-laravel-api` from `composer.json`. The `weather_cities` table and its migration **stay** - no data migration.
  - **The release note that must not be lost**, the same shape as TODO 33.4 and 33.5: `install()` never deletes, so the release carrying this must extend `release/upgrade.php` to remove `vendor/rakibdevs/` from deployed hosts.
  - Expected changes: ~80-120 lines under `app/`, `config/openweather.php`, `composer.json`, `WeatherCityFactory`, `release/upgrade.php`, and new `Http::fake()` tests.

- [x] **TODO 33.7: Finish the weather feature** - DONE on `v1-patch`
  - Delivered as described. The `weather_monthly_call` counter was **deleted** (written on every call, read nowhere). The calendar view needed one guard the TODO did not list: `current_weather['weather'][0]` and `['main']` were dereferenced unguarded, and a rate-limited response body carries neither.
  - **This is the second half of the TODO 20 decision, and it is a product requirement rather than an upgrade one** - the feature is wanted and today it cannot work. Do it after 33.6, so the refresh loop is written against the new client.
  - **The core gap: nothing ever refreshes the cache.** Rows are written only when a group admin saves the group form; the calendar is a pure reader. A new `weather:refresh` command must iterate the **distinct** cities of groups with `weather_enabled = 1` and a non-null `city_id`, and refresh each.
  - **Budget, and it decides the cadence.** The free tier allows 1000 calls/day and 60/minute; one refresh costs 2 calls per city. The forecast itself is only 3-hourly, so hourly refreshing buys nothing on that half. **Schedule it `0 */3 * * *`** - at 2 calls per city per run that supports roughly 60 cities well inside the cap, and the existing `last_try` throttle stays as the backstop. Register it in `Kernel.php` **and** add it to `SchedulerRegressionTest::EXPECTED_SCHEDULE`, which is a whitelist and will fail until you do.
  - **The calendar stays a pure reader** - no HTTP call during render, under any circumstances.
  - Needed:
    - **Fix finding 5:** guard `Events.php:296` and `:303`, and add the foreign key by migration with `onDelete('set null')` - `2024_12_04_194500` never created one. Then fix or delete the broken `WeatherCity::groups()` stub.
    - **Fix finding 6:** add the `group.weather.*` block to `en` and `de`. (`lang` was already tied to the locale in 33.6.)
    - **Commissioning, which no amount of code replaces:** the API key goes in through the admin screen (`state.env.OPENWAETHER_API_KEY`) or `.env`, and the `weather` toggle is a `settings` row seeded to `'0'` (`CoreSettingsSeeder.php:30`). **Rename the misspelled env key.** `OPENWAETHER_` should be `OPENWEATHER_`; the misspelling is **consistent** across `.env.example:60-61`, `config/openweather.php:11,55`, `Admin/Settings.php:57` and `settings.blade.php:359`, which is why it works today and why all five must move together. Decided in the TODO 20 review: fix it here. Deployed hosts already carry the old key in `.env`, so the release must either migrate it or have `config/openweather.php` read the new name with the old one as a fallback for one release.
    - Decide the `weather_monthly_call` counter's fate: it is written at `helpers.php:130-137` and read nowhere. Either give it a display on the admin screen or delete the write. `WeatherKnownGapsTest` pins the current state either way.
  - Expected changes: one new command plus its schedule entry and test-whitelist line, one migration, `Events.php` guards, two language files, and the `.docs/commands.md` row that TODO 20 deliberately left for this item.

- [x] **TODO 33.8: Remove `eusonlito/laravel-packer`** - DONE
  - Delivered on 2026-08-09, in **two commits**: the application-level replacement (helper, 21 tags, `config/app.php`, tests, `.docs/assets.md`), then the dependency removal (`composer.json` / `composer.lock`, `vendor/eusonlito` + `vendor/imagecow` = 67 tracked files, `config/packer.php`, the nine `.gitignore` lines, `release/upgrade.php`). Split so the ~200-line code diff stays readable next to the vendor tree, and so there is a bisect point where the new emission works while the package is still restorable. Suite: **1270 -> 1273 tests, 3343 -> 3564 assertions, green.**
  - **Why it moved to the front of the queue.** It was scheduled behind four larger Phase 3 items and pulled forward the day finding 1b took the icons off a browser. Nothing about the plan changed - the TODO 21 decision, the replacement design and the 17 acceptance tests were all already written. What changed is that the defect stopped being theoretical.
  - **What the delivery measured that the plan had not.** Finding 1's count was 4; the real count is **181** (see the correction under TODO 21). Two further defects were found and are recorded there: the request scheme baked into a cached file, and a cache-busting token derived from the *source* file's `filemtime` rather than the generated content - so when the content changed, the URL did not, and every cache kept the broken copy. That second one is why the browser did not recover on its own after the served file was fixed.
  - **Control experiments, as run** (the TODO 14 / 19 / 20 / 21.1 discipline): a mangled `data:` URI appended to `public/css/style.css` failed both new guards, the second reproducing the exact packer damage string; deleting one `pwbs_asset()` tag failed exactly two tests; the web-root detector was checked against three planted artifacts, one per glob shape. The `filemtime` follow-up test was green while measuring nothing until `clearstatcache()` was added - PHP's stat cache had served the second lookup from the first.
  - **The strongest single result.** Before: a full `composer test` recreated `public/storage` as a real directory holding exactly the packer's two files - the state that makes `artisan storage:link` skip the link - and rewrote the CSS the browser was being served. After: a full run leaves `public/storage`, `public/cache` and every `*-cache_*` artifact absent. The suite no longer touches the web root at all.
  - **A fourth asset-emitting layout that TODO 21 never counted, and this TODO deliberately did not touch.** Found while verifying the live site: `resources/views/public.blade.php` serves the entire guest area - login, register, password reset, the two-factor challenge, `main.blade.php`, the 404 page - with raw `asset()` calls, plus one hand-rolled `?ver={{ filemtime(public_path('js/custom.js')) }}`. TODO 21 counted **packer call sites**, not asset tags, so this layout was invisible to the measurement. It explains something useful: the guest pages were never affected by any of the four defects, because they always linked the committed sources directly - which is why the mixed-content failure only ever appeared behind a login. Converting it to `pwbs_asset()` is a small, obvious follow-up (that inline `filemtime()` is the helper written by hand for one file), but it is outside this TODO's scope and the layout has no test covering its emission. Recorded in `.docs/assets.md`; worth its own small item rather than a silent scope extension.
  - **This is the TODO 21 decision.** Unlike 33.5 and 33.6 it unblocks nothing - the package resolves fine under Laravel 13. It is here because the code is framework-neutral and provable today, because it empties two lines of `config/app.php` before the Laravel 11 skeleton change makes them awkward, and because finding 2 is a live bug on every deployed host.
  - **The replacement, in full:** a `pwbs_asset(string $path): string` helper in the already-autoloaded `app/Helpers/helpers.php` (the file is registered under `composer.json` `autoload.files`, and the `pwbs_` prefix is the house convention). It returns `asset($path)` with a `?v={filemtime}` query appended when the file exists, and plain `asset($path)` when it does not - never a hard failure on a missing asset, and never a write to disk.
  - **Concatenation is dropped on purpose.** It affects 3 of the 16 call sites and buys nothing measurable over HTTP/2, while the machinery behind it is what writes into the web root. The 3 multi-file calls become individual tags.
  - Needed:
    - Rewrite the **16 call sites**: `layouts/app.blade.php` (9), `layouts/setup.blade.php` (6), `livewire/groups/poster-edit-modal.blade.php` (1). Each `{!! Packer::css($src, $out) !!}` becomes `<link rel="stylesheet" href="{{ pwbs_asset($src) }}">`, each `Packer::js` a `<script src="...">`; the three multi-file calls expand to one tag per source, in the same order.
    - Delete the provider line (`config/app.php:185`) and the `Packer` alias (`:238`). **This also settles the second bullet of TODO 25** - there is no string literal left to convert, so do 33.8 first or drop that bullet.
    - Delete `config/packer.php`.
    - Drop `eusonlito/laravel-packer` from `composer.json`; `imagecow/imagecow` goes with it, since nothing else requires it.
    - Delete the **nine** `.gitignore` lines that exist only for Packer output, including the misspelled `/public/storages/cache/*`. Keep `/public/storage` - that one is for the symlink.
    - Extend `release/upgrade.php` with `vendor/eusonlito`, `vendor/imagecow` and the scattered `*-cache_*` artifacts under `public/dist/` and `public/plugins/` (the TODO 33.5 precedent).
    - **Repair `public/storage` on each host.** The stray real directory must be removed so `php artisan storage:link` can finally create the symlink. This is per-install state, not repository state, so the release hook has to handle it: remove the directory only when it is not a link **and** contains nothing but the `cache/` subtree Packer created, then create the link. Refusing to act on a directory with other contents is the safe default.
  - **Acceptance:** the nine `AssetPipelineKnownGapsTest` assertions fail - that is the point - and `AssetPipelineTest` is rewritten to the new emission (versioned URLs instead of packed filenames, individual tags instead of the two concatenated bundles), keeping its control-experiment property. The toastr icons render in a non-`local` environment, which finding 1 says they do not today.
  - Expected changes: `app/Helpers/helpers.php`, three blade files, `config/app.php`, `config/packer.php` deleted, `composer.json` / `composer.lock`, `.gitignore`, `release/upgrade.php`, both test files rewritten, and `.docs/assets.md`.

- [ ] **TODO 33.9: Convert the Hungarian code comments to English**
  - **The rule is now written down** (`AGENTS.md`, "Maintenance Rules For Contributors"): code comments are English, the same as the documentation. Every change set from TODO 31 onwards follows it. This item is about the existing tree, which does not.
  - **Measured on 2026-08-10**, counting comment lines that carry at least one Hungarian accented character:

    | Tree | Files | Comment lines |
    |---|---|---|
    | `tests/` | 101 | 3195 |
    | `app/` | 82 | 1196 |
    | `database/` | 17 | 76 |
    | `routes/` | 2 | 60 |
    | `config/` | 5 | 36 |
    | `resources/views/` | 1 | 6 |
    | **Total** | **208** | **4569** |

    **This is a lower bound, and knowingly so.** The detector keys on accented characters, so a Hungarian sentence that happens to contain none is invisible to it. Whoever executes this must read the files, not trust the count.
  - **Why the tests dominate, and why that is the hard half.** Roughly 70% of the volume is in `tests/`, and those are not incidental comments - Phases 1 and 2 deliberately wrote the *measurement* into the test files: what was found, what the control experiment was, what would break if the assertion were removed. Translating them is a real editorial job, not a mechanical pass, and a careless run would destroy the most valuable prose in the repository. A machine translation followed by no review is the failure mode to avoid.
  - **Zero runtime risk, which is the one thing in its favour.** Nothing here is executable. That makes it safe to do in slices and safe to interleave with anything else - but it also means no test will ever tell you it went wrong. `composer test` staying green proves only that no code was touched by accident.
  - Needed:
    - Decide the slicing. The obvious cut is by tree (`app/` first, `tests/` last) or by roadmap phase (the files a phase touches, translated as that phase runs). **Do not** interleave it with unrelated work in the same commit - a translation diff and a behaviour diff in one change set are unreviewable together.
    - Keep the *content*. These comments carry measurements, defect numbers, TODO cross-references and control experiments; the translation must preserve every fact, including the ones that read oddly. Where a comment names a Hungarian-language UI string or a translation key, that string stays as it is.
    - Watch for comments that are load-bearing for a test's meaning. `DevDependencyIsolationTest` greps *source text* today; if any future guard does the same for a comment, the translation moves that guard too.
  - Expected changes: comments only, across ~208 files. No behaviour, no test outcome, no route table, no schema.

---

## Phase 4 - Laravel 8 -> 9 (PHP 8.1)

- [ ] **TODO 34: Upgrade the core framework to the Laravel 9 dependency set**
  - Needed:
    - `laravel/framework` to `^9.0`.
    - Replace `facade/ignition` with `spatie/laravel-ignition` (the current one also fatals on PHP 8.3).
    - `nunomaduro/collision` to `^6.0`, keep PHPUnit at `^9.5`.
    - Bump `astrotomic/laravel-translatable`, `spatie/*`, `laravel/fortify` to their Laravel 9 lines.
    - **Re-verify the custom-pivot write path**, found by TODO 07.2. `Groups\ListUsers::updateUser()` hands the full validated array - including `finish_guest_registration`, which is **not a `group_user` column** - to `syncWithoutDetaching()` (`:257-259`). It only survives because `GroupUser` is a custom `Pivot` with a `$fillable` list, so Laravel routes through `updateExistingPivotUsingCustomClass()` and `fill()` drops the unknown key silently. If that path ever falls back to a raw update or insert, the save fatals with an unknown-column error. Pinned by `GroupRoleAssignmentTest::test_the_finish_guest_registration_flag_never_reaches_the_pivot_table`.
  - Expected changes: `composer.json`/`composer.lock`; `php81 artisan` boots on the Laravel 9 stack.

- [ ] **TODO 35: Remove the deprecated proxy and CORS packages**
  - Needed:
    - Remove `fideloper/proxy` and `fruitcake/laravel-cors`.
    - `app/Http/Middleware/TrustProxies.php:5` must extend `Illuminate\Http\Middleware\TrustProxies`; re-check the `$headers` constants (currently `HEADER_X_FORWARDED_FOR|HOST|PORT|PROTO|AWS_ELB`, whose AWS ELB handling differs).
    - `app/Http/Kernel.php:23` -> `Illuminate\Http\Middleware\HandleCors`.
  - Expected changes: middleware refactor, `app/Http/Kernel.php`, `composer.json`.

- [ ] **TODO 36: Validate the SwiftMailer -> Symfony Mailer switch**
  - Needed:
    - Verified low risk: zero direct SwiftMailer usage, zero Mailables, all 25 notifications use the stable `MailMessage` API, `config/mail.php` already uses the modern `mailers` shape.
    - The real exposure is address strictness: Symfony Mailer throws on invalid or empty `replyTo`/`bcc`. TODO 28 was the prerequisite and is **done on `v1-patch`** - the six `env('MAIL_FROM_ADDRESS')` sites now read `config('mail.from.address')`, so a cached configuration no longer produces the empty address that Symfony Mailer would reject.
    - Re-verify `spatie/laravel-failed-job-monitor`, which is wired to queued notifications.
  - Expected changes: mostly verification; the notification test suite is the gate.

- [ ] **TODO 37: Validate Flysystem 1 -> 3 behavior on every disk**
  - Needed:
    - Exercise all 5 disks (`local`, `public`, `web`, `news_files`, `s3`).
    - `delete()` on a missing file now returns `true`; `exists()` on a missing directory and `size()` behavior changed. Review the guard patterns in `app/Http/Controllers/GroupNewsDelete.php:20,21`, `app/Http/Livewire/Groups/NewsEdit.php:109,119,120,141,142`, `app/Models/GroupNewsFile.php:35,36`.
    - Confirm the bare `Storage::exists('installed.txt')` sentinel still behaves (`routes/web.php:78`, `app/Exceptions/Handler.php:57`).
  - Expected changes: targeted fixes; TODO 30 should have already de-risked the `web` disk.

- [ ] **TODO 38: Move `resources/lang/` to `lang/`**
  - Needed:
    - Laravel 9 relocates the language directory to the project root. Measured in TODO 17: **22 locale directories plus `vendor/`**, 130 files, 906 KB - but only 7 locales hold more than the `installer_messages.php` stub, and 5 root JSON files (`de/fr/hu/ro/sk.json`) move with them. `resources/lang/vendor/cookie-consent/` (27 locales) moves too.
    - **The `joedixon/laravel-translation` path warning previously recorded here was wrong and the package is gone by now.** TODO 17 measured that the driver took its path from `$this->app['path.lang']`, which follows the move; only its publish target was a literal. TODO 33.3 removed the package in Phase 3 and requires the replacement to resolve through `App::langPath()`, so the editor should need no change here - **verify it, do not assume it**: re-run `AdminTranslationEditorTest` and confirm `LangFiles` resolves to the new root.
  - Expected changes: directory move, `AdminTranslationEditorTest` re-run, translation UI re-tested.

---

## Phase 5 - Laravel 9 -> 10 (PHP 8.1, then switch to 8.3)

This is where the interpreter switches. Laravel 10.x supports PHP 8.1 through 8.3, so once the framework bump is green on `php81`, re-run the suite on the default `php` (8.3) and make that the working runtime for the rest of the roadmap. Update the *Execution Environment* section in the same commit.

- [ ] **TODO 39: Upgrade to Laravel 10 and align the toolchain**
  - Needed:
    - `laravel/framework` to `^10.0`, `nunomaduro/collision` to `^7.0`, PHPUnit to `^10.0`.
    - Reconcile Monolog 3 logging changes against `config/logging.php`.
    - **`protonemedia/laravel-verify-new-email` fails the resolution here if TODO 33.5 has slipped**, and this is the only place in the roadmap where it does so before Phase 10. Measured in TODO 19: the installed 1.6.0 requires `illuminate/support ^8.67||^9.0`. It is a lock bump, not a decision - `composer.json` already declares `^1.6`, so 1.13.0 resolves once `config.platform.php` stops pinning 8.0.9 (TODO 24). If TODO 33.5 shipped, the package is gone and this line is moot.
    - **`rakibdevs/openweather-laravel-api` does NOT fail here - corrected by TODO 22.** This line used to say it did, on PHP. It does not: the installed 1.9.0 declares no framework constraint and its `php ^7.2|^7.3|^7.4|^8.0` admits 8.1 through 8.4, because `^8.0` is a range and not a version. If TODO 33.6 has slipped, the package installs cleanly here and at every later hop, and stays broken at runtime instead - which is worse, not better, since nothing announces it. Do 33.6; do not rely on this phase to force it.
    - **`laravolt/avatar` fails the resolution here too, and unlike the two above it cannot be waved through.** Measured in TODO 22: the installed 4.1.7 declares `illuminate/support ^6.0|^7.0|^8.0|^9.0`, so Composer stops here - and `composer.json` declares `^4.1`, which admits **no** release supporting Laravel 10 or later. So this is neither a lock bump nor a one-line constraint edit: every Laravel-12/13-capable line of the package requires `intervention/image ^3.4` or `^4.0`, where the API the project calls no longer exists. **TODO 39.1 is the work**; do it in the same PR as this item, because the framework bump does not resolve without it.
  - Expected changes: composer updates plus the PHPUnit work in TODO 40.

- [ ] **TODO 39.1: Migrate `laravolt/avatar` to `^6.5` and Intervention Image 4**
  - **This is the execution half of TODO 22, correction 1.** Read it first: the package has exactly one call site in the whole project, and the break is in the imaging library underneath it rather than in the package's own API.
  - **Why `^6.5` and not `^5.1` or `^7.0`.** 6.5.1 covers Laravel 10 through 13, so one jump carries the package to the end of the roadmap; it needs PHP >= 8.2, which this phase already provides. Going to 5.1.0 instead would keep Intervention Image 2 and change no code, but the same break would then land at Phase 9 (Laravel 12 needs 6.1.2), in a busier hop. 7.0.0 buys nothing over 6.5.1 except a PHP 8.3 floor.
  - Needed:
    - `composer.json`: `laravolt/avatar` from `^4.1` to `^6.5`. `intervention/image` moves 2.7.2 -> 4.x transitively; it is not declared directly and should not become so.
    - Rewrite the one call site, `app/Http/Livewire/Groups/Messages.php:174-176`. `$image->stream("png")` has no v4 equivalent by that name - use `$image->encodeUsingFormat(Format::PNG)`, whose `EncodedImageInterface` is stringable, and pass it to the same `Storage::disk('web')->put()`. Keep the `Storage::exists()` guard at `:173`; it is what stops the board regenerating every avatar on every render.
    - Reconcile `config/laravolt/avatar.php` against the 6.x config: at minimum the `driver` key (Intervention 4 uses driver *classes*, not the string `'gd'`) and the `generator` binding. The font paths (`config/fonts/`) and the 100x100 dimensions should survive unchanged - the suite asserts both.
    - Rewrite the TODO 22.1 tripwire to the new API. The four reversed assertions are the acceptance criteria, not collateral damage.
  - **Acceptance, and the split matters.** Three tests in `AvatarGenerationTest` name the old world and are *expected* to be rewritten: the `stream()` tripwire (all four of its assertions reverse), the driver assertion (`'gd'` becomes a driver class), and the constraint measurement, which asserts `^4.1` / 4.1.7 / intervention 2.7.2 and is the record of the starting state. **The other seven must pass untouched**, because none of them mentions the library: a PNG of the configured 100x100 size arrives at `avatars/avatar-{user_id}.png` on the `web` disk, once per author, never on an empty board, never overwritten, with the disk root and the view prefix still a matched pair. If any of those seven needs editing, the migration changed behaviour - that is the thing to look at, not the thing to fix in the test.
  - **Watch the disk-root pairing.** The `web` disk's root is the relative path `'public'` and the view addresses the same file as `asset('public/avatars/avatar-{id}.png')`. Nothing in this item should touch either, and a test asserts the pair.
  - Expected changes: `composer.json` / `composer.lock`, one Livewire component, `config/laravolt/avatar.php`, one test file, and `.docs/components.md`.

- [ ] **TODO 40: Migrate the test layer to PHPUnit 10**
  - Needed:
    - `phpunit.xml`: `<coverage><include>` becomes `<source>`, `processUncoveredFiles` is removed, add `cacheDirectory`.
    - Convert every `@dataProvider` annotation to a `#[DataProvider]` attribute **and make the provider methods `static`**. Non-static providers are deprecated in PHPUnit 10 and forbidden in 11, so doing it here avoids a fatal in Phase 9.
    - **CORRECTION (measured in TODO 15): there are six, not two.** `AuthorizationGateTest::groupServantMembershipProvider`, `PaginationBehaviorTest::paginatingComponentProvider`, `LivewireRouteMountedComponentsTest::mountedRouteProvider`, `SetLocaleTest::privilegedRoleProvider`, `NotificationEnvFallbackTest::replyToNotificationProvider`, `NotificationRegressionTest::notificationProvider`. The count grew because Phase 1 added test files after this entry was written; TODO 13's providers are already static and are correctly absent from the list. Re-derive the list with a grep before executing rather than trusting this one.
    - Keep `.env.testing` and the `phpunit.xml` `<php>` block in sync; they currently duplicate each other.
  - Expected changes: `phpunit.xml`, two test files.

- [ ] **TODO 41: Refactor patterns deprecated by Laravel 10**
  - Needed:
    - `$dates` is already gone (TODO 29) - verify.
    - `Illuminate\Database\Query\Expression` now requires `getValue(Grammar $grammar)`. Only 4 sites: `app/Helpers/helpers.php`, `app/Http/Livewire/Partials/NavBar.php`, and the `orderByRaw('name_index, email')` inside the `belongsToMany` in `app/Models/Group.php`.
  - Expected changes: small, contained edits.

- [ ] **TODO 42: Modernize custom validation rules**
  - Needed:
    - `app/Rules/Throttle.php` and `app/Rules/TimeCheck.php` implement the deprecated `Illuminate\Contracts\Validation\Rule` interface. Move to `ValidationRule`. It still works through Laravel 12 but is on the removal path - do it while the test suite is green.
  - Expected changes: two rule classes and their call sites.

---

## Phase 6 - Livewire 2 -> 3 (standalone release on Laravel 10)

Gated by TODO 07 and TODO 08. Ship this as its own release, not bundled with a framework hop.

- [ ] **TODO 43: Run the official upgrade tooling and take stock**
  - Needed:
    - Bump `livewire/livewire` to `^3.0` and run `artisan livewire:upgrade`.
    - Treat its output as a starting point only; every item below needs manual review.
    - Remove the `vendor:publish --tag=livewire:assets` line from `composer.json` `post-autoload-dump` - it breaks under v3.
  - Expected changes: composer updates, an inventory of what the tool did and did not handle.

- [ ] **TODO 44: Convert the event system from `emit` to `dispatch`**
  - Needed:
    - 21 `emitTo()` + 6 `emitUp()` in PHP, 13 `$emit*` in Blade, 9 `Livewire.emit*` in JS.
    - Special case: `app/Http/Livewire/Events/Modal.php:60,419` uses a **runtime property** as the emit target (`$this->emitTo($this->refreshUp, 'refresh')`); v3's `dispatch()->to()` needs care here.
    - Convert the 19 `$listeners` arrays (mixed `public`/`protected`) to the v3 form or `#[On]` attributes.
  - Expected changes: broad but mechanical changes across `app/Http/Livewire/**` and `resources/views/livewire/**`.

- [ ] **TODO 45: Resolve the `wire:model` semantic inversion**
  - Needed:
    - **This is the least mechanical and highest-risk item in the whole roadmap.** In v3, `wire:model` is deferred by default, `wire:model.defer` is removed, and eager binding becomes `wire:model.live`.
    - Remove the modifier from the 85 `wire:model.defer` occurrences (behavior preserved).
    - Judge each of the **28 plain `wire:model`** occurrences individually: keep as `wire:model` (now deferred) or convert to `wire:model.live`. Getting this wrong produces forms that look fine but silently stop updating.
    - Review the 8 `wire:model.lazy` occurrences and the 2 invalid `wire:model.ignore` occurrences (not a real modifier in v2 either - a latent bug).
  - Expected changes: ~120 Blade edits, each verified against the TODO 07 interaction tests.

- [ ] **TODO 46: Rewrite the JavaScript bridge and layout hooks**
  - Needed:
    - `public/js/modal.js:14,20` uses `Livewire.emitTo(...)` for `hideModal`/`hiddenModal`. This generic bridge drives the 93 `dispatchBrowserEvent` calls and must be rewritten wholesale.
    - `resources/views/layouts/app.blade.php:113` `Livewire.emit(...)` -> `Livewire.dispatch(...)`; `:125-126` `livewire:load` -> `livewire:init`, and `Livewire.onPageExpired` changed.
    - `@livewireStyles` becomes a no-op in v3; review `layouts/app.blade.php:17,123` and `layouts/setup.blade.php:17,49`.
  - Expected changes: `public/js/modal.js`, both layout files.

- [ ] **TODO 47: Convert `dispatchBrowserEvent` calls**
  - Needed:
    - 93 occurrences move to the v3 `dispatch()` browser-event form.
    - Re-verify the 31 `wire:ignore.self` and 24 `wire:ignore` regions, whose DOM-diffing behavior changed.
    - Re-verify the 12 `@this.set()` / `@this.call()` sites and the 5 `wire:poll` usages.
  - Expected changes: `app/Http/Livewire/**` and the Blade views paired with each event.

- [ ] **TODO 48: Migrate pagination off `$paginationTheme`**
  - Needed:
    - `app/Http/Livewire/AppComponent.php` sets `protected $paginationTheme = 'bootstrap'`; v3 replaces it with `paginationView()` and the `WithoutUrlPagination` split.
    - One base class to fix, but every subclass inherits it. TODO 08's assertions are the verification: `paginationView()` must keep returning `livewire::bootstrap`, and the rendered markup must stay bootstrap rather than falling back to the v3 tailwind default.
    - **This is not only a theme swap. `Groups\ListUsers` needs a second, independent fix**, found by TODO 08. Its `render()` builds a `LengthAwarePaginator` by hand from `$this->page` (`:915-921`). In Livewire 2 the `WithPagination` trait keeps `$page` in step with `$paginators` because `setPage()` writes both; **Livewire 3 removes the trait property and that second write**. The component declares its own `public $page = 1` (`:36`), so the property survives but is never updated again - `gotoPage()` sets `paginators`, `render()` reads `$page`, and the list sits on page 1 **with no error**. Switch it to `$this->getPage()` (or rebuild it on `paginate()`), and re-run `GroupUserListPaginationTest`, whose page-2/page-3 content assertions are the tripwire.
    - Only three components actually paginate: `Admin\Users\ListUsers`, `Groups\ListGroups` and `Groups\ListUsers`. The first two go through the framework's `paginate()` and should migrate on the theme change alone.
    - `Groups\ListUsers` is also the only `->links()` call site with an argument (`onEachSide(1)`, `list-users.blade.php:312`) - re-check the window rendering after the hop.
  - Expected changes: `AppComponent`, `app/Http/Livewire/Groups/ListUsers.php`, possibly published pagination views.

- [ ] **TODO 49: Re-verify file uploads**
  - Needed:
    - `app/Http/Livewire/Groups/NewsEdit.php` is the only `WithFileUploads` component. Temporary-upload signing and configuration changed in v3.
    - Re-test `updatedFiles()` validation at `:161`, `temporaryUrl()` at `:172`, and `->store('/', 'news_files')` at `:109` against the private `news_files` disk.
    - Check whether `$this->file_types` contains `svg`, since MIME sniffing tightened across versions.
  - Expected changes: verified upload flow, possibly `config/livewire.php` temporary-upload settings.

- [ ] **TODO 50: Fix component tag syntax**
  - Needed:
    - camelCase attributes on tag syntax resolve differently in v3: `resources/views/layouts/app.blade.php:68` (`<livewire:events.modal :groupId="0" ...>`), `resources/views/livewire/events/modal.blade.php:246`, `resources/views/livewire/home.blade.php:109`.
    - These tags are also **unclosed** (no `/>`, no matching close tag) - fix while touching them.
    - 8 `@livewire(...)` directives and 3 `<livewire:...>` tags total.
  - Expected changes: Blade fixes, verified by the nested-component tests.

- [ ] **TODO 51: Decide the Livewire namespace and finalize config**
  - Needed:
    - v3's default namespace is `App\Livewire`; the project uses `App\Http\Livewire` via `config/livewire.php`. Either keep the override (cheap, non-standard) or move all 28 classes (clean, touches every test).
    - Confirm `'layout' => 'layouts.app'` still applies; the project has no `->layout()` calls anywhere, so this key is the only layout mechanism.
  - Expected changes: decision recorded, `config/livewire.php` finalized, `.docs/components.md` updated.

---

## Phase 7 - Frontend: Mix -> Vite (on Laravel 10)

**CORRECTED BY TODO 21.** This phase used to open with *"Mandatory, not optional: Laravel Mix 6 / webpack 5 does not build on the locally installed Node 24.18."* That reason does not hold, and the measurement is pinned by `AssetPipelineKnownGapsTest`:

- There are **zero `mix()` calls** in the project - not in the layouts, not anywhere.
- `resources/css/app.css` is **0 bytes**; `resources/js/app.js` is the untouched 25-byte Laravel default.
- The outputs `public/js/app.js` and `public/css/app.css` **do not exist**, and nothing references them.

**The Mix pipeline is dead**: it does not run, it never ran here, and nothing consumes its output. Node 24 not being able to build it therefore breaks nothing. What actually serves the application's assets is `eusonlito/laravel-packer` plus hand-placed files under `public/` - and TODO 21 decided to remove that package in Phase 3 (TODO 33.8), replacing it with a `pwbs_asset()` versioning helper and no build step at all.

**So this phase is a choice, not an obligation**, and its scope is an open question rather than a translation exercise: it is worth doing only if we want AdminLTE, jQuery, bootstrap, toastr, sweetalert2 and summernote under a real build graph, which is substantially more frontend work than the items below describe. **Decide that before starting**, and re-scope TODO 52 to whatever is decided. Nothing in Phases 4-13 depends on the answer.

- [ ] **TODO 52: Migrate the build from Laravel Mix to Vite**
  - **Prerequisite: decide the scope above.** If the answer is "keep serving hand-placed assets", the honest version of this item is to **delete** `webpack.mix.js`, `resources/js/`, `resources/css/` and the `laravel-mix` devDependency, and stop there - removing a pipeline that produces nothing rather than porting it.
  - Needed, if a real build is wanted:
    - Replace `webpack.mix.js` with `vite.config.js`; add `vite` and `laravel-vite-plugin`, drop `laravel-mix`.
    - The nominal entrypoints are `resources/js/app.js` and `resources/css/app.css`; both are effectively empty, so their contents have to be **written**, not migrated.
    - Bring the vendor assets under `public/plugins/` and `public/dist/` into the graph, and replace the `pwbs_asset()` tags left by TODO 33.8 with the `@vite` directive in `layouts/app.blade.php`, `layouts/setup.blade.php` and `livewire/groups/poster-edit-modal.blade.php`. (Before TODO 33.8 those are `Packer::` calls - there are **no** `mix()` helpers to replace in either case.)
    - Rewrite `tests/Feature/Assets/` to the new emission, as TODO 33.8 leaves it.
  - Expected changes: `package.json`, `vite.config.js`, `resources/js/`, `resources/css/`, three blade files, `.docs/assets.md`.

- [ ] **TODO 53: Establish a reproducible frontend build**
  - **Conditional on TODO 52.** If TODO 52 resolves to "delete the dead pipeline", there is no build to make reproducible and this item disappears with it - `package.json` goes too.
  - Needed, if a build exists:
    - Commit a `package-lock.json` (none exists today) and verify `npm ci && npm run build` on Node 24.
    - Record the Node/npm version in `upgrade-notes/`.
  - Expected changes: lockfile committed, working production build.

- [ ] **TODO 54: Verify the `eusonlito/laravel-packer` removal**
  - **The execution moved to Phase 3, TODO 33.8.** TODO 21 decided to remove the package and replace its 16 call sites with a `pwbs_asset()` helper; the code is framework-neutral, so it is written on Laravel 8 rather than here. Note that the package never blocked anything - the installed 2.2.6 declares `php >=5.5` and no `illuminate/*`, so it resolves under Laravel 13 unchanged. It was removed for what it does at runtime, not for what it constrains.
  - **With that moved, this item has no work left.** It reduces to verification.
  - Needed:
    - Confirm `eusonlito/laravel-packer` and `imagecow/imagecow` are absent from `composer.json` / `composer.lock`, that `config/packer.php` and the `Packer` provider and alias are gone, and that `vendor/eusonlito/` and `vendor/imagecow/` are gone from deployed hosts (the `release/upgrade.php` lines added by TODO 33.8).
    - Confirm `public/storage` is a symlink on every host, not the stray real directory Packer created.
    - ~~If TODO 33.8 slipped, do it before this phase rather than in it.~~ **It did not slip: TODO 33.8 shipped on 2026-08-09, in Phase 3 as planned.** The repository-side half of this checklist is therefore already true and pinned by `AssetPipelineKnownGapsTest`. What genuinely remains for this phase is the **per-host** half: whether `release/upgrade.php` actually removed the two vendor trees and repaired `public/storage` on every deployed install. That is not observable from the repository, so it stays here.
  - Expected changes: verification only.

---

## Phase 8 - Laravel 10 -> 11 (PHP 8.3)

The largest structural hop. No new runtime is needed: Laravel 11's `php: ^8.2` is satisfied by the installed PHP 8.3.

- [ ] **TODO 55: Upgrade to the Laravel 11 dependency set**
  - Needed:
    - `laravel/framework` to `^11.0`; `laravel/fortify`, `nunomaduro/collision` (`^8.0`) to their Laravel 11 lines.
    - `spatie/laravel-ignition` is absorbed into the framework - remove it.
    - **Watch the Carbon 2 -> 3 jump, which Laravel 11 opens the door to.** Carbon 3 made `diffIn*` **signed**; in Carbon 2 (2.57 today) it returns an absolute value. `app/Http/Middleware/setUserLastActivity.php:24` reads `Carbon::now()->diffInSeconds($user->last_activity) >= 60`, so under Carbon 3 a past timestamp yields a negative number, the condition never holds, and **`users.last_activity` silently stops updating** - which in turn breaks the `Groups\ListUsers` online/inactive filters and the GDPR inactivity anonymization. `SetUserLastActivityTest::test_an_old_activity_is_refreshed` is the tripwire. Audit every other `diffIn*` call at the same time.
  - Expected changes: composer realignment; `artisan` boots under PHP 8.3.

- [ ] **TODO 56: Migrate to the slim application skeleton**
  - Needed:
    - Rewrite `bootstrap/app.php` to `Application::configure()->withRouting()->withMiddleware()->withExceptions()->create()`.
    - Port `app/Http/Kernel.php`: the global stack, the `web` group (including `AuthenticateSession`, `SetLocale`, `SetUserLastActivity`, `HttpsProtocol`), the `api` group, and the 6 custom aliases (`groupAdmin`, `groupMember`, `profileFull`, `setGuestLanguage`, `checkRecaptcha`, plus the `guest` override) into `withMiddleware()`.
    - Port `app/Console/Kernel.php` scheduling into `routes/console.php` or `withSchedule()` - TODO 06 already extracted the closures into commands, which makes this straightforward.
    - Port `app/Exceptions/Handler.php` into `withExceptions()`: both heavyweight `renderable` closures must survive (the `MissingAppKeyException` handler that copies `.env.example` and runs `key:generate`, and the `QueryException` handler that redirects to setup). Note `$dontFlash` semantics shifted.
    - Convert the manual provider array in `config/app.php` to `bootstrap/providers.php`.
  - Expected changes: `bootstrap/app.php`, `bootstrap/providers.php`, deleted kernels and handler, `config/app.php` slimmed.
  - The route contract snapshot test is the primary verification that the middleware stacks came across intact.

- [ ] **TODO 57: Remove `doctrine/dbal` and fix the schema-manager call**
  - Needed:
    - Remove `doctrine/dbal` from `composer.json`. TODO 32 already neutralized the 16 `->change()` calls by squashing.
    - `app/Http/Controllers/Setup/DatabaseController.php:124` calls `->getDoctrineSchemaManager()`, which no longer exists on the connection - rewrite using `Schema::` / `getSchemaBuilder()`.
    - Re-run all migrations from scratch against `kozter_testing`.
  - Expected changes: `composer.json`, `DatabaseController.php`, verified by the TODO 12 setup-flow tests.

- [ ] **TODO 58: Execute the Laravel 11 blocker decisions**
  - Needed:
    - **The self-updater is no longer part of this item.** TODO 18 measured that the installed 1.0.2 declares only `php >=5.4.0`, so it blocks nothing; the move onto the project's own `mdylan/laraupdater` fork is done earlier, in **TODO 33.4** (Phase 3). If that has slipped, do it before touching the framework rather than here - it is Laravel-8-compatible work and does not belong in a hop. What remains for this hop is a re-check that the fork's `illuminate/support ^11.0` branch actually resolves, which is a version bump, not a decision.
    - **The translation package is no longer part of this item.** TODO 17 measured that its `require` block is **empty**, so it blocks nothing; its removal and the in-house editor that replaces it are done earlier, in **TODO 33.3** (Phase 3), and the `elegantly/laravel-translator` engine arrives later, in **TODO 66.1** (Phase 10). If TODO 33.3 has slipped, do it before touching the framework rather than here - it is Laravel-8-compatible work and does not belong in a hop.
    - **The GDPR package is no longer part of this item.** TODO 16 measured that its constraint is unbounded, so it blocks nothing; its replacement is done earlier, in **TODO 33.2** (Phase 3). If that has slipped, do it before touching the framework rather than here - it is Laravel-8-compatible work and does not belong in a hop.
    - The TODO 12 GDPR/setup tests and the TODO 13 encrypted round-trip tests are the acceptance criteria.
  - Expected changes: depends on the recorded decisions; likely new code under `app/` replacing vendor packages.

- [ ] **TODO 59: Modernize the Blade component alias**
  - Needed:
    - `app/Providers/BladeComponentServiceProvider.php:17` registers a **view path** as a component (`Blade::component('layouts.app', 'admin-layout')`) from `register()` rather than `boot()`, relying on a legacy fallback.
    - Replace with an anonymous component or `Blade::componentNamespace()`.
  - Expected changes: provider removed or rewritten; the 6 existing anonymous components in `resources/views/components/` are unaffected.

- [ ] **TODO 60: Re-verify queue, scheduler, and notification behavior**
  - Needed:
    - Laravel 11 changes queue serialization and scheduler internals. Run the real job tests from TODO 05 and the command tests from TODO 06 against the new stack.
    - Verify `spatie/laravel-failed-job-monitor` still fires.
  - Expected changes: runtime configuration updates and job-level fixes; `.docs/jobs.md` refreshed.

---

## Phase 9 - Laravel 11 -> 12 (PHP 8.3)

Comparatively small once Phase 8 lands.

- [ ] **TODO 61: Upgrade the framework and test stack to Laravel 12**
  - Needed:
    - `laravel/framework` to `^12.0`, `phpunit/phpunit` to `^11.0`.
    - **PHPUnit 11 makes non-static data providers fatal** - TODO 40 must already have converted both.
    - Carbon 3 compatibility in date-sensitive code: `app/Helpers/GroupDateHelper.php`, the calendar components, and the scheduler commands.
  - Expected changes: composer updates, test-layer fixes.

- [ ] **TODO 62: Audit Laravel 12 behavioral changes against this app**
  - Needed:
    - Duplicate route-name precedence changed - already resolved in TODO 26, so this is verification only.
    - `Storage::disk('local')` now defaults its root to `storage/app/private` unless configured explicitly. Verified: no direct `Storage::disk('local')` usage, but the bare `Storage::exists('installed.txt')` sentinel uses the default disk - **check this specifically**.
    - Request merge behavior for nested array payloads - relevant to the Livewire forms.
    - **Correction to earlier planning:** the SVG concern does not apply. Verified: this project has **zero** `image` validation rules. The only file validation is the dynamic `mimes:` list in `Groups\NewsEdit` (TODO 49).
  - Expected changes: targeted verification, `config/filesystems.php` if the local root needs pinning.

- [ ] **TODO 63: Re-confirm third-party Laravel 12 support**
  - Needed:
    - Refresh Appendix A. At the time of writing `astrotomic/laravel-translatable`, `laravolt/avatar`, and the `spatie/*` packages all support Laravel 12 or newer.
  - Expected changes: updated matrix.

---

## Phase 10 - Laravel 12 -> 13 (PHP 8.3)

PHP 8.3.16 is already installed locally, so no new runtime is required for this hop.

- [ ] **TODO 64: Upgrade to the Laravel 13 baseline**
  - Needed:
    - `laravel/framework` to `^13.0`; raise the `php` constraint in `composer.json` to `^8.3`.
    - `phpunit/phpunit` to `^12.0`, `nunomaduro/collision` to its current major, `mockery/mockery` and `fakerphp/faker` to current.
    - No runtime change is needed here - the switch to PHP 8.3 already happened in Phase 5.
  - Expected changes: composer updates, test-layer adjustments.

- [ ] **TODO 65: Resolve the Laravel 13 package blockers**
  - **`protonemedia/laravel-verify-new-email` is no longer part of this item.** TODO 19 decided to replace it in-house, and the replacement is done earlier, in **TODO 33.5** (Phase 3), because the code is framework-neutral and Laravel 8 compatible. If TODO 33.5 has slipped, do it before touching the framework rather than here. Note the fallback if it slipped *and* the schedule is tight: the package installs fine through Laravel 12 by bumping the lock to 1.13.0, so only the Laravel 13 hop actually forces it.
  - **`rakibdevs/openweather-laravel-api` is no longer part of this item either.** TODO 20 decided to replace it with a direct `Http::` client, and the replacement is done earlier, in **TODO 33.6** (Phase 3), for the same reason: the code is framework-neutral and Laravel 8 compatible. The roadmap's original "Blocks Laravel 13" was wrong, and so was TODO 20's own replacement for it: **the installed 1.9.0 blocks at no hop at all** - no framework constraint, and `php ^8.0` admits 8.1 through 8.4 (measured in TODO 22). The Laravel 13 wall belongs to v2.0.0, which the declared `^1.9` does not admit - but nothing forces anyone there, so if 33.6 slipped the package is simply still here, still broken.
  - **With both packages moved forward, this item has no blockers left.** It reduces to verification.
  - Needed:
    - Confirm that neither `protonemedia/laravel-verify-new-email` nor `rakibdevs/openweather-laravel-api` is still in `composer.json`, and that `vendor/protonemedia/` and `vendor/rakibdevs/` are gone from deployed hosts (the `release/upgrade.php` lines added by TODO 33.5 and 33.6).
    - If either replacement slipped, do it before touching the framework rather than here. The fallbacks, both measured: `protonemedia` installs fine through Laravel 12 by bumping the lock to 1.13.0; `rakibdevs` needs **no** composer change at all to resolve here (TODO 22 correction) - which is exactly why it has to be checked deliberately rather than waited for.
  - Expected changes: verification only, if TODO 33.5 and 33.6 both shipped.

- [ ] **TODO 66: Bump the remaining packages to their Laravel 13 lines**
  - **Rewritten from the TODO 22 measurement.** `laravolt/avatar` is no longer part of this item - it fails at Phase 5, not here, and its migration is TODO 39.1. What is left is mostly lock bumps plus **two one-line constraint edits** that the previous wording hid.
  - Needed:
    - **Lock bumps, already admitted by `composer.json`:** `astrotomic/laravel-translatable` -> 11.17.0, `spatie/laravel-activitylog` -> 4.12.3, `spatie/laravel-cookie-consent` -> 3.5.0, `spatie/laravel-failed-job-monitor` -> 4.5.0, `petercoles/multilingual-country-list` -> 1.2.14, `laravel/sail` -> 1.65.0. The per-hop ladder in Appendix A has the exact floors if an intermediate hop needs one.
    - **Constraint edits - these fail the resolution otherwise:** `laravel/tinker` `^2.5` -> `^3.0` (no 2.x release supports Laravel 13; the line stops at 2.10.2), and `barryvdh/laravel-debugbar` `^3.6` -> `^4.0` (the 3.x line stops at 3.15.4).
    - **`laravel/fortify`: pin at 1.36.2.** It is the last release covering Laravel 10 through 13 without pulling `laravel/passkeys`, which 1.37.0 added as a hard requirement while raising its floor to `illuminate ^11` / `php ^8.2`. Taking 1.37+ means reconciling new passkey routes into the hand-patched `routes/fortify.php` - see TODO 69. That is a feature decision, not an upgrade step, and it does not belong in this hop.
    - **`guzzlehttp/guzzle`: stay on `^7`.** 8.0.2 exists, and `laravel/framework` v13 declares `guzzlehttp/guzzle: ^7.8.2` in `require` - so bumping the major is a resolution failure. 7.15.3 is the target.
    - **`spatie/laravel-activitylog`: stay on 4.x.** 5.0.0 requires `php ^8.4`, above this roadmap's target; 4.12.3 covers Laravel 13 on `php ^8.1`.
    - **`spatie/calendar-links`: nothing to do.** It declares no framework constraint in any version, so it never blocked. The 2.x line (`php ^8.3`) is an optional cleanup outside this roadmap.
  - Expected changes: `composer.json` (two constraint edits) and `composer.lock`.

- [ ] **TODO 66.1: Adopt `elegantly/laravel-translator` as the engine under the translation editor**
  - **This is the second half of the TODO 17 decision**, and it is optional in the sense that the feature already works without it: TODO 33.3 delivered the editor in Phase 3, so nothing is broken while this waits. Read TODO 17 first.
  - It sits in Phase 10 rather than Phase 8 because the package pulls **7 runtime dependencies** (`laravel/ai`, `nikic/php-parser ^5.1`, `spatie/laravel-package-tools`, `spatie/simple-excel`, `symfony/finder ^7.0||^8.0`, `symfony/intl ^7.0||^8.0`, `illuminate/contracts ^11.0||^12.0||^13.0`). Doing it here means one Composer resolution against the final stack instead of three across the 11 -> 12 -> 13 hops. It is technically installable from Phase 8 if it is wanted sooner.
  - Needed:
    - Add `elegantly/laravel-translator` and delegate `App\Support\Translation\LangFiles::read()` / `write()` to its `php` and `json` drivers through the `Translator` facade.
    - Wire the analysis commands the in-house editor deliberately did not reimplement: `translator:missing`, `translator:dead`, `translator:sort`, and the CSV `translator:export` / `translator:import`. Surface missing-key counts per locale in the editor - with 22 locales and 2 complete ones, that is the feature with the most practical value.
    - Optionally expose AI translation (`translator:translate`, `translator:proofread`) from the editor. It requires `laravel/ai` credentials, so it must degrade cleanly when unconfigured.
  - **Verify before executing:** whether the facade actually exposes a per-key read/write API. The public documentation only shows the high-level `getMissingTranslations()` / `translateTranslations()` / `sortTranslations()` calls, not a single-key write. **If it does not, keep `LangFiles` as the writer** and use the package purely for analysis, export/import and AI translation. That does not weaken the TODO 17 decision, because the editor is already working by then.
  - **Explicitly not in scope: Laratranslate.** It is a commercial UI ($29 / $49, licence key plus a private Composer repository) whose Laravel 13 support is unstated and whose production authorization hook is undocumented. If it is ever revisited, both must be confirmed **before** purchase, and it would replace the in-house editor rather than complement it.
  - Expected changes: `composer.json`, `App\Support\Translation\LangFiles` refactored to delegate, the editor component extended, `AdminTranslationEditorTest` extended, and `.docs/commands.md` for the new Artisan commands.

- [ ] **TODO 67: Walk the official Laravel 13 upgrade guide**
  - Needed:
    - Read the Laravel 13 upgrade guide at execution time and record every applicable change here. This roadmap deliberately does not enumerate them, because the guide is authoritative and moves.
    - Pay attention to anything touching: encrypted casts, queue serialization, the scheduler, validation rules, and Blade compilation - the areas where this app has the most custom surface.
  - Expected changes: a Laravel 13-specific findings list appended to this phase, plus the resulting fixes.

- [ ] **TODO 68: Validate under PHP 8.4 (forward-looking, optional)**
  - Needed:
    - PHP 8.4 is not installed locally. Laravel 13 accepts `^8.3`, so this is not blocking.
    - If PHP 8.4 is the eventual production target, install it and run the suite; the implicit-nullable-parameter deprecation is the most likely source of noise across 30 models and 28 components.
  - Expected changes: optional, documented.

---

## Phase 11 - Fortify, Auth, and Middleware Integrity

- [ ] **TODO 69: Consolidate the vendored Fortify route file**
  - Needed:
    - `routes/fortify.php` is a hand-patched copy of Fortify's own route file, loaded via `Fortify::ignoreRoutes()` from `app/Providers/FortifyServiceProvider.php` inside a `Route::group(['namespace' => 'Laravel\Fortify\Http\Controllers'])`. The `namespace` group option is deprecated and should be removed (the file already uses FQCN array syntax).
    - Reconcile against the current Fortify route set: `PasswordController` was added, and the 2FA confirmation flow now uses `two_factor_confirmed_at`.
    - **Know where the version line sits before starting.** Measured in TODO 22: Fortify **1.37.0** adds `laravel/passkeys` as a hard requirement and registers passkey routes, so a hand-patched route file has to absorb them. TODO 66 deliberately pins at **1.36.2** to keep that out of the upgrade. If this item decides to move closer to default registration, taking 1.37+ becomes reasonable - but it is a feature decision made here, not a side effect of a composer bump.
    - Decide whether to keep fully custom routing or move closer to default registration. The custom `checkRecaptcha` middleware on login/register/forgot-password and the `authenticateThrough()` pipeline with `RedirectIfTwoFactorConfirmed` must be preserved either way. **`v1-patch H` added state to preserve here:** the three `checkRecaptcha` middleware now carry an expected-action parameter (`:login`, `:register`, `:password_reset`), `/register` and `/forgot-password` carry `throttle:5,1`, and `POST /user/confirm-password` is deliberately absent.
    - Verify the `DisableTwoFactorAuthentication` singleton override still binds - **and the `TwoFactorAuthenticationProvider` one added in `v1-patch H`**. That override exists because Fortify 1.11.2's own CVE-2022-25838 fix does not actually block a replay (measured; see the v1-patch H section). Before deleting it, check whether the Fortify version being taken carries the `true` -> `getTimestamp()` normalization itself - `tests/Feature/Auth/TwoFactorReplayTest.php` answers that empirically either way.
    - **The `~1.11.2` pin is this phase's to lift.** It exists because 1.12.0 introduces `two_factor_confirmed_at` and Fortify's own 2FA confirmation flow, which collide with this project's `two_factor_confirmed` boolean, its overridden actions, `User::confirmTwoFactorAuth()` and its `two-factor.confirm` route name. That is a schema plus flow migration, and it belongs with the reconciliation above - not with a security bump.
  - Expected changes: `routes/fortify.php`, `app/Providers/FortifyServiceProvider.php`, `.docs/fortify-routes.md`.

- [ ] **TODO 70: Re-verify authorization and middleware behavior end to end**
  - Needed:
    - Regression-test `groupAdmin`, `groupMember`, `profileFull`, `setGuestLanguage`, `checkRecaptcha` after the skeleton migration.
    - Verify the gate-based `is-admin` / `is-translator` permissions and the `password.confirm` gating on the admin, group, and translator route groups. **Note the one deliberate exception from `v1-patch H`:** `admin.users.login` is POST-only and therefore sits outside the `password.confirm` group - `RequirePassword` returns through `redirect()->intended()`, which re-issues the request as a GET. `/admin/users`, the only page rendering the button, still carries the confirmation.
    - **Impersonation is now session-bound** (`App\Http\Controllers\Admin\LoginToUserController`), not signed-URL-bound. `tests/Feature/Auth/ImpersonationTest.php` is the gate; any change to session regeneration or guard behaviour in a framework hop should be checked against it.
  - Expected changes: fixes in middleware and gate interactions; the TODO 09 middleware tests are the gate.

- [ ] **TODO 71: Decide the API surface**
  - Needed:
    - `routes/api.php` is an untouched stock stub with a single `auth:api` route, and `config/auth.php` may not define an `api` guard. Either remove the file or make it intentional. Laravel 11+ does not install API routing by default.
  - Expected changes: `routes/api.php`, `bootstrap/app.php` routing config, `.docs/routes.md`.

- [ ] **TODO 72: Reconsider the install-state route guard**
  - Needed:
    - `routes/web.php:78` calls `Storage::exists('installed.txt')` **at route-file parse time** to conditionally register the `setup/*` routes. Route caching bakes the install state in, and uncached requests hit the filesystem on every load.
    - Move the check to middleware or a config value.
  - Expected changes: `routes/web.php`, verified by the TODO 12 setup tests.

---

## Phase 12 - Release Readiness

- [ ] **TODO 73: Execute the full regression suite plus manual smoke checks**
  - Needed:
    - Full test run on the Laravel 13 stack, plus manual smoke checks for: login/logout, registration and email verification, profile update, group membership flows, event create/update/delete, all admin pages, the translation UI, the GDPR export, and the setup flow.
    - Verify scheduled commands and queue workers on the upgraded stack.
  - Expected changes: a release-candidate report with a pass/fail matrix in `upgrade-notes/`.

- [ ] **TODO 74: Bring documentation back in sync**
  - Needed:
    - Update `.docs/models.md`, `.docs/routes.md`, `.docs/jobs.md`, `.docs/fortify-routes.md`, `.docs/components.md`, `.docs/commands.md`, `.docs/notifications.md`, `.docs/observers.md`, `.docs/middleware.md`.
    - Update `AGENTS.md`: the stack snapshot still says "Laravel 8", and the note about Artisan failing on newer PHP runtimes no longer applies.
  - Expected changes: documentation matching final Laravel 13 behavior.

- [ ] **TODO 75: Prepare the rollback and deployment checklist**
  - Needed:
    - Backup procedure, with **explicit emphasis on `APP_KEY` and the 9 encrypted columns** - a key mismatch after deployment is unrecoverable without a restore.
    - Maintenance mode, migration execution order (the squashed baseline in TODO 32 changes how a fresh install behaves versus an existing production database), queue restart, cache/config/view/route cache rebuild, post-deploy verification.
    - Rollback path per phase, given that each phase is a separate PR.
  - Expected changes: a deployment runbook with an explicit recovery path.

---

## Appendix A - Package Compatibility Matrix

**Re-measured on 2026-08-08 by TODO 22**, and this table is now derived rather than remembered. The method is in that item: `composer.lock` for the installed version's real `require` block, `composer.json` for the declared constraint, Packagist's p2 endpoint for every stable release, and `Composer\Semver\Semver::satisfies()` - the resolver's own matching - to decide which admits what.

Two columns carry most of the information. **"Installed fails at"** is the first Laravel major the *installed* version's constraint rejects; `never` means Composer will not stop the upgrade at any hop, which is a silent risk rather than a safe one. **"Admits the target?"** compares the declared `composer.json` constraint against the release that supports Laravel 13: *lock bump* needs no file edit, *constraint edit* does.

**Re-check before each hop** (TODO 22) - upstream moves, and the per-hop ladder below tells you which packages have a floor rising in the hop you are about to do.

**The "Installed" column is the `v2-dev` baseline, not `v1-patch`.** The security work in `v1-patch H` moved nine of these inside their existing constraints - `laravel/framework` 8.83.1 -> 8.83.29, `laravel/fortify` 1.10.2 -> 1.11.2 (pinned `~1.11.2`, see that section), `livewire/livewire` 2.10.4 -> 2.12.8, `guzzlehttp/guzzle` 7.4.1 -> 7.15.3, plus `symfony/*`, `league/commonmark`, `laravel/tinker`, `phpunit/phpunit` and `barryvdh/laravel-debugbar`. **No declared constraint changed except Fortify's**, so every "Installed fails at" and "Verdict" below still holds - but read the installed numbers from `composer.lock` before measuring anything, not from this column.

| Package | Installed | Declared | Installed fails at | L13 target | Verdict |
| --- | --- | --- | --- | --- | --- |
| `laravel/framework` | 8.83.1 | `^8.12` | - | v13.24.0 (`php ^8.3`) | Target `^13.0`, one major per phase from Phase 4 |
| `laravelcollective/html` | 6.3.0 | `^6.2` | L10 | - | **Remove** (TODO 23) - zero `Form::` / `Html::` usage, provider already commented out |
| `fideloper/proxy` | 4.4.1 | `^4.4` | L10 | - | **Remove in Phase 4** - framework-native since Laravel 9 |
| `fruitcake/laravel-cors` | 2.1.0 | `^2.0` | L10 | - | **Remove in Phase 4** - framework-native since Laravel 9 |
| `facade/ignition` (dev) | 2.17.4 | `^2.5` | **L9** | - | **Replace in Phase 4** with `spatie/laravel-ignition`, then absorbed into the framework at Laravel 11. The earliest failure in the whole table |
| `doctrine/dbal` | 3.3.2 | `^3.1` | never (no framework constraint) | - | **Remove in Phase 8** - Laravel 11 reimplemented `change()` natively |
| `dialect/laravel-gdpr-compliance` | 1.4.7 (exact pin) | `1.4.7` | never | - | **Decided (TODO 16): replace in-house and remove.** Executed in **Phase 3, TODO 33.2**. `illuminate/support >=5.5` is unbounded, so Composer never fails on it; the earlier "Blocks Phase 8" reading was wrong |
| `joedixon/laravel-translation` | 1.1.2 | `^1.1` | never | - | **Decided (TODO 17): remove, replace the UI with an in-house Livewire editor.** Executed in **Phase 3, TODO 33.3**; `elegantly/laravel-translator` arrives as the engine in **Phase 10, TODO 66.1**. Its `require` block is literally `{}` |
| `mdylan/laraupdater` (was `pcinaglia/laraupdater` 1.0.2) | v2.0.0, own fork | `^2.0` | never | already declares `^13.0` | **Decided (TODO 18): keep the self-updater, move it onto the project's own fork.** Executed in **Phase 3, TODO 33.4**. The old pin blocked nothing either - 1.0.2 required only `php >=5.4.0` |
| `protonemedia/laravel-verify-new-email` | 1.6.0 | `^1.6` | **L10** | none - no 1.x supports L13 | **Decided (TODO 19): replace in-house and remove.** Executed in **Phase 3, TODO 33.5**. The only package in the table that fails a hop *and* has no L13 line. The Phase 5 half is a lock bump (`^1.6` admits 1.13.0); the Phase 10 wall is real |
| `rakibdevs/openweather-laravel-api` | 1.9.0 | `^1.9` | **never** | - | **Decided (TODO 20): replace with a direct `Http::` client and remove.** Executed in **Phase 3, TODO 33.6**, feature finished in 33.7. **Corrected by TODO 22:** the roadmap said this blocks at Phase 5 on PHP; it does not - `^8.0` is `>=8.0 <9.0` and admits 8.1-8.4. No framework constraint either, so **nothing forces it at any hop**. Replaced because the feature does not work, not because it blocks |
| `eusonlito/laravel-packer` | 2.2.6 | `^2.2` | never | - | **REMOVED on 2026-08-09 (TODO 33.8), replaced with a `pwbs_asset()` helper.** TODO 54 is now verification only. Requires only `php >=5.5` and `imagecow/imagecow ^2.4`. Removed for runtime reasons: it writes into the web root during a request (which left `public/storage` a real directory instead of a symlink), corrupts `data:` URIs while rewriting CSS, and never minifies. `imagecow` leaves with it |
| `livewire/livewire` | 2.10.4 | `^2.10.4` | L10 | v3 and v4 both cover L10-L13 | **Target v3** in Phase 6; v4 is optional (Appendix B) |
| `laravolt/avatar` | 4.1.7 | `^4.1` | **L10** | 6.5.1 | **NOT a version bump - the only one in the table.** `^4.1` admits no L10-capable release, and every L12/L13 line requires `intervention/image ^3.4`/`^4.0`, where the `stream()` the project calls no longer exists. Migrated in **Phase 5, TODO 39.1**; pinned by TODO 22.1 |
| `laravel/tinker` | 2.7.0 | `^2.5` | L10 | **3.0.2** | **Constraint edit** `^2.5` -> `^3.0` at Phase 10. No 2.x release supports Laravel 13 - the line stops at 2.10.2 |
| `laravel/fortify` | 1.10.2 (**1.11.2 on `v1-patch`**) | `^1.7` (**`~1.11.2` on `v1-patch`**) | L10 | 1.36.2 | Lock bump, but **pin at 1.36.2**: 1.37.0 adds `laravel/passkeys` as a hard requirement and raises its floor to `illuminate ^11` / `php ^8.2`. The vendored route file is the real work (TODO 69). **Widening `~1.11.2` is a Phase 11 decision, not a hop step** - 1.12.0 brings the `two_factor_confirmed_at` schema change |
| `astrotomic/laravel-translatable` | 11.10.0 | `^11.9` | L10 | 11.17.0 | Lock bump. 11.17.0 drops `illuminate ^8`, so it cannot be bumped before Phase 4 |
| `spatie/laravel-activitylog` | 4.4.0 | `^4.0.0` | L10 | 4.12.3 | Lock bump; **stay on 4.x** - 5.0.0 requires `php ^8.4`, above this roadmap's target. `User` already uses the modern `LogOptions` API |
| `spatie/laravel-cookie-consent` | 3.2.0 | `^3.1` | L10 | 3.5.0 | Lock bump. 3.5.0 needs `illuminate ^11` / `php ^8.2`, so the intermediate hops need the ladder below |
| `spatie/laravel-failed-job-monitor` | 4.1.1 | `^4.1` | L10 | 4.5.0 | Lock bump; 4.5.0 covers Laravel 7 through 13 in one release |
| `spatie/calendar-links` | 1.7.1 | `^1.6` | **never** | n/a | **Declares no framework constraint in any version**, so it never blocks. 1.11.1 is the last 1.x and is admitted today. The 2.x line (`php ^8.3`) is an optional cleanup outside this roadmap |
| `petercoles/multilingual-country-list` | 1.2.12 | `^1.2` | **L12** | 1.2.14 | Lock bump, and **maintained** - 1.2.14 (2026-04-11) added `~13`. The earlier "verify maintenance status at Phase 10" is answered |
| `guzzlehttp/guzzle` | 7.4.1 | `^7.0.1` | never | 7.15.3 | **Stay on `^7`.** 8.0.2 exists, but `laravel/framework` v13 declares `guzzlehttp/guzzle: ^7.8.2` in `require`, so bumping the major is a resolution failure |
| `phpunit/phpunit` (dev) | 9.5.14 | `^9.3.3` | - | 12.5.x | 9.5 -> 10 (Phase 5) -> 11 (Phase 9) -> 12 (Phase 10). **12 is the ceiling** at PHP 8.3 - PHPUnit 13 requires `php >=8.4.1` |
| `nunomaduro/collision` (dev) | 5.11.0 | `^5.0` | - | 8.9.5 | **Constraint edits**, in lockstep with the framework: `^6` (Phase 4) -> `^7` (Phase 5) -> `^8` (Phase 8). Declares no `illuminate/*`, so Composer will not catch a mismatch |
| `barryvdh/laravel-debugbar` (dev) | 3.6.7 | `^3.6` | L10 | **4.0.10+** | **Constraint edit** `^3.6` -> `^4.0` at Phase 10 - the 3.x line stops at 3.15.4 / Laravel 12. Also **stop hard-registering it in `config/app.php`** (TODO 25) |
| `laravel/sail` (dev) | 1.13.4 | `^1.0.1` | L10 | 1.65.0 | Lock bump |
| `mockery/mockery`, `fakerphp/faker` (dev) | 1.5.0 / 1.19.0 | `^1.4.2` / `^1.9.1` | - | 1.6.12 / 1.24.1 | Lock bumps only; neither declares a framework constraint |

### The per-hop version ladder

For every package that survives the upgrade, the **lowest** release admitting each Laravel major - so a hop's item can read a number instead of estimating one. Measured the same way as the table above.

| Package | L9 (Ph. 4) | L10 (Ph. 5) | L11 (Ph. 8) | L12 (Ph. 9) | L13 (Ph. 10) |
| --- | --- | --- | --- | --- | --- |
| `astrotomic/laravel-translatable` | 11.11.0 | 11.12.1 | 11.15.1 | 11.16.1 | 11.17.0 |
| `laravolt/avatar` | 4.1.7 | 5.0.0 | 5.1.0 | **6.1.2** | 6.4.0 |
| `spatie/laravel-activitylog` | 4.7.1 | 4.7.3 | 4.10.0 | 4.11.0 | 4.12.3 |
| `spatie/laravel-cookie-consent` | 3.2.3 | 3.2.4 | 3.3.2 | 3.3.3 | 3.5.0 |
| `spatie/laravel-failed-job-monitor` | 4.1.0 | 4.2.1 | 4.3.2 | 4.3.5 | 4.5.0 |
| `petercoles/multilingual-country-list` | 1.2.9 | 1.2.11 | 1.2.12 | 1.2.13 | 1.2.14 |
| `laravel/fortify` | 1.10.1 | 1.19.1 | 1.21.0 | 1.31.3 | 1.36.2 |
| `laravel/tinker` | 2.7.3 | 2.8.2 | 2.10.0 | 2.10.2 | **3.0.0** |
| `laravel/sail` | 1.3.1 | 1.19.0 | 1.27.2 | 1.52.0 | 1.65.0 |
| `barryvdh/laravel-debugbar` (dev) | 3.6.8 | 3.8.0 | 3.10.2 | 3.15.4 | **4.0.10** |

Two cells are boundaries rather than numbers. `laravolt/avatar` crosses the Intervention Image break between L11 and L12 - which is why **TODO 39.1 jumps straight to 6.5.1 at Phase 5** and does the break once, early, instead of meeting it in Phase 9. `laravel/tinker` and `barryvdh/laravel-debugbar` cross a major between L12 and L13, and neither is admitted by the constraint declared today.

Packages absent from the ladder either leave before Laravel 13 (`laravelcollective/html`, `fideloper/proxy`, `fruitcake/laravel-cors`, `facade/ignition`, `doctrine/dbal`, and the five replaced in Phase 3) or declare no framework constraint at all (`spatie/calendar-links`, `guzzlehttp/guzzle`, `phpunit/phpunit`, `nunomaduro/collision`, `mockery/mockery`, `fakerphp/faker`).

---

## Appendix B - Optional: Livewire 3 -> 4

Not part of the Laravel 13 upgrade and not blocking. Livewire 4 supports Laravel 10 through 13, so it can be evaluated independently once the framework upgrade has shipped and stabilized.

- [ ] **TODO 76: Evaluate Livewire 4 as a follow-up project**
  - Needed:
    - Assess after Phase 12 is released and stable in production.
    - Scope it as its own roadmap; do not bundle it with a framework hop.
  - Expected changes: a separate decision document.

---

## Appendix C - Post-Upgrade Refactoring

Deliberately scheduled **after** the Laravel 13 upgrade is released and stable. Doing it earlier would mean rewriting the same code twice - once for Livewire 3 and once for the refactor - and would invalidate the regression tests mid-upgrade.

- [ ] **TODO 77: Eliminate the duplicated scheduling logic between `Events\Modal` and `Events\EventEdit`**
  - Context: `app/Http/Livewire/Events/Modal.php` (562 lines) and `app/Http/Livewire/Events/EventEdit.php` (706 lines) each carry their own near-identical copy of the day-table builder - `getInfo()` at `EventEdit.php:124-364` and `Modal.php:71-394`, roughly 560 of the 1268 combined lines. Both independently reimplement slot generation, the `publishers`/`accepted` counters, the approval-based capacity ceiling (`max_publishers + config('events.max_columns')`), the `full`/`ready` status marking, and the `day_selects` filtering. `getRole()` is duplicated as well.
  - Why this matters: this is the application's central business logic, and the two copies **have already drifted apart** - TODO 07.1 proved it. The duplication also doubled the work in Phase 6 (every Livewire 3 change had to be applied twice) and doubles the test surface.
  - **The drift, measured (TODO 07.1).** `Modal.php:352` increments `publishers` for every event; `EventEdit.php:288-291` increments it only for accepted ones, and always in lockstep with `accepted`. Consequences to resolve here:
    - In `EventEdit` the two counters are always equal, which makes the approval-based ceiling in `saveEvent()` (`:477-482`) **unreachable dead code** - the `+ config('events.max_columns')` branch can never fire. The ceiling is enforced only indirectly, via the `$slots` count and the `day_selects` filter, which is why users hitting it see `event.invalid_value` instead of `event.reach_max_publisher`.
    - The extraction must therefore make an explicit decision: **should pending applications consume publisher capacity?** The calendar view (Modal) says yes, the save check (EventEdit) says no. Whichever is chosen, both the counter semantics and the user-facing error message need to follow it, and `EventModalSlotTableTest` plus the ceiling tests in `EventCapacityTest` must be rewritten to the decided behaviour rather than merely re-pointed.
    - ~~While in here, fix the `min([])` crash~~ **Already fixed on `v1-patch`** - the cell allocation tolerates more events on a slot than the current maximum allows, and the pinning test was inverted. Nothing left here for that half; the counter-semantics decision above is what remains.
  - Needed:
    - Extract the shared logic into a dedicated service or action class (for example `app/Classes/DayScheduleBuilder.php`, alongside the existing `app/Classes/GenerateSlots.php`), and have both components consume it.
    - Move the capacity and overlap rules out of the Livewire components entirely, so they can be unit-tested without a component harness.
    - **Prerequisite: TODO 07.1 must be complete and green.** Done - those tests are the proof that the extracted service behaves identically to both originals. Run them against each component before and after the extraction.
    - Consider whether `Events\EventEdit` still needs to be a separate component once the logic is shared.
  - Expected changes:
    - New service class under `app/Classes/`, both Livewire components substantially slimmed, the TODO 07.1 tests re-pointed at the service where they no longer need a component.
    - Updated `.docs/components.md`.

---

## Suggested Execution Strategy

- One PR per phase, or per TODO cluster within the larger phases.
- **Do not skip intermediate major versions.** Apply and validate each hop: 8 -> 9 -> 10 -> 11 -> 12 -> 13.
- Keep tests green at every hop before moving forward. A red suite is a stop condition, not a known issue.
- **Phase 1 (test coverage) is a hard gate.** No framework version changes until it is complete - it is the only thing that makes the later phases verifiable.
- Phase 6 (Livewire 3) and Phase 7 (Vite) are **standalone releases**, deliberately separated from framework hops so that a regression can be attributed to one cause.
- The interpreter switches exactly once, in Phase 5: `php81` for Laravel 8 and 9, the default `php` (8.3) from Laravel 10 through 13. No PHP install is required. Update the *Execution Environment* section in the same commit as the switch.
- Update the relevant `.docs/` files in the same change set as the behavior change, per the rules in `AGENTS.md`.
