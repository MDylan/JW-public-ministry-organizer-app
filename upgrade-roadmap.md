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
| Current baseline | 8.83.1 | `^8.0` (pinned to 8.0.9) | `php81` | 9.5 | 5.x | 24.18 (Mix broken) |
| Phase 4 | 9.x | `^8.0.2` | `php81` (see note) | 9.5 | 6.x | 24.18 (Mix broken) |
| Phase 5 | 10.x | `^8.1` | `php81`, then switch to `php` (8.3) | 10.x | 7.x | 24.18 (Mix broken) |
| Phase 7 | 10.x | `^8.1` | `php` (8.3) | 10.x | 7.x | 24.18 (Vite OK) |
| Phase 8 | 11.x | `^8.2` | `php` (8.3) OK | 10.x/11.x | 8.x | 24.18 |
| Phase 9 | 12.x | `^8.2` | `php` (8.3) OK | 11.x | 8.x | 24.18 |
| Phase 10 | 13.x | `^8.3` | `php` (8.3) OK | 12.x | current major | 24.18 |

- **Both required runtimes are already installed.** `^8.2` is a caret constraint, so PHP 8.3 satisfies Laravel 11 and 12; no PHP 8.2 install is needed.
- **Note on Phase 4:** although Laravel 9 declares `^8.0.2`, its officially tested ceiling is PHP 8.2, and the current `nesbot/carbon` line already fatals on PHP 8.3. Stay on `php81` for Laravel 9 and only move to PHP 8.3 once Laravel 10 is in place (Laravel 10.x supports PHP 8.1-8.3).
- PHP 8.4 is not installed. Laravel 13 accepts `^8.3`, so 8.4 is forward-looking only (TODO 68).
- Do not skip intermediate majors. Each hop gets its own composer resolution, its own test run, and its own PR.

## Project-Specific Baseline (Current State, verified)

### Framework and dependencies

- Laravel `8.83.1` on branch `v2-dev`; `composer.json` requires `laravel/framework: ^8.12`.
- PHP constraint is `^8.0` with `config.platform.php = 8.0.9`, which artificially holds back every dependency resolution.
- `minimum-stability: dev` with `prefer-stable: true` - risks pulling unstable packages during the upgrade.
- `composer.lock` was resolved in early 2022 and is roughly four years stale.
- `config/app.php` hard-registers the dev-only `Barryvdh\Debugbar\ServiceProvider`, which breaks `composer install --no-dev`. It also registers `Eusonlito\LaravelPacker\PackerServiceProvider` as a plain string instead of `::class`.

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
- `resources/lang/` must move to `lang/` in Laravel 9; `joedixon/laravel-translation` hardcodes assumptions about that path.
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

- Build uses Laravel Mix (`webpack.mix.js`, `laravel-mix ^6.0.6`). No `vite.config.js`, no `package-lock.json`, no `node_modules/`.
- Local Node is **24.18.0**; Mix 6 / webpack 5 will not build on it. **Vite migration is mandatory, not optional.**
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
  - Practical note for every later phase: `vendor/bin/phpunit` is a POSIX shell wrapper - invoking it through `php81` merely prints the script. Use `php81 vendor/phpunit/phpunit/phpunit`. `php81` itself resolves via `C:\scripts\php81.bat` and is reachable **only from PowerShell**, not from a POSIX shell.

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
    2. **Lowering `date_max_publishers` below the number of existing events on a slot makes the day unopenable.** `getInfo()` allocates `max_publishers + events.max_columns` cells per slot (`:241-243`) and consumes one per event via `min(array_keys(...))` (`:280`); once the cells run out, PHP 8 throws a `ValueError` from `min([])`. Reachable in production: the events are created under a high maximum, then an admin lowers it (or a scheduled future group change overwrites it). Pinned by `EventCapacityTest::test_lowering_the_maximum_below_the_existing_event_count_breaks_the_day_table`.
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
  - **The duplicate set is guarded at source level, not just at runtime.** `sourceRouteNameCounts()` tokenizes `routes/web.php`, `routes/fortify.php` and `routes/api.php` with `token_get_all()` and counts `->name('...')` calls. A tokenizer rather than a regex, because the commented-out Fortify `verification.notice` (`fortify.php:80-85`) must **not** count - comments are their own tokens, so it falls out for free. `test_the_route_files_contain_exactly_the_known_duplicate_names` pins the result to exactly `['password.confirm' => 2, 'verification.verify' => 2]`, so TODO 26 has to edit it, and a *new* duplicate would also fail it.
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

