# Laravel 8 -> Laravel 12 Upgrade Roadmap

This roadmap is designed for multi-step execution by AI agents.
Each item is intentionally small enough to complete and mark independently.

## Execution Environment (Important)

- Run all Laravel commands with `php81 artisan ...` in this project.
- Do not use plain `php artisan ...` here, because the default `php` points to PHP 8.3, which is not suitable for this Laravel 8 baseline.

## Status Convention

- `[ ]` not started
- `[x]` completed

## Project-Specific Baseline (Current State)

- Laravel `8.12` (`composer.json`)
- PHP constraint is `^8.0` with `config.platform.php = 8.0.9`
- No meaningful automated tests yet (only `tests/Feature/ExampleTest.php` and `tests/Unit/ExampleTest.php`)
- Custom Fortify routes are manually loaded (`Fortify::ignoreRoutes()`), with duplicate `verification.verify` route names in `routes/web.php` and `routes/fortify.php`
- Deprecated packages for modern Laravel are present (`fideloper/proxy`, `fruitcake/laravel-cors`, `facade/ignition`)
- `app/Http/Middleware/TrustProxies.php` still extends `Fideloper\Proxy\TrustProxies`
- Frontend build uses Laravel Mix (`webpack.mix.js`)

## Phase 0 - Safety and Baseline

- [ ] **TODO 01: Create a regression test baseline before any upgrade**
  - Needed:
    - Add test coverage for all existing routes, including middleware and authorization behavior (guest/auth/verified/profileFull/groupMember/groupAdmin/can:* checks).
    - Add explicit Livewire test coverage for route-mounted components: `Home`, `Events\Events`, `Events\LastEvents`, `Groups\ListGroups`, `Admin\AdminNewsletters`, `Admin\Users\ListUsers`, `Admin\Settings`, `Admin\StaticPages`, `Admin\StaticPageEdit`, `Admin\NewsletterEdit`, `Admin\Statistics`, `Admin\Translation`, `Groups\ListUsers`, `Groups\NewsList`, `Groups\UpdateGroupForm`, `Groups\DeleteGroup`, `Groups\NewsEdit`, `Groups\Statistics`, `Groups\History`.
    - Add Livewire integration tests for embedded/nested components: `Events\Modal`, `Events\EventEdit`, `Groups\Messages`, `Groups\PosterEditModal`, `Groups\SpecialDateModal`, `Partials\NavBar`, `Partials\SideMenu`, `Partials\EventsBar`.
    - Add tests for all observers (including model event triggers and side effects): `UserObserver`, `EventObserver`, `GroupObserver`, `GroupUserObserver`, `GroupLiteratureObserver`, `GroupNewsObserver`, `GroupNewsTranslationObserver`, `GroupDayObserver` (currently not registered, must be tested/documented as inactive or activated).
    - Add tests for all notifications in `app/Notifications` (delivery channel, recipients, payload content, and dispatch trigger coverage from routes/jobs/observers).
    - Replace example tests with real Feature tests for critical user flows.
    - Cover at least: login/logout, registration finish flow, email verification, profile update, group membership flows, event create/update/delete, scheduler-triggered side effects where possible.
    - Add unit tests for key domain helpers/services (group date generation, statistics calculations, notification trigger conditions).
  - Expected changes:
    - New/updated files under `tests/Feature` and `tests/Unit`.
    - Test data factories/seeders expanded to support realistic scenarios.
    - CI-ready `phpunit` execution with stable deterministic assertions.

- [ ] **TODO 02: Stabilize test environment configuration**
  - Needed:
    - Enable dedicated test DB config in `phpunit.xml` (prefer sqlite in-memory if compatible, otherwise dedicated MySQL test schema).
    - Ensure queues, cache, mail, and filesystem use test-safe drivers.
    - Add `.env.testing` defaults if missing.
  - Expected changes:
    - `phpunit.xml` updates.
    - Optional `.env.testing` file.
    - Fewer flaky tests and reproducible local/CI runs.

- [ ] **TODO 03: Freeze current behavior snapshot**
  - Needed:
    - Export route list, scheduled commands, and key config snapshots before upgrade.
    - Record known runtime issues on modern PHP (currently `php artisan` fails on PHP 8.3 with legacy dependencies).
  - Expected changes:
    - New artifact docs under `.docs/` or a dedicated `upgrade-notes/` folder.
    - A clear before/after comparison reference.

