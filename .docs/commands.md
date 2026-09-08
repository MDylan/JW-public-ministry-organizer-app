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
| `users:purge-unverified` | `PurgeUnverifiedUsers` | Deletes users who have not verified their email within a week. **Skips anonymized rows** - anonymization empties `email_verified_at` (TODO 33.2), and those rows are retained deliberately so `events.user_id` and `group_user.user_id` keep resolving. **Deletes one instance at a time on purpose** (TODO 33.5): a builder delete fires no model events, so `UserObserver::deleted()` never ran and the pending e-mail row of every purged user was left orphaned. |
| `events:expire-pending` | `ExpirePendingEvents` | Marks still-pending events (`status=0`) as denied (`status=2`) once their start time has passed. |
| `gdpr:anonymize-inactive` | `AnonymizeInactiveUsers` | Anonymizes users inactive beyond `gdpr.settings.ttl` months. Skips anyone the succession rule blocks (see below), then detaches group memberships and anonymizes. Reports the skipped count. No-op when `gdpr.enabled` is false. |
| `gdpr:notify-anonymization` | `NotifyUpcomingAnonymization` | Warns users approaching the retention limit, and separately alerts group editors (`roler`, `admin`) about members in a narrow 6-7 day window. Both halves apply the same succession rule as the anonymizer, so a user who cannot be anonymized is not warned either. No-op when `gdpr.enabled` is false. |
| `gdpr:purge-old-events` | `PurgeOldEvents` | Permanently deletes events whose `day` is older than `retention.events_months` (13), together with their `event_service_reports` rows via the FK cascade. No-op when `gdpr.enabled` is false. Accepts `--dry-run`. |
| `maintenance:purge-old-group-data` | `PurgeOldGroupData` | Permanently deletes `DayStat` and `GroupDate` rows older than the `group_data_retention` setting (`0` off, `12` or `24` months). Not gated on GDPR - these tables hold no personal data. Accepts `--dry-run`. |
| `maintenance:purge-log-history` | `PurgeLogHistory` | Deletes `LogHistory` entries older than three months. |
| `maintenance:daily-cleanup` | `DailyCleanup` | Force-deletes soft-deleted events older than three months and `GroupMessage` rows older than a week. |
| `statistics:record-daily-users` | `RecordDailyUserStatistics` | Records the daily active user count as statistics type `dialy_users`. |
| `groups:apply-future-changes` | `ApplyGroupFutureChanges` | Applies `GroupFutureChange` records whose `change_date` is today. |
| `newsletters:send-due` | `SendDueNewsletters` | Sends admin newsletters dated today that are published, flagged for sending, and not yet sent. |
| `statistics:record-active-users` | `RecordActiveUserStatistics` | Records the hourly active user count as statistics type `active_users`. |
| `scheduler:heartbeat` | `RecordSchedulerHeartbeat` | Updates `settings.last_schedule_run`, which the admin UI uses to show whether cron is alive. |
| `weather:refresh` | `RefreshWeatherCache` | Refreshes the cached OpenWeather data for every DISTINCT city used by a group with `weather_enabled = 1` and a non-null `city_id`. No-op when the global `weather` setting is off. Partial failures are warnings, not a non-zero exit - an unknown city must not alarm the scheduler. |

### Anonymization is conditional on succession, not on role

The rule lives in `App\Support\Gdpr\AnonymizationPolicy` and is enforced from
`User::anonymize()`, which overrides the trait's method (trait alias
`Anonymizable::anonymize as anonymizeAttributes`). Placing it on the model is
deliberate: **four** separate code paths anonymize users - and a rule living in
any one command would be bypassed by the other three.

| Path | Where |
|---|---|
| Nightly command, 07:00 | `AnonymizeInactiveUsers::handle()` |
| User-initiated GDPR request | `deletePersonalDataController::deletePersonalData()` |
| Group deletion | `DeleteGroupDataProcess::handle()` - anonymizes a member left with no other group |
| One-off backfill | `2024_12_01_223022_anonymize_old_data` migration |

