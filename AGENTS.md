# AGENTS Guide

This repository contains a Laravel 10 application for organizing congregation public ministry activity.

This file is the root documentation entrypoint. Detailed technical docs are under `.docs/`.

## Documentation Map

- `.docs/models.md`: Eloquent model catalog, relationships, casts, traits, observer bindings.
- `.docs/components.md`: Livewire and Blade component inventory with usage context.
- `.docs/routes.md`: HTTP route structure (`web`, `api`, `channels`) and middleware layering.
- `.docs/commands.md`: Artisan command and scheduler documentation.
- `.docs/jobs.md`: Queue job behavior, dispatch sources, and side effects.
- `.docs/fortify-routes.md`: Custom Fortify integration and authentication route behavior.
- `.docs/notifications.md`: Notification catalog, delivery channels, and trigger points.
- `.docs/observers.md`: Observer registration status and side-effect mapping.
- `.docs/middleware.md`: HTTP middleware stacks, aliases, and custom behavior.
- `.docs/assets.md`: CSS/JS delivery - the `pwbs_asset()` helper that replaced `laravel-packer`, the dead Mix pipeline, and the traps in both.
- `upgrade-roadmap.md`: the developer's framework-upgrade plan, Laravel 8 -> 13.
- `upgrade-guide.md`: the **operator's** counterpart - what a deployed host has
  to do to reach 2.0.0. Filled one hop at a time and **not complete** until the
  Laravel 13 hop; it says so at the top.

## Project Stack Snapshot

- Framework: Laravel 10
- UI architecture: Livewire + Blade views/components
- Auth stack: Laravel Fortify (custom route registration)
- Background processing: Laravel queue jobs + scheduler (`app/Console/Kernel.php`)
- Data layer: Eloquent models with observers and notification-driven workflows

## Maintenance Rules For Contributors

- Keep documentation in English.
- **Keep code comments in English too** - PHPDoc blocks, inline `//` comments and
  test explanations alike, in `app/`, `tests/`, `database/`, `routes/`, `config/`
  and Blade files. This applies to every new or edited comment, with no
  exception for "the surrounding comments are Hungarian".
  A large part of the existing tree still carries Hungarian comments; converting
  them is tracked as roadmap TODO 33.9 and is deliberately *not* something to do
  opportunistically inside an unrelated change set.
- When changing models/routes/jobs/auth flow, update the corresponding file under `.docs/` in the same change set.
- Prefer documenting behavior and integration points (what triggers what), not just file names.
- If behavior is disabled/commented in code, mark it clearly as inactive in docs.
- **Never write figures taken from a real database into documentation or code
  comments.** Row counts, "how many records were affected", distributions,
  timings measured on live data, sample values - none of it belongs in `.docs/`,
  in `upgrade-roadmap.md`, in PHPDoc blocks, in inline `//` comments or in test
  comments. Three reasons: the repository is distributed (the release archive is
  built from it, and `vendor/` is committed), so those figures travel to every
  install; they describe one deployment at one moment and are wrong everywhere
  else, including on the next deployment; and a count of affected rows is itself
  information about the data set.
  Measuring the database while investigating is right and expected - the rule is
  about what gets **written down** afterwards. Record the *mechanism* instead of
  the measurement: say which column was never cleared and why, not how many rows
  it affected. "Every row anonymized before this change still carried the
  original password hash" is a statement about the defect; "1234 rows still
  carried it" is a statistic about the production database.
  Test-suite numbers are a different thing and stay: test and assertion counts,
  file counts and line counts come from the repository, not from user data.

## Source-of-Truth Paths

- Models: `app/Models`
- Livewire components: `app/Http/Livewire`
- Blade class components: `app/View/Components`
- Routes: `routes/*.php`
- Scheduler/console: `app/Console/Kernel.php`, `routes/console.php`
- Jobs: `app/Jobs`
- Fortify customization: `app/Providers/FortifyServiceProvider.php`, `app/Actions/Fortify`, `routes/fortify.php`

## Notes

- The framework upgrade is in progress (`upgrade-roadmap.md`, target Laravel 13). The application is on Laravel 10 as of TODO 39 and **runs on the default `php` (8.3) since the Phase 5 interpreter switch**. `php81` is still installed and Laravel 9 still supports it, but nothing needs it: `composer test` calls `php`, and the suite is green on 8.3 with its numbers unmoved.
