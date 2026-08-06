# Commands Documentation

## Overview

Command-related behavior is split between:

- `app/Console/Commands/` (the application's own Artisan commands)
- `app/Console/Kernel.php` (package command registration + scheduler wiring)
- `routes/console.php` (closure-based Artisan commands)

The scheduler contains **no anonymous closures**. Every scheduled task is a named
Artisan command, so `php artisan schedule:list` is a complete and readable
description of what the system does on a timer, and each task can be invoked and
tested on its own. `tests/Unit/Scheduler/SchedulerRegressionTest.php` enforces
this: it fails if a closure is reintroduced or a task changes frequency.

## Application Artisan Commands (`app/Console/Commands/`)

| Signature | Class | Purpose |
|---|---|---|
| `users:purge-unverified` | `PurgeUnverifiedUsers` | Deletes users who have not verified their email within a week. |
| `events:expire-pending` | `ExpirePendingEvents` | Marks still-pending events (`status=0`) as denied (`status=2`) once their start time has passed. |
| `gdpr:anonymize-inactive` | `AnonymizeInactiveUsers` | Anonymizes users inactive beyond `gdpr.settings.ttl` months. Detaches group memberships first. Skips `mainAdmin` and `groupCreator`. No-op when `gdpr.enabled` is false. |
| `gdpr:notify-anonymization` | `NotifyUpcomingAnonymization` | Warns users approaching the retention limit, and separately alerts group editors (`roler`, `admin`) about members in a narrow 6-7 day window. No-op when `gdpr.enabled` is false. |
| `maintenance:purge-log-history` | `PurgeLogHistory` | Deletes `LogHistory` entries older than three months. |
| `maintenance:daily-cleanup` | `DailyCleanup` | Force-deletes soft-deleted events older than three months and `GroupMessage` rows older than a week. |
| `statistics:record-daily-users` | `RecordDailyUserStatistics` | Records the daily active user count as statistics type `dialy_users`. |
| `groups:apply-future-changes` | `ApplyGroupFutureChanges` | Applies `GroupFutureChange` records whose `change_date` is today. |
| `newsletters:send-due` | `SendDueNewsletters` | Sends admin newsletters dated today that are published, flagged for sending, and not yet sent. |
| `statistics:record-active-users` | `RecordActiveUserStatistics` | Records the hourly active user count as statistics type `active_users`. |
| `scheduler:heartbeat` | `RecordSchedulerHeartbeat` | Updates `settings.last_schedule_run`, which the admin UI uses to show whether cron is alive. |

### Package command registration (`Kernel::$commands`)

- `Dialect\Gdpr\Commands\AnonymizeInactiveUsers::class` - a package command,
  registered explicitly. **Not the same as `gdpr:anonymize-inactive` above**:
  the package command is `gdpr:anonymizeInactiveUsers` and applies the package's
  own rules, while the application command applies this project's stricter ones.

#### The two anonymizers differ in behaviour, and both run every day

The package schedules its own command from `GdprServiceProvider::boot()`, so the
scheduler runs two anonymizers with different rules. Measured by
`tests/Feature/Gdpr/AnonymizeCommandDivergenceTest.php`:

| | `gdpr:anonymizeInactiveUsers` (package) | `gdpr:anonymize-inactive` (project) |
|---|---|---|
| Runs at | daily `00:00` | daily `07:00` |
| Role filter | **none** | skips `mainAdmin` and `groupCreator` |
| Group memberships | left intact | deleted first |

Consequences worth knowing before changing either one:

- The package command runs **seven hours earlier** and has no role filter, so an
  inactive `mainAdmin` is anonymized - including `role` becoming `registered`.
  The project command's protection cannot prevent this.
- Because the package command leaves memberships in place, an anonymized user
  still matches `User::userGroupsEditable()` / `userGroupsDeletable()`, which do
  **not** filter `isAnonymized` (unlike `Group::groupUsers()` and `Group::users()`,
  which do). Such a user therefore stays in the `newsletters:send-due` recipient
  list.
- No mail actually reaches them, because `User::routeNotificationFor()` returns
  `null` for any anonymized user and for any address failing
  `FILTER_VALIDATE_EMAIL`. That method is load-bearing and must survive any
  replacement of the GDPR package.
- Uniqueness of the anonymized address rests entirely on
  `User::getAnonymizedEmail()`. Without it the trait writes the literal string
  `email` into a unique column, and the second user in the batch fails with
  `SQLSTATE[23000]`.

### Closure command (`routes/console.php`)

| Signature | Purpose |
|---|---|
| `inspire` | Prints an inspiring quote. |

## Scheduler (`Kernel::schedule`)

| Frequency | Task |
|---|---|
| Every minute | `queue:work --name=kozteruletek-job-1 --queue=default --max-time=25 --max-jobs=100 --sleep=3 --tries=3 --backoff=20`, with overlap protection. |
| Hourly at `:50` | `users:purge-unverified` |
| Every 5 minutes | `events:expire-pending` |
| Daily at `07:00` | `gdpr:anonymize-inactive` |
| Daily at `07:10` | `gdpr:notify-anonymization` |
| Daily | `maintenance:purge-log-history` |
| Daily | `maintenance:daily-cleanup` |
| Daily | `statistics:record-daily-users` |
| Every minute | `groups:apply-future-changes` |
| Every minute | `newsletters:send-due` |
| Hourly | `statistics:record-active-users` |
| Every minute | `scheduler:heartbeat` |
| Daily | `gdpr:anonymizeInactiveUsers` (scheduled by the Dialect GDPR package itself) |

## Known Behavioural Quirks

These are documented deliberately, pinned by characterization tests, and left
unchanged during the framework upgrade:

- **`newsletters:send-due` stops on an unknown recipient group.** If a
  newsletter's `send_to` is not one of `groupCreators`, `groupAdmins` or
  `groupServants`, the command returns immediately, skipping every remaining
  newsletter in the batch. Because `sent_time` is never stamped, it retries
  every minute and blocks the queue behind it indefinitely.
- **`statistics:record-daily-users` writes the type `dialy_users`.** The typo is
  intentional: existing data rows and the `Admin\Statistics` component both
  filter on that exact string. Renaming it requires a data migration.
- **The statistics commands use `Statistics::insert()`, not `create()`.** This
  bypasses casts, observers and timestamp handling.

## Operational Notes

- Required cron entry (example):
  - `* * * * * php /path/to/artisan schedule:run >> /dev/null 2>&1`
- Queue processing in this project is started by the scheduler (no separate supervisor config documented in repo).
- Several scheduled tasks assume mail, queue, and DB configuration is already valid.
- Test coverage lives in `tests/Feature/Commands/` (one file per functional area)
  and `tests/Unit/Scheduler/SchedulerRegressionTest.php` (schedule shape).