## Phase 1 - Dependency and Platform Preparation

- [ ] **TODO 04: Build package compatibility matrix for Laravel 9/10/11/12**
  - Needed:
    - Review every direct dependency in `composer.json` for Laravel 12 compatibility.
    - Mark packages as: compatible, needs version bump, replacement required, or blocked.
    - Prioritize high-risk packages: `laravelcollective/html`, `dialect/laravel-gdpr-compliance`, `livewire/livewire`, `joedixon/laravel-translation`, `pcinaglia/laraupdater`, `protonemedia/laravel-verify-new-email`.
  - Expected changes:
    - Compatibility table committed to roadmap or separate markdown file.
    - Clear list of blockers before composer updates.

- [ ] **TODO 05: Define runtime targets for each hop**
  - Needed:
    - Plan PHP runtime progression per major Laravel version.
    - Use isolated environments (containers or separate PHP binaries) so each intermediate version can boot.
  - Expected changes:
    - Documented version matrix (PHP, Composer, Node/NPM).
    - Reduced risk of mixed-runtime failures during upgrade.

- [ ] **TODO 06: Clean composer configuration constraints**
  - Needed:
    - Remove or revisit `minimum-stability: dev` if not required.
    - Update/remove `config.platform.php` pin once target runtime is validated.
  - Expected changes:
    - Cleaner dependency resolution and fewer hidden legacy locks.

## Phase 2 - Laravel 8 -> 9

- [ ] **TODO 07: Upgrade core framework to Laravel 9-compatible dependency set**
  - Needed:
    - Update `laravel/framework` to `^9.0` and align dev tools.
    - Replace `facade/ignition` with `spatie/laravel-ignition`.
    - Update `nunomaduro/collision` and PHPUnit versions required by Laravel 9.
  - Expected changes:
    - `composer.json` and `composer.lock` updates.
    - `artisan` should boot on the Laravel 9 stack.

- [ ] **TODO 08: Remove deprecated proxy/cors packages and refactor middleware**
  - Needed:
    - Remove `fideloper/proxy` and `fruitcake/laravel-cors` packages.
    - Update `TrustProxies` middleware to `Illuminate\Http\Middleware\TrustProxies`.
    - Switch CORS handling to framework-native middleware where required.
  - Expected changes:
    - `app/Http/Middleware/TrustProxies.php` refactor.
    - `app/Http/Kernel.php` middleware list changes.

- [ ] **TODO 09: Validate Laravel 9 breaking changes against app behavior**
  - Needed:
    - Review mailer changes (SwiftMailer -> Symfony Mailer impacts custom mail usage).
    - Validate Flysystem v3 behavior on all configured disks.
    - Run full regression tests and fix failing assumptions.
  - Expected changes:
    - Targeted fixes in mail/filesystem integration code.
    - Green test suite on Laravel 9.

## Phase 3 - Laravel 9 -> 10

- [ ] **TODO 10: Upgrade to Laravel 10 and align toolchain**
  - Needed:
    - Bump `laravel/framework` to `^10.0`.
    - Upgrade PHPUnit to `^10` and collision to Laravel 10 compatible versions.
    - Reconcile any logging changes tied to Monolog 3.
  - Expected changes:
    - Composer updates + test suite adjustments for PHPUnit 10.

- [ ] **TODO 11: Refactor code patterns deprecated by Laravel 10**
  - Needed:
    - Replace reliance on Eloquent `$dates` property with `$casts` date/datetime definitions.
    - Review database expression usage and typed return expectations.
  - Expected changes:
    - Model updates in `app/Models/*`.
    - Fewer deprecation warnings and cleaner future upgrades.

## Phase 4 - Laravel 10 -> 11

- [ ] **TODO 12: Upgrade to Laravel 11 dependency set**
  - Needed:
    - Bump `laravel/framework` to `^11.0`.
    - Upgrade `laravel/fortify`, `nunomaduro/collision`, `spatie/laravel-ignition`, and related first-party packages to Laravel 11 compatible versions.
  - Expected changes:
    - Composer dependency realignment and lock refresh.

- [ ] **TODO 13: Handle Laravel 11 database and schema changes**
  - Needed:
    - Remove `doctrine/dbal` unless a specific package still requires it.
    - Re-test all migrations and schema alterations.
  - Expected changes:
    - `composer.json` cleanup.
    - Migration compatibility fixes if needed.

