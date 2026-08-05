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

### Present but not registered

- `GroupDayObserver` for `GroupDay`

Because it is not registered, its hooks and queued job dispatches do not run in current runtime behavior.

**This is a deliberate decision, not an oversight.** `GroupDayObserver` is the *only* place that dispatches `GroupDayUpdatedProcess` and `GroupDayDeletedProcess`, so the "delete future events that no longer fit the day template" cleanup never runs today — that is a missing feature, not dead code. Registering it would start deleting bookings users have already made whenever an administrator narrows a group's day template, which is a product decision that needs its own testing. See roadmap TODO 10.1.

The observer has nevertheless been given the shared causer handling (below), so that registering it later cannot break the scheduler on the first run.

## Resolving the causer

All observers use `App\Observers\Concerns\ResolvesCauser`, which provides:

- `causerId(): int` — the acting user's id, or **`0` meaning "the system"**
- `causerName(): string` — the acting user's name, or `'SYSTEM'`

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
| `GroupDayObserver` (inactive) | `GroupDay` | `created`, `updated`, `deleted`, `forceDeleted` | Would record day-template history and dispatch `GroupDayUpdatedProcess` / `GroupDayDeletedProcess` jobs if registered. |

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

- Contains valid lifecycle logic and queue dispatches, but no active registration.
- It is the **sole dispatcher** of `GroupDayUpdatedProcess` and `GroupDayDeletedProcess`, so those jobs never run.
- `GroupDay` rows are written only by `app/Classes/updateGroupFutureChanges.php` (via `updateOrCreate` and `$del->delete()`, i.e. through Eloquent, so the events would genuinely fire). That class is also called by the **`ApplyGroupFutureChanges` scheduled command**, which has no authenticated user — the causer handling above is what makes registration survivable at all.
- Before registering `GroupDay::observe(GroupDayObserver::class)` in `EventServiceProvider`, decide what should happen to bookings that fall outside a narrowed day template: the jobs delete them. `ObserverCauserTest::test_the_group_day_observer_is_still_not_registered` is the guard that will fail first.
