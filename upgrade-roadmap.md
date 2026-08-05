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

- 20 test files, 134 `test_*` methods, roughly 184 executed cases after data providers.
- Strong coverage: 70-route contract snapshot including middleware stacks (`tests/Feature/RouteContractSnapshotTest.php`), route/middleware regression, all 8 observers, mail contract for all 26 notifications.
- Runs against a real MySQL schema `kozter_testing` via `RefreshDatabase`; `phpunit.xml` and `.env.testing` are configured with test-safe drivers.
- **Known gaps** (addressed in Phase 1): no job `handle()` body is ever executed (all `Bus::fake()`), the ~200 lines of inline scheduler closures are only tested at registration level, 19 Livewire components are smoke-only, 5 middleware are untested, 23 of 30 models have no factory.
- **The single largest gap: the core scheduling domain has zero coverage.** Per-slot publisher capacity, the raised limit for approval-based groups, time-range overlap between events, and the cross-group "publisher busy" check are the application's central business rules and **not one of them is exercised by any test** - despite `CalendarEventEditTest` configuring the limits in its fixture and `GroupFactory::withStrictPublisherLimits()` existing but never being called. In-group role assignment (`Groups\ListUsers::saveUser()`, ~100 lines of authorization) is likewise reached only by a route-returns-200 smoke test. See TODO 07.1 and TODO 07.2.
- `phpunit.xml` uses the PHPUnit 9 schema. Two `@dataProvider` annotations use **non-static** provider methods, which PHPUnit 11 forbids.
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
- `app/Notifications/GroupPriorityMessageNotificationTest.php` is a production class with a `Test` suffix, which some PHPUnit discovery configurations will pick up.

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

- [ ] **TODO 03: Freeze a verified behavior snapshot**
  - Needed:
    - Delete `.phpunit.result.cache` and run the full suite fresh with `php81` to record a **real** pass/fail baseline. The existing cache contains defect entries for tests that no longer exist and must not be trusted.
    - Export `php81 artisan route:list --json`, the scheduler listing, `php81 artisan about`, and `composer show --tree` into `upgrade-notes/`.
    - Record the exact PHP/Composer/Node versions used to produce the snapshot.
  - Expected changes:
    - New `upgrade-notes/` folder with baseline artifacts.
    - A documented green (or known-red) starting point that every later phase compares against.

---

## Phase 1 - Test Coverage Completion

Full coverage is required **before** any framework change. Every item here is Laravel 8 compatible and is the safety net for Phases 4 through 10.

- [ ] **TODO 04: Add factories for uncovered models and make seeding usable**
  - Needed:
    - Factories for the 23 models without one (`AdminNewsletter`, `AdminNewsletterTranslation`, `AdminNewsletterRead`, `DayStat`, `EventServiceReport`, `GroupDayDisabledSlots`, `GroupFutureChange`, `GroupLiterature`, `GroupMessage`, `GroupNews`, `GroupNewsFile`, `GroupNewsTranslation`, `GroupNewsUserLogs`, `GroupPosterRead`, `GroupPosters`, `GroupSurvey`, `GroupSurveyAnswer`, `GroupSurveyStatistics`, `LogHistory`, `Settings`, `StaticPageTranslation`, `Statistics`, `WeatherCity`).
    - Replace the ad-hoc builders in `tests/Concerns/BuildsDomainFixtures.php` with factories where a factory now exists.
    - Make `database/seeders/DatabaseSeeder.php` runnable; `StaticPagesSetupSeeder::run($user_id)` takes an argument and cannot be invoked via `db:seed` - fix or document.
  - Expected changes:
    - New files under `database/factories/`, simplified fixture concern.

- [ ] **TODO 05: Execute job `handle()` bodies in tests**
  - Needed:
    - Real (non-faked) execution tests for all 8 jobs. `DeleteGroupDataProcess`, `EventAutoCheck`, and `GroupDayDeletedProcess` currently have **zero** coverage; the other five are only asserted at dispatch level via `Bus::fake()`.
    - Assert side effects (DB writes, notifications, file deletions), and assert queue serialization survives a round trip - this is what breaks across framework majors.
  - Expected changes:
    - New `tests/Feature/Jobs/*Test.php`.

