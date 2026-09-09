# Laravel 8 -> Laravel 13 Upgrade Roadmap

This roadmap is designed for multi-step execution by AI agents.
Each item is intentionally small enough to complete and mark independently.

## Execution Environment (Important)

- **The default `php` on this machine is PHP 8.3.16, and it is the runtime the project uses.** The interpreter switched at Phase 5 (TODO 39.2's follow-up commit), one step ahead of the framework hop. This bullet used to say 8.3 "cannot boot the current Laravel 9 baseline" because `nesbot/carbon`'s `setLastErrors()` threw a `TypeError`; that reason expired with the `v1-patch H` refresh, and the roadmap said to measure it before writing it up as a risk. Measured: the Laravel 9 suite is green on 8.3 with **the same 1469 tests and 4030 assertions** it reports on `php81`, and with no deprecation notice.
- **Run all Laravel commands with `php artisan ...`.** `php81` is still installed and still works on this branch - Laravel 9 supports `^8.0.2` - but nothing needs it any more, and `composer test` no longer calls it.
- The required runtime changes as the upgrade progresses. After each framework hop, switch the interpreter according to the *Runtime and Version Matrix* below and update this section.
- Locally installed PHP runtimes (Laragon): `php-8.1.30-nts-Win32-vs16-x64`, `php-8.3.16-Win32-vs16-x64`. PHP 8.0 is not installed at all.
- **No additional PHP runtime needs to be installed.** Laravel 11 and 12 declare `php: ^8.2`, which the installed PHP 8.3 satisfies, and Laravel 13 declares `^8.3`. `php81` served Laravel 8 and 9 and is now unused; the default `php` (8.3) carries the rest of the path.
- Composer is 2.10.2 and enforces `config.allow-plugins`. TODO 24 added an explicit **empty** block, i.e. deny-by-default; the Laravel 9 resolution introduced no plugin, so it is still empty after TODO 34.
- **`config.platform.php` is gone.** TODO 24 raised it to `8.1.30` rather than removing it, because Composer ran on 8.3 while the application ran on 8.1 and an unpinned resolution could lock versions the real runtime could not execute. That rule said to delete the key once the two coincide, and the interpreter switch is when they do.
- **`composer audit` runs at the framework hops and once at the end (TODO 73.1) - not per item.** An item that touches no `composer.json`, no `composer.lock` and no `vendor/` cannot change this project's exposure, so running the audit inside one measures the **upstream advisory database**, not the work. That database moves on its own: entries are published, merged, withdrawn and re-classified between two runs of an unchanged lock, in both directions. Recording a count in a non-hop entry therefore produces a figure that drifts by itself and reads, later, as if the item had caused it. Delivered non-hop entries state that no composer file moved and stop there. Where an advisory **does** arrive mid-item and forces a lock bump, that is a composer change like any other and gets its own commit and its own entry - TODO 39.2 is the worked example. The `config.policy.advisories.ignore-id` block is re-evaluated at each hop, which is the practice TODO 24 established and TODO 34 followed.

## Status Convention

- `[ ]` not started
- `[x]` completed

## Runtime and Version Matrix

| Phase | Laravel | PHP required | Interpreter to use | PHPUnit | Collision | Node |
| --- | --- | --- | --- | --- | --- | --- |
| Phase 0-3 baseline | 8.83.29 | `^8.0` (pinned to 8.1.30 by TODO 24) | `php81` | 9.5 | 5.x | 24.18 (unused) |
| Phase 4 | 9.52.21 | `^8.0.2` | `php81`, then `php` (8.3) | 9.6 | 6.x | 24.18 (unused) |
| **Phase 5 - CURRENT** | **10.50.3** | **`^8.1`** | **`php` (8.3)** | **10.5** | **7.12** | 24.18 (unused) |
| Phase 7 | 10.x | `^8.1` | `php` (8.3) | 10.x | 7.x | 24.18 (only if TODO 52 keeps a build) |
| Phase 8 | 11.x | `^8.2` | `php` (8.3) OK | 10.x/11.x | 8.x | 24.18 |
| Phase 9 | 12.x | `^8.2` | `php` (8.3) OK | 11.x | 8.x | 24.18 |
| Phase 10 | 13.x | `^8.3` | `php` (8.3) OK | 12.x | current major | 24.18 |

- **Node is listed for completeness only.** No phase before 7 uses it: the Mix pipeline is dead (zero `mix()` calls, empty entrypoints, no outputs), and the assets are served by the `pwbs_asset()` helper since TODO 33.8 removed `eusonlito/laravel-packer`. Whether Node is ever needed is the open question in the Phase 7 preamble.
- **Both required runtimes are already installed.** `^8.2` is a caret constraint, so PHP 8.3 satisfies Laravel 11 and 12; no PHP 8.2 install is needed.
- ~~**Note on Phase 4:** although Laravel 9 declares `^8.0.2`, its officially tested ceiling is PHP 8.2, and the current `nesbot/carbon` line already fatals on PHP 8.3. Stay on `php81` for Laravel 9 and only move to PHP 8.3 once Laravel 10 is in place.~~ **Overtaken by measurement at Phase 5.** The Carbon fatal was real when this note was written and is not any more - 2.73.0 carries the untyped `setLastErrors()`. The switch happened at the start of Phase 5, on Laravel 9 and **before** the framework hop, as its own commit, so a runtime change and a framework change are never in the same diff. "Officially tested ceiling" remained a fair caution and is why it was measured rather than assumed; the suite is green on 8.3 with its numbers unmoved.
- PHP 8.4 is not installed. Laravel 13 accepts `^8.3`, so 8.4 is forward-looking only (TODO 68).
- Do not skip intermediate majors. Each hop gets its own composer resolution, its own test run, its own `composer audit`, and its own PR. **The audit belongs to the hop and to nothing smaller**: the hop is where the lock moves, so it is the only point before Phase 12 at which the run says something about this project rather than about the upstream advisory database. See *Execution Environment* for why, and TODO 73.1 for the final run.

## Project-Specific Baseline (Current State, verified)

### Framework and dependencies

- ~~Laravel **`8.83.29`** on branch `v2-dev`, the last 8.x tag; `composer.json` requires `laravel/framework: ^8.12`.~~ **Superseded by TODO 34 (2026-08-11): Laravel `9.52.21`, `composer.json` requires `^9.0`.** The 8.83.29 figure was itself a correction - this line read `8.83.1` until the 2026-08-09 fast-forward brought the v1-patch H security refresh onto `v2-dev`. Everything below this bullet still describes the state Phase 4 started from unless a bullet says otherwise; that is the point of a baseline section.
- PHP constraint was `^8.0` with `config.platform.php = 8.0.9`, which artificially held back every dependency resolution - and was three patch lines below the runtime that actually executes the code (`php81` = 8.1.30). See the correction in TODO 24: the fix is to raise the pin, not to drop it, because Composer here runs on 8.3. **Since TODO 24 the pin is `8.1.30`; since TODO 34 the declared constraint is `^8.0.2`**, Laravel 9's own floor.
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
- ~~`phpunit.xml` uses the PHPUnit 9 schema. **Six** `@dataProvider` annotations use **non-static** provider methods, which PHPUnit 11 forbids (measured in TODO 15; this line previously said two - see TODO 40 for the list).~~ **Closed by TODO 40 (2026-09-08).** `phpunit.xml` is on the PHPUnit 10 schema, and the annotations are `#[DataProvider]` attributes with static providers throughout. The count was wrong twice over: **36 annotations in 18 files, and ten** non-static providers, not six.
- `.phpunit.result.cache` contains stale defect entries for tests that no longer exist. It must be deleted before recording a baseline.

### Code-level upgrade risks

- ~~`app/Http/Middleware/TrustProxies.php:5` extends `Fideloper\Proxy\TrustProxies`; `app/Http/Kernel.php:23` uses `Fruitcake\Cors\HandleCors`. Both packages are removed in Laravel 9.~~ **Closed by TODO 35** (2026-08-11): both now name their framework classes and both packages are gone. The "removed in Laravel 9" wording was loose - the framework *absorbed* the functionality in Laravel 9, but both packages kept installing and booting fine on it, so this was never a blocker, only dead weight.
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
  - **A latent bug found and pinned:** `Groups\Statistics` has its `public $months` declaration commented out (`Statistics.php:16`) while `getMonthListFromDate()` and `setMonth()` still use it. It therefore becomes a **dynamic property**, which Livewire does not persist between requests - so `setMonth()`'s `isset()` check is always false and **the month selector silently does nothing**. The identical code works in `Groups\History`, where `$months` is declared. ~~Extra upgrade risk: dynamic properties are deprecated from PHP 8.2, so this will start emitting notices in Phase 8.~~ **Corrected by TODO 41 (2026-09-09), and wrong in both directions.** The runtime has been PHP 8.3 since the Phase 5 interpreter switch, so the eleven dynamic properties this project really had were raising deprecations already; and Phase 8 would not have shown them either, because what swallows them is the framework's test-mode error handler, not the interpreter version. All eleven are declared now, and the guard TODO 41 added is what makes the next one visible on the day it appears.
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
  - **Setup: the group did not exist in tests at all.** `storage/app/installed.txt` is present in the working copy, so `routes/web.php:104` never registered the routes - which is also why the route-contract fixture has no `setup.` entry. The real sentinel is deliberately untouched: `storage/app/.gitignore` excludes everything, so an interrupted test that deleted it would leave the development app stuck in "not installed" with no way to restore it from git. `SetupTestCase` instead overrides `createApplication()` and calls `useStoragePath()` on a temporary directory **before** `bootstrap()`, then restores `view.compiled` to the real path so the Blade cache is not rebuilt (TODO 07 measured that cost: 50s -> 10 minutes). Both directions are proven - `SetupFlowTest` sees the routes, `SentinelGuardsTheInstallerTest` (on the normal `FeatureTestCase`) sees 404s and asserts the fixture agrees.
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
  - **Full removal of the package command is decided in TODO 16 and was executed in TODO 33.2 - DONE.** `GdprServiceProvider::boot()` scheduled it inside an `app->booted()` callback, which runs *after* `Kernel::schedule()`, so it could not be filtered out of the schedule; only removing the package did. At the time of this entry `schedule:list` still showed the same entries and `SchedulerRegressionTest` was unchanged; **TODO 33.2 took the schedule from 17 entries to 16, deleted `PackageAnonymizeInactiveUsers` and emptied `Kernel::$commands`.** The divergence that remained here - the 00:00 timing and the missing membership detach - is retired with it.
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
    - **Trap recorded for whoever touches this next:** the source scanner must never be compared to the fixture by count. `setup.*` routes sit behind `web.php:104`'s `if (!Storage::exists('installed.txt'))`, so they exist in the source but not in the runtime snapshot. Duplicate detection is unaffected (those names are unique). A companion test (`test_the_route_files_use_no_group_level_name_prefixes`) keeps the scanner's flat-namespace assumption valid by rejecting `Route::name(` and `'as' =>`.
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

**TODO 39.1 added a step after that one, and it is the cheaper one to take first:** having looked at what the project calls through the library, ask whether it needs the package at all. Here the answer was two letters on a coloured circle, and the whole transitive-major problem evaporated. The reading rule above is for deciding how big a bump is; it cannot tell you the bump is unnecessary, and this project has now answered "remove" six times against three "upgrade"s.

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
    2. **A live link reverses the anonymization.** `PendingUserEmail::activate()` does not look at `isAnonymized`. Until the signed link expires, opening it writes the real address back onto the anonymized user **and marks it verified**, leaving a row that reads as anonymized while carrying real data. **CORRECTED: fixed on `v1-patch`, not in TODO 33.2.** `App\Models\PendingUserEmail` is a project subclass whose `activate()` deletes the pending rows and returns without writing anything when the user is anonymized or missing; `config/verify-new-email.php` points `model` at it. TODO 33.2 preserved it rather than writing it.
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

    In Intervention Image 2, `stream()` is **not a real method**: it is an `@method` line on the class docblock (`Image.php:53`) dispatched by `__call()` (`Image.php:106`) to `Commands\StreamCommand`. The v4 `Image.php` has no `stream()`, no `__call()` and no `Commands` namespace - it has `encode(EncoderInterface)` and `encodeUsingFormat(Format)`. `getImageObject()` survives the jump (6.5.1 still declares it, returning `\Intervention\Image\Image`), so the call site fails at the *next* hop rather than at the facade. ~~**Decision: go straight to `^6.5` at Phase 5** - 6.5.1 covers Laravel 10 through 13 and needs PHP >= 8.2, which the phase already provides, so it is one jump and one break instead of two.~~ **Superseded by TODO 39.1 (2026-09-07): the package was removed instead.** Two things this correction got wrong, both worth carrying forward. First, "which the phase already provides" was an assumption, not a check - the phase pins `config.platform.php` at 8.1.30, so the jump could not have resolved as scheduled. Second, and more useful: the measurement never asked what the dependency was *for*. One call site, two letters on a circle, and a package whose configured fonts had never existed on this install. Pinned by TODO 22.1, whose tripwire did exactly its job - it just fired on a different decision than the one it was written for.

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
  - **Outcome, recorded by TODO 39.1 (2026-09-07).** Seven of the ten tests were rewritten and the file now describes a computed SVG rather than a stored PNG. That is not the suite failing at its job - it is the suite doing it. The characterization made the mechanism legible enough to ask whether it was worth keeping, the answer was no, and the rewrite is the evidence of a genuine behaviour change rather than a silent one. Two of its measurements outlived the code they described: the disk-root note (already correct here, and stale only in the TODO 39.1 entry that has since been superseded), and the "single call site" count, which is now a guard on the replacement instead. The colour compatibility that made the removal safe was read out of the package **while this suite still held it in place**.
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

**TODO 33.2 shipped on 2026-08-10**, in one sitting rather than the multi-day
work this paragraph priced it at. Suite **1295 -> 1290 tests, 3613 assertions**.
The estimate was wrong the same way TODO 33.8's was: the item was priced by its
file count and its package removal, while the hard part - the decision, and the
68 tests that defined acceptance - had already been done by TODO 16 and TODO 12.
Two things it found that no plan had listed: emptying `email_verified_at` would
have let `users:purge-unverified` **hard-delete every anonymized user within the
hour**, and a faithful copy of the vendor trait would have silently dropped four
of the newly cleared columns, because `update()` honours `$fillable` and none of
them is in it.

**TODO 33.3 shipped on 2026-08-10 as well**, also in one sitting, suite
**1297 -> 1333 tests**. Its lesson is the sharpest one this phase has produced:
the roadmap told it to flatten language files with `Arr::dot()`, and that would
have corrupted roughly one key in five of every root JSON file the first time
somebody pressed save. The instruction had been written from reading the vendor
driver, not from measuring the data.

**TODO 33.5 shipped on 2026-08-10 too**, again in one sitting, suite
**1340 -> 1361 tests** including a same-day audit follow-up. Its first lesson is
not about the package at all: the plan
said "fail into a flash message", and the obvious key for that - `error` - is
rendered by **no view in this project**. Writing it would have reproduced, in the
fix, the exact defect TODO 19 recorded as its sixth finding: the package's own
error message never reached a user either. Before adding a flash, grep for what
the layouts actually render. The other measurement worth carrying forward: all
three numbers this item was specified with (30 tests, 5 known-gap cases, 2
language files) were stale by the time it ran - 32, 7 and 3. Re-measure before
executing a Phase 2 specification, however precise it looks.

**And then an external audit found three more defects in the same change set**,
two of which were the original defect in a different disguise. The one worth
carrying into every later item: **a guard that reads its condition before the
window and writes after it is not a guard**, and `Model::save()` writing only the
dirty attributes is what makes that failure silent rather than loud. The second:
**an observer only fixes the paths that go through the model** - a builder delete
fires no events, and this codebase had two of those on live routes. Both are
patterns, not accidents of this feature; the roadmap has other items that lean on
observers and on read-then-write guards.

**TODO 32 shipped on 2026-09-06**, which closes this phase - and it shipped
**without squashing anything**. The entry deserved its own plan, but not for the
reason the warning about multi-day items suggested. The work was one sitting;
what changed under measurement was the **size of the problem**. "16 `->change()`
calls across 8 files" reads like sixteen hazards, and the entry priced a squash
against that impression. Measured, **two columns** diverge under Laravel 11's
native `change()`, and one migration restating eight definitions closes it.

The squash was built and verified byte-for-byte before being reverted, so the
comparison is not hypothetical, and two lessons came out of it. The first
repeats one TODO 33.3 already paid for: the entry prescribed a `schema:dump`
baseline, and that form **cannot run on the hosting this project installs
onto** - loading a dump shells out to the `mysql` client while the installer
runs `migrate:fresh` from a web request, and it would have failed silently,
because the schema path is derived from the connection name and the installer's
connection is called `setup`. An instruction written from reading the framework
is not the same as one written from running it.

The second is new and larger: **measure the blast radius of the problem before
choosing the size of the solution.** A baseline would have made a
release-manifest key load-bearing for schema correctness on live installations,
which is a real operational risk accepted to avoid two `ALTER TABLE`
statements. It took the user asking whether a small migration would have done
instead to get the two numbers compared at all. One small, well-specified
follow-up also sits here unclaimed: converting
`resources/views/public.blade.php` to `pwbs_asset()`, recorded at the end of
TODO 33.8.

**TODO 33.9 shipped on 2026-08-11**, sliced into three commits exactly the way
the entry recommended - by tree, smallest first: `database/` + `routes/` +
`config/` + `resources/views/` (33 files) first, then `app/` (75 files), then
`tests/` (97 files) last, since that tree's comments carry the measurements
and control experiments earlier phases recorded and needed the most careful
read-through. `composer test` stayed at **1361 tests, 3790 assertions, green**
across all three commits - the entry's zero-behaviour-change requirement held.
A handful of Hungarian-accented spots were deliberately left untranslated
because they are data, not comment prose, the same distinction the entry
itself drew: exception message strings actually shown to users
(`WeatherException`), the environment guard messages a developer sees when
`composer test` catches a misconfigured `.env` (`CreatesApplication`), test
fixture values and assertion-failure messages throughout `tests/`, and
commented-out `Log::debug()` calls in `GroupDateHelper` whose string argument
is dead-code data rather than prose.

> **TODO 33.8 was on that list, and it should not have been.** It shipped on
> 2026-08-09 in two commits, in one sitting. The estimate was wrong because the
> item was priced by its file count (a helper, three blades, `composer.json`, the
> vendor tree, `.gitignore`, the release hook, two test files, two documents)
> rather than by its risk, and the specification TODO 21 left behind - including
> the 17 tests that defined acceptance - had already done the hard part. It was
> pulled forward because one of its defects reached a browser: see finding 1b.
> The lesson is worth keeping for the four items still listed: a large *diff* and
> a large *decision* are not the same thing, and only the second one takes days.

~~One loose end noticed on the way and not worth its own TODO: `composer validate`
warns that `dialect/laravel-gdpr-compliance` is pinned to the exact version
`1.4.7`.~~ **Closed by TODO 33.2**, which removed the package. It was the only
exact pin in the file, so `composer validate` is now clean.

### The `v1-patch` branch - a last Laravel 8 release, cut before the framework moves

Branched from `v2-dev` (which already contains all of `dev`, so the Phase 0-2 test
suite sits directly on top of the production line). The goal was a final Laravel 8
patch carrying the test suite and the measured bug fixes to the `v1` line, so the
value of Phases 0-2 does not have to wait for the whole upgrade.

**Scope decision, taken by the user:** behaviour-neutral fixes plus the real
production defects, the weather feature fixed and finished, and three
behaviour-changing items explicitly approved. Deliberately OUT of scope, and
recorded below: TODO 33.8 (packer), TODO 33.2 and 33.5 (GDPR and pending-email
package replacements, though two GDPR-relevant defects were fixed without
replacing anything - and TODO 33.2 later carried both through its rewrite), and
the pure upgrade-preparation items TODO 23, 24, 27, 29
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
    - ~~**The block is temporary and must be re-evaluated after TODO 34.**~~ **Re-evaluated in TODO 34, and the answer is: all three stay, and a fourth joined them.** Measured with Composer's own `Semver::satisfies()` against Packagist's advisory API rather than read off the resolver's error text - which lists the union over a version *range* and is therefore misleading. 8.83.29 was affected by 3 records; 9.52.21 is affected by 4.
      - The three existing ones (fixed in 12.61.1 / 12.60.0 / 10.48.29) all cover the whole 9.x line, so none is obsolete yet. Their `reason` texts said "no Laravel 8 backport" and now say Laravel 9, and each names the hop at which it expires: **PKSA-8qx3-n5y5-vvnd is the first to go, at TODO 39** (fixed in 10.48.29); the other two at TODO 61 (Laravel 12).
      - The fourth, **`PKSA-mdq4-51ck-6kdq` (CVE-2026-48019), is not a new vulnerability**: it is Packagist's *second* record of the same `GHSA-5vg9-5847-vvmq` CRLF issue already ignored as `PKSA-3r5d-mb8f-1qw9`. The two records differ only in their affected range - the GitHub-database one says `<12.60.0`, the laravel/framework YAML one says `>=9.0.0,...` - which is exactly why it blocks from Laravel 9 onwards and never blocked Laravel 8. The mitigation in place is unchanged: the `strictEmail` middleware.
      - **`PKSA-w7xr-vk7n-rstm` (CVE-2024-52301) needed no entry**, although the resolver named it. It is fixed in 9.52.17 and the hop resolves to 9.52.21. Worth recording as the reason not to paste the resolver's ID list into the config: two of the five it printed were noise.
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

- [x] **TODO 32: Squash the migration history** - DONE, but **NOT by squashing**
  - Delivered on 2026-09-06. The squash was built, verified byte-for-byte, and then **deliberately reverted** on the user's decision. What shipped instead is one migration, two test files and one new document. Suite: **1425 -> 1436 tests, 3952 -> 3976 assertions, green**; `database/migrations/` still holds its full history, now 102 files. Nothing is committed.
  - **THE LESSON, and it is the expensive one: the size of the problem was assumed from the shape of the instruction, not measured.** "16 `->change()` calls across 8 files" reads like sixteen hazards, and this entry priced a squash against that impression. Measured, **two columns** diverge under Laravel 11's native `change()`. The rest redeclare enough to survive. The whole item is two `ALTER TABLE` statements' worth of risk, and it took the user asking "wouldn't a migration that fixes those few fields have been simpler?" to get it measured. **Measure the blast radius of the problem before choosing the size of the solution** - this roadmap already had the rule for specifications (`:955`) and did not apply it to its own framing.
  - **CORRECTION to this entry's own numbers.** "97 migrations" is **101**; "16 `->change()` calls across 8 files" is 16 across **7**; "68 `dropColumn`/`renameColumn`" is correct. The schema is 36 tables, 291 columns, 103 indexes and 30 foreign keys - repository figures, since `kozter_testing` is built from the migration path.

  - **What the trap actually is, measured.** Under Laravel 8-10 a `->change()` goes through `doctrine/dbal`, which reads the column's current definition and alters only what the migration redeclares. Laravel 11's native `change()` **drops every attribute not redeclared**. Of the eight `up()` declarations:

    | Column | Declared | Laravel default | Actual | Diverges |
    | --- | --- | --- | --- | --- |
    | `group_news_translations.title` | `string()->nullable()` | NULL | NULL | no |
    | `group_news_translations.content` | `text()->nullable()` | NULL | NULL | no |
    | `groups.name` | `text()` | NOT NULL | NOT NULL | no |
    | `group_posters.info` | `mediumText()` | NOT NULL | NOT NULL | no |
    | `static_page_translations.content` | `longText()` | NOT NULL | NOT NULL | no |
    | `jobs.attempts` | `unsignedSmallInteger()` | NOT NULL | NOT NULL | no |
    | **`events.comment`** | `text()` | NOT NULL | **NULL** | **yes** |
    | **`group_user.note`** | `text()` | NOT NULL | **NULL** | **yes** |

    The charset and collation halves are harmless in this schema, because every table already defaults to `utf8mb4` / `utf8mb4_unicode_ci`, which is what the columns fall back to. **Both divergent columns carry the `encrypted` cast**, and `null` bypasses that cast in both directions, so the flip is a write failure rather than a cosmetic difference. And it bites **nobody on an existing installation** - those migrations have run and never run again - only a **fresh install after the Laravel 11 hop**, which is what the web installer does on customer hosting. The two would then diverge permanently, surfacing months later on the first event saved without a comment.

  - **What shipped:**
    - **`database/migrations/2026_09_06_120000_pin_the_changed_column_definitions.php`.** It **restates** the final definition of all eight columns as raw `ALTER TABLE`, so it does not depend on what any version of `change()` preserves and needs no revisiting when the framework changes its mind again. It compares against `information_schema` first and issues a statement only for a column that has actually drifted: a `MODIFY` on a TEXT column can rebuild the table, and on a deployed host this runs inside the updater's **web request** against tables that are not small. On every existing installation it therefore issues nothing at all - which is the property the test asserts, because "harmless statement" and "no statement" are very different things there.
    - **`tests/Feature/Database/ChangedColumnDefinitionsTest.php`** (5 tests): the no-op case, a nullability repair, a collation repair, idempotence, and a **census** that fails if a new `->change()` call appears on a column the migration does not pin. The list is the assertion, the same shape as TODO 13's cast list.
    - **`tests/Feature/Database/SchemaStructureTest.php`** (5 tests): the exact table set, all 30 foreign keys **with delete rules**, all 13 non-primary unique indexes **with column order**, the primary-key exception, and the absence of the orphaned translation tables. **Nothing covered any of this before** - the only two structural assertions in the suite sat inside the feature tests that happened to need them. Four more framework majors have to replay 101 migrations on every fresh install, and a structural loss raises no error at all: a missing foreign key or unique index fails nothing until the data is already wrong.
    - **`EncryptedColumnSchemaTest` gained `COLLATION_NAME`.** This entry demanded collation survive and nothing pinned it. The control proved it earns its place: changing one column's collation fails **exactly** this new case while `test_every_encrypted_column_stays_on_utf8mb4` stays green.
    - **New `.docs/database.md`**, plus the index and a pointer from `.docs/models.md`.

  - **Why the squash was reverted, and it is a live-data argument rather than a taste one.** The updater unpacks an archive and runs `migrate --force`, so an installation several releases behind gets every migration it has not run yet **from its own copy of the files**, with no coordination. A baseline cannot do that: it must carry a guard that skips it on any existing installation, so anything folded into it never reaches a host that was behind, and that host's schema freezes silently. The only defence would have been the `previous_version` chain in `laraupdater.json`, which would have made a release-manifest key **load-bearing for schema correctness** rather than for the major-version ceiling alone. That is a new operational risk on live data, taken on to avoid two `ALTER TABLE` statements. The user's call, and the right one.

  - **The squash was built and fully verified before being reverted, so its measurements are real and are the reason to trust the alternative.** Recorded because re-deriving them costs a day:
    1. **`database/schema/*.sql` is not usable here, whatever form the baseline takes.** `MySqlSchemaState::dump()` shells out to `mysqldump` and `load()` runs `Process::fromShellCommandline('mysql … < file')` - external binaries, a shell and `proc_open`, none guaranteed on customer hosting where `migrate:fresh` runs from a web request. And it would have failed **silently**: `MigrateCommand::schemaPath()` builds the path from the **connection name**, the installer's connection is called `setup`, so it would look for `setup-schema.sql`, never find `mysql-schema.sql`, skip the load without raising anything and build a near-empty database. `SetupDatabaseTest`'s docblock records that this branch is deliberately untested, so the suite would not have caught it. **If a squash is ever revisited, it cannot be `schema:dump`.**
    2. **Parity, measured rather than argued.** A PHP baseline of 35 raw `CREATE TABLE` statements, generated from a scratch database migrated with the pre-cut files only, was compared against a database built from the full history across `TABLES`, `COLUMNS` (including `ORDINAL_POSITION`, `COLUMN_DEFAULT`, `EXTRA`, `COLUMN_COMMENT`), `STATISTICS` (excluding the sampled `CARDINALITY`), `KEY_COLUMN_USAGE`, `REFERENTIAL_CONSTRAINTS` and `CHECK_CONSTRAINTS`: **zero differences**, and the full suite was green on it. The squash worked. It was reverted for the reason above, not because it failed.
    3. **The installer path was driven by hand**, since the suite cannot: `migrate:fresh --database=setup` produced the same 35 tables with zero mismatches. The interesting part is *why* it works - `Migrator::usingConnection()` swaps the DatabaseManager's **default** connection, so a migration's bare `DB::statement()` and `Schema::hasTable()` follow `--database`. That is a framework internal worth knowing before writing any migration that uses the facades directly.
    4. **The cut line, if it is ever needed.** `v.1.2.0` is the same commit as `v1`; `v1` carries 98 migrations and `v2-dev` 101, the difference being exactly the three `2026_08_10_*` files, none of which has ever shipped. `kozter_live` cannot answer "has every host run this?" - it is a developer copy migrated along with `v2-dev`, one batch per delivered item.

  - **Four findings that outlived the revert:**
    1. **`password_resets` has no primary key.** The structural test first asserted that every table has one and failed on exactly that table - Laravel's own stock migration creates an indexed `email`, a `token` and a nullable `created_at`, nothing more. The assertion pins the exception rather than the rule, so a *second* such table is still an error.
    2. **DDL causes an implicit COMMIT in MySQL.** A single `ALTER TABLE` inside a test ends the transaction `RefreshDatabase` opened; the rollback then does nothing, the seeded fixtures survive into the next test and it dies on a duplicate e-mail address. That is how this was found. Any test performing DDL must do it on its own database - `ChangedColumnDefinitionsTest` carries the pattern.
    3. **`SHOW CREATE TABLE` is not idempotent on the first round trip.** The first parity run reported 25 differing tables and **zero** `information_schema` differences, which is the signature of a rendering artefact rather than a schema one. A column inheriting its collation prints only `COLLATE utf8mb4_unicode_ci`, because that is not utf8mb4's default collation on MySQL 8; feeding that text back makes the collation explicit, so the next dump also prints `CHARACTER SET utf8mb4`. Normalising the clause away leaves zero differences, and a second round trip is a fixed point - both verified.
    4. **The `disableForeignKeyConstraints()` reflex is wrong for a generated schema.** With the checks off, a missing table lets every dependent `CREATE TABLE` succeed and surfaces far from the cause; in topological order with the checks on, the omission fails on the statement that needs it. It also avoids leaving `FOREIGN_KEY_CHECKS=0` on a connection if a statement throws, which on the updater path is a live web request.

  - **CORRECTION to TODO 57 (`:2183`), which this entry invalidates.** It reads "TODO 32 already neutralized the 16 `->change()` calls by squashing." The calls are still there and still need `doctrine/dbal` on Laravel 8-10. What TODO 32 delivered is that their **outcome** no longer depends on `change()` semantics: `2026_09_06_120000` restates the final definitions afterwards. Removing `doctrine/dbal` is still safe at Phase 8, because Laravel 11's `change()` is native and needs no package - the corrective migration is what makes its behaviour harmless. Corrected in place.

  - **Not done, and deliberately:** `doctrine/dbal` stays in `composer.json` and `DatabaseController::databaseHasData()` still calls `getDoctrineSchemaManager()`; both are TODO 57. The migration history stays as it is - see the revert reasoning above, and `.docs/database.md` for the standing version of it.
  - Expected changes: as delivered - 1 new migration, 2 new test files, 1 test extended, 1 new document, 2 documents touched.
  - Original specification, unchanged:
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
    - ~~Decide the fate of `RedirectIfUnansweredTerms`, which is never registered in `app/Http/Kernel.php`.~~ **Decided in TODO 16: it is deleted with the rest of the consent feature, in TODO 33.2 - DONE, the file is gone.**
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

- [x] **TODO 33.2: Replace `dialect/laravel-gdpr-compliance` with in-house code** - DONE
  - Delivered on 2026-08-10, in three steps with a control run between each. Suite **1295 -> 1290 tests, 3622 -> 3613 assertions, ~2:25, green**. The package is gone from `composer.json`, `composer.lock` and `vendor/`; `composer validate` no longer warns about the exact version pin, because that line was the only one in the file.
  - **The numbers this entry was written with had all drifted, and every one of them was measured again before being used.** The suite was 957 at the time of TODO 16 and is 1295 today; `SchedulerRegressionTest` holds **17** entries, not 13, and lives in `tests/Unit/Scheduler/`, not `tests/Feature/`; the route fixture had **71** app routes, not 70. The "78 -> 75" line was wrong in a different way: **78 is nowhere in the code.** `test_every_named_route_is_accounted_for` builds a set difference from the union of the two fixture files, so it needed no edit at all - only the exact-snapshot test forces the fixture change. The final counts are **17 -> 16** scheduled tasks and **71 -> 68** app routes.
  - **CORRECTION to this entry and to TODO 19: the two pending-email defects were already fixed.** `:646` and `:1615` both say defects 1 and 2 belong here. They shipped on the `v1-patch` line instead - `User::anonymize()` calls `clearPendingEmail()`, and `App\Models\PendingUserEmail` is a project subclass whose `activate()` refuses an anonymized (or missing) user and deletes the row. So the job here was to **preserve** them through the rewrite, which `tests/Feature/NewEmail/` (32 tests) confirms it did.
  - **A live data-loss hazard was found while doing the user's NULL-field request, and it had nothing to do with the package.** `users:purge-unverified` runs hourly at `:50` and **hard-deletes** every row with `email_verified_at IS NULL` created more than a week ago. Emptying `email_verified_at` on anonymization - which the user asked for - drops every anonymized user straight into that selection: they are months old by definition, so the row would have been deleted within the hour, leaving `events.user_id` and `group_user.user_id` dangling and contradicting the design that keeps the row and replaces its data. The command now carries `->where('isAnonymized', 0)`. **Control step: the new test fails without that filter while the three existing purge tests pass** - so the hazard was real, and no existing test covered it.
  - **The user's request, as built: `$gdprNullFields`.** A second declaration next to `$gdprAnonymizableFields`, listing columns that are simply emptied. The vendor could only express that as `'phone_number' => null` inside the one list, which cannot be told apart from "no value was given". `User` now declares **13** columns there and **6** with replacement values. Seven of the thirteen were not anonymized at all before: `two_factor_secret`, `two_factor_recovery_codes`, `remember_token`, `calendars`, `last_login_time`, `email_verified_at`, `accepted_gdpr` - plus `two_factor_confirmed` (`0`, the column is NOT NULL) and `password`. `users.email` was deliberately left as the `Str::random(10)` token: making it nullable is a schema migration this change set did not need.
  - **The new lists only help future anonymizations, so a backfill was needed.** No ordinary run ever revisits an anonymized row: `gdpr:anonymize-inactive` selects on `isAnonymized = 0`, so a row is anonymized exactly once. Everyone anonymized before this change therefore still carries a working password hash, a usable `remember_token` and - where two-factor was on - the encrypted TOTP secret with its recovery codes. `2026_08_10_140000_reanonymize_users_for_the_null_field_list` repairs them. It writes with the query builder rather than through `User::anonymize()`, for two reasons: the policy returns `false` *silently*, so a blocked row would keep its password hash with nothing reporting it, and a migration must not change meaning when `$gdprNullFields` is edited later - the list is frozen at the TODO 33.2 state. It leaves `email`, `name`, `role` and `isAnonymized` alone, all of which the old anonymizer already handled correctly. 7 tests, the load-bearing one being that a **non**-anonymized user is untouched: the whole migration hangs off one predicate, and without it the backfill would wipe every credential in the table.
    - **Method note, and now a rule in `AGENTS.md`: the measurements that drove this were deliberately not written down.** Deciding the shape of the backfill needed the live database - which columns had survived, whether the succession rule would block anyone, what a bcrypt pass would cost. Recording those counts in the migration docblock was a mistake, corrected in the same change set: the repository is distributed (the release archive is built from it), the figures describe one deployment at one moment, and a count of affected rows is itself information about the data set. **Measure freely, write down the mechanism rather than the measurement.** Suite counts are unaffected by this - they come from the repository, not from user data.
  - **Three of those omissions were more than untidiness.** The encrypted TOTP secret and its recovery codes survived anonymization indefinitely; `remember_token` kept a live "remember me" cookie working, because `Auth::logout()` only runs on the profile-initiated path and never in the nightly command; and the password hash itself was untouched, so the only thing making the account unreachable was the replaced address. `getAnonymizedPassword()` now writes a hash of 64 characters nobody holds. This inverted `AnonymizationTest::test_the_password_survives_anonymization` into `test_the_password_becomes_unusable`.
  - **The finding that made `forceFill()` necessary, and it would have been silent.** `update()` honours `$fillable`, and **none** of `two_factor_secret`, `two_factor_recovery_codes`, `two_factor_confirmed`, `remember_token` is in `User::$fillable`. A faithful copy of the vendor trait would therefore have dropped all four without an error, a log or a failing test - personal data left behind by a method whose whole job is to remove it. The trait writes with `forceFill($updates)->save()`; the field list is declared on the model and never comes from a request, so the guard protects nothing here. `test_a_column_outside_fillable_is_emptied_too` pins it, and states the control: switch back to `update()` and it fails on the token while every other column still passes.
  - **The three vendor defects were fixed, and a fourth was found while writing the replacement.** The recursion guard now works (the vendor pushed relation names as array *values* and tested them as *keys*, so it never matched; nothing looped only because `Group` and `Event` declare no `$gdprWith`); the broken Closure branch is gone; the dead `setVisible()` branch is gone. **The fourth: the `getAnonymized{Column}` hook was built from the declared VALUE, not the column name** - `Str::studly($val)` - so it only ever fired for the keyless form, and `'name' => 'Anonym'` was looking for `getAnonymizedAnonym()`. It is built from the column now, which also removes the `Str::studly(null)` PHP 8.1 deprecation every `=> null` entry was triggering. All four are behaviour-neutral today, **and the 68 unchanged GDPR tests passing on the first control run is the proof**.
  - **A fifth change is deliberately not behaviour-neutral:** a keyless entry with no matching hook now throws a `LogicException` instead of writing the column's own name into the column. That silent fallback is the exact mechanism TODO 12 measured - remove `getAnonymizedEmail()` and the second row of a nightly batch dies on `SQLSTATE[23000]`, stopping GDPR retention permanently. A declaration mistake should fail where it is made.
  - **The control sequence, and what each step actually proved:**
    1. Traits and FormRequest into `app/`, 8 imports re-pointed, nothing else touched. **68 tests, 190 assertions, green** - the copy is faithful, measured separately from the removal, and the four defect fixes are confirmed inert.
    2. New tests written and run **before** the application change: **4 failures in `AnonymizationTest`** (all three new column groups plus the password) and **1 in `MaintenanceCommandsTest`** (the purge hazard). The informative half is what passed: the hook-resolution, keyless-throw and recursion-guard tests were already green, because that machinery landed in step 1.
    3. Removal. Full suite **1303 -> 1290**, green.
  - **Test churn, as delivered:** `ConsentTermsTest` (6) and `UnansweredTermsMiddlewareTest` (4) deleted; `AnonymizeCommandDivergenceTest` (9) rewritten as **`AnonymizeCommandTest` (6)** - the old name describes a gap that no longer exists; `AnonymizationTest` 7 -> 14; `AnonymizationSuccessionTest` 26 -> 26 (`test_the_package_command_respects_the_rule` became `test_the_model_call_respects_the_rule`, which is the better test anyway: it exercises the path `DeleteGroupDataProcess` and the backfill migration actually use); `MaintenanceCommandsTest` +1. **One test the plan for this item never listed, found by measuring rather than reading:** `RouteAdditionalBehaviorRegressionTest::test_gdpr_routes_require_auth_and_process_acceptance_download_flow` exercised all three consent routes and had to be narrowed to the download.
  - **Two cases from the old divergence file were worth keeping and are now built differently.** "An anonymized admin stays on the newsletter list" is asserted from the other side - the surviving command detaches, so they do *not* stay - and the routing-guard test that proves no mail leaves is now built by calling `User::anonymize()` directly with the membership intact, which is the state `DeleteGroupDataProcess` and the backfill migration really produce. Its `getSwiftMailer()` line is still the only SwiftMailer dependency in the file and still belongs to Phase 4.
  - **`release/upgrade.php` extended**, same reasoning as TODO 33.4: `install()` never deletes, so only this hook can clear a deployed host. It now removes `vendor/dialect`, `resources/views/gdpr`, `app/Http/Middleware/RedirectIfUnansweredTerms.php` and `app/Console/Commands/PackageAnonymizeInactiveUsers.php`. The `bootstrap/cache/packages.php` / `services.php` lines were already there and matter here too - both named `Dialect\Gdpr\GdprServiceProvider`.
  - **Route contract verified byte-identical**, which was the acceptance criterion for moving the registration: `php81 artisan route:list --name=gdpr` shows exactly one row, `POST gdpr/download` -> `gdpr-download`, `web` + `App\Http\Middleware\Authenticate`. The group is built from `config('gdpr.uri')` and `config('gdpr.middleware')` and sits **outside** every other group in `routes/web.php`, so the config owns the middleware list rather than the surrounding `auth` group. `web` therefore appears twice; `Router::uniqueMiddleware()` collapses it, and `RouteContractSnapshotTest` gathers middleware into array keys anyway, so the fixture entry for `gdpr-download` did not change.
  - `.docs/routes.md`, `commands.md`, `middleware.md` and `models.md` updated in the same change set, including the new anonymization matrix and the corrected "four anonymization call paths" (it was five).
  - **Not done, and deliberately:** `vendor/` is git-tracked by force-add, so the deleted tree is part of the diff - but nothing is committed. `config/gdpr.php` stays; checked that nothing relied on the vendor `mergeConfigFrom` default (the project file overrides both `ttl` and `user_model_fqn`).
  - Original specification, unchanged:
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

- [x] **TODO 33.3: Remove `joedixon/laravel-translation` and build an in-house translation editor** - DONE
  - Delivered on 2026-08-10, in three steps with a control run between each. Suite **1297 -> 1333 tests, 3714 assertions, green**. The package, its Vue 2 / Tailwind bundle, its twelve published views, its vendor language files, `config/translation.php` and its five global helpers are all gone; `admin.translate` is the whole feature.
  - **The roadmap's central instruction for this item was wrong, and measuring is what caught it.** It specified `Arr::dot()` for `read()` and, implicitly, `Arr::set()` for `write()`. Those two are **not inverses**: `Arr::get()` checks a literal key before splitting on dots, so a key that CONTAINS a dot resolves today, while `Arr::set()` always splits. The language tree is full of them - most of `hu/setup.php`, part of `hu/validation.php` and the whole `laraupdater` group, and in the root JSON files, whose keys are English sentences, **roughly one key in five**. Running the prescribed round trip over the tree restructures five `.php` files, `hu` among them.
    - **Control, run before the writer was final:** the class was temporarily switched to an `Arr::dot()`/`Arr::set()` writer and two tests failed - a dotted `.php` key **vanished entirely** (the assertion read `null`), and a JSON sentence key was split into a nested branch. The other fifteen passed. So `LangFiles::read()` returns a structural **path** next to each entry and `write()` addresses that path; the dotted string is a label and a search target, never an address.
  - **The written format is the project's own, not the package's.** The package emitted `var_export()` after a `ksort()`: old `array()` syntax, two-space indent, every file alphabetised on every save. It had already done that to fourteen `en` files; the `hu` tree - hand-written, four-space, short syntax, grouped by meaning, and the locale everything is translated *from* - had never been through it. The new writer keeps the file's own key order, emits short syntax, and uses `var_export()` per key and per value so escaping stays correct and **integer keys stay integers** (the weekday lists). **What is still lost is comments**, since the file is regenerated from its parsed array; the mitigation is that `write()` rewrites nothing when the value is unchanged, which a test pins by leaving a comment in place across a no-op save.
  - **The locale list follows the registry, and that is a visible narrowing.** `locales()` reads `ApplicationSettings::languages()` - the cached reader TODO 31 built - rather than listing directories the way the package did. Several locale directories in this project are not in the registry, some with real content. They do not appear in the editor until an administrator registers them, at which point they arrive **with their files intact**, because `ensureLocale()` never touches an existing directory. Both halves are tested.
  - **The split brain is closed at the one place a locale is created.** `Admin\Settings::languageAdd()` now calls `LangFiles::ensureLocale()`. Before this, that method registered a locale and never made its directory, while the package's UI made a directory and never registered the locale - so the two could drift apart in both directions. The editor deliberately gained **no** "add locale" of its own; a third writer would have re-opened the problem. **Control: without the `ensureLocale()` call exactly one test fails and every other language test passes.**
  - **A latent fatal was found next door and fixed in the same change set.** `CoreSettingsSeeder` wrote `settings.languages` as a bare string per locale, while the installer wrote the object form. Every consumer does `$value['visible']`, and reaching that on a string is a `TypeError` on PHP 8 - so `db:seed` on a fresh database produced a language switcher, group form and translation screen that would fatal on render. The installer path was correct, which is why nothing had ever noticed. The seeder is fixed, `LangFiles::locales()` normalizes all three shapes, and **the control shows the new seeder test failing on the old shape alone.**
  - **A second hardcoded link was found that the plan for this item never listed.** The roadmap named `translation.blade.php` as the file holding the `/languages` URLs. `settings.blade.php` held two more. All of them now point at `route('admin.translate')`.
  - **The move tightened access rather than loosening it.** The package's route group carried `web, auth, can:is-translator, password.confirm`; `admin.translate` also carries `verified` and `profileFull`. The hardcoded links bypassed exactly those two gates, so removing them closes a small hole nobody had recorded. `routes/web.php` itself was not touched, which is what preserves production access for `translator` and `mainAdmin`; `RouteAdditionalBehaviorRegressionTest` keeps all three assertions (403 / password-confirm / 200), re-pointed at the editor.
  - **The orphaned tables are dropped.** `languages` and `translations` were created unconditionally by the package's `loadMigrationsFrom` even though this project always ran the `file` driver, so nothing ever read them. With the package gone the creating migrations leave the migration path, so keeping the tables would have meant old and new installations diverging on schema permanently. `down()` deliberately does not recreate them - the definitions left with the package, so a faithful rollback is not expressible.
  - **Test churn, as delivered:** new `tests/Unit/Support/LangFilesTest.php` (17) and `tests/Feature/Livewire/AdminTranslationEditorTest.php` (16); `AdminSettingsTest` 21 -> 23 and now bound to a temporary language path, because `languageAdd()` writes to disk; `SeederTest` +1; `RouteAdditionalBehaviorRegressionTest` re-pointed; route fixture **68 -> 61** app routes. The roadmap's "70 -> 63" was stale by the three routes TODO 33.2 had already removed.
  - **Audit after delivery, at the user's request, and it found two things.**
    1. **Authorization did not rest where it looked like it rested.** A Livewire ACTION does not travel over the route it was rendered from - it POSTs to `livewire/message`, whose middleware group is only `web`. What re-applies `can:is-translator` there is `Livewire::getPersistentMiddleware()`, a hardcoded list inside the package. **Measured by removing `Illuminate\Auth\Middleware\Authorize` from that list and posting a valid payload as a plain `registered` user: the endpoint answered 200 and the language file was rewritten.** The component now calls `Gate::authorize('is-translator')` itself, in `mount()` and in every writing action; re-running the same control with the middleware still removed gives **403**. This matters beyond tidiness because **Livewire 3 reworks persistent middleware**, and the route middleware alone would have carried the whole guarantee across that hop.
    2. **The JSON writer could empty a file.** `json_encode()` returns `false` rather than throwing - on malformed UTF-8 above all - and the writer concatenated that result, which writes an **empty root JSON language file**: every key gone, and the next save would then "restore" a file holding one key. Found by testing the writer rather than by reading it. The encoder result is checked and the save refused, and the write path now goes through a temporary file that is **read back and compared to the array it was built from** before an atomic `rename()` replaces the original. A language file is loaded on every request that renders a translated string, so a truncated one is an outage, not a bad save.
    3. **The atomic rename is not reliable on Windows, and that had to be measured too.** The first version of the hardening called `rename()` once. Running the writer in a loop showed it failing intermittently with "access denied" on a few percent of attempts - enough to make roughly one save in thirty fail for no reason - and it happens with or without the preceding `require`, so it is the environment rather than the code (this roadmap already records an intercepting scanner on the development workstation, TODO 33.4). With a short retry the failure disappeared over hundreds of runs and never needed more than a second attempt. Deployment is Linux, where this does not arise, but the developer machine is where the editor is exercised most.
    4. **The suite could reach the real language tree, and once did.** Two application language files were found reformatted - values intact, only the syntax rewritten - which is the signature of this writer running against `App::langPath()`. It was not reproducible from any test afterwards, so rather than hunt it, the possibility was removed: `Tests\Feature\FeatureTestCase` now binds `LangFiles` to a temporary directory for **every** feature test, so reaching `resources/lang` takes a deliberate act instead of an oversight. The same reasoning as `SetupTestCase` and the storage path.
    - Also recorded while measuring: `verified`, `profileFull` and `password.confirm` are **not** re-applied to component actions either - that is true of every Livewire screen in this application, not of this one - and `AuthenticateSession` in the `web` group rejects a session whose user was swapped, which is what makes a naive "log in as somebody else and reuse the payload" test answer 401 instead of 403.
  - **A design note worth carrying to TODO 66.1:** `$rows` holds one page of keys, rebuilt from disk whenever locale, group, filter or page changes, and guarded by a token so a render cannot discard what the browser just sent. Livewire serializes public properties into every response and the root JSON group runs to several hundred keys, so carrying the whole group would have been a large payload per request. The visible consequence - changing page without saving discards edits on screen - is stated in the view.
  - `.docs/components.md`, `routes.md` and `models.md` updated in the same change set; the stale "the first two are fixed with the GDPR replacement" note in `models.md` corrected, since TODO 33.2 has shipped.
  - `release/upgrade.php` extended, same reasoning as TODO 33.2 and 33.4: `install()` never deletes, so only the hook clears `vendor/joedixon`, `public/vendor/translation`, `resources/views/vendor/translation`, `resources/lang/vendor/translation` and `config/translation.php` from a deployed host.
  - **Not done, and deliberately:** the `country_code` rule on `languageAdd()` is still `alpha`, which rejects the `pt-br` shape this project already has on disk. It is a separate product question, not this change set's.
  - Original specification, unchanged:
  - **This is the execution of the TODO 17 decision.** It sits in Phase 3 rather than Phase 8 because the package's `require` block is **empty**, so it blocks no Composer resolution at any hop, while the replacement uses only stable filesystem and Eloquent APIs - it can be written and proven green on Laravel 8 today, which takes one package, 417 KB of Vue 2 / Tailwind 0.6 assets and 5 injected global functions out of every subsequent hop. Read TODO 17 first; it carries the measurements, the corrected consumed surface and the split-brain finding.
  - Needed:
    - **Write the editor first, prove it green, and only then remove the package.** Two independent failure sources otherwise. TODO 33.2 did exactly this and it paid: the 68 unchanged GDPR tests passing with the traits moved but the package still installed is what proved the copy faithful, separately from the removal.
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

- [x] **TODO 33.5: Replace `protonemedia/laravel-verify-new-email` with in-house code** - DONE
  - Delivered on 2026-08-10, in one sitting, plus a same-day follow-up for an external audit (see below). Suite **1340 -> 1361 tests, 3728 -> 3790 assertions, green.** This is the execution of the TODO 19 decision. It sat in Phase 3 rather than Phase 10 for a reason that differs from 33.2/33.3/33.4: this package's constraint really **is** bounded (`illuminate/support ^8.67||^9.0` in the installed 1.6.0), so Composer really would have failed on it - at **Phase 5**, not Phase 10. But that half was a lock bump, not a decision, and the replacement code is framework-neutral, so writing it here removed the package from the Phase 5, 8, 9 and 10 resolutions in one move. Read TODO 19 first; it carries the measurements and the five defects.

  - **CORRECTION to this entry's own numbers, all three measured before starting.** They had gone stale between TODO 19 and the execution:
    1. **The suite is 32 tests, not 30.** `PendingEmailFlowTest` 12, `PendingEmailVerificationTest` 13, `PendingEmailKnownGapsTest` **7** - the v1-patch B12/B13 work added two and reversed four.
    2. **Therefore "the five in `PendingEmailKnownGapsTest`" is 7**, of which **3** had to break here (defects 3, 4, 5), not two.
    3. **"Two language files" is three, and there is no fourth to write.** An application-level `email.php` / `user.php` exists in **`hu`, `en` and `de` only** - 21, 20 and 16 files respectively. The other 19 locale directories are installer stubs carrying `installer_messages.php` and at most the framework's own `auth/validation/passwords`; they hold no application key at all. Adding a one-block `email.php` to each would have gone against the whole layout - the translation editor built in TODO 33.3 is what fills them.

  - **What shipped, as delivered:**
    - **New code, 467 lines under `app/`** (the estimate said 250-300; the difference is documentation comments, not logic): `App\Support\Email\MustVerifyNewEmail` (the trait, same public API - four call sites depend on the names), `App\Models\PendingUserEmail` rewritten from a vendor subclass into a standalone model, `App\Http\Controllers\User\VerifyNewEmailController`, and `App\Mail\VerifyNewEmail` / `VerifyFirstEmail` in a new `app/Mail/` directory.
    - **`pendingEmail.verify` moved into `routes/web.php:97`**, name and URI byte-identical so links in flight keep working. `throttle:6,1` moved from the vendor controller's constructor onto the route definition - the constructor form disappears with the Laravel 11 skeleton. `tests/Fixtures/route-contracts.json` needed **no** edit: the snapshot aggregates by name and sorts the middleware, so `["signed","throttle:6,1","web"]` is the same string list from either source.
    - **The two published Blade views moved** from `resources/views/vendor/verify-new-email/` to `resources/views/emails/`, next to the two views already there. They had to move: the release hook deletes the vendor directory from deployed hosts.
    - **`config/verify-new-email.php` kept and rewritten**, `route` key dropped, everything else re-pointed and re-commented.
    - **Defect 3** fixed in `UserObserver::deleted()`, with a control test proving it does not touch another user's row.
    - **Defect 4** fixed in `activate()`, which now reports three outcomes through class constants instead of returning void.
    - **Defect 5** fixed with `email.verifyFirstEmail.line_1` / `line_2` in `hu`, `en` and `de`.
    - `composer.json` / `composer.lock` / `vendor/protonemedia/` gone; `release/upgrade.php` extended with block 5d.

  - **The finding that changed the design, and it is the same defect the item was meant to fix.** The plan said the collision and the invalid link should "fail into a flash message", and the natural key for that is `error`. **Nothing in this project renders a flash named `error`.** The only three that reach a user are `message` (a toastr call in `layouts/app.blade.php:111`), `success` and `profile_message` (`user/profile.blade.php:39,44`, the second as a red alert) - plus `verified` on the login page. An `error` flash would therefore have been invisible, which is **exactly** the sixth TODO 19 finding: the package's own "The verification link is not valid anymore." never reached anyone either, because it travelled inside an exception that the redirect discarded. The messages ride `profile_message`, and `auth/login.blade.php` gained the one render site it was missing. Two tests assert the rendering itself, not just the session key.

  - **A behaviour change beyond the five defects, worth naming.** The vendor `activate()` returned void, so the controller could not tell "activated" from "refused", and an **anonymized** user's link produced the *success* page - a "confirmed" screen after nothing had happened. With three distinct outcomes that branch now lands where the expired link lands. `PendingEmailKnownGapsTest::test_the_model_guard_holds_even_if_the_pending_row_survives` is the test that moved for it.

  - **Test churn, as delivered: 32 -> 40.** Four cases were rewritten and their old text kept as the explanation - the reviewable diff, the TODO 14 discipline:
    | Test | Why it moved |
    | --- | --- |
    | `PendingEmailVerificationTest::test_the_verification_route_is_registered_only_because_the_config_route_key_is_null` | The `route` config key is gone. **The plan's "25 must stay green untouched" missed this one** - a config key that only ever meant "should the package load its own routes?" cannot survive the package |
    | `KnownGaps::test_defect_deleting_a_user_orphans_the_pending_row` | Reversed: the delete now clears the row |
    | `KnownGaps::test_defect_activation_fatals_when_the_address_was_taken_in_the_meantime` | Reversed: redirect plus message, `users.email` untouched, and the pending row **stays** so the user can retry |
    | `KnownGaps::test_defect_the_first_verification_mail_view_is_not_localised` | Reversed, and it now also renders the mail body on the `hu` locale - the keys existing is not the same as the stub text being gone |
    Plus one mechanical edit with no assertion change: `PendingEmailFlowTest`'s two Mailable imports.

  - **Control experiment, as run** (TODO 14 / 15 / 19.1 discipline): `$user->newEmail(...)` commented out at `UpdateUserProfileInformation.php:51`, suite re-run - **exactly two tests failed**, both profile-path cases, which is what TODO 19.1 rebuilt them to do after its own control run found one of them blind. Line restored, `git diff` clean, suite green.

  - **One test-writing note worth keeping:** `Mail::fake()` and `Mailable::render()` are mutually exclusive - `MailFake` has no `render()`. The test that proves the first-verification body carries no untranslated stub text therefore creates its row through `createPendingUserEmailModel()`, the non-sending half of `newEmail()`, and skips the fake entirely.

  - **AN EXTERNAL AUDIT OF THIS CHANGE SET FOUND THREE MORE, AND TWO OF THEM WERE THE ORIGINAL DEFECT WEARING A DIFFERENT HAT.** Fixed in a follow-up commit on the same day; suite **1348 -> 1361 tests, 3753 -> 3790 assertions**. All three are worth reading, because the pattern behind them is not specific to this feature.

    1. **The anonymization guard was defeated by an interleaving, and it failed silently.** `activate()` decided on the user instance the relation had already loaded, then saved it. An anonymization landing in between was undone - and because `Model::save()` writes only the DIRTY attributes, only `email` was written: the row kept `isAnonymized = 1` while carrying the real address back. Anonymized to every reader, and not anonymized in fact - precisely the GDPR defect this item recorded as closed. **A guard that reads before the window and writes after it is not a guard.** Fixed with a transaction that re-reads the user under `lockForUpdate()`; the same rule now covers the create side, so every mutation of a user's pending rows happens under a lock on that user's `users` row.

    2. **The observer was the only cleanup path, and two live routes delete users without firing model events.** `users:purge-unverified` (hourly) and `FinishRegistration::cancel()` both used `User::where(...)->delete()`, which is a builder delete: no `deleted` event, so `UserObserver::deleted()` never ran. The purge is the sharper one - it selects *unverified* users, which is exactly the population that has a pending row, because an unverified account is the one that receives the first confirmation mail. Both now delete one instance at a time. **The general lesson: an observer is a fix only for the paths that go through the model.** Grep for builder deletes before calling an observer sufficient.

    3. **Nothing enforced "one pending row per user", and the collision catch had a hole.** The clear-then-create was two statements with no transaction and no unique index behind it, so two concurrent profile updates could leave two rows and two live signed links - and a resend would stop being a token rotation. And `addressIsTaken()` could not close its own window: the competing row belongs to a user that does not exist yet, so there is nothing to lock, and the documented `SQLSTATE[23000]` 500 was still reachable. Fixed by the transaction above, by `2026_08_10_170000_add_unique_user_index_to_pending_user_emails` (which deduplicates deterministically - highest `id` per user survives - before adding the index), and by catching the unique violation on the write and reporting it as `REJECTED_TAKEN`.

  - **Test churn from the audit: 40 -> 53**, the new ones in `tests/Feature/NewEmail/PendingEmailIntegrityTest.php`. **Five control experiments were run, one per fix**, each confirming the matching test fails without it: the stale re-read (2 tests fail), the two builder deletes (2), the unique-violation catch (1, as an error), the migration held out of `database/migrations/` (2 - rolling it back on the dev database proves nothing, because `RefreshDatabase` builds the test schema from the files), and the create transaction (1).
  - **One test was written that did not discriminate, and it is recorded because the mistake is easy to repeat.** "The pending row is gone after a refusal" stays green when the activation *succeeds*, since a successful activation also clears the table. It only became a test of the guard once paired with an assertion that the address did **not** move. A test that passes in both worlds measures nothing.
  - **And one limit stated rather than papered over:** a single-threaded PHPUnit run cannot produce the interleaving the row lock exists for. What the suite does cover from one process is the transaction (a failed create rolls the clear back, so the earlier request survives), the unique index, and the stale-snapshot path - which is reproducible exactly, by writing the competing state through the query builder.

  - ~~**Defects 1 and 2 are NOT fixed here - they belong to TODO 33.2**, where `User::anonymize()` is rewritten.~~ **Both shipped earlier, on `v1-patch`, and TODO 33.2 carried them through its rewrite unchanged.** This change set re-pointed `clearPendingEmail()` at the in-house trait and kept the model guard, with its full explanatory comment.
  - **The release note, as executed**, the same shape as TODO 33.4: `install()` never deletes, so `release/upgrade.php` block 5d removes `vendor/protonemedia/` and `resources/views/vendor/verify-new-email/` from deployed hosts. `config/verify-new-email.php`, the `pending_user_emails` table and its migration all **stay** - no data migration, same shape.
  - Expected changes: as delivered - five new files under `app/`, two new Blade views, one route registration, `config/verify-new-email.php`, `composer.json` / `composer.lock`, six language files, `auth/login.blade.php`, `release/upgrade.php`, three test files, and `.docs/models.md` / `notifications.md` / `routes.md`. The audit follow-up added one migration, one test file, and touched `PurgeUnverifiedUsers`, `FinishRegistration` and `.docs/commands.md`.

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
    - Extend `release/upgrade.php` with `vendor/eusonlito`, `vendor/imagecow` and the scattered `*-cache_*` artifacts under `public/dist/` and `public/plugins/` (the TODO 33.5 precedent). **That precedent ends here: from TODO 34 onwards the hook does not grow** - see the standing rule in *Suggested Execution Strategy*.
    - **Repair `public/storage` on each host.** The stray real directory must be removed so `php artisan storage:link` can finally create the symlink. This is per-install state, not repository state, so the release hook has to handle it: remove the directory only when it is not a link **and** contains nothing but the `cache/` subtree Packer created, then create the link. Refusing to act on a directory with other contents is the safe default.
  - **Acceptance:** the nine `AssetPipelineKnownGapsTest` assertions fail - that is the point - and `AssetPipelineTest` is rewritten to the new emission (versioned URLs instead of packed filenames, individual tags instead of the two concatenated bundles), keeping its control-experiment property. The toastr icons render in a non-`local` environment, which finding 1 says they do not today.
  - Expected changes: `app/Helpers/helpers.php`, three blade files, `config/app.php`, `config/packer.php` deleted, `composer.json` / `composer.lock`, `.gitignore`, `release/upgrade.php`, both test files rewritten, and `.docs/assets.md`.

- [x] **TODO 33.9: Convert the Hungarian code comments to English**
  - **The rule is now written down** (`AGENTS.md`, "Maintenance Rules For Contributors"): code comments are English, the same as the documentation. Every change set from TODO 31 onwards follows it. This item was about the existing tree, which did not.
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

    **This was a lower bound, and knowingly so.** The detector keyed on accented characters, so a Hungarian sentence that happened to contain none was invisible to it. The delivery read every flagged file rather than trusting the count, which is also why the file counts actually touched per tree (below) differ from this table: some flagged files turned out to carry no comment at all (only Hungarian *data* - fixture strings, exception messages), and file-count differences in `config/` and `resources/views/` came from the detector matching UI text and translation strings that were correctly left alone.
  - **Shipped on 2026-08-11, in three commits, sliced by tree exactly as this entry recommended** - smallest and lowest-risk first:
    1. `database/` + `routes/` + `config/` + `resources/views/` together (33 files).
    2. `app/` (75 files).
    3. `tests/` (97 files) - last, because roughly 70% of the original volume sat here and these are not incidental comments: Phases 1 and 2 deliberately wrote the *measurement* into the test files - what was found, what the control experiment was, what would break if the assertion were removed. Translating them was a real editorial job, not a mechanical pass: every fact, TODO/patch cross-reference, defect description and control experiment was preserved verbatim in meaning, never summarized or shortened.
  - **`composer test` stayed green across all three commits: 1361 tests, 3790 assertions, unchanged.** Zero behaviour was touched, as the entry required - no assertion, fixture value, route table entry, or schema changed in any of the three commits.
  - **What stayed Hungarian on purpose, in every slice.** The distinction this entry drew between comment prose and comment-adjacent *data* held throughout: exception message strings actually shown to users (`WeatherException`), the environment guard messages a developer sees when `composer test` catches a misconfigured `.env` (`CreatesApplication`), test fixture values and assertion-failure messages throughout `tests/`, commented-out `Log::debug()` calls in `GroupDateHelper` whose string argument is dead-code data rather than prose, and quoted Hungarian UI labels/translation keys named inside an otherwise-translated comment (e.g. "Mégsem", "Hozzáadás", "Módosítások mentése").
  - `DevDependencyIsolationTest`, which greps *source text*, stayed green through all three commits - no future guard yet depends on comment text, so nothing needed to move with the translation.

---

## Phase 4 - Laravel 8 -> 9 (PHP 8.1)

**COMPLETE as of 2026-09-04.** All five items (34, 35, 35.1, 36, 37, 38) are
delivered and the suite stands at **1425 tests, 3952 assertions, green** on
Laravel 9.52.21 / PHP 8.1.30. The interpreter has deliberately **not** moved -
that is Phase 5's first act, per the *Runtime and Version Matrix*.

Three findings from this phase are worth carrying forward, because none of them
was predicted by the entry that found it:

1. **A green suite across a hop is not evidence about uncovered paths.** TODO 36
   and TODO 37 were both written as "mostly verification" and both found live
   defects, in each case in the one place nothing looked - an SMTP transport
   nobody built, a metadata call nobody made in a test.
2. **A blanket safety setting is only as wide as its implementation.** TODO 30's
   `'throw' => false` on every disk is why the Flysystem hop was green, and it
   does not cover `size()` at all. Check what a setting actually wraps before
   reading a green suite as proof it held.
3. **Not every consequence of a hop is expressible in a release archive.**
   TODO 38's directory move is invisible to an updater that only adds and
   overwrites, and the framework prefers the stale directory. That class of
   problem lives in `upgrade-guide.md`, and Phase 5 onwards should expect more
   of it rather than fewer.

**From this phase on, deployed-host cleanup is written down, not automated.** `release/upgrade.php` is frozen (see *Suggested Execution Strategy*); every hop's orphaned `vendor/` paths, moved files and `.env` changes accumulate in **`upgrade-guide.md`**, which the 2.0.0 release turns into an actual procedure.

- [x] **TODO 34: Upgrade the core framework to the Laravel 9 dependency set** - DONE
  - **Delivered on 2026-08-11, in three commits: the dependency set (`composer.json`/`composer.lock`/`vendor/`), the test layer, then documentation.** `laravel/framework` `^8.12` -> `^9.0`, resolving to **v9.52.21**; `php81 artisan about` reports it on PHP 8.1.30. Suite: **`OK (1361 tests, 3790 assertions)` - identical to the Laravel 8 baseline on both counts.**
  - **Only four `composer.json` lines actually needed editing, and this entry's own fourth bullet was already satisfied before it ran.** TODO 24's re-resolution had moved `astrotomic/laravel-translatable` (11.12.1), every `spatie/*`, `laravel/fortify` (1.11.2), `livewire`, `laravolt/avatar` and `barryvdh/laravel-debugbar` onto versions that already admit `^9.0`, so those were lock movement rather than constraint edits. That was the stated point of doing it there, and it held. The four edits: `php` `^8.0` -> `^8.0.2`, the framework, `facade/ignition` -> `spatie/laravel-ignition ^1.0`, and `nunomaduro/collision` `^5.0` -> `^6.0` (v5 requires `symfony/console ^5.0`, Laravel 9 requires `^6.0`). PHPUnit stayed on `^9.3.3`.
  - **The lock moved 8 out, 13 in, 27 changed, and two entries look wrong until measured.** `brick/math` was **downgraded** 0.13.1 -> 0.11.0, because Laravel 9 caps it at `^0.9.3|^0.10.2|^0.11` where Laravel 8 left it unbounded via `ramsey/uuid`'s `>=0.8.16 <=0.18`. And `fruitcake/php-cors` **arrived** while `fruitcake/laravel-cors` stayed at 2.2.0 - the new one is a direct requirement of `laravel/framework` v9, for the framework-native `HandleCors`. Both CORS stacks now sit in the tree at once, which is TODO 35's subject.
  - **The advisory question TODO 24 left open is answered under that entry**: three ignores stay, a fourth (`PKSA-mdq4-51ck-6kdq`) is a duplicate Packagist record of one already ignored, and a fifth the resolver printed needed no entry at all.
  - **Exactly five of 1361 tests went red, and neither cause was application behaviour.**
    1. **Three errors: `Mailer::getSwiftMailer()` is gone.** `GroupCreationTest::sentMessages()` and `AnonymizeCommandTest`'s recipient read hit a `BadMethodCallException`. The first carried a PHPDoc block predicting exactly this and saying the test was valuable *for that reason* - it was. Rewritten onto `Mail::getSymfonyTransport()`, `SentMessage::getOriginalMessage()`, `Address::getAddress()` and `getHtmlBody()`. Control experiment: swapping the expected `replyTo` for a wrong address fails with "Failed asserting that an array contains ...", so the parsed address list really is what is being read.
    2. **Two failures: three new named routes.** `spatie/laravel-ignition` registers `ignition.healthCheck` / `executeSolution` / `updateConfig` from `boot()` **unconditionally**, while `facade/ignition` gated its five behind `if ($this->app->runningInConsole()) { return $this; }` - and PHPUnit runs in console, so the snapshot had never seen them. `test_every_named_route_is_accounted_for` (TODO 14) caught it, which is the job it was written for. `ignition.` joins `debugbar.` in a new `DEV_ONLY_ROUTE_PREFIXES` constant, on the reasoning the Livewire test already gives for Debugbar: both are `require-dev`, absent on a `--no-dev` host, so snapshotting their contract would freeze dev-only state into a fixture production can never satisfy. The constant exists so the two skip lists cannot drift apart and silently widen the hole. Control experiment: removing `'ignition.'` reproduces both failures exactly.
  - **The strongest single result: neither route fixture was touched, and both snapshot tests pass.** Every application-owned named route - methods and full middleware stack - is byte-identical across a framework major. The only route-table change the hop produced is four dev-only vendor routes: three ignition, plus one from the Debugbar 3.7.0 -> 3.16.0 bump.
  - **TODO 36 and TODO 37 produced nothing to fix here, and that is a result rather than a gap.** Both changes land physically *in this hop* - Laravel 9 requires `symfony/mailer ^6.0` and `league/flysystem ^3.8` - so the suite already ran on Symfony Mailer and Flysystem 3, green, with the assertion count unmoved. TODO 28 (every `env()` out of runtime code) and TODO 30 (`'throw' => false` on every disk, `public_path()` root) are why. Both items reduce to verification; see the notes added under them.
  - **Verified beyond the suite:** `artisan optimize` still completes (the TODO 26 property); `composer audit` reports the four ignored advisories - so `on-audit: false` works - and **one** abandoned package instead of three, since `swiftmailer/swiftmailer` and `maximebf/debugbar` left with the hop (`fruitcake/laravel-cors` remains, for TODO 35); and a real `composer install --no-dev` tree boots under `APP_ENV=production` with `route:list` and `about` both exiting 0, which is the TODO 25 check carried onto Laravel 9 with the new ignition package. The dev tree was restored afterwards and `git status` came back clean.
  - **The custom-pivot write path this entry asked to re-verify survived untouched.** `GroupRoleAssignmentTest::test_the_finish_guest_registration_flag_never_reaches_the_pivot_table` is green, so `syncWithoutDetaching()` still routes through `updateExistingPivotUsingCustomClass()` -> `fill()` and still drops the unknown key silently.
  - **`artisan about` is now available and captured** in `upgrade-notes/laravel9-about.txt`, closing the thread TODO 03 left open.
  - **What the delivery found that the plan had not.** `config/mail.php:46-52` carries a `stream.ssl` block (`verify_peer` / `allow_self_signed`, `APP_ENV=local` only) that **Laravel 9 silently stops reading**: `MailManager::createSmtpTransport()` has no `stream` key, and the Symfony equivalent is `verify_peer` at the mailer level, passed through as a DSN option. Nothing in the suite measures it - the tests use the `array` transport - and it only affects a local mail catcher with a self-signed certificate, so it was left alone rather than changed blind. **Recorded under TODO 36**, which owns the mailer.
  - **Deployed-host cleanup did NOT go into `release/upgrade.php`**, and this is where that changed for the rest of the roadmap - see the standing rule in *Suggested Execution Strategy*. The six vendor paths this hop orphaned (`vendor/facade`, `vendor/swiftmailer`, `vendor/opis`, `vendor/maximebf`, `vendor/symfony/polyfill-iconv`, `vendor/symfony/polyfill-php73`) are recorded in the new **`upgrade-guide.md`** instead.
  - Needed:
    - `laravel/framework` to `^9.0`.
    - Replace `facade/ignition` with `spatie/laravel-ignition` (the current one also fatals on PHP 8.3).
    - `nunomaduro/collision` to `^6.0`, keep PHPUnit at `^9.5`.
    - Bump `astrotomic/laravel-translatable`, `spatie/*`, `laravel/fortify` to their Laravel 9 lines.
    - **Re-verify the custom-pivot write path**, found by TODO 07.2. `Groups\ListUsers::updateUser()` hands the full validated array - including `finish_guest_registration`, which is **not a `group_user` column** - to `syncWithoutDetaching()` (`:257-259`). It only survives because `GroupUser` is a custom `Pivot` with a `$fillable` list, so Laravel routes through `updateExistingPivotUsingCustomClass()` and `fill()` drops the unknown key silently. If that path ever falls back to a raw update or insert, the save fatals with an unknown-column error. Pinned by `GroupRoleAssignmentTest::test_the_finish_guest_registration_flag_never_reaches_the_pivot_table`.
  - Expected changes: `composer.json`/`composer.lock`; `php81 artisan` boots on the Laravel 9 stack.

- [x] **TODO 35: Remove the deprecated proxy and CORS packages** - DONE
  - **Delivered on 2026-08-11, in four commits: the pin tests, the middleware swap, the package removal, then documentation.** `composer test`: **OK (1371 tests, 3818 assertions)** - the 1361/3790 baseline plus this item's ten cases and nothing else. `composer audit` now reports **zero abandoned packages**, down from one; the four ignored `laravel/framework` advisories are unchanged.
  - **Nothing in the suite could see either class, and that shaped the whole delivery.** Both live in the kernel's GLOBAL `$middleware` stack, and `RouteContractSnapshotTest` / `RouteMiddlewareRegressionTest` read `$route->gatherMiddleware()`, which returns route and group middleware only. `HandleCors` could have been deleted from `app/Http/Kernel.php` outright and all 1361 tests would have stayed green; `grep` confirms it from the other side, with zero occurrences of `cors` or `Access-Control` under `tests/`. So the tests were written and run **against the old packages first** (commit 1), and the swap (commit 2) had to leave them green with **identical numbers** - `CorsHeadersTest` 6 tests / 17 assertions, `TrustProxiesTest` 4 / 11, before and after. That is the difference between "nothing broke" and "behaviour was preserved".
  - **This entry's own `$headers` warning pointed at the wrong thing, and the correction is stronger than the warning.** The AWS ELB handling is *identical* between `Fideloper\Proxy\TrustProxies` and `Illuminate\Http\Middleware\TrustProxies`. What is true is that both resolve `$headers` against **single** `Request::HEADER_*` constants - fideloper with a `switch`, the framework with a `match` - and fall through to a `default` arm for anything else. The value here was a combined bitmask, which equals no single constant, so **the override never selected anything under either parent**; it only ever reached the same default it was trying to restate. It was therefore deleted rather than ported, which is bit-identical at runtime. The two defaults differ by exactly one flag: the framework's also trusts `X-Forwarded-Prefix`.
  - **And that flag cannot surface on this installation either.** `$proxies` is `null` and there is no `config/trustedproxy.php` - the value that used to answer `config('trustedproxy.proxies')` was the removed package's own published default, also `null`, and a missing key reads the same. With nothing trusted, `isFromTrustedProxy()` is always false and the trusted-header set is never consulted. **Net behaviour change from the TrustProxies swap: zero**, and `TrustProxiesTest`'s first case pins exactly that, with the same request under a trusted `$proxies` as its control. The framework parent additionally auto-trusts on Laravel Cloud / Forge / Vapor hosts and strips `X-Forwarded-Host` on the latter two; none of those conditions holds here. It still reads `config('trustedproxy.proxies')`, so a published file would be honoured after the removal too.
  - **`config/cors.php` needed no edit, measured rather than assumed.** `fruitcake/php-cors`'s `CorsService::setOptions()` explicitly accepts the snake_case keys (`allowed_origins`, `max_age`, ...) that the old package's service provider used to convert to camelCase by hand. Three real differences exist and none is observable: the package validated the config shape (a lost `RuntimeException`, not a lost behaviour), it registered a `RequestHandled` fallback listener (moot - `Illuminate\Routing\Pipeline::handleException()` converts the exception to a response *inside* the pipeline, so the middleware's after-code still runs), and it skipped its headers when `Access-Control-Allow-Origin` was already set (nothing here sets it by hand).
  - **One test was written wrong, and the failure is why it is worth having.** "No `Origin`, no header" failed on the **old** package, so the rule got measured instead of assumed: with `allowed_origins => ['*']` and `supports_credentials => false`, both implementations normalise the wildcard to allow-everything and emit a **static** `Access-Control-Allow-Origin: *` without consulting the request's `Origin` at all (asm89 turns `array('*')` into `true` in `normalizeOptions()`; php-cors sets `allowAllOrigins`). The request's origin only matters on the else branch. So on this configuration the header is a constant and the **path match is the only gate** - which is what the negative case (`GET /` gets nothing) measures.
  - **The lock moved by exactly three removals, zero installs, zero version changes.** `asm89/stack-cors` left with `fruitcake/laravel-cors`, its only dependent. **`fruitcake/php-cors` stays** and is now load-bearing: a direct requirement of `laravel/framework` v9, supplying the `Fruitcake\Cors\CorsService` the framework's `HandleCors` type-hints. So `vendor/fruitcake` does **not** disappear - only `laravel-cors` inside it, the first *partial* namespace removal in `upgrade-guide.md`'s orphan list. The two CORS stacks the TODO 34 entry recorded are now one, and it is the one being used.
  - **Control experiments.** Commenting `HandleCors` out of the kernel turns 4 of the 6 CORS cases red, and the two that stay green are the two that should (the negative path case and the configuration case). `TrustProxiesTest` carries its control inside the file: the same request and headers with `$proxies` set to `'*'` do get through, so the first case cannot be passing merely because the middleware never ran.
  - **Verified beyond the suite:** `artisan optimize` completes; a real `composer install --no-dev` tree boots under `APP_ENV=production` with `route:list` exiting 0 **and both middleware classes resolving from the container** - the sharper check here, since `HandleCors` type-hints a class from a `require` package and would fatal if that had gone with the removal. The dev tree was restored afterwards and `git status` came back clean.
  - **What the delivery found that the plan had not: the committed `vendor/` tree is incomplete.** `.gitignore` excludes `/vendor` and the tree was force-added, so **1044 of the 9485 files on disk have never been tracked** - the whole `vendor/asm89` among them. `release/build-update.php` builds every archive from a **git diff**, so a package git cannot see never shipped in an update and can never be removed by one. Recorded in `upgrade-guide.md` section 2, where it is also an argument for the manual full-`vendor/` branch.
  - **This is also the first release under the TODO 34 freeze that actually deletes tracked files** - thirteen, from the two packages. `release/build-update.php` will report them as deletions its hook does not cover, which is now **expected output**; its header said the opposite and was corrected to point at `upgrade-guide.md`. The hook's `main()` was not touched.
  - **The state this item started from, measured in TODO 34 and left here as written.** Both packages install and boot fine on Laravel 9, so nothing forced this item at that hop - `fideloper/proxy` 4.4.2 declares `illuminate/contracts ...|^9.0` and `fruitcake/laravel-cors` 2.2.0 declares `illuminate/support ^6|^7|^8|^9`. What did change is that **the framework now requires `fruitcake/php-cors` directly**, for its own `Illuminate\Http\Middleware\HandleCors`. So the tree currently carries two CORS implementations side by side: the framework's (unused, since `app/Http/Kernel.php:23` still names Fruitcake's) and the package's, on `asm89/stack-cors`. `composer audit` reports `fruitcake/laravel-cors` as abandoned and it is now the **only** abandoned package left - the other two went with the Laravel 9 hop.
  - Needed (all done):
    - Remove `fideloper/proxy` and `fruitcake/laravel-cors`.
    - `app/Http/Middleware/TrustProxies.php:5` must extend `Illuminate\Http\Middleware\TrustProxies`; ~~re-check the `$headers` constants (currently `HEADER_X_FORWARDED_FOR|HOST|PORT|PROTO|AWS_ELB`, whose AWS ELB handling differs)~~ - **the AWS ELB handling is identical; see the delivery record for what the difference actually is and why `$headers` was deleted.**
    - `app/Http/Kernel.php:23` -> `Illuminate\Http\Middleware\HandleCors`.
  - Expected changes: middleware refactor, `app/Http/Kernel.php`, `composer.json`. **Actual:** those three, plus `composer.lock`/`vendor/`, two new test files, `.docs/middleware.md`, `upgrade-guide.md` and `release/build-update.php`'s header.

- [x] **TODO 35.1: The committed `vendor/` tree was incomplete, and no release could be built from this branch** - DONE
  - **Delivered on 2026-08-11, in three commits: the mechanism, the catch-up, then documentation.** `composer test`: **OK (1371 tests, 3818 assertions)**, unchanged - the commits touch no line the suite executes, so any movement would itself have been the finding. `artisan optimize` exits 0. `git ls-files vendor` is now **9445**. `git ls-files --others --exclude-standard vendor` is **empty**; the ignore-independent `git ls-files --others vendor` returns exactly twenty files - the 19 IDE-cache ones and the stray `.phpunit.result.cache` noted below - which is the distinction the guard is built on, so the two forms disagreeing here is the point rather than a leftover.
  - **The entry below offered two fixes as alternatives. Measurement said they are not alternatives, and both landed.** Dropping the `.gitignore` line alone leaves packages able to hide release content with their own nested `.gitignore` files; a builder guard alone leaves `git status` lying to every human who reads it. Neither covers the other's case.
  - **Removing the `/vendor` line turns three already-documented mechanisms live, not one.** `git add -A` picks up new packages by itself. `release/build-update.php`'s dirty-tree check (`:194`) starts seeing untracked vendor files as `??` records instead of nothing. And `release/README.md`'s own advice - "Check `git status --porcelain vendor/` for directories Composer did not name" - stops returning an empty list every single time it is followed. It also removes the need for `git add -f`, the flag that put `vendor/_laravel_ide/` into a release once already, on `v1-patch H`.
  - **The nested-`.gitignore` hole is why a guard was still needed, and it is not hypothetical.** Eight packages ship a `.gitignore` under `vendor/`, and **a nested one beats the root**. `vendor/spatie/ignition/resources/compiled/.gitignore` excludes `*` from a directory holding 900 KB of release assets; three `!` lines happen to cover its current contents exactly, so today's residue is zero, but a version bump adding a fourth file there would drop it from every release **with no signal at all** - `git status` stays silent, and `git add` cannot fix it. `.git/info/exclude` and `core.excludesFile` do the same and are per-machine, so they survive no review whatsoever.
  - **So `invisibleVendorFiles()` is deliberately ignore-independent.** It reads `git ls-files --others vendor` **without** `--exclude-standard` - which lists ignored files too - then asks `git check-ignore -v -z --stdin` which rule matched each one. A rule sourced from the repository's own `.gitignore` is allowed, because that is the file a reviewer reads and the right place for a deliberate exception; **anything else fails the build, quoting the rule**. The two cases are reported separately (`untracked` vs `hidden by <rule>`) because only the first is fixed by committing. It runs **before** the dirty-tree check: now that vendor is not ignored, an incomplete tree is also a dirty tree, and the generic message would otherwise win the race and say nothing useful.
  - **Correction to the count below: 1037 files were untracked, but only 1017 were committable.** The other twenty are correctly ignored and now say so explicitly - 19 in `vendor/_laravel_ide/` (the IDE plugin's cache, which is also in the builder's `EXCLUDE_PREFIXES` now, so a stray force-add cannot ship it either), and one `vendor/spatie/laravel-failed-job-monitor/.phpunit.result.cache`, a stray artifact that package ships inside its own dist archive with its author's machine paths in it, caught by the root `.gitignore`'s existing `.phpunit.result.cache` rule.
  - **The catch-up was proven purely additive before it was made**, which is why no `composer install` round trip was needed: all 130 locked packages present on disk, no package directory on disk the lock does not name, no tracked package the lock does not name, and a clean `git status` - so tracked was a strict subset of disk and disk equalled the lock. The commit is **1017 additions, zero modifications, zero deletions**, produced by a plain `git add vendor` with no `-f`.
  - **`release/build-update.php --dry-run` against `v1`, before and after, is the end-to-end proof:**

    | | before | after |
    |---|---|---|
    | files shipped | 5048 (31.9 MB) | **6063 (35.8 MB)** |
    | files deleted | 1471 | **1466** |
    | not yet covered by the hook | 1228 | **1223** |
    | `vendor/fruitcake/php-cors` on the deletion list | **5 files** | **gone** |

    The last row is the sharpest: that package was tracked at `v1`, TODO 24 removed it, TODO 34 brought it back untracked, so git still called it deleted. The tooling would have handed the operator an archive missing `symfony/mailer` **and** a list telling them to delete the package whose `CorsService` the framework's `HandleCors` type-hints. `fideloper/proxy` (5) and `fruitcake/laravel-cors` (8) stay on the list, correctly - those are TODO 35's genuine removals.
  - **Both branches of the guard were fired on purpose, not just left green.** A throwaway `vendor/zz-guard-control.txt` fails the build as `untracked`. The same file inside `vendor/spatie/ignition/resources/compiled/` - where `git status --porcelain` reports **nothing whatsoever** - fails it as `hidden by vendor/spatie/ignition/resources/compiled/.gitignore:1:*`. Without the second experiment the guard's whole reason for existing would be untested.
  - **Why the dev-only packages are committed too, which is a decision and not drift.** 370 of the 1017 belong to `require-dev` packages, joining 1736 already tracked. If the committed tree were `--no-dev`, a developer's own `composer install` would leave 2100+ untracked files standing permanently and the new check would block every build. The tree that is committed has to be the tree Composer produces here, or the invariant is not checkable at all.
  - **The `dev` (1.x) branch was left alone, by decision.** It tracks 9459 `vendor/` files and was kept complete by hand; the gap was created entirely by `v2-dev`'s own commits, starting at TODO 24 (`ebaf6185`). The fix reaches the 1.x line with the 2.0.0 merge. Until then a 1.x patch release built from `dev` still needs the old manual discipline, which `release/README.md` states.
  - **What this does not fix: the deployed 1.x hosts.** They carry packages that never shipped in any update and that no deletion list can name, because the updater only ever knew what git knew. `upgrade-guide.md` section 2 keeps that as an argument for section 6's Branch B - it is a property of those hosts, not of this repository, so it does not expire.
  - **Everything below is the diagnosis as it was written before the fix**, left as it stood apart from the `**Actual:**` clause closing the last bullet. Only the file count has moved, and the correction above says how.
  - **Found while delivering TODO 35, and it is more serious than the note that item records.** `.gitignore:17` excludes `/vendor`, but this repository commits `vendor/` on purpose - the release archive carries it, there is no `composer install` on target hosts. The tree got there by `git add -f`, and **`git add -A` and `git add -u` both skip ignored paths**. So every hop that *adds* a package silently leaves it out, while modifications and deletions of already-tracked files go in normally. Nothing announces it: `git status` stays clean, `composer test` stays green, and `artisan about` is happy, because the working tree on this machine is complete.
  - **Measured on 2026-08-11, after TODO 35: 9465 files on disk, 8428 tracked, so 1037 are not.** 469 of them sit in **14 packages with not one tracked file**, the other 568 are scattered inside packages that are otherwise tracked. The 14: `fruitcake/php-cors`, `guzzlehttp/uri-template`, `league/flysystem-local`, `nunomaduro/termwind`, `php-debugbar/php-debugbar`, `spatie/backtrace`, `spatie/flare-client-php`, `spatie/ignition`, `spatie/laravel-ignition`, `symfony/mailer`, `symfony/polyfill-php83`, `symfony/polyfill-uuid`, `symfony/uid`, `symfony/yaml`.
  - **The list dates the drift exactly.** Thirteen of the fourteen are TODO 34's "13 arrived" set; the fourteenth, `symfony/yaml`, arrived with TODO 24. So this began at TODO 24 with a single package and TODO 34 turned it into fourteen. Every package added from here on joins them until this item is done.
  - **What `release/build-update.php` would ship today, measured against `v1..HEAD`:** the whole Laravel 8 -> 9 framework upgrade contributes **one** added file (`Illuminate/Support/Timebox.php`) next to 665 modified ones, while 145 new framework files sit untracked on disk. `vendor/symfony/mailer` contributes **nothing at all** - 0 of 63 files. An archive built from this branch installs a Laravel 9 framework without the mailer it requires, so the host fatals on the first request that boots the mail manager.
  - **The sharpest expression of it is a deletion, not an omission.** `vendor/fruitcake/php-cors` was tracked at `v1` (it is in `release/dist/RELEASE-1.2.0.files.txt`), TODO 24 removed it, and TODO 34 brought it back **untracked**. Git therefore still sees it as deleted, so the builder writes it into `RELEASE-<version>.deleted.txt` - **it would instruct the operator to delete the package whose `Fruitcake\Cors\CorsService` the framework's `HandleCors` type-hints.** The release tooling would hand out an archive that cannot boot *and* a list that finishes the job.
  - **`release/README.md` already states the rule this violates** - "Only files that would actually ship have to be committed" - and nothing enforces it. The builder's own dirty-tree check (`:549`) uses `git status --porcelain --untracked-files=all`, which does not list ignored files, so the gap is invisible from there too.
  - Needed, and the shape is a decision rather than a mechanical fix:
    - **Root cause:** drop `/vendor` from `.gitignore` (keeping `vendor/_laravel_ide/`, which is IDE-generated cache) so that `git add -A` picks up new packages by itself and `git status` shows the drift. This is the option that cannot rot.
    - **Or** keep the ignore and make the tooling refuse to build from an incomplete tree: `git ls-files --others vendor` must come back empty, checked in `build-update.php` before the diff is read.
    - Either way a one-off `git add -f vendor` is needed to catch up the 1037 files, and it is worth doing as its own commit so the diff stays readable.
    - Whatever is chosen, **doing nothing is not neutral**: TODO 39 (Laravel 10) will add packages exactly the same way.
  - Expected changes: `.gitignore` and/or `release/build-update.php`, plus one large `vendor/` catch-up commit. **Actual:** both of the first two, plus `release/README.md` and `upgrade-guide.md`.

- [x] **TODO 36: Validate the SwiftMailer -> Symfony Mailer switch** - DONE
  - **Delivered on 2026-08-11, in four commits: the pin tests, the mail config, the failed-job monitor, then documentation.** `composer test`: **OK (1399 tests, 3890 assertions)** - the 1371/3818 baseline plus this item's 28 cases in `tests/Feature/Mail/` (`MailTransportConfigTest` 8/26, `NotificationAddressingTest` 14/35, `FailedJobMonitorRouteTest` 6/11) and nothing else. `artisan optimize` exits 0 and the builder's `--dry-run` passes at 6064 shipped files.
  - **This item said "what is left here is genuinely verification". That was right, and the verification found two defects rather than none.** Both had been invisible for the same reason: nothing in the suite ever built an SMTP transport, and nothing ever rendered a `MailMessage` into a `Symfony\Component\Mime\Email`. The two places the switch could bite were the two places nothing looked.
  - **Correction to this entry's own `config/mail.php` note, and it changes the decision.** "Laravel 9 never reads the `stream` key" is true, but the load-bearing fact is the other half: **Laravel 8 DID read it** - `MailManager:222-223`, `if (isset($config['stream'])) $transport->setStreamOptions(...)`. So the block was not always dead configuration; **TODO 34 dropped a setting that worked**, silently. That turns "port it or delete it as dead" from a matter of taste into a restore, which is what was done.
  - **A second dead key the entry did not know about, with a different story: `auth_mode`.** Laravel 8 read it too (`MailManager:238-239`), but only under `isset()`, and the configured value is `null` in both the `smtp` and `phpmail` mailers - so `setAuthMode()` never actually ran, even then. Symfony has no equivalent at all (authenticators are negotiated; `EsmtpTransport` has no `setAuthMode()`), so there is nothing to port it to. Removed from both mailers. Two keys that look alike, two different findings.
  - **The port, and why it is exactly as narrow as what it replaces.** Laravel 9 hands the whole mailer config to Symfony as the `Dsn`'s options, and `EsmtpTransportFactory:36` reads a **mailer-level `verify_peer`**, turning a falsy value into `ssl.verify_peer = false` **and** `ssl.verify_peer_name = false` - the old block's only two effective settings. `allow_self_signed` has no equivalent and needs none: it applies only while verification is still on. `Dsn::getOption()` resolves with `??`, so a literal `null` is indistinguishable from an absent key, which is why the replacement reads `env('APP_ENV') === 'local' ? false : null` rather than `: true`.
  - **Measured on a booted application, because the suite runs under `APP_ENV=testing` and never takes the `local` branch:**

    | `APP_ENV` | `config('mail.mailers.smtp.verify_peer')` | transport stream options |
    |---|---|---|
    | `local` | `false` | `{"ssl":{"verify_peer":false,"verify_peer_name":false}}` |
    | `production` | `NULL` | `[]` |

    The mechanism assertions were written and run **against the old configuration first**, so the port confirmed a measurement rather than hoping for one. They also stay useful afterwards: they set `stream` themselves, so they are what would notice if a future framework version started reading it again.
  - **Correction to the address-strictness claim, which was more optimistic than the evidence.** This entry read the green TODO 34 suite as answering the question "empirically". It did not: `NotificationRegressionTest` walks all 25 notifications but stops at the `MailMessage` object, and **rendering is the step where the mailer's address handling applies**. `tests/Feature/Mail/NotificationAddressingTest.php` is the missing third kind of coverage - it sends the five address-carrying notifications through the `array` transport and reads `getTo()` / `getReplyTo()` / `getBcc()` off the finished message. The conclusion held; the reasoning behind it did not.
  - **`groups.replyTo` is a nullable `text` column and every producer passes it through untouched** (`EventObserver:56`, `CalculateDatesEvents:237`), so `null` is a production value rather than a contrived one. Only `EventDeletedNotification` guards it with `?? ''`; the other three call `trim()` on the null directly. All four reach the same address, so it is recorded rather than fixed.
  - **The `spatie/laravel-failed-job-monitor` re-verification this entry asked for found a real defect, and it fires before Symfony Mailer is reached at all.** `Notifiable::routeNotificationForMail()` is typed `: array` and returns `config('failed-job-monitor.mail.to')` untouched, so a **null** recipient is a `TypeError`. The config read `env('MAIL_FROM_ADDRESS', 'email@example.com')` - and an `env()` default only applies when the **key is absent**, while `.env.example:38` ships `MAIL_FROM_ADDRESS=null`, which `env()` resolves to a real null (`Env.php:88`). On such an install **the first failed queue job took down the thing whose entire job is to report failed queue jobs**, silently. Fixed with `env('MAIL_FROM_ADDRESS') ?: 'email@example.com'` - `?:` and not `??`, because `env()` returns `''` for an empty value and `??` would pass that on into an invalid address further down.
  - **That defect was committed as a red-then-green pair rather than described.** The pin commit asserts the broken value (it failed once, on the way to being written down that way); the fix commit flips the assertion and carries the call through to `routeNotificationForMail()`, so it now ends where the failure used to start. The vendor's `TypeError` stays pinned as a constraint - it is not this repository's to change, which is precisely why the configuration has to hold the invariant.
  - **`.env.example:38` still reads `MAIL_FROM_ADDRESS=null`** and was deliberately left alone: the fix belongs in the config, which ships to every host, rather than in a template only a manual installer copies. Setting a real address is still right, it is simply no longer load-bearing.
  - **Everything below is the entry as it stood before delivery**, left as it was apart from the `**Actual:**` clause closing the last bullet. Two of its statements are corrected above rather than merely completed.
  - **The switch itself already happened, in TODO 34** - Laravel 9 requires `symfony/mailer ^6.0`, so there is no separate step to perform. The suite ran on Symfony Mailer at that hop and came back **green with the assertion count unmoved**, which answers the address-strictness question below empirically: TODO 28 had already removed every path that could produce an empty `replyTo`/`bcc`. What is left here is genuinely verification.
  - **One real finding is still open, and it is not in the notification layer.** `config/mail.php:46-52` sets `stream.ssl.verify_peer` / `verify_peer_name` / `allow_self_signed` when `APP_ENV=local`. **Laravel 9 never reads the `stream` key**: `MailManager::createSmtpTransport()` builds a Symfony `EsmtpTransport` from a DSN and only understands `local_domain`, `source_ip`, `timeout` and the DSN options. The Symfony equivalent of that block is a `verify_peer` key at the **mailer** level, which `EsmtpTransportFactory` reads. Nothing in the suite measures it (the tests use the `array` transport) and it only affects a local mail catcher with a self-signed certificate, so TODO 34 deliberately did not change it blind. **Decide here: port it to `verify_peer`, or delete it as dead configuration.** Verify against the installed `symfony/mailer` version rather than from this note.
  - Needed:
    - Verified low risk: zero direct SwiftMailer usage, zero Mailables, all 25 notifications use the stable `MailMessage` API, `config/mail.php` already uses the modern `mailers` shape.
    - The real exposure is address strictness: Symfony Mailer throws on invalid or empty `replyTo`/`bcc`. TODO 28 was the prerequisite and is **done on `v1-patch`** - the six `env('MAIL_FROM_ADDRESS')` sites now read `config('mail.from.address')`, so a cached configuration no longer produces the empty address that Symfony Mailer would reject.
    - Re-verify `spatie/laravel-failed-job-monitor`, which is wired to queued notifications. **Done, and it did not pass: see the delivery record above.** The defect was in `config/failed-job-monitor.php`, not in the package, and not in the mail layer.
  - Expected changes: mostly verification; the notification test suite is the gate. **Actual:** three new test files, `config/mail.php`, `config/failed-job-monitor.php`, `.docs/notifications.md` and `upgrade-guide.md`.

- [x] **TODO 37: Validate Flysystem 1 -> 3 behavior on every disk** - DONE
  - **Delivered on 2026-09-04, in four commits: the pin tests, the `s3` removal, the guard fix, then documentation.** `composer test`: **OK (1425 tests, 3952 assertions)** at the end of the pair with TODO 38 - this item's own contribution is 20 tests (`tests/Feature/Storage/` 14, `GroupNewsFileSizeTest` 4, one case each added to `NewsFileDownloadScopeTest` and `SentinelGuardsTheInstallerTest`). `artisan optimize` exits 0.
  - **This entry said the item was verification of the uncovered paths rather than a migration. That was right, and the verification found a live defect** - the same shape as TODO 36: the two places nothing looked were the two places the switch bit.
  - **The finding, and it is not the one this entry predicted. `'throw' => false` does not cover `size()`.** `FilesystemAdapter` wraps `get()`, `put()`, `delete()` and `mimeType()` in `try/catch` with `throw_if($this->throwsExceptions(), $e)`; **`size()` (`:553-556`) and `lastModified()` (`:599-602`) call the driver straight through.** So TODO 30's blanket `'throw' => false` - which is why the hop was green - never protected the one call this application makes on every news listing. Every `exists()`-then-`size()` guard in the tree is load-bearing rather than defensive, and nothing said so.
  - **The second half of the defect is a change this entry did not list at all: `exists('')`.** Flysystem 1 short-circuited an empty path (`strlen($path) === 0 ? false`, measured on the Laravel 8 tree via `git show dev:vendor/league/flysystem/src/Filesystem.php:53-58`); Flysystem 3's `has()` is `fileExists() || directoryExists()` (`Filesystem.php:46-51`), and an empty path normalizes to the disk root, which is a directory. **So the empty path went from `false` to `true` across the hop.**
  - **Put together, that is a 500 where there used to be a zero, on a page a group member loads.** `GroupNewsFile::getSizeAttribute()` guarded with `exists()` and then called `size()`; the accessor is in `$appends`, so it runs on every serialization. `GroupNewsFileDownloadController:36` had the same pairing, and `download()` reads `size()` for its `Content-Length` (`FilesystemAdapter:283` via `response()`), so its intended 404 became a 500 too.
  - **The input is reachable, which is what makes this a defect rather than a curiosity.** `NewsEdit:109` writes `TemporaryUploadedFile::store()`'s return value straight into a non-nullable `string` column, and a failed store returns `false` - which reaches the column as `''`. No validation stands between the two.
  - **Fixed by moving exactly two guards to `fileExists()`**, in the two places whose next call is a metadata read. **Control experiment, as run:** restoring both to `exists()` fails exactly two tests - the reversed cases in `GroupNewsFileSizeTest` and `NewsFileDownloadScopeTest` - and moves nothing else in the suite.
  - **Three guards deliberately keep `exists()`, and the reasoning is the point rather than the outcome.** `NewsEdit:119`/`:141` are followed by `delete()`, which is a no-op on a missing path and returns `true` either way - redundant but harmless. `Messages:173` is followed by `put()` and is load-bearing for an unrelated reason (it stops every avatar being regenerated on every render, which `AvatarGenerationTest:141` pins). `EnsureInstallerToken:78`/`:88` wrap `get()` and `put()`, both covered by the throw key. The rule that came out of it: **`exists()` is fine before a write, wrong before a metadata read.**
  - **`Storage::fake()` never reads the disk configuration, which is why none of this was visible.** `Facades/Storage.php:105` merges the caller's own `$config` with a temporary root - it does not consult `filesystems.disks.*`. The three existing `Storage::fake('news_files')` tests therefore never exercised this project's `'throw' => false`, `visibility` or `permissions` keys at all. `FlysystemThreeSemanticsTest` uses `Storage::build()` with the real disk entry and an overridden root, so its assertions are about this application rather than about Flysystem.
  - **The `s3` decision, which this entry left open: removed.** No `league/flysystem-aws-s3-v3`, no `aws/aws-sdk-php`, and zero call sites in `app/`, `routes/` or `resources/` - the entry was framework default configuration that could never have resolved. It goes with the `config/livewire.php:85` example that referenced it and the four `AWS_*` keys in `.env.example`. `FilesystemDiskContractTest` keeps a `class_exists()` assertion in its place, so adding a cloud disk without its adapter now fails in the suite rather than at the first upload.
  - **"Exercise all 5 disks" resolved to four, and the `public` disk deserves its own sentence.** The `s3` entry is gone; of the remaining four, `local` is the default disk and therefore already exercised by the whole `tests/Feature/Setup/` tree (both sentinels live on it), `web` by `AvatarGenerationTest`, and `news_files` by the news attachment tests. **`public` has zero application call sites** - its reason to exist is being the `storage:link` target (`config/filesystems.php:115`), not being called - so "exercising" it means running the same semantic assertions against its configuration, which genuinely differs (`visibility => 'public'`, no `permissions` block, a `url` key). That is the honest answer, and `test_the_storage_link_map_covers_only_the_public_disk` records the other half: the `news_files` link on the next line stays commented out on purpose, because a symlink would put private attachments in the docroot and route around `GroupNewsFileDownloadController` entirely.
  - **The sentinel confirmation this entry asked for: it holds, and it now says where.** `Storage::exists('installed.txt')` is a bare facade call, so it resolves through `filesystems.default` - `local`, rooted at `storage/app`. That was never asserted; it is now, in `SentinelGuardsTheInstallerTest`. The call itself did not change across the hop: `exists()` on an absent name behaved identically on Flysystem 1.
  - **Line references in this entry were stale and are corrected above and throughout the document** - `routes/web.php:78` is `:104` and `app/Exceptions/Handler.php:57` is `:60` (seven places, including three test comments); `GroupNewsDelete.php:20,21` is `:17-25`, and the `if(...)` there is the **Eloquent** `Model::delete()`, not the Storage one. `.docs/components.md` carried two claims that had been wrong since TODO 30 (the `web` disk root and the view's URL prefix) and one pointing at a `Packer::js()` call TODO 33.8 removed; all three fixed.
  - **Everything below is the entry as it stood before delivery.**
  - **Flysystem 3 has been in place since TODO 34** (`league/flysystem` 1.1.10 -> 3.35.2, plus `league/flysystem-local`), because Laravel 9 requires `^3.8.0`. The suite was green across that hop with no change to any guard, so the `delete()`/`exists()`/`size()` semantics below did not break a single covered path - TODO 30 (`'throw' => false` on every disk, `web` disk root `public_path()`) is why. This item is therefore verification of the **uncovered** paths, not a migration.
  - **Note for whoever does it: the `s3` disk has no adapter package installed** and did not have one before the hop either - `league/flysystem-aws-s3-v3` is absent from `composer.lock`. So "exercise all 5 disks" cannot mean the same thing for `s3` as for the other four; decide whether the disk is live at all before writing a test that pretends it is.
  - Needed:
    - Exercise all 5 disks (`local`, `public`, `web`, `news_files`, `s3`).
    - `delete()` on a missing file now returns `true`; `exists()` on a missing directory and `size()` behavior changed. Review the guard patterns in `app/Http/Controllers/GroupNewsDelete.php:20,21`, `app/Http/Livewire/Groups/NewsEdit.php:109,119,120,141,142`, `app/Models/GroupNewsFile.php:35,36`.
    - Confirm the bare `Storage::exists('installed.txt')` sentinel still behaves (`routes/web.php:104`, `app/Exceptions/Handler.php:60`).
  - Expected changes: targeted fixes; TODO 30 should have already de-risked the `web` disk.

- [x] **TODO 38: Move `resources/lang/` to `lang/`** - DONE
  - **Delivered on 2026-09-04, in three commits: the pin tests, the move, then documentation** (the documentation commit is shared with TODO 37). `composer test`: **OK (1425 tests, 3952 assertions)**. This item's contribution is 6 tests: `SetupLanguageListTest` 3, `LangPathTest` 3.
  - **`git mv resources/lang lang`, 122 files, every one recorded as a rename**, so `git log --follow` still works on them. The count corrects this entry's own "130 files" below, which was a TODO 17 measurement taken while `joedixon/laravel-translation` still had files published under `vendor/translation/`.
  - **The move on its own broke 11 tests, and all 11 trace back to two lines** - measured deliberately, by running the suite after the `git mv` and before touching anything else. `MetaController::welcome():24` and `BasicsController::languages():37` both read `File::files(base_path('resources/lang'))` to build the installer's language list, and `File::files()` raises on a directory that is not there, so both installer screens 500'd; that took eight `Setup` tests with them, plus the three path assertions the pin commit had put there for this moment.
  - **Everything else stayed green, and that is the actual deliverable.** `AdminTranslationEditorTest`, `LangFilesTest` and `AdminSettingsTest` did not move by a single assertion. This entry demanded "verify it, do not assume it" about the editor resolving through `App::langPath()`; the measurement is that unmoved number, not an inspection of the code.
  - **The two controllers now read `lang_path()`** (the helper exists in Laravel 9: `Foundation/helpers.php:512-522`). They carry byte-identical copies of one loop; `SetupLanguageListTest` asserts the two screens offer the same list, so changing one and forgetting the other fails in the suite. **Merging the duplicate belongs in Appendix C**, not in a hop commit.
  - **THE FINDING, and it is an operator problem rather than a code one: the old directory wins if it survives.** `Application::bindPathsInContainer()` (`vendor/laravel/framework/src/Illuminate/Foundation/Application.php:349-355`) resolves the language path from the disk - `resources/lang` when that directory exists, `base_path('lang')` otherwise. An update archive overwrites and adds files but **never removes a directory**, so a host that unpacks 2.0.0 ends up with both, keeps reading the old one, and every translation silently freezes at its pre-upgrade content. No exception, no log line: new keys render as raw keys and changed text stays old, so it looks like the release simply carried no translation changes.
  - **Two halves, and only one of them is the repository's.** `LangPathTest::test_the_repository_carries_exactly_one_language_directory` fails if both directories ever exist here, which catches a bad merge or a half-applied revert. The host's half can only be a written instruction, and it is now the first entry in `upgrade-guide.md` section 3 that is not "nothing", repeated in section 5.
  - **No `useLangPath()` call was added, and the reasoning is recorded for TODO 75.** The framework resolves correctly on its own once `resources/lang` is gone; an override would only mask the directory coming back; and `bootstrap/app.php` is the file the Laravel 11 skeleton (TODO 56) rewrites wholesale. **If TODO 75 chooses Branch A** (an automated 2.0.0 hook), that is the point at which pinning the path in one line becomes worth it - an automated upgrade cannot rely on an operator working through section 3.
  - **`resources/lang/vendor/cookie-consent/` moved with the rest and needed no step of its own**: `FileLoader::loadNamespaceOverrides()` (`:103`) builds `{$this->path}/vendor/{$namespace}/...` from the same container binding. Worth recording, because it also means the override layer **cannot be tested by its output**: all 27 published files are byte-identical to the package's own copies apart from line endings, so a `trans()` assertion could not tell an override from a default. `LangPathTest` asserts the file's location instead, and says why.
  - **`release/upgrade.php:144` was deliberately not touched.** Its `resources/lang/vendor/translation` removal serves the **1.x** line, where the directory is still in the old place, so the line remains correct and the TODO 34 freeze holds.
  - **Also carried in the move commit, because they state where the language files live**: the three `lang_help` texts that tell an administrator which folder to put language files in (`de`/`en`/`hu`), and five comments naming `resources/lang` as the tree the suite protects. **`lang/hu/translation.php:4` keeps its mention on purpose** - it records where the content was salvaged from when `joedixon/laravel-translation` was removed, and that path was correct then.
  - **One thing found and not fixed:** that same comment is **Hungarian**, written without accents, which is exactly the blind spot TODO 33.9 predicted for its accent-based detector - and it sits in `resources/lang/`, a tree TODO 33.9 never scanned. Worth a small item; not folded into this one.
  - **Everything below is the entry as it stood before delivery.**
  - Needed:
    - Laravel 9 relocates the language directory to the project root. Measured in TODO 17: **22 locale directories plus `vendor/`**, 130 files, 906 KB - but only 7 locales hold more than the `installer_messages.php` stub, and 5 root JSON files (`de/fr/hu/ro/sk.json`) move with them. `resources/lang/vendor/cookie-consent/` (27 locales) moves too.
    - **The `joedixon/laravel-translation` path warning previously recorded here was wrong and the package is gone by now.** TODO 17 measured that the driver took its path from `$this->app['path.lang']`, which follows the move; only its publish target was a literal. TODO 33.3 removed the package in Phase 3 and requires the replacement to resolve through `App::langPath()`, so the editor should need no change here - **verify it, do not assume it**: re-run `AdminTranslationEditorTest` and confirm `LangFiles` resolves to the new root.
  - Expected changes: directory move, `AdminTranslationEditorTest` re-run, translation UI re-tested.

---

## Phase 5 - Laravel 9 -> 10 (PHP 8.1, then switch to 8.3)

This is where the interpreter switches. Laravel 10.x supports PHP 8.1 through 8.3, so once the framework bump is green on `php81`, re-run the suite on the default `php` (8.3) and make that the working runtime for the rest of the roadmap. Update the *Execution Environment* section in the same commit.

**CORRECTION (TODO 39.1, 2026-09-07): that ordering was unachievable as written, and is now merely optional.** TODO 39.1 targeted `laravolt/avatar ^6.5`, which requires **PHP >= 8.2**, while `config.platform.php` is pinned at **8.1.30** - so Composer would have refused the resolution before any suite could be green on `php81`. The preamble put the switch after the hop; TODO 22 assumed it had already happened ("which this phase already provides"). Neither statement was wrong on its own; they were never read together.

Removing the package instead dissolved the conflict rather than resolving it: nothing in Phase 5 now needs PHP 8.2, so **the interpreter switch is a free-standing step that can go before, during or after the framework bump.** Doing it first, as its own commit, is still the recommendation - it isolates a runtime change from a framework change - but it is a preference now, not a constraint.

**DONE on 2026-09-08, and the measurement this preamble asked for is the reason it was cheap.** The interpreter switched
**before** the framework hop, on Laravel 9, as its own commit - the ordering this preamble recommends and TODO 39.1
demoted from a constraint to a preference. `composer test` reports **OK (1469 tests, 4030 assertions)** on PHP 8.3.16,
the same numbers it reports on `php81`, with no deprecation notice and no test touched.

Three things moved with it, and two of them this document never mentioned:

- **`composer.json`'s `test` script hardcoded `php81`, twice.** It is the command every delivered entry quotes its suite
  numbers from, so leaving it would have kept the whole suite on 8.1 while the framework moved to 10 - a switch that
  documented itself and changed nothing.
- **`config.platform.php` is deleted**, executing TODO 24's own rule: the pin existed because Composer ran on 8.3 while
  the application ran on 8.1, and it says to remove the key once the two coincide. They now do.
- `AGENTS.md`, `release/README.md` and the baseline report's *How to compare after a hop* recipe. The `php81` mentions
  left in that report are records of the TODO 03 run and stay.

`php81` remains installed and Laravel 9 still supports it, so this commit is revertible on its own.

**Worth measuring before assuming it is risky - and it was, with the answer above:** the *Execution Environment* section's justification for staying on `php81` is Carbon's `setLastErrors()` fataling on PHP 8.3. The installed `nesbot/carbon` 2.73.0 already carries the fixed, untyped implementation (`vendor/nesbot/carbon/src/Carbon/Traits/Creator.php:955`, which accepts `false`), so that reason has probably expired with the `v1-patch H` refresh. Run the current suite on the default `php` before writing the switch up as a risk. **It had expired, and the run is the one recorded above.**

- [x] **TODO 39: Upgrade to Laravel 10 and align the toolchain** - DONE
  - **Delivered on 2026-09-08, in three commits: the dependency set (`composer.json`/`composer.lock`/`vendor/`), the test layer, then an application defect the hop introduced.** `laravel/framework` `^9.0` -> `^10.0`, resolving to **v10.50.3**; `artisan about` reports it on PHP 8.3.16. Suite: **1471 tests, 4079 assertions, green** - the 1469/4030 the interpreter switch left, plus this item's two cases. Documentation is shared with TODO 40.
  - **Five `composer.json` edits, and the fifth was not in this entry.** `php` `^8.0.2` -> `^8.1`, the framework, `nunomaduro/collision` `^6.0` -> `^7.0`, `phpunit/phpunit` `^9.3.3` -> `^10.0`, and **`spatie/laravel-ignition` `^1.0` -> `^2.0`** - see the corrected bullet below. The advisory block lost `PKSA-8qx3-n5y5-vvnd` exactly as its own reason text instructed; the other three name Phase 9 and stay. `config.allow-plugins` is still an explicit empty block: the Laravel 10 resolution introduced no plugin either.
  - **The lock moved 2 in, 69 changed, 2 out.** `laravel/prompts` arrived as a direct framework requirement and `spatie/error-solutions` with ignition 2; `doctrine/instantiator` and `sebastian/resource-operations` left with PHPUnit 9. Four of the 69 are majors that are not the framework and are all load-bearing here: **`monolog/monolog` 2 -> 3** (this item's own reconciliation), **`pragmarx/google2fa` 8 -> 9** (the TOTP engine the replay protection is built on), **`bacon/bacon-qr-code` 2 -> 3** (Fortify's QR renderer) and **`hamcrest/hamcrest-php` 2 -> 3** (under Mockery). `laravel/fortify` moved 1.19.1 -> 1.36.2, which is the TODO 39.2 range doing what it was written to do. Four Symfony components crossed to 7.x while the rest stayed on 6.4 - that is Laravel 10 declaring `^6.2|^7.0` on some and `^6.2` on others, not a partial upgrade.
  - **The Monolog reconciliation is verification, and the honest form of it says what the suite cannot prove.** `config/logging.php` is the untouched framework skeleton: no `tap` class, no custom formatter, no processor, and **not one `Monolog\` import anywhere under `app/` or `tests/`**. The three handler classes it names all exist in Monolog 3. But every logging assertion in the suite is a `Log::spy()` over the facade, so a green suite is not evidence here - this is the Phase 4 preamble's first lesson, applied to itself rather than discovered again.
  - **Six of 1469 went red, and they are two causes rather than six.**
    1. **Five are one API change.** `Illuminate\Database\QueryException::__construct()` gained `$connectionName` as its **first** parameter, so every Laravel 9-shaped construction passes the previous `Throwable` where an array is expected. Two of the five wear a different hat, and the symptom does not point at the cause: in `SetLocaleTest` and `ApplicationSettingsTest` the constructor runs in the test body and PHPUnit reports a `TypeError`; in the two exception-handler tests it runs inside a route closure, so the framework turns it into a **500** and the test says "expected a redirect, got 500", which reads like a handler regression and is not one. Control experiment: forcing the installer branch of `Handler::register()` to `false` failed **exactly two** of the eight handler cases - the two that assert the redirect - and the installed branch stayed green, so the exception really does reach the handler again.
    2. **One is an application defect this hop introduces, in every language at once.** Laravel 9's `Password` rule resolved its own messages and fell back to a hardcoded English sentence, which `fail()` then ran through the translator - and JSON translations key on English strings, which is exactly why `de.json`, `fr.json` and `hu.json` carry those five sentences translated. Laravel 10 calls `$validator->addFailure($attribute, 'password.mixed')`, which resolves through `lang/<locale>/validation.php` and never touches the JSON layer. **All six locales carried `password` as the flat pre-Laravel-9 string**, so every password-strength message rendered as its own key - English included. The five sentences are lifted verbatim from where each locale already kept them, so **no user-visible string changed**; `de`/`fr` stay fully translated, `hu` keeps its four plus its untranslated `uncompromised`, `ro`/`sk` stay English.
  - **The new guard has two halves, and the control experiment is why.** "No locale renders the failure as a raw key" stayed **green** when one locale's array was turned back into a string, because `APP_FALLBACK_LOCALE` is `en` and a broken locale resolves through English. That is a true statement about what a user sees and worth asserting - and on its own it would have stayed green until English broke too, which is precisely the state this hop created. So a second test asserts the files themselves; the same control then fails exactly once and names the locale.
  - **The strongest single result, again: neither route fixture moved.** `spatie/laravel-ignition` 1 -> 2 was the seam TODO 34 flagged, and it registered no new named route. The application's own route table is byte-identical across a second framework major.
  - **Verified beyond the suite:** `artisan optimize` exits 0; `artisan about` reports Laravel 10.50.3 on PHP 8.3.16 and is captured in `upgrade-notes/laravel10-about.txt`; `git ls-files --others --exclude-standard vendor` is empty; the two `bootstrap/cache` manifests were deleted and `package:discover` re-run after the `--no-scripts` update.
  - **What is deliberately NOT here:** `doctrine/dbal` stays (native `change()` is Laravel 11), `livewire/livewire` stays on 2 (Phase 6), and `laravel/tinker` needed no edit - `^2.5` resolved to a release admitting Laravel 10 through 12 on its own.
  - **Everything below is the entry as it stood before delivery**, apart from the two bullets the delivery corrected in place.
  - Needed:
    - `laravel/framework` to `^10.0`, `nunomaduro/collision` to `^7.0`, PHPUnit to `^10.0`.
    - Reconcile Monolog 3 logging changes against `config/logging.php`.
    - ~~**`protonemedia/laravel-verify-new-email` fails the resolution here if TODO 33.5 has slipped**, and this is the only place in the roadmap where it does so before Phase 10.~~ **Moot: TODO 33.5 shipped on 2026-08-10** and the package is gone from `composer.json` and `composer.lock`. Kept for the record, because it was the only genuine upper bound of the Phase 2 four: the installed 1.6.0 required `illuminate/support ^8.67||^9.0`, and the fallback would have been a lock bump to 1.13.0 rather than a decision.
    - **`rakibdevs/openweather-laravel-api` does NOT fail here - corrected by TODO 22.** This line used to say it did, on PHP. It does not: the installed 1.9.0 declares no framework constraint and its `php ^7.2|^7.3|^7.4|^8.0` admits 8.1 through 8.4, because `^8.0` is a range and not a version. If TODO 33.6 has slipped, the package installs cleanly here and at every later hop, and stays broken at runtime instead - which is worse, not better, since nothing announces it. Do 33.6; do not rely on this phase to force it.
    - ~~**`laravolt/avatar` fails the resolution here too, and unlike the two above it cannot be waved through.** Measured in TODO 22: the installed 4.1.7 declares `illuminate/support ^6.0|^7.0|^8.0|^9.0`, so Composer stops here - and `composer.json` declares `^4.1`, which admits **no** release supporting Laravel 10 or later. **TODO 39.1 is the work**; do it in the same PR as this item, because the framework bump does not resolve without it.~~ **Closed by TODO 39.1 (2026-09-07), and not the way this line expected: the package was removed rather than migrated.** The measurement was right - it was this hop's only real resolution blocker - but the conclusion "do it in the same PR" no longer applies, because the removal depends on nothing in this item and shipped ahead of it, on Laravel 9. ~~**This hop therefore has no package left that blocks its resolution.**~~ **Wrong, and corrected by TODO 39.2 (2026-09-08): two were left.** The sentence was true of the packages the struck-through lines above had been about, and it was never re-derived across the whole tree. Measured from the committed `vendor/`: `laravel/fortify` 1.11.2 declares `illuminate/support ^8.82|^9.0` under a `~1.11.2` pin that admits no L10-capable release, and `spatie/laravel-ignition` 1.7.2 declares `^8.77|^9.27` plus `monolog/monolog ^2.3` under a `^1.0` that admits none either. **TODO 39.2 removed the first, on Laravel 9.** The second is a constraint edit belonging to this item and is listed above.
    - **`spatie/laravel-ignition` `^1.0` -> `^2.0`.** The 1.x line ends at Laravel 9 and requires Monolog 2, which Laravel 10 will not have. This was missing from the entry entirely: TODO 34 introduced the package and its own commit message said "the v2 bump belongs to TODO 39", but the line was never written here. Expect the same class of consequence TODO 34 met at the same seam - the package registers its `ignition.*` routes from `boot()` unconditionally, and `RouteContractSnapshotTest`'s `DEV_ONLY_ROUTE_PREFIXES` is what catches a change in that set.
  - Expected changes: composer updates plus the PHPUnit work in TODO 40.

- [x] **TODO 39.1: Replace `laravolt/avatar`** - DONE, but **NOT by migrating it**
  - Delivered on 2026-09-07. **The package is gone rather than upgraded, and `intervention/image` left with it.** The entry below used to plan a jump to `^6.5` plus an Intervention Image 2 -> 4 migration; what it did not do was ask whether the dependency was worth carrying at all. It was not. Suite: **1436 -> 1460 tests, 3976 -> 4007 assertions, green.**
  - **What made the question worth asking.** The whole cost centre was one call site drawing two letters on a coloured circle, and the CSS shrinks the result to 40x40 anyway (`.direct-chat-img`, `public/build/scss/_direct-chat.scss:104-108`). An SVG data URI produces the same picture with no imaging library, no GD, and no file - so `App\Support\Avatar\InitialsAvatar` replaced both packages, following the Phase 3 pattern that removed five others.
  - **THE PACKAGE WAS BROKEN, AND NOTHING SAID SO.** `config/laravolt/avatar.php:51` pointed at two fonts under `config/fonts/`, **a directory that does not exist** - there is no `.ttf` anywhere outside `vendor/`. The package picks its font from that config list rather than from its own default (`Avatar.php:480`), and `Intervention\Image\AbstractFont::hasApplicableFontFile()` (`:277-284`) tests with `file_exists()`, so every avatar fell through to GD's built-in bitmap font. The configured 38px `fontSize` and the align/valign settings never applied. This invalidates the "the font paths should survive unchanged - the suite asserts both" line in the superseded entry: the suite asserted the dimensions and the driver, never a font file.
  - **Colour compatibility was measured, not assumed.** The package chose a background by summing the name's BYTES modulo the palette size (`Avatar.php:427-435`), on the RAW name - before the e-mail and ASCII handling that only ever touched the initials. `InitialsAvatar::background()` transcribes that, so **no existing user's avatar changed colour**. The expected hexes in `tests/Unit/Support/InitialsAvatarTest::colourCases()` were read out of the installed package by reflection while it was still in the tree; they are measurements, and they are the proof.
  - **Two behaviour changes, both improvements, both deliberate.**
    - Accented initials survive. The package ran `Str::ascii()` first, so an accented name lost its accent; the browser draws the real letter.
    - A renamed user finally gets a matching avatar. The old `Storage::exists()` guard meant the first PNG ever written for a user was served forever, so a rename never reached the board. The SVG is computed per render.
    - The only case with no counterpart is the empty name: the package seeded it with `chr(rand(65, 90))`, drawing a **new colour on every render** (twelve calls produced ten colours). The replacement is deterministic.
  - **The consequence for TODO 39, and it is the useful one.** `laravolt/avatar` was the Laravel 10 hop's only real resolution blocker - `^4.1` admits no L10-capable release. With it gone, TODO 39 is a plain dependency bump, and **the sequencing conflict in the Phase 5 preamble dissolves**: `^6.5` needed PHP >= 8.2 while `config.platform.php` is pinned at 8.1.30, so "green on `php81` first, switch to 8.3 after" could never have resolved. The interpreter switch is now a free-standing step rather than a hostage of the hop.
  - **What happened to the TODO 22.1 suite, on the record.** Seven of its ten tests described the file-based mechanism - PNG signature, configured dimensions, GD driver, the disk-root/URL-prefix pairing, "generated once", "never overwritten", and the `stream()` tripwire. None could survive a decision to stop writing files. TODO 22.1's rule (a behaviour test may only be rewritten when the behaviour genuinely changed) is met, and `AvatarGenerationTest`'s docblock carries the record. What replaced them protects the user-visible behaviour that did **not** change, plus one new invariant: rendering the board touches no disk.
  - **One thing the new suite needed that the old one did not.** The old mechanism wrote its PNG from `render()` regardless of privilege, so a test could observe it without passing `checkPrivilege()`. The avatar now exists only in the rendered markup, so every assertion depends on the message list actually being drawn - hence the `message_use = 2` grant in the fixture.
  - **Also corrected here:** the superseded entry's "the `web` disk's root is the relative path `'public'` and the view addresses `asset('public/avatars/...')`" is two releases out of date. TODO 30 made the root `public_path()` and the view has used `asset('avatars/...')` since; `AvatarGenerationTest` had already recorded the fix, only this roadmap had not.
  - Files: `app/Support/Avatar/InitialsAvatar.php` (new), `app/Http/Livewire/Groups/Messages.php`, `resources/views/livewire/groups/messages.blade.php`, `tests/Unit/Support/InitialsAvatarTest.php` (new), `tests/Feature/Avatar/AvatarGenerationTest.php`, `composer.json` / `composer.lock`, `config/laravolt/` (deleted), `.gitignore`, `.docs/components.md`, `upgrade-guide.md` sections 2, 3 and 5.
  - **Deployed hosts:** `vendor/laravolt` and `vendor/intervention` orphan, `config/laravolt/` disappears, `public/avatars/*.png` becomes dead data, and `bootstrap/cache/packages.php` must go - it names two service providers that no longer exist. That last one fired here during development, on the first test run after the removal, which is the cheapest possible demonstration that the section 5 warning is real.
- [x] **TODO 39.2: Lift the `laravel/fortify` version ceiling** - DONE
  - **Delivered on 2026-09-08, in five commits: the pin tests, the storage migration, the ceiling lift, an advisory bump the audit surfaced, then documentation.** `laravel/fortify` `~1.11.2` -> `>=1.19.1 <1.37.0`, resolving to **v1.19.1**. Suite: **1460 -> 1469 tests, 4007 -> 4030 assertions, green.** Delivered on Laravel 9, ahead of the hop, for the same reason TODO 39.1 was.
  - **THIS ITEM EXISTS BECAUSE TODO 39's CLOSING LINE WAS WRONG.** That entry ends *"This hop therefore has no package left that blocks its resolution."* Measured from the committed `vendor/` tree, **two** packages reject `illuminate ^10` and neither is admitted by its declared constraint: `laravel/fortify` 1.11.2 (`illuminate/support ^8.82|^9.0`, declared `~1.11.2`) and `spatie/laravel-ignition` 1.7.2 (`^8.77|^9.27` plus `monolog/monolog ^2.3`, declared `^1.0`). The second is a one-line constraint edit and belongs to TODO 39 itself; **the first is this item.** Everything else in the tree already admits `^10` today, `livewire/livewire` 2.12.8 included - see the Appendix A correction below.
  - **The contradiction was two statements that were never read together**, which is the same failure shape TODO 39.1 recorded for the `laravolt/avatar` / `config.platform.php` pair. Appendix A's Fortify row says *"Installed fails at L10"* and, in the same cell, *"Widening `~1.11.2` is a Phase 11 decision, not a hop step"*; TODO 69 repeats the second. Both are corrected in place.
  - **The ladder was re-measured, not read off the table.** TODO 22's method - Packagist's p2 endpoint for every stable release, Composer's own `Semver::satisfies()` - re-run a month after the original measurement returns every number unchanged: **L9 1.10.1, L10 1.19.1, L11 1.21.0, L12 1.31.3, L13 1.36.2**, with **1.37.0** the first release requiring `laravel/passkeys`. 1.19.1 is the lowest release admitting Laravel 9 **and** 10, which is what makes this a Laravel 9 commit rather than cargo for the hop.
  - **The constraint is a range on purpose.** `>=1.19.1 <1.37.0` states both ends in the file a reviewer reads: the floor is the Laravel 10 requirement, the ceiling is the passkey boundary TODO 66 already decided to keep out of the upgrade. Every later floor in the ladder sits inside the range, so **Phases 8, 9 and 10 need lock movement and no further edit here.**
  - **THE PLAN'S ONE REAL SECURITY RISK DID NOT MATERIALIZE, and the measurement is the deliverable.** The concern was that Fortify >= 1.12 stamps `two_factor_confirmed_at` on **enable**, which would mark a second factor confirmed before the user ever proved they could read their authenticator - precisely the lockout the `authenticateThrough()` pipeline exists to avoid. On the installed 1.19.1, `EnableTwoFactorAuthentication` writes **only** the secret and the recovery codes. `config/fortify.php` therefore needed no edit and `'confirm'` stays off.
  - **More generally: on this installation the package writes neither column.** Every vendor path touching `two_factor_confirmed_at` is gated on `Fortify::confirmsTwoFactorAuthentication()`, which is **false** here - confirmed at runtime, not inferred from the config array. The package's own disable action skips the timestamp, `hasEnabledTwoFactorAuthentication()` does not read it, and `ConfirmTwoFactorAuthentication` is never routed because `Fortify::ignoreRoutes()` hands the whole route table to `routes/fortify.php`. **So the bump did not force the schema change**, and the migration is recorded as a deliberate convergence rather than a forced one: keeping the answer in the column the vendor would use turns "adopt Fortify's own confirmation flow" into a TODO 69 config decision instead of a schema migration made inside an authentication change.
  - **A dating correction for TODO 69: 1.11.2 already referenced `two_factor_confirmed_at`**, in the same gated way. The column awareness predates 1.12.0; what 1.12.0 added was the published migration and the flow around it, none of which this installation could reach.
  - **The characterization suite is what made the decision executable**, and it is the TODO 19.1 / 20.1 / 21.1 / 22.1 rule again: the only 2FA coverage was `TwoFactorReplayTest`, which measures the TOTP provider. Enabling, confirming, disabling and the login challenge that depends on all three had **zero** tests, so "bump", "override" and "migrate" were indistinguishable in risk. `TwoFactorConfirmationFlowTest` is nine cases; eight ask the model rather than the column and **did not move by a character** across the storage migration, which is the actual proof that the migration changed where the answer is kept and nothing else. The ninth was directional on purpose - it asserted the old storage so that it would fail the moment the confirmation moved - and its reversal was the review.
  - **Control experiments, as run.** Forcing `RedirectIfTwoFactorConfirmed`'s condition to `false` failed **exactly one** case, with the login landing on `/home` instead of `/two-factor-challenge`. Removing the confirmation write from `User::confirmTwoFactorAuth()` failed **exactly three** - that one, the valid-code case and the storage case - and nothing else in either run. Restored, `git diff` empty.
  - **The second run exposed a blind test, which is why both were worth running.** "Disabling clears the secret, the recovery codes and the confirmation" stayed green with the confirmation write gone, because *"not confirmed afterwards"* is equally true of a user who was never confirmed at all. It now asserts the confirmed state before disabling, and the comment names the experiment that found it.
  - **The migration's backfilled value is a marker, not a measurement.** The old schema stored *whether* a second factor was confirmed and never *when*, so confirmed rows get their own `updated_at`, falling back to `created_at` and then `now()`. Both directions were **executed** rather than read - `RefreshDatabase` starts from an empty table and so never exercises a backfill - by seeding two probe rows on the pre-migration schema: the confirmed one received its own `updated_at`, the other stayed null, and `down()` put the boolean back with the timestamp dropped.
  - **What the delivery found that the plan had not, and it is a test-only defect.** The TODO 33.2 backfill migration writes `two_factor_confirmed` unconditionally, and `ReanonymizeBackfillTest` invokes that `up()` directly against the fully migrated schema - where the column is now gone. No deployed host can hit this, because that migration always runs before this one, but the test would have died on an unknown column. It now writes the boolean only when the column is there; the guard changes nothing at the moment the migration actually runs.
  - **An advisory arrived from upstream during the item and was closed in its own commit.** `composer audit` reported 5 advisories across 2 packages where the tree has carried 4 across 1 since TODO 34. The fifth is **not** Fortify: `league/commonmark` published GHSA-8rr7-cvq3-gmfh on 2026-09-01, affecting `>=1.5.0,<2.10.0`. It is a lock bump inside the framework's own `^2.2.1` - 2.9.2 -> 2.10.1 - and doing it here rather than at the hop keeps it out of a diff where a framework major is already explaining thousands of files. Exposure here is most likely nil (the Attributes extension is not in Laravel's default environment and nothing enables it), but Composer 2.10 blocks advisory-affected versions during resolution, so the hop would have had to move it anyway. Audit is back to **4 advisories across 1 package, zero abandoned**.
  - **Verified beyond the suite:** the lock moved by exactly one package in each of the two composer commits (0 installs, 1 update, 0 removals); all **15** Fortify controllers `routes/fortify.php` names by FQCN exist in 1.19.1, checked rather than assumed, and the two the package ships that it does not name stay unregistered; `git ls-files --others --exclude-standard vendor` is empty after each `git add`; `artisan package:discover` was re-run after both `--no-scripts` updates with the two `bootstrap/cache` manifests deleted first. **Neither route fixture moved**, across eight minor versions of an authentication package.
  - **Not a regression, but now written down:** the project's `DisableTwoFactorAuthentication` override replaces `__invoke()` wholesale and therefore does not dispatch `TwoFactorAuthenticationDisabled`. The package dispatched that event in 1.11.2 too, so nothing changed, and `EventServiceProvider` registers no Fortify listener.
  - **What is deliberately left to TODO 69:** the vendored route file is reconciled only where the bump forced it, which turned out to be nowhere. Adopting `ConfirmedTwoFactorAuthenticationController`, `TwoFactorSecretKeyController`, `'confirm' => true`, or moving closer to default registration are all feature decisions, and that entry keeps them.
  - Files: `database/migrations/2026_09_08_120000_move_two_factor_confirmation_onto_a_timestamp.php` (new), `tests/Feature/Auth/TwoFactorConfirmationFlowTest.php` (new), `app/Models/User.php`, `app/Actions/Fortify/RedirectIfTwoFactorConfirmed.php`, `app/Actions/Fortify/DisableTwoFactorAuthentication.php`, `app/Support/Gdpr/Anonymizable.php`, `database/migrations/2026_08_10_140000_reanonymize_users_for_the_null_field_list.php`, `resources/views/layouts/app.blade.php`, `resources/views/user/twofactorsettings.blade.php`, `tests/Feature/Auth/TwoFactorReplayTest.php`, `tests/Feature/Gdpr/AnonymizationTest.php`, `tests/Feature/Gdpr/ReanonymizeBackfillTest.php`, `composer.json` / `composer.lock` / `vendor/`, `.docs/fortify-routes.md`, `.docs/commands.md`, `upgrade-guide.md` sections 2-5 and its change log, and this roadmap.
  - **Deployed hosts:** nothing orphans - no package leaves - but the migration **drops a column**, which is the first non-additive one in `upgrade-guide.md`. Rolling back to the previous release therefore needs `migrate:rollback` *before* the old code goes back, not after; `down()` restores the boolean and refills it from the timestamp.

- [x] **TODO 40: Migrate the test layer to PHPUnit 10** - DONE
  - **Delivered on 2026-09-08, in one commit plus the shared documentation commit.** `composer test`: **OK (1474 tests, 4102 assertions)** and, for the first time since the framework hop, **no "there were issues" line at all** - the 14 PHPUnit deprecations are gone.
  - **Both counts in this entry were stale, and it said so itself.** Re-derived from PHPUnit 10's own deprecation output rather than from a grep, which is the better evidence: **ten** non-static providers, not six. The four this entry does not know - `AuthEndpointHardeningTest::crlfPayloads`, `RetentionWindowTest::garbageSettingValues`, `FailedJobMonitorRouteTest::emptyEnvValueProvider`, `NotificationAddressingTest::replyToNotificationProvider` - were all added after the correction below was written. The annotation count was never here at all: **36 `@dataProvider` annotations across 18 files**, so "Expected changes: `phpunit.xml`, two test files" is wrong by sixteen files.
  - **Eleven of the 36 are the single-line form** (`/** @dataProvider x */`, all in `EncryptedAttributeTest`), which is the shape a naive multi-line regex skips. **Thirty-five of the 36 docblocks held nothing but the annotation** and are gone entirely; the one that carried prose kept it.
  - **One provider could not simply be made static.** `NotificationRegressionTest::notificationProvider()` calls `sharedPayload()`, so `static` turned that into "Using `$this` when not in object context" and took the mail-contract test down with it. `sharedPayload()` never needed `$this` either - it builds a literal array - so it is static too, and three call sites go through `self::`.
  - **Control experiments, as run.** Removing one `#[DataProvider('encryptedColumns')]` drops `EncryptedAttributeTest` from 96 cases to 88 and fails the orphaned method with "Too few arguments ... 0 passed and exactly 3 expected" - exactly that provider's nine rows minus the one argument-less call left behind. Restored, `git diff` empty. **The stronger evidence is the count that did not move:** 1471 tests before the conversion and 1471 after it, so no attribute silently failed to land.
  - **The `phpunit.xml` / `.env.testing` item turned out to be a trap rather than duplication, and it is now enforced rather than remembered.** Measured, the two files already agree - 15 keys in both, none disagreeing - so there was nothing to repair; the work is keeping it that way. PHPUnit writes `<php><server>` into `$_SERVER` before boot and Laravel then reads `.env.testing` with Dotenv's **immutable** reader, which does not overwrite an existing variable. **`phpunit.xml` wins, so editing one of those 15 lines in `.env.testing` does nothing at all, silently.** Neither file can go: `phpunit.xml` has to carry the database-safety keys so they hold even when `.env.testing` is absent - the pairing `CreatesApplication` guards - and `.env.testing` carries `APP_KEY`. So both stay and `TestEnvironmentContractTest` asserts they never disagree where they overlap. Control: changing `DB_HOST` in `.env.testing` alone fails it, with the message naming which file wins.
  - **`TELESCOPE_ENABLED` is deleted from `phpunit.xml`** and the new guard forbids it: `laravel/telescope` has never been a dependency here, and the switch had been sitting there implying otherwise.
  - **Two things fixed in passing, both in files this commit had open.** `RetentionWindowTest`'s docblock quoted a `day_stats` row count measured on a real database, which `AGENTS.md` forbids in test comments for the same reason it forbids it anywhere else; the sentence now names what the command would delete rather than how much of it there was. And `storage/framework/.gitignore` gains `lsp-*`, because the IDE language server writes a cache file into an otherwise tracked directory and nothing was ignoring it - the same decision the repository already made for `vendor/_laravel_ide/`.
  - **Everything below is the entry as it stood before delivery.**
  - Needed:
    - `phpunit.xml`: `<coverage><include>` becomes `<source>`, `processUncoveredFiles` is removed, add `cacheDirectory`.
    - Convert every `@dataProvider` annotation to a `#[DataProvider]` attribute **and make the provider methods `static`**. Non-static providers are deprecated in PHPUnit 10 and forbidden in 11, so doing it here avoids a fatal in Phase 9.
    - **CORRECTION (measured in TODO 15): there are six, not two.** `AuthorizationGateTest::groupServantMembershipProvider`, `PaginationBehaviorTest::paginatingComponentProvider`, `LivewireRouteMountedComponentsTest::mountedRouteProvider`, `SetLocaleTest::privilegedRoleProvider`, `NotificationEnvFallbackTest::replyToNotificationProvider`, `NotificationRegressionTest::notificationProvider`. The count grew because Phase 1 added test files after this entry was written; TODO 13's providers are already static and are correctly absent from the list. Re-derive the list with a grep before executing rather than trusting this one.
    - Keep `.env.testing` and the `phpunit.xml` `<php>` block in sync; they currently duplicate each other.
  - Expected changes: `phpunit.xml`, two test files.

- [x] **TODO 41: Refactor patterns deprecated by Laravel 10** - DONE
  - **Delivered on 2026-09-09, in four commits plus this documentation one:** the deprecation fixes, the guard that found them, the kernel rename, and two leftovers. Suite: **1474 -> 1476 tests, 4102 -> 4105 assertions, green.** No composer change, so no `vendor/` movement and no `package:discover` step.
  - **THE ENTRY WAS EMPTY AND THE ITEM WAS NOT.** Both bullets below were already struck through, and both were right: `$dates` went with TODO 29 and `DateCastingTest` guards it, and the `Expression::getValue(Grammar)` half has no call site to fix. What had never happened was re-deriving the list from the Laravel 10 upgrade guide against the actual tree. Doing that found **one** live Laravel-10-deprecated pattern - and the sweep meant to confirm "nothing else is left" found **sixteen deprecation sites of a different kind**, in ten files.
  - **THE REAL FINDING IS THAT THE SUITE COULD NOT SEE A DEPRECATION AT ALL, and it never could.** Two measurements from the installed vendor tree explain it: `HandleExceptions::bootstrap()` installs its own `set_error_handler()`, and `shouldIgnoreDeprecationErrors()` is true whenever `runningUnitTests()` is true and `LOG_DEPRECATIONS_WHILE_TESTING` is unset, so the handler **returns without logging, rethrowing or forwarding**; and `PHPUnit\Runner\ErrorHandler::enable()` **gives up when a handler is already registered** (`restore_error_handler()`, then `return`), so from the first booted test onwards PHPUnit's own handler is not in the chain either. **A `failOnDeprecation` attribute in `phpunit.xml` would therefore have been inert**, which is why the guard is a trait rather than a configuration line. This also settles the standing of every "no deprecation notice" line in this document - TODO 03, the Phase 5 preamble, TODO 39, TODO 40: they were not false, they were **unfalsifiable**. They are testable from this item onwards.
  - **Wired up for the first time, the guard failed 212 of 1474 tests.** Two families, neither of them a Laravel 10 change:
    1. **Creation of dynamic properties, deprecated since PHP 8.2 - eleven sites.** `GenerateStatProcess::$group_data`; `updateGroupFutureChanges`'s `$default_colors`, `$days_original` and `$parent_group`; `Events\EventEdit::$service_days`; and `$first_day` / `$last_day` on `Groups\Statistics`, `Groups\History` and `Events\LastEvents`.
    2. **Passing null to a non-nullable internal parameter, deprecated since PHP 8.1 - five sites.** `strlen($this->searchTerm)` in `Groups\ListUsers` (the property's declared default *is* `null`, so this fired on every unfiltered render and accounted for 81 of the failures), `trim()` on a nullable pivot note and on the admin search term, and `trim($this->data['replyTo'])` in the three event notifications - where a null reply-to is the ordinary configuration, not an edge case.
  - **This document predicted the first family for the wrong phase, and the correction is instructive.** TODO 07 wrote that dynamic properties "will start emitting notices in Phase 8". Wrong twice over: the runtime has been PHP 8.3 since the Phase 5 interpreter switch, so they have been raising deprecations for a release already; and no phase would ever have shown them, because the thing swallowing them is the framework's test-mode handler, not the interpreter version. That line is corrected in place.
  - **The visibility of the eleven declarations was measured, not copied from the neighbouring lines.** Livewire 2 collects its public properties with `(new ReflectionObject($this))->getProperties()` (`InteractsWithProperties::getPublicPropertiesDefinedBySubClass()`), and a dynamic property **is** public by that measure - so those four components were already putting the values into the payload and the view data. Declaring them `public` preserves that exactly; `protected` would have silently removed them from both. The two non-component classes get `private`, matching the block they join. `?? ''` was chosen over a cast or a widened default for the same reason: `strlen(null)` is 0 and `trim(null)` is `''`, so the coalesce provably changes nothing, while giving `$searchTerm` a `''` default would have moved the Livewire payload and the `queryString` `except` comparison with it.
  - **The one live Laravel 10 pattern: `app/Http/Kernel.php`'s `$routeMiddleware` -> `$middlewareAliases`.** Laravel 9.19 renamed it and marked the old spelling `@deprecated` (`Foundation/Http/Kernel.php:70-72`); `syncMiddlewareToRouter()` merges both (`:464`), which is exactly why it survived two framework majors without a single symptom. Laravel 11 deletes the kernel outright, so this takes a decision out of the TODO 56 port instead of adding one to it. **The 19 aliases did not move by a character, and neither route fixture moved.**
  - **CONTROL EXPERIMENTS, as run.**
    - Removing `public $service_days` again fails all 15 `CalendarEventEditTest` cases, each naming EventEdit.php line 165 and nothing else. **The same deprecation with the trait commented out of `Tests\TestCase` reports `OK (15 tests, 29 assertions)`** - that is the whole argument for the guard in one line.
    - Reverting the `?? ''` on `Groups\ListUsers:1026` fails all 21 `GroupHierarchyLinkTest` cases, each naming line 1026.
    - Renaming the kernel property back fails both `MiddlewareAliasContractTest` cases and leaves `RouteContractSnapshotTest` and `RouteMiddlewareRegressionTest` **green** - and that green is the proof the rename is behaviour-neutral, because the framework merges the two properties before the router sees either.
    - Misspelling the `groupAdmin` alias key leaves both new cases green and errors exactly one `RouteMiddlewareRegressionTest` case. The new test's docblock records that limit rather than claiming coverage it does not have.
    - Restored after each, `git diff` empty.
  - **What the guard does not prove, written into its own docblock:** it sees what the suite executes. A deprecation on a path no test reaches stays invisible - `updateGroupFutureChanges::$parent_group` was fixed on inspection, not because the guard reported it. It is also scoped to `app_path()` on purpose: a deprecation raised inside `vendor/` still goes to Laravel's handler and is still dropped, because failing on those would make this suite hostage to whatever a `composer update` installs.
  - **Two leftovers came in with the sweep and are neither of the above.** `DispatchesJobs` left `App\Http\Controllers\Controller` - Laravel 10 removed the trait's `dispatchNow()` and dropped the trait from its own skeleton, and `$this->dispatch()` / `dispatchNow()` / `dispatchSync()` were measured to have **zero call sites** here. And the two implicit-nullable parameters in `MustVerifyNewEmail` became `?callable`; see the TODO 68 correction below.
  - **What is deliberately NOT here, all measured and all owned elsewhere:** `implements Rule` -> `ValidationRule` in the two rule classes (**TODO 42**, the next item), `getDoctrineSchemaManager()` and the 16 `->change()` calls (**TODO 57**), `Blade::component('layouts.app', 'admin-layout')` (**TODO 59**), `@livewireStyles` / `@livewireScripts` (**Phase 6**), and `protected $casts` -> the `casts()` method in 14 models, which is a Laravel 11 style change rather than a deprecation (**TODO 55/56**).
  - Files: `tests/Concerns/FailsOnApplicationDeprecations.php` (new), `tests/Feature/Middleware/MiddlewareAliasContractTest.php` (new), `tests/TestCase.php`, `app/Http/Kernel.php`, `app/Http/Controllers/Controller.php`, `app/Classes/updateGroupFutureChanges.php`, `app/Jobs/GenerateStatProcess.php`, `app/Http/Livewire/Events/EventEdit.php`, `app/Http/Livewire/Events/LastEvents.php`, `app/Http/Livewire/Groups/History.php`, `app/Http/Livewire/Groups/Statistics.php`, `app/Http/Livewire/Groups/ListUsers.php`, `app/Http/Livewire/Admin/Users/ListUsers.php`, `app/Notifications/EventCreatedNotification.php`, `app/Notifications/EventStatusChangedNotification.php`, `app/Notifications/EventUpdatedNotification.php`, `app/Support/Email/MustVerifyNewEmail.php`, `.docs/middleware.md`, `AGENTS.md`, `upgrade-guide.md` and this roadmap.
  - **Deployed hosts: nothing to do.** No package moves, no file is deleted or relocated, no `.env` key changes and there is no migration. `upgrade-guide.md` says so explicitly rather than staying silent.
  - **Everything below is the entry as it stood before delivery.**
  - Needed:
    - ~~`$dates` is already gone (TODO 29) - verify.~~ **Verified at TODO 39:** `DateCastingTest` asks by reflection whether either model still declares the property, and it is green on Laravel 10.
    - ~~`Illuminate\Database\Query\Expression` now requires `getValue(Grammar $grammar)`. Only 4 sites: `app/Helpers/helpers.php`, `app/Http/Livewire/Partials/NavBar.php`, and the `orderByRaw('name_index, email')` inside the `belongsToMany` in `app/Models/Group.php`.~~ **Corrected while delivering TODO 39, and this half of the item is empty.** `app/Helpers/helpers.php` contains **no raw SQL call at all** - its only database use is a plain `DB::table()`. The real four are `app/Http/Livewire/Partials/NavBar.php:94,95`, `app/Models/Group.php:78` and `database/migrations/2026_08_10_170000_add_unique_user_index_to_pending_user_emails.php:63`. **None of them constructs an `Expression` and none calls `getValue()`** - all four are `DB::raw()` / `*Raw()` producers the framework consumes internally - so the signature change has no call site to fix. The Laravel 10 suite is green with these untouched, which is the measurement. **A fifth producer has since joined them** (`database/migrations/2026_09_08_120000_move_two_factor_confirmation_onto_a_timestamp.php:70`, added by TODO 39.2), and it is the same shape.
  - Expected changes: small, contained edits.

- [x] **TODO 42: Modernize custom validation rules** - DONE
  - **Delivered on 2026-09-09, in three commits plus this documentation one:** the contract migration, a defect the migration exposed, and the tests. Suite: **1476 -> 1498 tests, 4105 -> 4160 assertions, green**, with no "there were issues" line. No composer change, so no `vendor/` movement and no `package:discover` step.
  - **"Expected changes: two rule classes and their call sites" is wrong on its second half: the call sites needed ZERO edits**, and `git diff --stat` carries no file under `app/Http/Livewire/`. `ValidationRuleParser:117-118` wraps a `ValidationRule` in `InvokableValidationRule`, and that wrapper itself `implements Rule, ValidatorAwareRule`, so `Validator::validateUsingCustomRule()` (`:849-893`) runs the path it always ran. Two consequences carry the item: the wrapper calls `setData()` on the invokable when it is a `DataAwareRule`, so `TimeCheck`'s data dependency survives untouched; and the failed-rule key comes from `$rule->invokable()` (`:876-878`), so it stays `App\Rules\TimeCheck` / `App\Rules\Throttle` and `assertHasErrors([... => Throttle::class])` keeps working.
  - **THE ITEM RAISED NO DEPRECATION AND COULD NOT HAVE.** `Illuminate\Contracts\Validation\Rule` carries an `@deprecated` docblock and nothing else - no runtime notice - so the guard TODO 41 installed had nothing to report here. This is preventive work, and the guard for it is therefore a test rather than a deprecation: `DeprecatedValidationContractTest` scans `app/` for the three deprecated contracts. **Its pattern must keep the `Contracts` segment**: `Illuminate\Validation\Rule`, the static builder behind `Rule::unique()`, is a different class and is legitimately imported in four files. Dropping the segment was run as a control and reports exactly those four as false positives.
  - **A THEORY THIS ENTRY WAS BUILT ON TURNED OUT TO BE FALSE, and it is instructive.** The planning read `date("H:i", $this->other_time)` with a null timestamp as a PHP 8.1 implicit-null deprecation that the guard would fail on. It is not one: `date(string $format, ?int $timestamp = null)` has been explicitly nullable since PHP 8.0, read off `ReflectionFunction` rather than off the manual. The real defect on that line is a different one, below.
  - **The one behavioural change, and its exact size.** `TimeCheck` had two failure paths that set no error string - an attribute that did not split into exactly two segments, and a missing counterpart - so `message()` resolved `trans('validation.')`, which no lang file defines, and the literal string `validation.` was attached to a field that was not the problem. Neither path fails any more: a comparison cannot decide anything without both operands, and the counterpart carries its own `required` and `date_format:H:i`, which report the real defect against the field that has it. Measured against the pre-change class across **eleven** inputs, eight are byte-identical and three change - missing, null and non-string counterpart. **In all three `fails()` stays `true`**, because the counterpart's own rules still fire, so nothing that used to be blocked now saves.
  - **HOW REACHABLE THAT DEFECT WAS, measured rather than assumed - and the answer reframes the whole item.** Not through the form. `UpdateGroupForm::render()` (`:559-580`) regenerates each service day's start and end option lists from the counterpart on **every** request and rewrites any value that has fallen out of its list; Livewire runs `render()` after every property update, so a reversed range cannot be assembled through the component at all - the second field is corrected before anything is submitted. **`TimeCheck`'s rejection branch is a server-side backstop against a forged payload, not the guard a user meets.** It also makes the update *order* significant, which is why the new feature test widens the end of the day before setting the later start: on an 08:00-16:00 template, setting the start to 18:00 first clamps it straight back to 00:00. This is asserted, not just recorded, because a future change to the clamp would silently promote `TimeCheck` to the only guard.
  - **The two findings that are NOT this item's to fix are TODO 42.1, below.** The three `days.*` rules on `UpdateGroupForm`'s first validator have never run, and half of the day-time error keys do not match what the view looks for.
  - **The rules also lost state that nothing had noticed.** A single `TimeCheck` instance validates every row of the wildcard, so `$error_str` and `$other_time` were properties shared across rows. It was never observable, because `validateUsingCustomRule()` reads `message()` immediately after `passes()` - but the message is now built inside `validate()`, which makes the sharing unrepresentable rather than merely unobserved. The test that covers it says so in its docblock instead of claiming to have caught a live bug.
  - **`->translate()` IS LOAD-BEARING, and only a test can say so.** `$fail('some.lang.key')` puts the **raw key** into the error bag - `PotentiallyTranslatedString::__toString()` returns the string it was given unless `translate()` was called - so a rule that omits it renders `group.messages.limit` to the user with nothing reporting a problem. Both rules call it, and both have an assertion on the resolved sentence rather than on the mere existence of an error. It is also the better spelling on its own terms: it resolves through `$this->validator->getTranslator()` rather than the global container.
  - **`TimeCheck` had no test at all, and `Throttle` had no test of its rule.** The component tests that reach `TimeCheck` all submit 08:00-10:00, so exactly one branch of it ever ran; no midnight case existed anywhere in the suite, and nothing looked at a message. `GroupMessagesTest` asserted that a fourth message is rejected, but not by which rule or with what text. **The suite runs with `APP_LANG=hu`** (`.env.testing`), so the new assertions quote the Hungarian sentences.
  - **CONTROL EXPERIMENTS, as run.**
    - Reverting `Throttle` to the pre-migration class fails **exactly two** tests - the guard, and the contract assertion in `ThrottleTest`. The budget, the message, the `failed()` key, the lockout, the per-key budgets and all 20 `GroupMessagesTest` cases stay **green**. That green is the whole argument for the guard: the migration is behaviour-neutral, so nothing else would ever notice it being undone.
    - Removing `->translate()` from `Throttle` fails two tests, both reporting `group.messages.limit` as the message.
    - Removing `->translate([...])` from `TimeCheck`'s `before` branch fails three `TimeCheckTest` cases, each showing `validation.before`.
    - Re-introducing the old `validation.` failure branch fails **exactly one** test, showing `'3.start_time' => ['validation.']`. That is the behavioural delta, isolated to a single case.
    - Incrementing the throttle counter before the budget check instead of after it fails four tests. The counter is spent on the passing branch only, so that a blocked user cannot extend their own lockout by retrying.
    - Dropping `Contracts` from the guard's pattern reports `app/Actions/Fortify/CreateNewUser.php:9`, `app/Actions/Fortify/UpdateUserProfileInformation.php:8` and both `ListUsers` components. Run once, not kept.
    - Restored after each, `git diff` empty.
    - **Not run, recorded so the next reader recognises it:** removing `use Closure;` from either rule resolves the parameter to `App\Rules\Closure` and fatals at autoload, so the suite does not start at all.
  - **What the guard cannot see, in its own docblock:** it reads source text, so a class that inherits one of the contracts from a parent or picks it up through a trait goes unnoticed. Reflection would catch that at the price of autoloading every class under `app/`; with `app/Rules` holding two files and nothing else in `app/` implementing a validation contract, the text scan is the cheaper instrument for the same result.
  - Files: `app/Rules/Throttle.php`, `app/Rules/TimeCheck.php`, `tests/Unit/Rules/TimeCheckTest.php` (new), `tests/Unit/Rules/ThrottleTest.php` (new), `tests/Unit/Rules/DeprecatedValidationContractTest.php` (new), `tests/Feature/Groups/GroupDayTimeRangeTest.php` (new), `tests/Feature/Livewire/GroupMessagesTest.php`, `.docs/validation.md` (new), `AGENTS.md`, `upgrade-guide.md` and this roadmap. **Neither call site moved**, and neither did any lang file.
  - **Deployed hosts: nothing to do.** No package moves, no file is deleted or relocated, no `.env` key changes and there is no migration. The one operator-visible change is a message that stops appearing, on a request the form cannot produce.
  - **Everything below is the entry as it stood before delivery.**
  - Needed:
    - `app/Rules/Throttle.php` and `app/Rules/TimeCheck.php` implement the deprecated `Illuminate\Contracts\Validation\Rule` interface. Move to `ValidationRule`. It still works through Laravel 12 but is on the removal path - do it while the test suite is green.
  - Expected changes: two rule classes and their call sites.

- [ ] **TODO 42.1: Make the service-day time validation visible**
  - **Both findings are TODO 42's, measured while delivering it, and neither is a regression** - they are how `UpdateGroupForm` has always behaved. They are here rather than in TODO 42 because fixing either changes what a live form shows, and because they are entangled with each other and with the midnight semantics.
  - Needed:
    - **`UpdateGroupForm:249-251` declares three `days.*` rules that compile to nothing.** `mount()` sets `$this->state = $group->toArray()` (`:88`) before it first touches `$group->days` (`:96`), `Group` declares no `$with`, and `toArray()` serialises only loaded relations - so `$this->state` has no `days` key. Laravel expands a wildcard rule whose root is absent into **zero** rules; reproduced against a bootstrapped container, where `getRules()` returns the single key `name`. So `days.*.start_time`, `days.*.end_time` and `days.*.day_number` have never run, and the second validator's `TimeCheck` is the only live guard on these times.
    - **Do not simply "repair" those three rules.** `before_or_equal:days.*.end_time` reads `00:00` as the start of the day and would reject the `18:00 - 00:00` template that `TimeCheck` exists to allow - `GroupDayTimeRangeTest::test_a_service_day_may_end_at_midnight` is the tripwire. Either drop the dead rules and keep `TimeCheck` as the single guard, or teach the first validator the midnight exception; do not leave both.
    - **Half of the day-time error keys do not match the view.** The second validator is handed `$this->days` as its whole data set, so its keys are `3.start_time`, not `days.3.start_time`. `update-group-form.blade.php:522-529` uses the bare key and **does** render the message; the `is-invalid` class at `:494` and `:512` looks for the prefixed key and never fires, and the `@error('{{$day}}.end_time')` at `:518` passes uninterpolated Blade as a PHP argument and is dead.
    - **A rough edge in the clamp, pinned by `GroupDayTimeRangeTest` rather than fixed.** Setting a start that falls outside the current day pulls *both* fields: `render()` rebuilds the end options from the start value it was handed, so the stored end falls out of its list too, and the pair that survives is `00:00 - 00:00`, the "no service" template. A user who mis-clicks a start time can therefore empty the day without being told.
  - Expected changes: `app/Http/Livewire/Groups/UpdateGroupForm.php`, `resources/views/livewire/groups/update-group-form.blade.php`, and the assertions in `tests/Feature/Groups/GroupDayTimeRangeTest.php` that currently pin the present behaviour.

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
    - Port `app/Http/Kernel.php`: the global stack, the `web` group (including `AuthenticateSession`, `SetLocale`, `SetUserLastActivity`, `HttpsProtocol`), the `api` group, and the 6 custom aliases (`groupAdmin`, `groupMember`, `profileFull`, `setGuestLanguage`, `checkRecaptcha`, plus the `guest` override) into `withMiddleware()`. **The property holding them is `$middlewareAliases` since TODO 41** - it was still the Laravel-9-deprecated `$routeMiddleware` when this line was written, and `MiddlewareAliasContractTest` asserts the router's alias map has no source other than that declaration, which is a useful thing to keep pointing at `withMiddleware()->alias()` after the port.
    - Port `app/Console/Kernel.php` scheduling into `routes/console.php` or `withSchedule()` - TODO 06 already extracted the closures into commands, which makes this straightforward.
    - Port `app/Exceptions/Handler.php` into `withExceptions()`: both heavyweight `renderable` closures must survive (the `MissingAppKeyException` handler that copies `.env.example` and runs `key:generate`, and the `QueryException` handler that redirects to setup). Note `$dontFlash` semantics shifted.
    - Convert the manual provider array in `config/app.php` to `bootstrap/providers.php`.
  - Expected changes: `bootstrap/app.php`, `bootstrap/providers.php`, deleted kernels and handler, `config/app.php` slimmed.
  - The route contract snapshot test is the primary verification that the middleware stacks came across intact.

- [ ] **TODO 57: Remove `doctrine/dbal` and fix the schema-manager call**
  - Needed:
    - Remove `doctrine/dbal` from `composer.json`. **TODO 32 did NOT squash** - see the correction in that entry. The 16 `->change()` calls are still in the migration path, but Laravel 11 needs no package for them, and `2026_09_06_120000_pin_the_changed_column_definitions` restates the eight affected columns afterwards so the native semantics cannot bite.
    - `app/Http/Controllers/Setup/DatabaseController.php:124` calls `->getDoctrineSchemaManager()`, which no longer exists on the connection - rewrite using `Schema::` / `getSchemaBuilder()`.
    - Re-run all migrations from scratch against `kozter_testing`.
  - Expected changes: `composer.json`, `DatabaseController.php`, verified by the TODO 12 setup-flow tests.

- [ ] **TODO 58: Execute the Laravel 11 blocker decisions**
  - Needed:
    - **The self-updater is no longer part of this item.** TODO 18 measured that the installed 1.0.2 declares only `php >=5.4.0`, so it blocks nothing; the move onto the project's own `mdylan/laraupdater` fork is done earlier, in **TODO 33.4** (Phase 3). If that has slipped, do it before touching the framework rather than here - it is Laravel-8-compatible work and does not belong in a hop. What remains for this hop is a re-check that the fork's `illuminate/support ^11.0` branch actually resolves, which is a version bump, not a decision.
    - **The translation package is no longer part of this item - it was removed in TODO 33.3 on 2026-08-10.** TODO 17 measured that its `require` block is **empty**, so it blocked nothing; the in-house editor was written and proven green on Laravel 8 in Phase 3. The `elegantly/laravel-translator` engine may still arrive later, in **TODO 66.1** (Phase 10).
    - **The GDPR package is no longer part of this item - it was removed in TODO 33.2 on 2026-08-10.** TODO 16 measured that its constraint is unbounded, so it blocked nothing; the replacement was written and proven green on Laravel 8 in Phase 3, which is what takes it out of every hop from here on.
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
    - Refresh Appendix A. At the time of writing `astrotomic/laravel-translatable` and the `spatie/*` packages all support Laravel 12 or newer. (`laravolt/avatar` was on this list until TODO 39.1 removed it.)
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
  - **`protonemedia/laravel-verify-new-email` is no longer part of this item - and no longer part of the project.** TODO 19 decided to replace it in-house; **TODO 33.5 executed that on 2026-08-10**, so the package is out of `composer.json`, out of the lock, and out of `vendor/`. Nothing to resolve here.
  - **`rakibdevs/openweather-laravel-api` is no longer part of this item either.** TODO 20 decided to replace it with a direct `Http::` client, and the replacement is done earlier, in **TODO 33.6** (Phase 3), for the same reason: the code is framework-neutral and Laravel 8 compatible. The roadmap's original "Blocks Laravel 13" was wrong, and so was TODO 20's own replacement for it: **the installed 1.9.0 blocks at no hop at all** - no framework constraint, and `php ^8.0` admits 8.1 through 8.4 (measured in TODO 22). The Laravel 13 wall belongs to v2.0.0, which the declared `^1.9` does not admit - but nothing forces anyone there, so if 33.6 slipped the package is simply still here, still broken.
  - **With both packages moved forward, this item has no blockers left.** It reduces to verification.
  - Needed:
    - Confirm that neither `protonemedia/laravel-verify-new-email` nor `rakibdevs/openweather-laravel-api` is still in `composer.json` - **both were removed in Phase 3, 33.6 and 33.5 respectively** - and that `vendor/protonemedia/` and `vendor/rakibdevs/` are gone from deployed hosts (the `release/upgrade.php` lines added by TODO 33.5 and 33.6).
  - Expected changes: verification only. Both replacements shipped.

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

- [ ] **TODO 66.2: AI translation in the editor, on `laravel/ai`**
  - **A new feature requested by the user**, and deliberately placed after the Laravel 13 hop: `laravel/ai` cannot be installed before it. TODO 66.1 already pulls the package in as a transitive dependency of `elegantly/laravel-translator`, so this item is about the **user interface and the policy around it**, not about the plumbing.
  - **Decide first which of two shapes this takes**, because they are different products:
    1. **Through `elegantly/laravel-translator`** - it ships `translator:translate` and `translator:proofread`, so the editor would drive an existing command. Cheapest, but it inherits whatever prompt and batching the package chose, and it exists only if TODO 66.1 was actually adopted.
    2. **Directly on `laravel/ai`** from `App\Support\Translation` - a few hundred lines, full control of prompt, model and batching. Independent of TODO 66.1, which matters because that item is explicitly optional.
  - Needed, whichever shape:
    - **A "translate the missing keys of this group" action** next to the missing-key counter the editor already shows. That counter is the number a translator actually works from, so it is the natural place - and per-group is the unit that keeps one request small enough to review before saving.
    - **Never write straight to disk.** Generated text lands in the editor's rows as a *proposal* that a human saves or discards. A machine translation committed unseen is indistinguishable from a human one afterwards, and a language file carries no history of its own.
    - **The source locale is the prompt input**, not the English fallback: `hu` is this project's only complete locale and the one everything else is translated from.
    - **Placeholders must pass through untouched.** Translation strings carry `:name` placeholders, `:count` for `trans_choice`, and pluralization pipes (`{0} ...|{1} ...|[2,*] ...`). A model that rewrites or localizes those produces strings that fail at runtime rather than merely read badly. Both the prompt and a post-check have to defend this - a rejected proposal is better than a broken key.
    - **Degrade cleanly when unconfigured.** No credentials must mean the action is absent, not an error. The feature ships off, the way `weather` and `gdpr.enabled` do.
    - **A cost and rate ceiling, and a visible one.** Twenty-two locales times the whole key set is a large number of requests; the editor translates what is on screen or what is missing in one group, never "everything".
  - **Authorization is the editor's gate** (`is-translator`) - plus the finding TODO 33.3 recorded: a Livewire action does not travel over the route it was rendered from, so any new action needs the component's own `Gate::authorize()` call as well, not just the route middleware.
  - **Verify before executing:** which models `laravel/ai` supports at that point, what a translation of this size costs, and whether `elegantly/laravel-translator` handles pluralization pipes at all. If it does not, option 2 is the honest answer.
  - Expected changes: `composer.json`, new code under `App\Support\Translation/`, the editor component and view extended, a `config/` entry for the switch and the model, `AdminTranslationEditorTest` extended, and `.docs/components.md`.

- [ ] **TODO 67: Walk the official Laravel 13 upgrade guide**
  - Needed:
    - Read the Laravel 13 upgrade guide at execution time and record every applicable change here. This roadmap deliberately does not enumerate them, because the guide is authoritative and moves.
    - Pay attention to anything touching: encrypted casts, queue serialization, the scheduler, validation rules, and Blade compilation - the areas where this app has the most custom surface.
  - Expected changes: a Laravel 13-specific findings list appended to this phase, plus the resulting fixes.

- [ ] **TODO 68: Validate under PHP 8.4 (forward-looking, optional)**
  - Needed:
    - PHP 8.4 is not installed locally. Laravel 13 accepts `^8.3`, so this is not blocking.
    - ~~If PHP 8.4 is the eventual production target, install it and run the suite; the implicit-nullable-parameter deprecation is the most likely source of noise across 30 models and 28 components.~~ **Measured and closed by TODO 41 (2026-09-09): there were exactly two, and both are gone.** `MustVerifyNewEmail::newEmail()` and `sendPendingEmailVerificationMail()` carried `callable $withMailable = null`; every other `= null` default in first-party code is either already `?Type` or **untyped**, and an untyped parameter is unaffected by the deprecation. The "30 models and 28 components" was an estimate from the size of the tree, not a count. Still install PHP 8.4 and run the suite when the target is decided - the deprecation guard TODO 41 added reports whatever else 8.4 raises in `app/`, which is the part this entry could not predict.
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
    - ~~**The `~1.11.2` pin is this phase's to lift.**~~ **Lifted at TODO 39.2 (2026-09-08), and it could not have waited for this phase: the pin admitted no release supporting Laravel 10, so Phase 5 would not resolve with it standing.** The schema half is done - the confirmation now lives in `two_factor_confirmed_at` - and the constraint is `>=1.19.1 <1.37.0`, whose ceiling keeps the passkey question here. **What genuinely remains for this phase is the flow half**, and TODO 39.2 measured why it is a decision rather than a repair: every package path touching the timestamp is gated on `Fortify::confirmsTwoFactorAuthentication()`, which is `false` here, so the application's own confirmation controller and `two-factor.confirm` route still own the flow end to end. Turning `'confirm' => true` on, adopting `ConfirmedTwoFactorAuthenticationController` and retiring `User::confirmTwoFactorAuth()` is one coherent change, and it is this item's.
  - Expected changes: `routes/fortify.php`, `app/Providers/FortifyServiceProvider.php`, `.docs/fortify-routes.md`.

- [ ] **TODO 70: Re-verify authorization and middleware behavior end to end**
  - Needed:
    - Regression-test `groupAdmin`, `groupMember`, `profileFull`, `setGuestLanguage`, `checkRecaptcha` after the skeleton migration.
    - Verify the gate-based `is-admin` / `is-translator` permissions and the `password.confirm` gating on the admin, group, and translator route groups. **Note the one deliberate exception from `v1-patch H`:** `admin.users.login` is POST-only and therefore sits outside the `password.confirm` group - `RequirePassword` returns through `redirect()->intended()`, which re-issues the request as a GET. `/admin/users`, the only page rendering the button, still carries the confirmation.
    - **Impersonation is now session-bound** (`App\Http\Controllers\Admin\LoginToUserController`), not signed-URL-bound. `tests/Feature/Auth/ImpersonationTest.php` is the gate; any change to session regeneration or guard behaviour in a framework hop should be checked against it.
  - Expected changes: fixes in middleware and gate interactions; the TODO 09 middleware tests are the gate.

- [ ] **TODO 71: Decide the API surface**
  - **The CORS configuration belongs to this decision, added by TODO 35.** `config/cors.php` `paths` is `['api/*', 'sanctum/csrf-cookie']`, and **`laravel/sanctum` is not in `composer.json`** - so half that list has never matched anything. The other half matches exactly one route, the stub below. `allowed_origins` is `['*']`, which on this configuration means a **static** `Access-Control-Allow-Origin: *` on every response under `api/*`, regardless of the request's `Origin` (measured in TODO 35). TODO 35 deliberately changed neither value - a package replacement that also alters policy cannot be shown to preserve behaviour - but if this item removes `routes/api.php`, the whole file becomes dead configuration and should go with it. `CorsHeadersTest` asserts the current values, so a change reports itself as a configuration change rather than as several unexplained middleware failures.
  - Needed:
    - `routes/api.php` is an untouched stock stub with a single `auth:api` route. Either remove the file or make it intentional. Laravel 11+ does not install API routing by default.
    - **Correction, measured in TODO 35: `config/auth.php` DOES define an `api` guard** (`:44-48`, the `token` driver with `hash => false`). This line used to hedge with "may not". The guard exists, so the stub route answers 401 rather than fataling - which is worse for the "is anyone using this?" question, not better, because a broken surface announces itself and a working-but-unintended one does not.
    - Decide `config/cors.php`'s fate in the same breath, per the note above.
  - Expected changes: `routes/api.php`, `bootstrap/app.php` routing config, `config/cors.php`, `.docs/routes.md`.

- [ ] **TODO 72: Reconsider the install-state route guard**
  - Needed:
    - `routes/web.php:104` calls `Storage::exists('installed.txt')` **at route-file parse time** to conditionally register the `setup/*` routes. Route caching bakes the install state in, and uncached requests hit the filesystem on every load.
    - Move the check to middleware or a config value.
  - Expected changes: `routes/web.php`, verified by the TODO 12 setup tests.

---

## Phase 12 - Release Readiness

- [ ] **TODO 73: Execute the full regression suite plus manual smoke checks**
  - Needed:
    - Full test run on the Laravel 13 stack, plus manual smoke checks for: login/logout, registration and email verification, profile update, group membership flows, event create/update/delete, all admin pages, the translation UI, the GDPR export, and the setup flow.
    - Verify scheduled commands and queue workers on the upgraded stack.
  - Expected changes: a release-candidate report with a pass/fail matrix in `upgrade-notes/`.

- [ ] **TODO 73.1: Close the advisory ledger**
  - **This is the roadmap's final `composer audit`, and the only one outside a framework hop.** The rule and its reasoning are in *Execution Environment*: between hops the lock does not move, so an audit run inside a non-hop item measures the upstream advisory database rather than the work, and a count recorded there drifts on its own. Everything accumulated across the path is settled here instead, once, on the lock that actually ships.
  - Needed:
    - Run `composer audit` on the final Laravel 13 lock and record the result: advisories, affected packages, and abandoned packages.
    - **Empty the `config.policy.advisories.ignore-id` block, or justify every entry that survives.** It exists because Composer 2.10 blocks advisory-affected versions during resolution, and it was filled with Laravel 8 advisories that had no fixed 8.x release (TODO 24, re-evaluated at TODO 34). Those reasons expire with Laravel 8. **An entry that outlives its framework is an unexplained ignore**, and shipping 2.0.0 with one is the failure mode this item exists to prevent - so each survivor needs a fixed-version check and a written reason, not a carry-forward.
    - Cross-check the survivors against the *Package Compatibility Matrix* in Appendix A: an advisory with no fix is a different decision from an advisory whose fix needs a package this project has already decided not to take.
    - Re-run after the final `composer update`, not before it, so the recorded result describes the lock in the release archive.
  - **Where the result goes:** the release-candidate report TODO 73 produces, and a line in `upgrade-guide.md` if any advisory survives - an operator taking a host to 2.0.0 is entitled to know what is knowingly unfixed. Nothing is written down as an advisory count in a non-hop entry, per the rule above.
  - Expected changes: an audit result in the release-candidate report, a `composer.json` policy block that is either empty or explained, and possibly one `upgrade-guide.md` line.

- [ ] **TODO 74: Bring documentation back in sync**
  - Needed:
    - Update `.docs/models.md`, `.docs/routes.md`, `.docs/jobs.md`, `.docs/fortify-routes.md`, `.docs/components.md`, `.docs/commands.md`, `.docs/notifications.md`, `.docs/observers.md`, `.docs/middleware.md`.
    - Update `AGENTS.md`: the stack snapshot still says "Laravel 8", and the note about Artisan failing on newer PHP runtimes no longer applies.
  - Expected changes: documentation matching final Laravel 13 behavior.

- [ ] **TODO 75: Prepare the rollback and deployment checklist**
  - **TODO 35.1 used to block this item outright and no longer does** (closed 2026-08-11). The committed `vendor/` tree is complete, `release/build-update.php` refuses to build while it is not, and a `--dry-run` against `v1` now ships 6063 files instead of 5048. Two things from that item still bear on this one: the archive grew to ~36 MB uncompressed, and the **deployed** 1.x hosts still carry packages no update ever shipped - which only Branch B's full `vendor/` replacement can clear.
  - **This is where `upgrade-guide.md` is finished and marked complete.** From TODO 34 onwards every hop appends to it (runtime floor, orphaned `vendor/` paths, moved files, `.env` changes, per-install repairs), and section 6 - "the upgrade procedure" - is deliberately left undecided until here: automated 2.0.0-specific hook, or a manual full `vendor/` replacement. Remove the INCOMPLETE banner only when a real host has been taken through the chosen procedure end to end.
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
| `laravel/framework` | 8.83.1 (**10.50.3 since TODO 39**) | `^8.12` (**`^10.0`**) | - | v13.24.0 (`php ^8.3`) | Target `^13.0`, one major per phase from Phase 4. Phases 4 and 5 are done |
| `laravelcollective/html` | 6.3.0 | `^6.2` | L10 | - | **Remove** (TODO 23) - zero `Form::` / `Html::` usage, provider already commented out |
| ~~`fideloper/proxy`~~ | 4.4.1 | `^4.4` | L10 | - | **REMOVED in TODO 35** (2026-08-11). `App\Http\Middleware\TrustProxies` now extends the framework class. The "fails at L10" reading held - 4.4.2 declares `illuminate/contracts ...\|^9.0` - but nothing forced it at Phase 4; it went because it was dead weight, not because it blocked |
| ~~`fruitcake/laravel-cors`~~ | 2.1.0 | `^2.0` | L10 | - | **REMOVED in TODO 35** (2026-08-11), taking `asm89/stack-cors` with it. It was the last **abandoned** package in the tree. Do not confuse it with `fruitcake/php-cors`, which the framework itself requires and which **stays** |
| `facade/ignition` (dev) | 2.17.4 | `^2.5` | **L9** | - | **Replace in Phase 4** with `spatie/laravel-ignition`, then absorbed into the framework at Laravel 11. The earliest failure in the whole table |
| `doctrine/dbal` | 3.3.2 | `^3.1` | never (no framework constraint) | - | **Remove in Phase 8** - Laravel 11 reimplemented `change()` natively |
| ~~`dialect/laravel-gdpr-compliance`~~ | 1.4.7 (exact pin) | - | never | - | **REMOVED in TODO 33.2** (2026-08-10), executing the TODO 16 decision. The two traits live in `app/Support/Gdpr/`, the form request in `app/Http/Requests/`, the one surviving route in `routes/web.php`; the consent half was dropped. `illuminate/support >=5.5` was unbounded, so Composer never failed on it - the earlier "Blocks Phase 8" reading was wrong |
| ~~`joedixon/laravel-translation`~~ | 1.1.2 | - | never | - | **REMOVED in TODO 33.3** (2026-08-10), executing the TODO 17 decision. The editor is `App\Http\Livewire\Admin\Translation` + `App\Support\Translation\LangFiles`; `elegantly/laravel-translator` may still arrive as the engine in **Phase 10, TODO 66.1**. Its `require` block was literally `{}`, so it never blocked anything |
| `mdylan/laraupdater` (was `pcinaglia/laraupdater` 1.0.2) | v2.0.0, own fork | `^2.0` | never | already declares `^13.0` | **Decided (TODO 18): keep the self-updater, move it onto the project's own fork.** Executed in **Phase 3, TODO 33.4**. The old pin blocked nothing either - 1.0.2 required only `php >=5.4.0` |
| ~~`protonemedia/laravel-verify-new-email`~~ | 1.6.0 | - | never | - | **REMOVED in TODO 33.5** (2026-08-10), executing the TODO 19 decision. The trait is `App\Support\Email\MustVerifyNewEmail`, the model `App\Models\PendingUserEmail`, the route app-owned in `routes/web.php`, the two Mailables in `App\Mail`. It was the only package in the table that failed a hop (**L10**, `illuminate/support ^8.67||^9.0`) *and* had no L13 line - the one place the Phase 2 preamble's "read both ends of the constraint" rule cut the other way |
| `rakibdevs/openweather-laravel-api` | 1.9.0 | `^1.9` | **never** | - | **Decided (TODO 20): replace with a direct `Http::` client and remove.** Executed in **Phase 3, TODO 33.6**, feature finished in 33.7. **Corrected by TODO 22:** the roadmap said this blocks at Phase 5 on PHP; it does not - `^8.0` is `>=8.0 <9.0` and admits 8.1-8.4. No framework constraint either, so **nothing forces it at any hop**. Replaced because the feature does not work, not because it blocks |
| `eusonlito/laravel-packer` | 2.2.6 | `^2.2` | never | - | **REMOVED on 2026-08-09 (TODO 33.8), replaced with a `pwbs_asset()` helper.** TODO 54 is now verification only. Requires only `php >=5.5` and `imagecow/imagecow ^2.4`. Removed for runtime reasons: it writes into the web root during a request (which left `public/storage` a real directory instead of a symlink), corrupts `data:` URIs while rewriting CSS, and never minifies. `imagecow` leaves with it |
| `livewire/livewire` | 2.10.4 (**2.12.8 installed**) | `^2.10.4` | ~~L10~~ **never** | v3 and v4 both cover L10-L13 | **Target v3** in Phase 6; v4 is optional (Appendix B). **Correction (TODO 39.2):** the "fails at L10" reading was measured on 2.10.4 and the `v1-patch H` refresh moved the lock to **2.12.8**, which declares `illuminate/support ^7.0|^8.0|^9.0|^10.0`. Livewire 2 does **not** block the Laravel 10 hop, so Phase 6 stays a standalone release by choice rather than by force |
| ~~`laravolt/avatar`~~ | 4.1.7 | `^4.1` | **L10** | - | **REMOVED in TODO 39.1** (2026-09-07), taking `intervention/image` with it. The measurement held to the end - `^4.1` admitted no L10-capable release, and it was the Laravel 10 hop's only genuine resolution blocker - but the verdict "migrate to 6.5.1" was answered by deleting the dependency: `App\Support\Avatar\InitialsAvatar` builds the same avatar as an SVG data URI. It was also the only package in the table that was **broken in production while resolving cleanly**, drawing with GD's bitmap font because the fonts its config named were never published |
| `laravel/tinker` | 2.7.0 | `^2.5` | L10 | **3.0.2** | **Constraint edit** `^2.5` -> `^3.0` at Phase 10. No 2.x release supports Laravel 13 - the line stops at 2.10.2 |
| `laravel/fortify` | 1.10.2 (**1.11.2 on `v1-patch`**; **1.19.1 since TODO 39.2**) | `^1.7` (**`~1.11.2` on `v1-patch`**; **`>=1.19.1 <1.37.0` since TODO 39.2**) | L10 | 1.36.2 | **Constraint edit, and it was this table's one self-contradiction.** The "Installed fails at L10" reading was right and the verdict below it was not: this cell used to end *"Widening `~1.11.2` is a Phase 11 decision, not a hop step"*, which cannot both be true - a pin admitting no L10-capable release **is** what stops the Phase 5 resolution. **Lifted in TODO 39.2 (2026-09-08), on Laravel 9**, to `>=1.19.1 <1.37.0`: the floor is the L10 requirement, the ceiling the passkey boundary (1.37.0 adds `laravel/passkeys` and raises its floor to `illuminate ^11` / `php ^8.2`). Every later floor in the ladder sits inside that range, so no further edit is needed before Phase 11. The vendored route file is still the real work (TODO 69) |
| `astrotomic/laravel-translatable` | 11.10.0 | `^11.9` | L10 | 11.17.0 | Lock bump. 11.17.0 drops `illuminate ^8`, so it cannot be bumped before Phase 4 |
| `spatie/laravel-activitylog` | 4.4.0 | `^4.0.0` | L10 | 4.12.3 | Lock bump; **stay on 4.x** - 5.0.0 requires `php ^8.4`, above this roadmap's target. `User` already uses the modern `LogOptions` API |
| `spatie/laravel-cookie-consent` | 3.2.0 | `^3.1` | L10 | 3.5.0 | Lock bump. 3.5.0 needs `illuminate ^11` / `php ^8.2`, so the intermediate hops need the ladder below |
| `spatie/laravel-failed-job-monitor` | 4.1.1 | `^4.1` | L10 | 4.5.0 | Lock bump; 4.5.0 covers Laravel 7 through 13 in one release |
| `spatie/calendar-links` | 1.7.1 | `^1.6` | **never** | n/a | **Declares no framework constraint in any version**, so it never blocks. 1.11.1 is the last 1.x and is admitted today. The 2.x line (`php ^8.3`) is an optional cleanup outside this roadmap |
| `petercoles/multilingual-country-list` | 1.2.12 | `^1.2` | **L12** | 1.2.14 | Lock bump, and **maintained** - 1.2.14 (2026-04-11) added `~13`. The earlier "verify maintenance status at Phase 10" is answered |
| `guzzlehttp/guzzle` | 7.4.1 | `^7.0.1` | never | 7.15.3 | **Stay on `^7`.** 8.0.2 exists, but `laravel/framework` v13 declares `guzzlehttp/guzzle: ^7.8.2` in `require`, so bumping the major is a resolution failure |
| `phpunit/phpunit` (dev) | 9.5.14 (**10.5.64 since TODO 40**) | `^9.3.3` (**`^10.0`**) | - | 12.5.x | ~~9.5 -> **10 (Phase 5)**~~ done -> 11 (Phase 9) -> 12 (Phase 10). **12 is the ceiling** at PHP 8.3 - PHPUnit 13 requires `php >=8.4.1` |
| `nunomaduro/collision` (dev) | 5.11.0 (**7.12.0 since TODO 39**) | `^5.0` (**`^7.0`**) | - | 8.9.5 | **Constraint edits**, in lockstep with the framework: ~~`^6` (Phase 4) -> `^7` (Phase 5)~~ both done -> `^8` (Phase 8). Declares no `illuminate/*`, so Composer will not catch a mismatch |
| `barryvdh/laravel-debugbar` (dev) | 3.6.7 | `^3.6` | L10 | **4.0.10+** | **Constraint edit** `^3.6` -> `^4.0` at Phase 10 - the 3.x line stops at 3.15.4 / Laravel 12. Also **stop hard-registering it in `config/app.php`** (TODO 25) |
| `laravel/sail` (dev) | 1.13.4 | `^1.0.1` | L10 | 1.65.0 | Lock bump |
| `mockery/mockery`, `fakerphp/faker` (dev) | 1.5.0 / 1.19.0 | `^1.4.2` / `^1.9.1` | - | 1.6.12 / 1.24.1 | Lock bumps only; neither declares a framework constraint |

### The per-hop version ladder

For every package that survives the upgrade, the **lowest** release admitting each Laravel major - so a hop's item can read a number instead of estimating one. Measured the same way as the table above.

| Package | L9 (Ph. 4) | L10 (Ph. 5) | L11 (Ph. 8) | L12 (Ph. 9) | L13 (Ph. 10) |
| --- | --- | --- | --- | --- | --- |
| `astrotomic/laravel-translatable` | 11.11.0 | 11.12.1 | 11.15.1 | 11.16.1 | 11.17.0 |
| ~~`laravolt/avatar`~~ | 4.1.7 | 5.0.0 | 5.1.0 | **6.1.2** | 6.4.0 | *(removed in TODO 39.1 - the row is kept because it is the measurement the removal decision was made against)*
| `spatie/laravel-activitylog` | 4.7.1 | 4.7.3 | 4.10.0 | 4.11.0 | 4.12.3 |
| `spatie/laravel-cookie-consent` | 3.2.3 | 3.2.4 | 3.3.2 | 3.3.3 | 3.5.0 |
| `spatie/laravel-failed-job-monitor` | 4.1.0 | 4.2.1 | 4.3.2 | 4.3.5 | 4.5.0 |
| `petercoles/multilingual-country-list` | 1.2.9 | 1.2.11 | 1.2.12 | 1.2.13 | 1.2.14 |
| `laravel/fortify` | 1.10.1 | 1.19.1 | 1.21.0 | 1.31.3 | 1.36.2 |
| `laravel/tinker` | 2.7.3 | 2.8.2 | 2.10.0 | 2.10.2 | **3.0.0** |
| `laravel/sail` | 1.3.1 | 1.19.0 | 1.27.2 | 1.52.0 | 1.65.0 |
| `barryvdh/laravel-debugbar` (dev) | 3.6.8 | 3.8.0 | 3.10.2 | 3.15.4 | **4.0.10** |

~~Two cells are boundaries rather than numbers. `laravolt/avatar` crosses the Intervention Image break between L11 and L12 - which is why **TODO 39.1 jumps straight to 6.5.1 at Phase 5** and does the break once, early, instead of meeting it in Phase 9.~~ **`laravolt/avatar` left the tree in TODO 39.1**, so that boundary is no longer on the path; its row stays above as the measurement, not as a plan. `laravel/tinker` and `barryvdh/laravel-debugbar` cross a major between L12 and L13, and neither is admitted by the constraint declared today - those two are still real.

**A note the ladder itself cannot express, and TODO 39.1 is the case for it.** Every column here answers "which release admits this Laravel major", which quietly assumes the answer is a version. For one package it was not: the cheapest release turned out to be no release. The ladder is the right tool for deciding *how far* to jump, and the wrong one for deciding *whether* to - that question belongs with the call sites, and this project has answered it "remove" six times now (TODO 33.2 through 33.6, 33.8, and 39.1).

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
- **`release/upgrade.php` is frozen from TODO 34 onwards; deployed-host cleanup goes into `upgrade-guide.md`.** The hook removes one orphaned path per line, which worked while a Phase 3 release dropped one or two vendor trees. The framework hops replace essentially the whole `vendor/` tree, so listing every orphan hop by hop in one `main()` would be neither reviewable nor exercised until the single release that needs it. Each hop therefore appends its orphaned paths, moved files, `.env` changes and per-install repairs to the new **`upgrade-guide.md`**, and the 2.0.0 release decides in one place what to do with the accumulated list. This reverses, for Phase 4 onwards only, the "extend `release/upgrade.php`" instruction in the TODO 33.5 and 33.8 entries; the hook stays in the tree and keeps serving the 1.x line.
- **2.0.0 cannot arrive through the auto-updater, by design.** `App\Support\Updates\UpdateBranch` derives a ceiling from `version.txt`'s major and `EnsureUpdateWithinBranch` enforces it on `/updater.update` itself, so a 1.x install answers 403 to a 2.x release and renders a manual-update card. The 1.x -> 2.0.0 step is therefore a manual operation; `upgrade-guide.md` section 6 is where its procedure is being written, and TODO 75 closes it.