**Check the constraint before assuming a package blocks anything.** A package with an *unbounded* framework constraint never fails a Composer resolution - it installs on Laravel 13 and would only misbehave at runtime. When that is the case and the replacement is framework-neutral, the execution should be pulled **forward** into Phase 3 rather than left in a hop, so the hop carries one dependency less. TODO 16 is the worked example, executed as TODO 33.2.

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

- [ ] **TODO 17: Assess and decide - `joedixon/laravel-translation`**
  - Context: installed v1.1.2; latest stable v2.2.0 supports at most Laravel 10 (a `3.x-dev` branch exists). Blocks Laravel 11.
  - Consumed surface: `app/Http/Livewire/Admin/Translation.php`, `config/translation.php`, and hardcoded assumptions about the `resources/lang` path (which moves in Phase 4).
  - Expected changes: decision recorded, including whether `3.x-dev` is viable or a self-hosted translation UI is cheaper.

- [ ] **TODO 18: Assess and decide - `pcinaglia/laraupdater`**
  - Context: pinned at exactly `1.0.2`; latest `1.0.3.4` supports at most Laravel 10. Blocks Laravel 11. Registered as a provider in `config/app.php`, with `config/laraupdater.php`.
  - Consider whether a self-updater is still needed at all if deployment moves to git/CI.
  - Expected changes: decision recorded.

- [ ] **TODO 19: Assess and decide - `protonemedia/laravel-verify-new-email`**
  - Context: installed v1.6.0; latest v1.13.0 supports at most Laravel 12. Blocks Laravel 13.
  - Consumed surface: the `MustVerifyNewEmail` trait on `User`, `config/verify-new-email.php`, and an interaction with the duplicated `verification.verify` route resolved in TODO 26.
  - Expected changes: decision recorded (wait for upstream Laravel 13 support, fork, or reimplement).

- [ ] **TODO 20: Assess and decide - `rakibdevs/openweather-laravel-api`**
  - Context: installed v1.9.0; latest v2.0.0 supports at most Laravel 12. Blocks Laravel 13. Small consumed surface (`WeatherCity`, `config/openweather.php`) - a direct HTTP-client call is a realistic replacement.
  - Expected changes: decision recorded.

- [ ] **TODO 21: Assess and decide - `eusonlito/laravel-packer`**
  - Context: v3.0.1 exists with no Laravel constraint at all. Registered as a **string literal** provider in `config/app.php` plus a `Packer` facade alias.
  - Likely made redundant by the Vite migration (Phase 7) - assess after that decision, not before.
  - Expected changes: decision recorded, most likely removal.

- [ ] **TODO 22: Confirm the remaining dependencies need only version bumps**
  - Needed:
    - Verify at execution time that `astrotomic/laravel-translatable`, `laravolt/avatar`, `spatie/laravel-activitylog`, `spatie/laravel-cookie-consent`, `spatie/laravel-failed-job-monitor`, `spatie/calendar-links`, `petercoles/multilingual-country-list`, `laravel/fortify`, `laravel/tinker`, and `guzzlehttp/guzzle` still declare Laravel 13 support.
    - Appendix A records the state at the time of writing; re-check before each hop, since upstream support moves.
  - Expected changes: refreshed Appendix A.

---

## Phase 3 - Laravel 8 Cleanup

Everything in this phase is Laravel 8 compatible and shortens every later phase. Nothing here changes the framework version.

- [ ] **TODO 23: Remove `laravelcollective/html`**
  - Needed:
    - Verified: zero `Form::` or `Html::` usages anywhere, and the provider is already commented out at `config/app.php:167`. Remove the requirement outright.
  - Expected changes: `composer.json` / `composer.lock`. This eliminates one of the most common hard blockers for Laravel upgrades at zero cost.

- [ ] **TODO 24: Clean composer configuration**
  - Needed:
    - Remove `config.platform.php = 8.0.9`.
    - Change `minimum-stability` from `dev` to `stable`.
    - Add a `config.allow-plugins` block for the plugins actually in use (Composer 2.10 requires it).
  - Expected changes: honest dependency resolution and no hidden legacy locks.