- [ ] **TODO 06: Extract scheduler closures into testable commands**
  - Needed:
    - `app/Console/Kernel.php::schedule()` contains ~200 lines of inline closures (user cleanup, event auto-expire, GDPR anonymization, log purge, newsletter sending, statistics). `commands()` also loads a non-existent `app/Console/Commands` directory.
    - Move each closure into its own Artisan command class under `app/Console/Commands`, keeping the schedule registration identical.
    - Add a test per command that executes the real logic; keep `tests/Unit/Scheduler/SchedulerRegressionTest.php` asserting the registration and frequencies.
  - Expected changes:
    - New `app/Console/Commands/*`, slimmed `app/Console/Kernel.php`, new command tests.
    - Update `.docs/commands.md` in the same change set.

- [ ] **TODO 07: Add interaction tests for the 19 smoke-only Livewire components**
  - Needed:
    - These currently only assert "mounts and returns 200": `Admin\AdminNewsletters`, `Admin\NewsletterEdit`, `Admin\Settings`, `Admin\StaticPages`, `Admin\StaticPageEdit`, `Admin\Statistics`, `Admin\Translation`, `Events\LastEvents`, `Groups\DeleteGroup`, `Groups\History`, `Groups\ListUsers`, `Groups\NewsEdit`, `Groups\NewsList`, `Groups\Statistics`, `Groups\Messages`, `Partials\NavBar`, `Partials\SideMenu`, `Partials\EventsBar`, plus the uncovered half of `Groups\ListGroups`.
    - For each: cover the public methods, validation rules, authorization boundaries, `$listeners`, and emitted events.
    - `Groups\NewsEdit` needs explicit `WithFileUploads` coverage (upload, `mimes` validation, `temporaryUrl()`, storage on the `news_files` disk) because file uploads change substantially in Livewire 3.
    - The two heaviest business-rule surfaces inside these components are broken out separately: see **TODO 07.1** (event scheduling capacity and overlap) and **TODO 07.2** (group creation and role assignment).
  - Expected changes:
    - New per-component test files under `tests/Feature/Livewire/`.
    - **This is a hard prerequisite for Phase 6.** Without it the Livewire 3 migration is unverifiable.

- [ ] **TODO 07.1: Cover event scheduling capacity and overlap rules**
  - Context: this is the core domain logic of the application and it is **entirely untested today**. `tests/Feature/CalendarEventEditTest.php` sets up `date_max_publishers => 3` and `date_min_time => 60` in its fixture, but not one of its 15 tests ever fills a slot, so the capacity branch in `saveEvent()` never executes. `GroupFactory::withStrictPublisherLimits()` exists and is used by **zero** tests. All 5 rules below are Livewire-component logic that must survive the Livewire 3 migration.
  - How the logic actually works (verified, needed to write meaningful tests):
    - Capacity is enforced **per time slot**, not per event. `getInfo()` (`app/Http/Livewire/Events/EventEdit.php:228-326`) builds `day_data['table']` from `GenerateSlots::generate()` using `date_min_time * 60` as the step, then walks every existing event and increments `publishers` and `accepted` on each slot the event spans (`:284-296`). `saveEvent()` (`:475-485`) re-walks the requested `start..end` range in the same steps and rejects the save if any single slot is at capacity.
    - `Events\Modal.php:71-394` carries a near-identical copy of the same `getInfo()` table builder, so **both** components need coverage. That duplication is scheduled for removal after the upgrade (TODO 77), and these tests are its prerequisite.
  - Needed:
    - **Max publishers per slot.** Fill a slot to `date_max_publishers` with accepted events, then assert a further save fails with the `event.reach_max_publisher` error on both `start` and `end` (`EventEdit.php:567-575`). Assert the boundary explicitly: the Nth booking succeeds, the N+1th fails.
    - **Approval groups allow overbooking, but only up to a point.** When `need_approval` is set, the effective per-slot limit is `max_publishers + config('events.max_columns')` (currently 4) **while** `accepted < max_publishers`; as soon as the accepted count reaches `max_publishers`, the limit drops back to `max_publishers` (`EventEdit.php:477-482`, mirrored at `:302-305`). Test all three states: under-subscribed (extra pending applicants accepted), at the raised ceiling (rejected), and accepted-count-reached (limit collapses, further pending applications rejected). Contrast against a `withAutoApproval()` group where the limit is always `max_publishers`.
    - **Overlapping time ranges.** The user's example must be an explicit test: with `date_min_time => 60` and `max_publishers => 1`, an accepted 08:00-10:00 event must make 09:00-10:00 unavailable for a second 09:00-12:00 application, while 10:00-12:00 remains free. Assert both the rejection and that a non-overlapping range still saves. Repeat with a sub-hour `min_time` (e.g. 30) so the slot-stepping arithmetic is exercised at more than one granularity.
    - **Full slots disappear from the selectable times.** `getInfo():300-322` marks saturated slots `full` and removes them from `day_selects['start']` / `day_selects['end']`. Assert that, and assert the `ready` status once `accepted >= min_publishers`. Also cover `GroupDayDisabledSlots` (`disabled_slots`), which suppresses slots through the same code path.
    - **Cross-group double booking (`busy`).** `saveEvent():583-601` rejects a save when the same user already has an **accepted** event in a **different** group whose range overlaps (`events.start < end AND events.end > start`), producing `event.error.publisher_busy`. Test the overlap, the exact-touch boundary (08:00-10:00 then 10:00-12:00 must be allowed), that pending events in the other group do not block, and that soft-deleted events and soft-deleted groups are ignored.
  - Expected changes:
    - New `tests/Feature/Events/EventCapacityTest.php` and `tests/Feature/Events/EventOverlapTest.php`, covering both `Events\EventEdit` and `Events\Modal`.
    - Extend `tests/Concerns/BuildsDomainFixtures.php` with a helper that seeds N accepted/pending events across a slot range.
    - A unit test for `App\Classes\GenerateSlots` (`app/Classes/GenerateSlots.php`), since every rule above depends on its stepping arithmetic.

