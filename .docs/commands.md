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
| `gdpr:anonymize-inactive` | `AnonymizeInactiveUsers` | Anonymizes users inactive beyond `gdpr.settings.ttl` months. Skips anyone the succession rule blocks (see below), then detaches group memberships and anonymizes. Reports the skipped count. No-op when `gdpr.enabled` is false. |
| `gdpr:notify-anonymization` | `NotifyUpcomingAnonymization` | Warns users approaching the retention limit, and separately alerts group editors (`roler`, `admin`) about members in a narrow 6-7 day window. Both halves apply the same succession rule as the anonymizer, so a user who cannot be anonymized is not warned either. No-op when `gdpr.enabled` is false. |
| `gdpr:anonymizeInactiveUsers` | `PackageAnonymizeInactiveUsers` | Overrides the Dialect package command of the same name (see below). Same behaviour as the package's, minus the redundant `isAnonymized` write that bypassed the succession rule. |
| `maintenance:purge-log-history` | `PurgeLogHistory` | Deletes `LogHistory` entries older than three months. |
| `maintenance:daily-cleanup` | `DailyCleanup` | Force-deletes soft-deleted events older than three months and `GroupMessage` rows older than a week. |
| `statistics:record-daily-users` | `RecordDailyUserStatistics` | Records the daily active user count as statistics type `dialy_users`. |
| `groups:apply-future-changes` | `ApplyGroupFutureChanges` | Applies `GroupFutureChange` records whose `change_date` is today. |
| `newsletters:send-due` | `SendDueNewsletters` | Sends admin newsletters dated today that are published, flagged for sending, and not yet sent. |
| `statistics:record-active-users` | `RecordActiveUserStatistics` | Records the hourly active user count as statistics type `active_users`. |
| `scheduler:heartbeat` | `RecordSchedulerHeartbeat` | Updates `settings.last_schedule_run`, which the admin UI uses to show whether cron is alive. |

### Anonymization is conditional on succession, not on role

The rule lives in `App\Support\Gdpr\AnonymizationPolicy` and is enforced from
`User::anonymize()`, which overrides the package trait's method (trait alias
`Anonymizable::anonymize as anonymizeAttributes`). Placing it on the model is
deliberate: **three** separate code paths anonymize users - the project command,
the package command, and the user-initiated profile request - and a rule living
in any one command would be bypassed by the others.

Two conditions, both about whether somebody is left to take over:

1. A `mainAdmin` may be anonymized only if another **non-anonymized** `mainAdmin`
   remains. `role` is in `$gdprAnonymizableFields`, so anonymization also demotes
   them to `registered`; the last one would leave the site with no administrator.
2. A user holding `group_role = 'admin'` in a group may be anonymized only if
   every such group passes `pwbs_check_group_other_admins()` - the **same helper**
   the group "leave" flow uses (`Groups\ListGroups::confirmLogout()`), so the two
   rules cannot drift apart. The `groupCreator` role is no longer a condition in
   itself: creating a group makes the creator an admin of it, so rule 2 covers it.

`Group::activeAdmins()` backs the helper's successor lookup. It filters what
`Group::groupAdmins()` does not: anonymized users and unaccepted invitations.
Without that filter the package command - which anonymizes but leaves
memberships in place - produces rows that look like admins, and a group's real
admins can be anonymized one after another. `groupAdmins()` itself is unchanged;
its other call sites check the caller's own membership.

`User::anonymize()` returns `false` when blocked and does nothing. Callers that
need a consequence must ask the policy first:

- `gdpr:anonymize-inactive` checks **before** detaching memberships - the reverse
  order would leave a blocked user memberless and destroy the succession evidence.
- `deletePersonalDataController` checks in both steps (`asktodelete` before
  sending the signed link, `deletePersonalData` again when it is opened, since
  the link is valid for 60 hours) and flashes `user.delete.no_successor_admin` /
  `user.delete.no_successor_group` to the profile page. A GDPR request must not
  vanish silently.

Covered by `tests/Feature/Gdpr/AnonymizationSuccessionTest.php`.

### Package command registration (`Kernel::$commands`)

- `App\Console\Commands\PackageAnonymizeInactiveUsers::class` **replaces**
  `Dialect\Gdpr\Commands\AnonymizeInactiveUsers::class`, which used to be
  registered here. Same signature (`gdpr:anonymizeInactiveUsers`), so the later
  registration wins: the package registers from its provider through an
  `Artisan::starting()` callback, while `Kernel::$commands` is resolved after the
  console application is constructed.
- Why the override exists: the package's `handle()` runs `$user->anonymize()`
  **and then** `$user->update(['isAnonymized' => true])`. The model guard stops
  the first call but not the second, so a protected user would keep their data
  yet be flagged anonymized - invisible in every group listing and unable to
  receive any mail. The subclass skips the whole iteration when the policy
  blocks. Everything else about the package's behaviour is preserved.
- The package schedules the command from `GdprServiceProvider::boot()` inside an
  `app->booted()` callback, which runs **after** `Kernel::schedule()`. It
  therefore cannot be filtered out of the schedule; unscheduling it would require
  disabling package auto-discovery, which belongs to the TODO 16 package decision.

#### The two anonymizers still differ, and both run every day

Measured by `tests/Feature/Gdpr/AnonymizeCommandDivergenceTest.php`:

| | `gdpr:anonymizeInactiveUsers` (package name, project class) | `gdpr:anonymize-inactive` (project) |
|---|---|---|
| Runs at | daily `00:00` | daily `07:00` |
| Succession rule | applied | applied |
| Group memberships | left intact | deleted first |

Consequences worth knowing before changing either one:

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
| Daily | `gdpr:anonymizeInactiveUsers` (scheduled by the Dialect GDPR package itself; served by `PackageAnonymizeInactiveUsers`) |

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
