# Observers Documentation

## Overview

Observer classes are located in `app/Observers` and are used mainly for:

- audit trail creation (`LogHistory`)
- business side effects (notifications, status propagation)
- asynchronous processing (job dispatch)

## Registration Status

From `app/Providers/EventServiceProvider.php`.

### Registered (active)

- `UserObserver` for `User`
- `EventObserver` for `Event`
- `GroupObserver` for `Group`
- `GroupUserObserver` for `GroupUser`
- `GroupLiteratureObserver` for `GroupLiterature`
- `GroupNewsObserver` for `GroupNews`
- `GroupNewsTranslationObserver` for `GroupNewsTranslation`
- `StaticPageObserver` for `StaticPage` **and** `StaticPageTranslation`
- `SettingsObserver` for `Settings`

The last two are cache invalidators rather than audit or notification hooks — see "Cache-invalidating observers" at the end of this file.

### Present but not registered

- `GroupDayObserver` for `GroupDay`

Because it is not registered, its hooks and queued job dispatches do not run in current runtime behavior.

**This is a deliberate decision, not an oversight** — settled in roadmap TODO 10.1. `GroupDayObserver` was the only place that dispatched `GroupDayUpdatedProcess` and `GroupDayDeletedProcess`, which for a while was read as "the cleanup never runs, so this is a missing feature". **That reading was wrong.** The cleanup does run through a different chain, so the observer's cleanup dispatches were a *superseded implementation*. Registering the full observer would still add one distinct capability — `GroupDay` audit-history records — but it would also have run the legacy cleanup hooks in addition to the active cleanup. The decision to leave it unregistered therefore deliberately leaves the day-template audit trail disabled. See "The day-template cleanup" below.

**TODO 10.2 followed through on that finding and deleted `GroupDayDeletedProcess` outright.** `deleted()` and `forceDeleted()` now only write their history record; the comments left in their place say why. `GroupDayUpdatedProcess` survives because its `handle()` delegates to `CalculateDatesEvents::generate()`, i.e. it is a thin wrapper over live code rather than a parallel implementation.

The observer has nevertheless been given the shared causer handling (below), and so has the job it would dispatch — passing `0` alone would only have moved the failure downstream.

## Resolving the causer

Observers, jobs and the service classes behind them use `App\Support\Concerns\ResolvesCauser`, which provides:

- `causerId(): int` — the acting user's id, or **`0` meaning "the system"**
- `causerName(): string` — the acting user's name, or `'SYSTEM'`
- `causerNameFor($userId): string` (static) — the name for an id **captured earlier**, mapping `0`, `false`, `null` and a since-deleted user onto `'SYSTEM'`

The third one exists because a job receives the causer id at dispatch time but runs later, in a worker, where `auth()` cannot serve as a fallback and `User::find(0)` is always `null`. `CalculateDatesEvents` reads the causer's *name* straight into notification payloads, so a `null` there becomes an `ErrorException` — Laravel promotes PHP warnings to exceptions. (The now-deleted `GroupDayDeletedProcess` had the same defect; `SystemCauserJobsTest` kept its since-deleted-user case and moved it onto `CalculateDatesEvents`.)

Model events do not only fire from HTTP requests: a scheduled command, a queue worker, a console command or a seeder can all write models with no authenticated user. Before TODO 10 three different behaviours coexisted for that situation — some observers skipped the history record, some wrote `0`, and eight call sites simply fataled on `auth()->user()->id`.

`log_histories.causer_id` has no foreign key, so `0` is safe; `LogHistory::user()` returns `null` for it.

**Consequence worth knowing:** `GroupObserver::updated()` and `GroupUserObserver::updated()` used to skip the audit record entirely when unauthenticated, so system-driven changes (notably `ApplyGroupFutureChanges`) happened without a trace. They now write one with `causer_id = 0`.

## Observer Catalog

| Observer | Model | Key Hooks | Main Responsibilities |
|---|---|---|---|
| `EventObserver` | `Event` | `created`, `updated`, `deleted` | Creates `LogHistory`, sends event notifications, handles status-change side effects (including auto-rejecting overlapping pending events in other groups when one is accepted). |
| `UserObserver` | `User` | `created`, `updated`, `deleted`, `forceDeleted` | Sends role-change/admin notifications and dispatches username index rebuild job (`CalulcateUserNameIndexProcess`). |
| `GroupObserver` | `Group` | `updated`, `deleted` | Writes group-level change history to `LogHistory`. |
| `GroupUserObserver` | `GroupUser` | `updated`, `deleted` | Writes membership pivot change/deletion history to `LogHistory`. |
| `GroupLiteratureObserver` | `GroupLiterature` | `created`, `updated`, `deleted` | Tracks literature lifecycle changes in audit history. |
| `GroupNewsObserver` | `GroupNews` | `updated`, `deleted` | Stores news change/deletion history records. |
| `GroupNewsTranslationObserver` | `GroupNewsTranslation` | `created`, `updated`, `deleted` | Stores localized news content change history. |
| `GroupDayObserver` (inactive) | `GroupDay` | `created`, `updated`, `deleted`, `forceDeleted` | Would record day-template history and dispatch `GroupDayUpdatedProcess` if registered. |
| `StaticPageObserver` | `StaticPage`, `StaticPageTranslation` | `saved`, `deleted` | Clears the `sidemenu_auth` / `sidemenu_guest` cache entries. No audit trail, no notification — cache invalidation only. |
| `SettingsObserver` | `Settings` | `saved`, `deleted` | Clears the `application_settings` cache entry behind `App\Support\Settings\ApplicationSettings`. Cache invalidation only. |