- [ ] **TODO 07.2: Cover group creation and role assignment authorization**
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

- [ ] **TODO 08: Cover `AppComponent` pagination behavior**
  - Needed:
    - `app/Http/Livewire/AppComponent.php` sets `protected $paginationTheme = 'bootstrap'`, which no longer exists in Livewire 3.
    - Assert paginated output for the components extending it (`Admin\StaticPages`, `Admin\AdminNewsletters`, `Groups\NewsList`, `Groups\History`, `Groups\ListUsers`): page size, page navigation, rendered pagination markup.
  - Expected changes:
    - New pagination assertions; a documented expectation to re-verify after the Livewire 3 hop.

- [ ] **TODO 09: Test the 5 uncovered middleware directly**
  - Needed:
    - `SetLocale`, `SetGuestLanguage`, `setUserLastActivity`, `CheckRecaptcha`, `HttpsProtocol` have no direct test.
    - `CheckRecaptcha` and `HttpsProtocol` are currently never exercised because `USE_RECAPTCHA=false` and `USE_HTTPS=false`; test them in the enabled state too.
    - `SetLocale` performs DB work plus `Cache::rememberForever` plus `View::share` on every web request and can force a logout in maintenance mode - cover all three paths.
  - Expected changes:
    - New `tests/Feature/Middleware/*Test.php`.

- [ ] **TODO 10: Add real behavior coverage for `GroupDayObserver`**
  - Needed:
    - The observer is currently not registered, and the only assertion is that it is inactive. Its `GroupDayUpdatedProcess` and `GroupDayDeletedProcess` dispatch paths are untested.
    - Decide and document whether it should be activated; test the handlers either way.
  - Expected changes:
    - Observer tests, plus an updated `.docs/observers.md`.

- [ ] **TODO 11: Fill notification trigger coverage gaps**
  - Needed:
    - 13 notification classes have a mail contract test but no dispatch-trigger test: `FinishRegistration`, `GroupParentGroupAttachedNotification`, `GroupParentGroupDetachedNotification`, `GroupPriorityMessageNotification`, `LoginData`, `Newsletter`, `TestNotification`, `UserProfileChangedNotification`, `UserProfileRenewalNotification`, `UserProfileRenewalAdminNotification`, `UserWillBeAnonymizeNotification`, `UserWillBeAnyonimizeAdminNotification`, and the misnamed `GroupPriorityMessageNotificationTest`.
    - Pay special attention to the 6 notifications calling `env('MAIL_FROM_ADDRESS')` in `replyTo()`/`bcc()` - these become hard failures in Phase 4.
  - Expected changes:
    - Extended `tests/Feature/NotificationTriggerRegressionTest.php` or new per-notification tests.

