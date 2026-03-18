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
  - include acting user (`causer_id`) where available

## Inactive GroupDayObserver

- Contains valid lifecycle logic and queue dispatches, but no active registration.
- If you plan to rely on `GroupDayUpdatedProcess` or `GroupDayDeletedProcess`, first register `GroupDay::observe(GroupDayObserver::class)` in `EventServiceProvider`.