- [ ] **TODO 25: Fix provider registration hygiene**
  - Needed:
    - Remove the hard-registered `Barryvdh\Debugbar\ServiceProvider` from `config/app.php` and rely on auto-discovery (or gate it behind an environment check); it is a `require-dev` package and currently breaks `composer install --no-dev`.
    - Replace the `'Eusonlito\LaravelPacker\PackerServiceProvider'` string literal with `::class`.
    - Review the `\Debugbar::enable()` call in `app/Providers/AppServiceProvider.php`.
  - Expected changes: `config/app.php`, `app/Providers/AppServiceProvider.php`.

- [ ] **TODO 26: Resolve duplicate route names**
  - Needed:
    - **`verification.verify`: resolved empirically in TODO 03.** Only one entry reaches the routing table - `Route::get()` overwrites by `method + domain + uri`, and **the Fortify definition wins** (`Laravel\Fortify\Http\Controllers\VerifyEmailController@__invoke`, middleware `web, Authenticate:web, ValidateSignature, ThrottleRequests:6,1`). The closure at `routes/web.php:122` is **dead code that never executes** and can be deleted with zero runtime change - confirm the Fortify middleware stack above is the intended one first.
    - `password.confirm` is defined twice in `routes/web.php` (`:128` GET, `:138` POST). **Both survive** because they differ by HTTP method, but `route('password.confirm')` resolves against the *last* registration, so URL generation points at the POST route. Confirm that is intended. Note `password.confirm` is also a middleware alias in `app/Http/Kernel.php:68`.
    - `verification.notice` at `routes/web.php:74` uses a string callable while Fortify's own is commented out at `routes/fortify.php:82-86` with the note "disabled, it's generate problem" - resolve that properly.
    - **TODO 14 built the tripwires for this; they must be rewritten in the same change set.** `RouteContractSnapshotTest::test_the_route_files_contain_exactly_the_known_duplicate_names` and `test_the_web_php_verification_verify_closure_never_reaches_the_routing_table` will both fail the moment a duplicate definition is deleted - that failure *is* the intended, reviewable diff. TODO 14 also verified by experiment that removing the `verification.verify` closure leaves the main route snapshot green, so that deletion carries **zero** runtime change. The `password.confirm` case is different: both definitions are live, so any change there is a real behaviour change and `test_both_password_confirm_definitions_survive_with_different_middleware` / `test_url_generation_resolves_password_confirm_to_the_post_definition` must be updated to state the new intent.
  - Expected changes: `routes/web.php`, `routes/fortify.php`, updated route contract fixture, updated `.docs/routes.md` and `.docs/fortify-routes.md`.

- [ ] **TODO 27: Replace the removed Fortify password rule**
  - Needed:
    - `app/Actions/Fortify/PasswordValidationRules.php:5` and `app/Http/Controllers/FinishRegistration.php:14` import `Laravel\Fortify\Rules\Password`, which no longer exists in modern Fortify.
    - Move to `Illuminate\Validation\Rules\Password::min(8)->mixedCase()->numbers()`, preserving the current `requireUppercase()->requireNumeric()` semantics.
    - Consumers to update: `CreateNewUser`, `ResetUserPassword`, `UpdateUserPassword`, `FinishRegistration`.
  - Expected changes: small, isolated, high-value. Existing auth tests cover the flows.

- [ ] **TODO 28: Move `env()` calls out of runtime code into config**
  - Needed:
    - 25 occurrences outside `config/`, notably `app/Http/Middleware/HttpsProtocol.php:19`, `app/Http/Middleware/CheckRecaptcha.php:20`, `app/Http/Livewire/Admin/Settings.php:77,78,81`, `app/Http/Controllers/Setup/MailController.php:67`, 7 in Blade views (`auth/login`, `auth/register`, `auth/forgot-password`, `livewire/groups/update-group-form`), and 6 in notifications.
    - **Highest priority: the mailer ones.** `->replyTo(env('MAIL_FROM_ADDRESS'))` in `EventCreatedNotification.php:68`, `EventDeletedNotification.php:66`, `EventStatusChangedNotification.php:71`, `EventUpdatedNotification.php:75`, `UserWillBeAnonymizeNotification.php:62`, and `->bcc(...)` in `UserRoleIsGroupCreatorNotification.php:45`. Symfony Mailer (Phase 4) throws on a null address; SwiftMailer did not.
    - The two middleware occurrences are now **covered and provably runtime-dependent**: `HttpsProtocolTest` and `CheckRecaptchaTest` (TODO 09) toggle `$_SERVER['USE_HTTPS']` / `$_SERVER['USE_RECAPTCHA']` mid-test and get different behaviour from the same request, which is exactly what `config:cache` would freeze. Both files carry a `test_the_flag_is_read_from_the_environment_on_every_request` case that must be rewritten once the value moves into config - treat that rewrite as part of this TODO.
    - Note a second defect in `HttpsProtocol:19` while you are there: the comparison is `env('USE_HTTPS', "false") == "true"`, i.e. against the literal string. `USE_HTTPS=1` therefore does **not** enable the redirect. Pinned by `test_a_truthy_but_non_string_true_value_does_not_enable_the_redirect`.
  - Expected changes: new/extended config keys, `env()` confined to `config/*.php`, `config:cache` becomes safe.