## Detailed Behavior Notes

## EventObserver

- Adds audit records on create/update/delete.
- Sends:
  - `EventCreatedNotification`
  - `EventUpdatedNotification`
  - `EventStatusChangedNotification`
  - `EventDeletedNotification`
  - `EventDeletedAdminsNotification`
- On status change to accepted (`status == 1`), it updates overlapping pending events for the same user in other groups to deleted status (`2`).

## UserObserver

- Sends `NewAdminNotification` when a new `mainAdmin` is introduced.
- Sends `UserRoleIsGroupCreatorNotification` on role promotion to `groupCreator`.
- Dispatches `CalulcateUserNameIndexProcess` on user create/delete/forceDelete and when name changes.

## Group/GroupUser/Content Observers

- `GroupObserver`, `GroupUserObserver`, `GroupLiteratureObserver`, `GroupNewsObserver`, and `GroupNewsTranslationObserver` follow a shared pattern:
  - compare dirty fields
  - save old/new payload into `LogHistory`
  - record the acting user via `causerId()`, falling back to `0` for system-driven writes
- `GroupObserver::deleted()` used to read `$group->group_id`, a field the `Group` model does not have (its key is `id`). The resulting `null` hit a `NOT NULL` column, so **every** `$group->delete()` through Eloquent failed. Fixed in TODO 10; only the mass-delete path in `GroupDelete` kept it hidden in production.

## Inactive GroupDayObserver

- Contains inactive audit-history logic and one queue dispatch, but no active registration. Its cleanup dispatches were superseded and are gone; its audit-history behavior is not provided elsewhere.
- It is the **sole dispatcher** of `GroupDayUpdatedProcess`, so that job never runs.
- `GroupDay` rows are written only by `app/Classes/updateGroupFutureChanges.php` (via `updateOrCreate` and `$del->delete()`, i.e. through Eloquent, so the events would genuinely fire). That class is called from `Groups\UpdateGroupForm` and from the **`ApplyGroupFutureChanges` scheduled command**, which has no authenticated user — the causer handling above is what makes registration survivable at all.
- `forceDeleted()` used to dispatch `GroupDayDeletedProcess::dispatch([...])`, passing an **array as a single argument** where the constructor took six — an `ArgumentCountError` waiting to happen, though unreachable because `GroupDay` does not use `SoftDeletes` and so has no `forceDelete()`. TODO 10.2 removed the call along with the job, which closed the bug as a side effect.
- `ObserverCauserTest::test_the_group_day_observer_is_still_not_registered` is the guard that will fail first if it is registered.

## The day-template cleanup

The behaviour the two inactive jobs would provide — remove or adjust future events that no longer fit a narrowed service day — **already happens**, on this chain:

```
Groups\UpdateGroupForm::updateGroup()
  -> GroupFutureChange saved first
  -> GroupDateHelper::generateDate()      rewrites future group_dates rows
                                          against the PENDING template
  -> GroupDateHelper::recalculateDates()
  -> CalculateDateProcess
  -> CalculateDatesEvents::generate()     deletes/adjusts events, notifies
                                          users, purges group_dates + day_stats
```

`CalculateDatesEvents::generate()` is exactly what `GroupDayUpdatedProcess::handle()` calls — that job's own body is entirely commented out and replaced by a single call to it. The `GroupDate` rows are rewritten to the *pending* template at save time, so the scheduled `initChanges()` only has to sync the `group_days` template itself.

`tests/Feature/Groups/GroupDayTemplateCleanupTest.php` pins this: narrowing a day deletes the events outside the new window and pulls partially overlapping ones inside; removing a day deletes its events, its `group_dates` row and its `day_stats`; past dates and widened days are left alone. Removing the `recalculateDates()` call makes six of its ten tests fail, which is how we know they measure this chain and not something else.

One difference was worth knowing while the decision was open: `GroupDayDeletedProcess` used a `LEFT JOIN`, so it would also have seen events with no `group_dates` row, while the helper chain starts from the `group_dates` rows. In practice such events do not occur — a booking can only be made on a generated date — which is why TODO 10.2 could delete the job rather than merge the difference.

## Cache-invalidating observers

Two observers do no auditing at all - they exist only to keep a `rememberForever`
cache entry from outliving its source. Both follow the same rule: **the
invalidation is bound to the data, not to the call sites that write it.**

- `StaticPageObserver` (v1-patch B4) clears `sidemenu_auth` and `sidemenu_guest`,
  which `SetLocale` fills. Before it, only `Admin\StaticPageEdit` and the
  installer's `AccountController` cleared them by hand, so a page created by a
  seeder, a console command or a direct model write never reached the menu -
  and never expired out of it either. It watches the translation model too,
  because the menu renders titles and those live in `static_page_translations`.
- `SettingsObserver` (TODO 31) clears `application_settings`, which
  `App\Support\Settings\ApplicationSettings` fills. Every setting write in the
  application goes through `Settings::updateOrCreate()` - `Admin\Settings` in
  seven places, `CoreSettingsSeeder`, the installer - so one `saved`/`deleted`
  hook covers all of them.

**What neither covers: a write that bypasses Eloquent.** There is no such write
in the application today; if one is added, it has to flush by hand. A mass
delete through the query builder (`Settings::query()->delete()`) is the same
case - it fires no model events.
