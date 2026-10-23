# Jobs Documentation

## Overview

Queue jobs are defined in `app/Jobs` and are used for recalculation, cleanup, and asynchronous notifications.

All jobs in this folder implement `ShouldQueue`. One job is unique.

## Job Catalog

| Job | Primary Purpose | Dispatch Source(s) | Notes |
|---|---|---|---|
| `CalculateDateProcess` | Recalculate date/event compatibility and optional cleanup for specific group dates. | `App\Helpers\GroupDateHelper::recalculateDates`, `App\Livewire\Groups\SpecialDateModal` | Calls `CalculateDatesEvents::generate`, can delete disabled dates/stat rows after recalc. |
| `CalulcateUserNameIndexProcess` | Rebuild sortable `name_index` for users. | `UserObserver` (`created`, `updated(name)`, `deleted`, `forceDeleted`) | Implements `ShouldBeUnique` (`uniqueId = CalculateUserNameIndex`). Its docblock announces a `uniqueFor`, but the property is missing, so the lock has no expiry - see "Runtime" below. Name contains typo (`Calulcate`). |
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

`GroupDayUpdatedProcess` is dispatched by `GroupDayObserver`, but that observer is **not registered** (`AppServiceProvider::registerObservers()` since TODO 56, `EventServiceProvider` before), so the job does not trigger. See `.docs/observers.md` for why the registration is a deliberate decision.

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

## Runtime: connection, worker, failures

Measured on Laravel 11.57 by `tests/Feature/Queue/DatabaseQueueWorkerTest.php`
(roadmap TODO 60). It pushes onto the `jobs` table and runs the framework's own
`queue:work`; every other job test runs on phpunit.xml's `sync` connection.

- **Connection:** `database` (`QUEUE_CONNECTION` in `.env.example`; note that
  `config/queue.php` falls back to `sync` when the key is missing). Failed jobs
  go to `failed_jobs` through the `database-uuids` driver.
- **Worker:** the scheduler starts `queue:work --name=kozteruletek-job-1
  --queue=default --max-time=25 --max-jobs=100 --sleep=3 --tries=3
  --backoff=20` every minute, `withoutOverlapping(1)` (`routes/console.php`).
  The test reads those options from the schedule rather than restating them.
- **Who decides the retries:** a class's own `$tries` / `$backoff` win over the
  worker's options. The jobs in `app/Jobs` set none, so they get 3 attempts 20
  seconds apart. **The 24 queued notifications set `$tries = 2880` and
  `$backoff = 60`**, so a notification that throws is retried once a minute for
  48 hours, and only then reaches `failed_jobs` and the monitor. Pinned as a
  characterization, not changed.
- **The failed-job monitor** (`spatie/laravel-failed-job-monitor`) mails once
  per job, when its tries are exhausted, to `failed-job-monitor.mail.to`
  (`.docs/notifications.md`). The test drives a job through all three scheduled
  attempts and renders the mail on Laravel 11.
- **Unique lock:** Laravel 11 writes the `ShouldBeUnique` key as
  `laravel_unique_job:<class>:<uniqueId>`; Laravel 8 wrote it without the
  colon. A lock the 1.x release left in the cache is therefore never released
  by 2.x - and never consulted either, so it blocks nothing; `optimize:clear`
  clears the cache store with it. Because `CalulcateUserNameIndexProcess` has
  no `uniqueFor`, a lock whose job was lost before it ran (a crashed worker,
  a flushed `jobs` table) holds until the cache is cleared, and the name index
  is not rebuilt until then. Recorded, not changed.

### Rows the 1.x release queued

A host may hold `jobs` rows the 1.x release (laravel/framework 8.83) wrote when
2.0.0 arrives. `tests/Fixtures/Queue/laravel8-payloads.json` holds rows built
by the v1 tree's own framework and classes (`generate-laravel8-payloads.php`
in the same directory). On the Laravel 11 worker:

- all six jobs and the four sampled notifications (`UserRegisteredNotification`,
  `EventCreatedNotification`, `EventDeletedNotification`, `Newsletter`) run and
  send. Every class v1 queued still exists with the same properties, and the
  framework's `Queueable`, `SendQueuedNotifications` and `SendQueuedMailable`
  gained only an untyped, null-default `$maxExceptions`;
- **the exception is the e-mail confirmation.** v1 queued
  `ProtoneMedia\LaravelVerifyNewEmail\Mail\VerifyFirstEmail` / `VerifyNewEmail`;
  TODO 33.5 replaced the package with `App\Mail`. Such a row unserializes to an
  incomplete object, fails after the worker's three attempts, lands in
  `failed_jobs` and triggers one monitor mail. The user has to request the
  confirmation again. `upgrade-guide.md` section 5 (TODO 60) drains the queue
  on the old release first.