- [ ] **TODO 29: Replace `$dates` with `$casts`**
  - Needed:
    - `app/Models/Group.php:16` (`['deleted_at']` - already handled by `SoftDeletes`, likely just removable) and `app/Models/GroupUser.php:29` (a `Pivot` model, `['created_at','updated_at','deleted_at']`).
  - Expected changes: two model edits; removes a Laravel 10 blocker early.

- [ ] **TODO 30: Fix filesystem disk configuration**
  - Needed:
    - `config/filesystems.php` `web` disk root is the bare relative path `'public'` instead of `public_path()`. It resolves against the PHP working directory and is fragile under Flysystem 3. Used by `app/Http/Livewire/Groups/Messages.php:173,176`.
    - Add an explicit `'throw'` value to every disk, since Flysystem 3 changes `exists()`/`delete()` semantics.
    - Review the `news_files` disk (private visibility, explicit permission maps) used in 8 places.
  - Expected changes: `config/filesystems.php`, possibly the `exists()`-then-`delete()` guards in `GroupNewsDelete.php` and `NewsEdit.php`.

- [ ] **TODO 31: Fix boot-time database access and silent exception swallowing**
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

- [ ] **TODO 33: Clean up middleware naming and dead code**
  - Needed:
    - Rename `app/Http/Middleware/setUserLastActivity.php` to `SetUserLastActivity` (PSR-4 tolerates the current name locally, but case-sensitive deploy targets will not).
    - ~~Decide the fate of `RedirectIfUnansweredTerms`, which is never registered in `app/Http/Kernel.php`.~~ **Decided in TODO 16: it is deleted with the rest of the consent feature, in TODO 33.2.**
    - `app/Providers/RouteServiceProvider.php` still passes `->namespace($this->namespace)` where the property is commented out.
    - **Three naming defects found by TODO 07.2, all pinned by tests today:**
      1. **`helpers.php:68` asks for `can('is-groupCreator')` but the gate is `is-groupcreator`.** Gate names are case-sensitive, so the branch is always false and a plain `groupCreator` never receives the newsletters targeted at them - only `mainAdmin` does, through `is-admin`. A one-character fix that **changes production behaviour**, so it needs the user's go-ahead. Affects `Admin\AdminNewsletters`, `Partials\NavBar`, `Partials\SideMenu`; pinned by `AuthorizationGateTest::test_a_group_creator_never_receives_group_creator_newsletters`, which must be inverted when fixed.
      2. **`Groups\ListUsers::isNotHelper()` and `isNotEditor()` are literally identical** (`:792-798`), both allowing only `['admin','roler']`. The `helper` role is therefore not a helper by this check, and `maxRoles()`'s `member`/`helper` branches are unreachable. Decide whether `helper` was meant to have write access; pinned by `test_a_helper_cannot_open_the_user_editor_either`.
      3. **`helpers.php:23` guards on `pwbs_check_group_admins` while defining `pwbs_check_group_other_admins`** - the `function_exists()` check never matches its own function.
  - Expected changes: `app/Http/Kernel.php`, middleware files, `app/Helpers/helpers.php`, `app/Http/Livewire/Groups/ListUsers.php`, `.docs/middleware.md`.

- [ ] **TODO 33.1: Make `CheckRecaptcha` survive a Google outage**
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
    - The real exposure is address strictness: Symfony Mailer throws on invalid or empty `replyTo`/`bcc`. TODO 28 must be complete first.
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
    - Laravel 9 relocates the language directory to the project root. 13+ locale directories plus JSON files.
    - `joedixon/laravel-translation` hardcodes path assumptions - verify `app/Http/Livewire/Admin/Translation.php` and `config/translation.php` after the move. This directly feeds the TODO 17 decision.
  - Expected changes: directory move, config updates, translation UI re-tested.