- [ ] **TODO 12: Add end-to-end tests for the GDPR and setup flows**
  - Needed:
    - GDPR: data export (`Portable`), anonymization (`Anonymizable`), the `AnonymizeInactiveUsers` scheduled command, and the `$gdprHidden`/`$gdprAnonymizableFields` declarations on `User`/`Group`.
    - Setup: the full `setup/*` flow, including `app/Http/Controllers/Setup/DatabaseController.php` and the `Storage::exists('installed.txt')` sentinel used in `routes/web.php:78` and `app/Exceptions/Handler.php:57`.
    - Both flows are tied to packages that must be replaced or forked later, so behavior must be pinned down first.
  - Expected changes:
    - New `tests/Feature/Gdpr/*` and `tests/Feature/Setup/*`.

- [ ] **TODO 13: Add round-trip tests for all encrypted cast columns**
  - Needed:
    - Write-then-read assertions for all 9 `encrypted` columns: `User.phone_number`, `User.name`, `User.congregation`, `Group.replyTo`, `Group.name`, `Event.comment`, `GroupMessage` payload, `GroupPosters` info, `GroupUser` note.
    - Assert nullability and length behavior, since several migrations widened these to `text`.
  - Expected changes:
    - New `tests/Feature/Models/EncryptedAttributeTest.php`.
    - **These tests are the guard rail for the migration squash (TODO 32) and every schema change afterwards.**

- [ ] **TODO 14: Harden the route contract snapshot**
  - Needed:
    - Extend `tests/Fixtures/route-contracts.json` and `RouteContractSnapshotTest` to explicitly record which definition currently wins for `verification.verify` and `password.confirm`.
    - This makes the deduplication in TODO 26 a visible, intentional diff rather than a silent behavior change.
  - Expected changes:
    - Updated fixture and test.

- [ ] **TODO 15: Rename the production class with a `Test` suffix**
  - Needed:
    - `app/Notifications/GroupPriorityMessageNotificationTest.php` is a production notification. Rename it and update all references.
  - Expected changes:
    - Renamed class, updated dispatch sites, updated `.docs/notifications.md`.

---

## Phase 2 - Dependency Decisions

Every blocker gets its own assess-then-decide pair. The **decision** is recorded here; the **execution** happens in the phase where the package first blocks (Phase 8 for Laravel 11 blockers, Phase 10 for Laravel 13 blockers). See Appendix A for the verified compatibility matrix.

For each package, "assess" means: list every API the project actually consumes, then price out three options - **fork/vendor into the project**, **replace with an alternative or in-house code**, or **drop the feature**.

- [ ] **TODO 16: Assess and decide - `dialect/laravel-gdpr-compliance`**
  - Context: last release 2020-01-06, effectively abandoned. Its `illuminate/support: >=5.5` constraint is unbounded, so Composer will happily install it on Laravel 13 while its code silently breaks.
  - Consumed surface: `Portable` and `Anonymizable` traits on `User` and `Group`, `$gdprHidden`, `$gdprWith`, `$gdprAnonymizableFields`, `config/gdpr.php`, and the scheduled `Dialect\Gdpr\Commands\AnonymizeInactiveUsers`.
  - This is the largest and highest-risk decision in the roadmap. Blocked by TODO 12.
  - Expected changes: a written decision with an effort estimate, recorded in this file.

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
    - `verification.verify` is defined twice (`routes/fortify.php:89` and `routes/web.php:122`), same URI, same name; whichever provider boots last wins. Route caching makes this worse, and newer Laravel is stricter about duplicate names.
    - `password.confirm` is defined twice in `routes/web.php` (`:128` GET, `:138` POST). Note `password.confirm` is also a middleware alias in `app/Http/Kernel.php:68`.
    - `verification.notice` at `routes/web.php:74` uses a string callable while Fortify's own is commented out at `routes/fortify.php:82-86` with the note "disabled, it's generate problem" - resolve that properly.
    - The snapshot test from TODO 14 must be updated in the same change set to show the intended winner.
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
  - Expected changes: `app/Providers/AppServiceProvider.php`.

- [ ] **TODO 32: Squash the migration history**
  - Needed:
    - 97 migrations, 16 `->change()` calls across 8 files, 68 `dropColumn`/`renameColumn` occurrences. Squashing to a single schema dump removes the `doctrine/dbal` dependency risk and the Laravel 11 native-`change()` attribute-loss trap in one move.
    - **Before squashing**, verify the resulting schema against production for the encrypted columns (`text` widening from `2022_04_12_*` and `2022_06_05_*`) - nullable, default, charset, and collation must survive.
    - The TODO 13 encrypted round-trip tests must pass against the squashed schema.
  - Expected changes: `database/schema/*.sql` baseline, archived migrations, green test suite on a freshly migrated `kozter_testing`.

