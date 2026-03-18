# AGENTS Guide

This repository contains a Laravel 8 application for organizing congregation public ministry activity.

This file is the root documentation entrypoint. Detailed technical docs are under `.docs/`.

## Documentation Map

- `.docs/models.md`: Eloquent model catalog, relationships, casts, traits, observer bindings.
- `.docs/components.md`: Livewire and Blade component inventory with usage context.
- `.docs/routes.md`: HTTP route structure (`web`, `api`, `channels`) and middleware layering.
- `.docs/commands.md`: Artisan command and scheduler documentation.
- `.docs/jobs.md`: Queue job behavior, dispatch sources, and side effects.
- `.docs/fortify-routes.md`: Custom Fortify integration and authentication route behavior.

## Project Stack Snapshot

- Framework: Laravel 8
- UI architecture: Livewire + Blade views/components
- Auth stack: Laravel Fortify (custom route registration)
- Background processing: Laravel queue jobs + scheduler (`app/Console/Kernel.php`)
- Data layer: Eloquent models with observers and notification-driven workflows

## Maintenance Rules For Contributors

- Keep documentation in English.
- When changing models/routes/jobs/auth flow, update the corresponding file under `.docs/` in the same change set.
- Prefer documenting behavior and integration points (what triggers what), not just file names.
- If behavior is disabled/commented in code, mark it clearly as inactive in docs.

## Source-of-Truth Paths

- Models: `app/Models`
- Livewire components: `app/Http/Livewire`
- Blade class components: `app/View/Components`
- Routes: `routes/*.php`
- Scheduler/console: `app/Console/Kernel.php`, `routes/console.php`
- Jobs: `app/Jobs`
- Fortify customization: `app/Providers/FortifyServiceProvider.php`, `app/Actions/Fortify`, `routes/fortify.php`

## Notes

- This codebase contains legacy Laravel 8 dependencies. Some Artisan inspection commands may fail on newer PHP runtimes unless dependencies are upgraded.