---

## Phase 5 - Laravel 9 -> 10 (PHP 8.1, then switch to 8.3)

This is where the interpreter switches. Laravel 10.x supports PHP 8.1 through 8.3, so once the framework bump is green on `php81`, re-run the suite on the default `php` (8.3) and make that the working runtime for the rest of the roadmap. Update the *Execution Environment* section in the same commit.

- [ ] **TODO 39: Upgrade to Laravel 10 and align the toolchain**
  - Needed:
    - `laravel/framework` to `^10.0`, `nunomaduro/collision` to `^7.0`, PHPUnit to `^10.0`.
    - Reconcile Monolog 3 logging changes against `config/logging.php`.
  - Expected changes: composer updates plus the PHPUnit work in TODO 40.

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

Mandatory, not optional: Laravel Mix 6 / webpack 5 does not build on the locally installed Node 24.18.

- [ ] **TODO 52: Migrate the build from Laravel Mix to Vite**
  - Needed:
    - Replace `webpack.mix.js` with `vite.config.js`; add `vite` and `laravel-vite-plugin`, drop `laravel-mix`.
    - Current entrypoints are `resources/js/app.js` and `resources/css/app.css`.
    - Replace `mix()` asset helpers with the `@vite` directive in `layouts/app.blade.php` and `layouts/setup.blade.php`.
  - Expected changes: `package.json`, `vite.config.js`, layout files.

- [ ] **TODO 53: Establish a reproducible frontend build**
  - Needed:
    - Commit a `package-lock.json` (none exists today) and verify `npm ci && npm run build` on Node 24.
    - Record the Node/npm version in `upgrade-notes/`.
  - Expected changes: lockfile committed, working production build.

- [ ] **TODO 54: Execute the `eusonlito/laravel-packer` decision**
  - Needed:
    - Apply the TODO 21 decision. If removed: drop the requirement, the string-literal provider, the `Packer` facade alias, and `config/packer.php`.
  - Expected changes: `composer.json`, `config/app.php`, `config/packer.php`.

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
    - Apply the TODO 17 (`joedixon/laravel-translation`) and TODO 18 (`pcinaglia/laraupdater`) decisions. Both block here - they declare bounded Laravel constraints, so Composer stops on them.
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
  - Needed:
    - `protonemedia/laravel-verify-new-email` (latest v1.13.0 supports at most Laravel 12) and `rakibdevs/openweather-laravel-api` (latest v2.0.0 supports at most Laravel 12) both block here.
    - Apply the TODO 19 and TODO 20 decisions: wait for upstream Laravel 13 support, fork, or replace. The weather package has a small surface and is a realistic candidate for a direct HTTP-client call.
    - Re-check upstream at execution time - support may have landed since this roadmap was written.
  - Expected changes: per the recorded decisions.

- [ ] **TODO 66: Bump the remaining packages to their Laravel 13 lines**
  - Needed:
    - Verified as available: `astrotomic/laravel-translatable` (`^13.0` in its constraint), `laravolt/avatar` 7.x, `spatie/laravel-cookie-consent` 3.5+, and the other `spatie/*` packages.
    - `laravolt/avatar` 7.x requires PHP >= 8.3 and Intervention Image 4 - check the avatar generation in `app/Http/Livewire/Groups/Messages.php:173,176`, which writes to the `web` disk.
  - Expected changes: composer bumps plus avatar-generation verification.

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
    - Decide whether to keep fully custom routing or move closer to default registration. The custom `checkRecaptcha` middleware on login/register/forgot-password and the `authenticateThrough()` pipeline with `RedirectIfTwoFactorConfirmed` must be preserved either way.
    - Verify the `DisableTwoFactorAuthentication` singleton override still binds.
  - Expected changes: `routes/fortify.php`, `app/Providers/FortifyServiceProvider.php`, `.docs/fortify-routes.md`.

- [ ] **TODO 70: Re-verify authorization and middleware behavior end to end**
  - Needed:
    - Regression-test `groupAdmin`, `groupMember`, `profileFull`, `setGuestLanguage`, `checkRecaptcha` after the skeleton migration.
    - Verify the gate-based `is-admin` / `is-translator` permissions and the `password.confirm` gating on the admin, group, and translator route groups.
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