The last two were undercounted until the TODO 16 assessment; both discard the
return value, so a blocked user is simply left alone. There used to be a fifth -
the Dialect package's own 00:00 command - removed with the package in TODO 33.2.

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

### One anonymizer, since TODO 33.2

`Kernel::$commands` is **empty**. Until TODO 33.2 it held
`PackageAnonymizeInactiveUsers`, a subclass whose only job was to shadow
`Dialect\Gdpr\Commands\AnonymizeInactiveUsers` - and it won only because
`Kernel::getArtisan()` resolves `$commands` *after* the `Artisan::starting()`
callbacks a service provider registers through. A data-protection guarantee
resting on framework-internal ordering was one of the reasons the package went.

The removed command ran at `00:00`, seven hours before `gdpr:anonymize-inactive`,
and **never detached group memberships**. That is what used to leave an
anonymized user on the newsletter recipient list, and it is retired for good.
Measured by `tests/Feature/Gdpr/AnonymizeCommandTest.php`.

Consequences still worth knowing:

- `User::userGroupsEditable()` / `userGroupsDeletable()` do **not** filter
  `isAnonymized` (unlike `Group::groupUsers()` and `Group::users()`, which do).
  The nightly command detaches memberships, so it no longer produces such a row -
  but `DeleteGroupDataProcess` and the backfill migration call
  `User::anonymize()` directly and leave memberships alone.
- No mail reaches an anonymized user regardless, because
  `User::routeNotificationFor()` returns `null` for any anonymized user and for
  any address failing `FILTER_VALIDATE_EMAIL`. That method is load-bearing.
- Uniqueness of the anonymized address rests entirely on
  `User::getAnonymizedEmail()`. Since TODO 33.2 a keyless field declared without
  such a method raises a `LogicException` instead of writing the column's own
  name into a unique column, which is what used to kill the second row of a
  batch with `SQLSTATE[23000]`.

#### What anonymization writes

Two declarations on `App\Models\User`, and the split is the point.

| List | Columns |
|---|---|
| `$gdprAnonymizableFields` (replacement values) | `email` (`getAnonymizedEmail()`, a 10-character token), `password` (`getAnonymizedPassword()`, a hash of 64 random characters), `name` = `Anonym`, `role` = `registered`, `isAnonymized` = `1` |
| `$gdprNullFields` (simply emptied) | `phone_number`, `congregation`, `show_fields`, `opted_out_of_notifications`, `last_login_ip`, `firstDay`, `two_factor_secret`, `two_factor_recovery_codes`, `two_factor_confirmed_at`, `remember_token`, `calendars`, `last_login_time`, `email_verified_at`, `accepted_gdpr` |

The two-factor confirmation used to sit in the **replacement-value** list with a
`0`, because the `two_factor_confirmed` boolean it lived in was NOT NULL and
"never confirmed" was not expressible. TODO 39.2 moved the confirmation onto the
nullable `two_factor_confirmed_at`, so it joined the null list and the
replacement value went away.

The last seven of the null list, plus `password` and the two-factor confirmation, were
not anonymized at all before TODO 33.2. Three of those mattered beyond tidiness:
the encrypted TOTP secret and its recovery codes outlived the anonymization
forever, a live "remember me" cookie kept working because `Auth::logout()` only
runs on the profile path, and the password hash itself survived untouched.

The trait writes with `forceFill()->save()` rather than `update()`, because
`two_factor_secret`, `two_factor_recovery_codes`, `two_factor_confirmed_at` and
`remember_token` are **not** in `User::$fillable` - `update()` would have dropped
them silently. See `app/Support/Gdpr/Anonymizable.php`.

