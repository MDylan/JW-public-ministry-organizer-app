# Commands Documentation

## Overview

Command-related behavior is split between:

- `app/Console/Kernel.php` (registered commands + scheduler)
- `routes/console.php` (closure-based Artisan commands)

## Registered Artisan Commands

### Application command registration (`Kernel::$commands`)

- `Dialect\Gdpr\Commands\AnonymizeInactiveUsers::class`

This is a package command registered explicitly in the app kernel.

### Closure command (`routes/console.php`)

| Signature | Purpose |
|---|---|
| `inspire` | Prints an inspiring quote. |

## Scheduler (`Kernel::schedule`)

The scheduler is heavily used for queue processing, cleanup, anonymization, notifications, and statistics.

| Frequency | Task |
|---|---|
| Every minute | Runs queue worker command: `queue:work --name=kozteruletek-job-1 --queue=default --max-time=25 --max-jobs=100 --sleep=3 --tries=3 --backoff=20` with overlap protection. |
| Hourly at `:50` | Deletes users older than one week who have not verified email. |
| Every 5 minutes | Marks pending events (`status=0`) as deleted (`status=2`) when start time has passed. |
| Daily at `07:00` | GDPR anonymization of inactive users (except `mainAdmin` and `groupCreator`). |
| Daily at `07:10` | Sends anonymization warning notifications to users and group admins. |
| Daily | Deletes old `LogHistory` entries older than 3 months. |
| Daily | Deletes old soft-deleted events (older than 3 months), old `GroupMessage` entries (>7 days), and stores daily active-user counter (`dialy_users`). |
| Every minute | Applies due `GroupFutureChange` updates and sends scheduled admin newsletters. |
| Hourly | Stores hourly active users counter (`active_users`). |
| Every minute | Updates `settings.last_schedule_run` timestamp. |

## Operational Notes

- Required cron entry (example):
  - `* * * * * php /path/to/artisan schedule:run >> /dev/null 2>&1`
- Queue processing in this project is started by the scheduler (no separate supervisor config documented in repo).
- Several scheduled tasks assume mail, queue, and DB configuration is already valid.