Verified against Packagist at the time of writing. **Re-check before each hop** (TODO 22).

| Package | Installed | Highest supported Laravel | Verdict |
| --- | --- | --- | --- |
| `laravel/framework` | 8.83.1 | - | Target `^13.0` (PHP `^8.3`) |
| `laravelcollective/html` | 6.3.0 | - | **Remove** - zero usage, provider already commented out (TODO 23) |
| `fideloper/proxy` | 4.4.1 | 8 | **Remove in Phase 4** - framework-native since Laravel 9 |
| `fruitcake/laravel-cors` | 2.1.0 | 8 | **Remove in Phase 4** - framework-native since Laravel 9 |
| `facade/ignition` | 2.17.4 (dev) | 8 | **Replace in Phase 4** with `spatie/laravel-ignition`, then absorbed into the framework at Laravel 11 |
| `doctrine/dbal` | 3.3.2 | - | **Remove in Phase 8** - Laravel 11 reimplemented `change()` natively |
| `dialect/laravel-gdpr-compliance` | 1.4.7 (exact pin) | unbounded `>=5.5`, last release **2020-01-06** | **Decided (TODO 16): replace in-house and remove.** Executed in **Phase 3, TODO 33.2**. **Blocks nothing** - the unbounded constraint means Composer never fails on it; the earlier "Blocks Phase 8" reading was wrong |
| `joedixon/laravel-translation` | 1.1.2 | 10 (v2.2.0; `3.x-dev` exists) | **Blocks Phase 8** - decision required (TODO 17) |
| `pcinaglia/laraupdater` | 1.0.2 (exact pin) | 10 (v1.0.3.4) | **Blocks Phase 8** - decision required (TODO 18) |
| `protonemedia/laravel-verify-new-email` | 1.6.0 | 12 (v1.13.0) | **Blocks Phase 10** - decision required (TODO 19) |
| `rakibdevs/openweather-laravel-api` | 1.9.0 | 12 (v2.0.0) | **Blocks Phase 10** - decision required (TODO 20) |
| `eusonlito/laravel-packer` | 2.2.6 | v3.0.1 declares no Laravel constraint | Likely redundant after Vite (TODO 21, TODO 54) |
| `livewire/livewire` | 2.10.4 | v3 and v4 both support Laravel 10-13 | **Target v3** in Phase 6; v4 is optional (Appendix B) |
| `laravel/fortify` | 1.10.2 | current | Bump per hop; the vendored route file is the real work (TODO 69) |
| `astrotomic/laravel-translatable` | 11.10.0 | 13 (v11.17.0 declares `^13.0`) | Version bump only |
| `laravolt/avatar` | 4.1.7 | 13 (v7.0.0, needs PHP >= 8.3, Intervention Image 4) | Version bump, verify avatar generation (TODO 66) |
| `spatie/laravel-cookie-consent` | 3.2.0 | 13 (v3.5.0) | Version bump only |
| `spatie/laravel-activitylog` | 4.4.0 | current | Version bump; `User` already uses the modern `LogOptions` API |
| `spatie/laravel-failed-job-monitor` | 4.1.1 | current | Version bump; re-verify after Phase 4 and Phase 8 |
| `spatie/calendar-links` | 1.7.1 | framework-agnostic | Version bump only |
| `petercoles/multilingual-country-list` | 1.2.12 | small/niche | Verify maintenance status at Phase 10 |
| `laravel/tinker`, `guzzlehttp/guzzle` | 2.7.0 / 7.4.1 | current | Version bump only |
| `phpunit/phpunit` | 9.5.14 (dev) | - | 9.5 -> 10 (Phase 5) -> 11 (Phase 9) -> 12 (Phase 10) |
| `nunomaduro/collision` | 5.11.0 (dev) | - | 5 -> 6 -> 7 -> 8 -> current, in lockstep with the framework |
| `barryvdh/laravel-debugbar` | 3.6.7 (dev) | current | Version bump; **stop hard-registering it in `config/app.php`** (TODO 25) |
| `laravel/sail`, `mockery/mockery`, `fakerphp/faker` | - | current | Version bumps only |

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
    - While in here, fix the `min([])` crash pinned by `EventCapacityTest::test_lowering_the_maximum_below_the_existing_event_count_breaks_the_day_table`: the cell allocation must tolerate more events on a slot than the current maximum allows.
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