**Rows anonymized before TODO 33.2 are repaired by a one-off backfill**,
`2026_08_10_140000_reanonymize_users_for_the_null_field_list`. No ordinary run
would ever reach them: `gdpr:anonymize-inactive` selects on `isAnonymized = 0`,
so a row is anonymized exactly once. The migration writes with the query builder
rather than through `User::anonymize()`, so the succession rule cannot silently
skip a row that still holds a real password hash, and its column list is frozen
at the TODO 33.2 state - a later addition to `$gdprNullFields` needs its own
backfill. It costs roughly one bcrypt per affected row. Covered by
`tests/Feature/Gdpr/ReanonymizeBackfillTest.php`.

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
| Daily at `03:20` | `activitylog:clean --force` (Spatie's own command, scheduled here since v1-patch E; skipped by a `when()` filter while `gdpr.enabled` is false) |
| Daily at `03:30` | `gdpr:purge-old-events` |
| Daily at `03:40` | `maintenance:purge-old-group-data` |
| Daily | `maintenance:purge-log-history` |
| Daily | `maintenance:daily-cleanup` |
| Daily | `statistics:record-daily-users` |
| Every minute | `groups:apply-future-changes` |
| Every minute | `newsletters:send-due` |
| Hourly | `statistics:record-active-users` |
| Every minute | `scheduler:heartbeat` |
| `0 */3 * * *` | `weather:refresh` |

## Known Behavioural Quirks

These are documented deliberately, pinned by characterization tests, and left
unchanged during the framework upgrade:

- ~~**`newsletters:send-due` stops on an unknown recipient group.**~~ **Fixed in
  v1-patch B1.** It used to `return` on an unrecognised `send_to`, skipping every
  REMAINING newsletter in the batch, and since `sent_time` was never stamped it
  retried every minute and blocked the queue indefinitely. The offending row is
  now skipped and reported, the rest are delivered, and the command exits
  non-zero to say something was left behind. `sent_time` deliberately stays null
  on the skipped row: nothing was sent.
- **`weather:refresh` costs 2 API calls per city per run.** The free OpenWeather
  tier allows 1000 calls/day and 60/minute, which is why the schedule is
  `0 */3 * * *` rather than hourly - roughly 60 cities fit inside the cap. The
  forecast itself is only 3-hourly, so a tighter cadence would buy nothing. The
  `WeatherCache` 15-minute `last_try` throttle remains the backstop, and a
  failed refresh deliberately does NOT touch `updated_at`, so stale data never
  looks fresh.
- **`activitylog:clean` must be scheduled with `--force`.** The Spatie command
  opens with `ConfirmableTrait::confirmToProceed()`, which prompts in
  `production`. Run from the scheduler there is no TTY, `confirm()` returns its
  `false` default, the command prints `Command Cancelled!`, exits 1 and deletes
  nothing - silently, since nothing surfaces a scheduler exit code. "Already
  clean" and "cancelled every night" are indistinguishable from the outside.
  `SchedulerRegressionTest::EXPECTED_SCHEDULE` carries the flag inside the
  command string, so dropping it turns the test red. Its window is
  `config('activitylog.delete_records_older_than_days')` = 90 days, which had
  been declared but never applied before v1-patch E4.
- **The retention purges delete through the query builder, never per model.**
  `Eloquent\Builder::forceDelete()` is a bare `$this->query->delete()` and fires
  no model events. Deleting events per model instance would run
  `EventObserver::deleted()` on every row - a mail to the publisher and to every
  group admin, plus one `log_histories` row each. On the first production run
  that is 121,000 events.
  `RetentionCommandsTest::test_purge_old_events_fires_no_model_events` pins it.
- **`maintenance:purge-old-group-data` takes `group_dates` with `day_stats`.**
  `Groups\Statistics` builds its daily rows from `group_dates`, not from
  `day_stats`. Purging only the statistics would leave a fully populated table
  claiming the group served 0 hours out of N available on every historical day.
- **An unrecognised `group_data_retention` value is a no-op, not a fallback.**
  The value is whitelisted (`config('retention.group_data_options')`) rather than
  cast, in both `Admin\Settings::saveGroupDataRetention()` and
  `RetentionWindow`. Cast to int an `'x'` would become `0`, and a zero-month
  window would put the floor on today.
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