- [ ] **TODO 14: Upgrade Livewire 2 -> 3**
  - Needed:
    - Bump `livewire/livewire` to the Laravel 11-compatible major version.
    - Refactor Livewire components/events/hooks and front-end integration for v3 API changes.
    - Revisit `post-autoload-dump` script that publishes Livewire assets.
  - Expected changes:
    - Significant updates across `app/Http/Livewire` and related Blade views.
    - Front-end behavior parity validated by tests.

## Phase 5 - Laravel 11 -> 12

- [ ] **TODO 15: Upgrade framework and test stack to Laravel 12 baseline**
  - Needed:
    - Bump `laravel/framework` to `^12.0`.
    - Upgrade `phpunit/phpunit` to `^11.0` (or Pest v3 if adopted).
    - Ensure Carbon 3 compatibility in date/time-sensitive code.
  - Expected changes:
    - Composer updates and test-layer fixes.

- [ ] **TODO 16: Audit Laravel 12 behavioral changes impacting this app**
  - Needed:
    - Route precedence changed for duplicate route names: confirm intended behavior for duplicate `verification.verify` definitions.
    - Confirm local disk root behavior (`Storage::disk('local')` now defaults to `storage/app/private` unless explicitly configured).
    - Validate image upload rules if SVGs are expected (`image` rule no longer includes SVG by default).
    - Check request merge behavior for nested array payloads.
  - Expected changes:
    - Route/auth flow adjustments in `routes/web.php`, `routes/fortify.php`, and possibly `FortifyServiceProvider`.
    - `config/filesystems.php` and validation rule updates as needed.

## Phase 6 - Fortify/Auth and Route Integrity (Project-Specific)

- [ ] **TODO 17: Refactor Fortify route strategy to remove ambiguity**
  - Needed:
    - Decide whether to keep fully custom Fortify routing or move closer to default route registration.
    - Remove duplicate named routes and confirm middleware parity (`signed`, `throttle`, `checkRecaptcha`, `auth`).
  - Expected changes:
    - Cleaner and deterministic authentication/verification routing.
    - Updated auth-related docs in `.docs/fortify-routes.md` and `.docs/routes.md`.

- [ ] **TODO 18: Re-verify authorization and middleware behavior**
  - Needed:
    - Regression-test custom middleware (`groupAdmin`, `groupMember`, `profileFull`, `setGuestLanguage`, `checkRecaptcha`).
    - Verify gate-based admin/group permissions after framework upgrades.
  - Expected changes:
    - Potential fixes in middleware and policy/gate interactions.

## Phase 7 - Frontend Build and Delivery

- [ ] **TODO 19: Decide Mix vs Vite migration path**
  - Needed:
    - Evaluate whether to keep current Mix build temporarily or migrate to Vite for long-term support.
    - If migrating, refactor asset entrypoints and Blade asset loading.
  - Expected changes:
    - `package.json`, build config, and Blade asset includes updated.

- [ ] **TODO 20: Validate queue/scheduler/notifications in staging**
  - Needed:
    - Test scheduled jobs, queue workers, notification dispatches, and cleanup flows on upgraded stack.
    - Pay extra attention to jobs documented in `.docs/jobs.md` and observer-triggered dispatch paths.
  - Expected changes:
    - Runtime configuration updates and possible job-level bug fixes.

## Phase 8 - Release Readiness

- [ ] **TODO 21: Execute full regression test suite + smoke checks**
  - Needed:
    - Run full test suite, plus manual smoke checks for auth, group/event workflows, admin pages, and setup flow.
  - Expected changes:
    - Release candidate report with pass/fail matrix.

- [ ] **TODO 22: Update project documentation in same change sets**
  - Needed:
    - Update `.docs/models.md`, `.docs/routes.md`, `.docs/jobs.md`, `.docs/fortify-routes.md`, `.docs/components.md`, `.docs/commands.md` where behavior changed.
  - Expected changes:
    - Documentation kept in sync with final Laravel 12 behavior.

- [ ] **TODO 23: Prepare rollback and deployment checklist**
  - Needed:
    - Define backup, rollback, maintenance mode, migration execution order, queue restart, and post-deploy verification steps.
  - Expected changes:
    - Safer production rollout with explicit recovery path.

## Suggested Execution Strategy

- Execute this roadmap in separate PRs per phase (or per TODO cluster).
- Do not skip intermediate major versions in code changes; apply and validate each hop.
- Keep tests green at every hop before moving forward.