- [ ] **TODO 33: Clean up middleware naming and dead code**
  - Needed:
    - Rename `app/Http/Middleware/setUserLastActivity.php` to `SetUserLastActivity` (PSR-4 tolerates the current name locally, but case-sensitive deploy targets will not).
    - Decide the fate of `RedirectIfUnansweredTerms`, which is never registered in `app/Http/Kernel.php`.
    - `app/Providers/RouteServiceProvider.php` still passes `->namespace($this->namespace)` where the property is commented out.
  - Expected changes: `app/Http/Kernel.php`, middleware files, `.docs/middleware.md`.

---

## Phase 4 - Laravel 8 -> 9 (PHP 8.1)

- [ ] **TODO 34: Upgrade the core framework to the Laravel 9 dependency set**
  - Needed:
    - `laravel/framework` to `^9.0`.
    - Replace `facade/ignition` with `spatie/laravel-ignition` (the current one also fatals on PHP 8.3).
    - `nunomaduro/collision` to `^6.0`, keep PHPUnit at `^9.5`.
    - Bump `astrotomic/laravel-translatable`, `spatie/*`, `laravel/fortify` to their Laravel 9 lines.
  - Expected changes: `composer.json`/`composer.lock`; `php81 artisan` boots on the Laravel 9 stack.

- [ ] **TODO 35: Remove the deprecated proxy and CORS packages**
  - Needed:
    - Remove `fideloper/proxy` and `fruitcake/laravel-cors`.
    - `app/Http/Middleware/TrustProxies.php:5` must extend `Illuminate\Http\Middleware\TrustProxies`; re-check the `$headers` constants (currently `HEADER_X_FORWARDED_FOR|HOST|PORT|PROTO|AWS_ELB`, whose AWS ELB handling differs).
    - `app/Http/Kernel.php:23` -> `Illuminate\Http\Middleware\HandleCors`.
  - Expected changes: middleware refactor, `app/Http/Kernel.php`, `composer.json`.

- [ ] **TODO 36: Validate the SwiftMailer -> Symfony Mailer switch**
  - Needed:
    - Verified low risk: zero direct SwiftMailer usage, zero Mailables, all 26 notifications use the stable `MailMessage` API, `config/mail.php` already uses the modern `mailers` shape.
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
    - Convert both `@dataProvider` annotations to `#[DataProvider]` attributes **and make the provider methods `static`** (`LivewireRouteMountedComponentsTest::mountedRouteProvider`, `NotificationRegressionTest::notificationProvider`). Non-static providers are deprecated in PHPUnit 10 and forbidden in 11, so doing it here avoids a fatal in Phase 9.
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
    - One base class to fix, but every subclass inherits it. TODO 08's assertions are the verification.
  - Expected changes: `AppComponent`, possibly published pagination views.

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
    - Apply the TODO 17 (`joedixon/laravel-translation`), TODO 18 (`pcinaglia/laraupdater`), and TODO 16 (`dialect/laravel-gdpr-compliance`) decisions. All three block here.
    - The GDPR package is the deepest: it touches the `User`/`Group` traits, `config/gdpr.php`, the scheduled anonymization command, and interacts with the encrypted columns.
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
| `dialect/laravel-gdpr-compliance` | 1.4.7 (exact pin) | unbounded `>=5.5`, last release **2020-01-06** | **Abandoned. Blocks Phase 8** - decision required (TODO 16). Highest risk item |
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
  - Why this matters: this is the application's central business logic, and the two copies can silently drift apart - a capacity rule fixed in one component but not the other produces a calendar that shows a slot as available and then rejects the save. The duplication also doubled the work in Phase 6 (every Livewire 3 change had to be applied twice) and doubles the test surface in TODO 07.1.
  - Needed:
    - Extract the shared logic into a dedicated service or action class (for example `app/Classes/DayScheduleBuilder.php`, alongside the existing `app/Classes/GenerateSlots.php`), and have both components consume it.
    - Move the capacity and overlap rules out of the Livewire components entirely, so they can be unit-tested without a component harness.
    - **Prerequisite: TODO 07.1 must be complete and green.** Those tests are the only proof that the extracted service behaves identically to both originals - run them against each component before and after the extraction.
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
