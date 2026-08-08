# Jobs Documentation

## Overview

Queue jobs are defined in `app/Jobs` and are used for recalculation, cleanup, and asynchronous notifications.

All jobs in this folder implement `ShouldQueue`. One job is unique.

## Job Catalog

| Job | Primary Purpose | Dispatch Source(s) | Notes |
|---|---|---|---|
| `CalculateDateProcess` | Recalculate date/event compatibility and optional cleanup for specific group dates. | `App\Helpers\GroupDateHelper::recalculateDates`, `App\Http\Livewire\Groups\SpecialDateModal` | Calls `CalculateDatesEvents::generate`, can delete disabled dates/stat rows after recalc. |
| `CalulcateUserNameIndexProcess` | Rebuild sortable `name_index` for users. | `UserObserver` (`created`, `updated(name)`, `deleted`, `forceDeleted`) | Implements `ShouldBeUnique` (`uniqueId = CalculateUserNameIndex`). Name contains typo (`Calulcate`). |
| `DeleteGroupDataProcess` | Remove group-owned data and optionally anonymize users with no memberships left. | `App\Http\Controllers\GroupDelete` | Deletes events, dates, days, stats, news, surveys, memberships, messages, future changes. |
| `GenerateStatProcess` | Generate slot-level `DayStat` records for a group/day. | `App\Classes\GenerateStat::generate` | Uses `run_job` flag on `GroupDate` to avoid parallel duplication. |
| `GroupDayUpdatedProcess` | Handle service day time changes and recalculate affected dates/events. | `GroupDayObserver::updated` | Current active path calls `CalculateDatesEvents::generate`; older direct update/delete logic is commented out. Uses weekday conversion between PHP and MySQL weekday numbering. |
| `UserLogoutFromGroupProcess` | Remove future events when a user leaves a group and send notifications. | `App\Classes\GroupUserMoves::detach` | Regenerates stats per affected day and notifies non-anonymized users. |

## Dispatch Topology

### Core dispatch points in application code

- `GroupUserMoves` -> `UserLogoutFromGroupProcess`
- `GenerateStat` -> `GenerateStatProcess`
- `GroupDateHelper` -> `CalculateDateProcess`
- `GroupDelete` controller -> `DeleteGroupDataProcess`
- `UserObserver` -> `CalulcateUserNameIndexProcess`

### Observer-dependent dispatch

`GroupDayUpdatedProcess` is dispatched by `GroupDayObserver`, but that observer is **not registered** in `EventServiceProvider`, so the job does not trigger. See `.docs/observers.md` for why the registration is a deliberate decision.

### Removed

`GroupDayDeletedProcess` was **deleted in roadmap TODO 10.2**. It was dispatched only from the same unregistered observer, so it never ran; and the work it would have done — removing future events that no longer fit a narrowed or deleted service day — is already performed by the live `GroupDateHelper` -> `CalculateDateProcess` -> `CalculateDatesEvents` chain, pinned by `tests/Feature/Groups/GroupDayTemplateCleanupTest.php`. Its removal also eliminated an `ArgumentCountError` that sat in `GroupDayObserver::forceDeleted()`.

## Side Effects to Be Aware Of

- Jobs frequently trigger notifications (`EventDeletedNotification`, `EventUpdatedNotification`, etc.).
- Several jobs mutate event/date/stat tables in bulk.
- `DeleteGroupDataProcess` can anonymize users depending on remaining memberships
  and passed flags. Since v1-patch B10 the "no memberships left" test excludes
  the group being deleted explicitly, so the outcome no longer depends on
  whether the caller soft-deleted the group first. `User::anonymize()` may still
  refuse under the succession policy (TODO 12.2).
- `EventAutoCheck` was **deleted** (v1-patch B9). It was unrunnable rather than
  merely unfinished - an empty `foreach`, an invalid `'=<'` SQL operator, and a
  body that read an object property off an array element - and both dispatch
  sites in `EventObserver` had been commented out from the start, so it had
  provably never run. The `auto_approval` / `auto_back` group columns remain;
  the feature simply has no implementation behind it. Writing one means a new
  job, not reviving this class. `JobSerializationTest` guards both the deletion
  and the fact that its job list matches `app/Jobs/` exactly.
