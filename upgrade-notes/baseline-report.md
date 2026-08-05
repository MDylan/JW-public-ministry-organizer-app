# Behavior Baseline Snapshot (TODO 03)

Recorded: **2026-08-05**, branch `v2-dev`, before any upgrade code change.

This is the reference point every later upgrade phase compares against.

## Runtime used

| Component | Version |
| --- | --- |
| PHP (`php81`, `C:\scripts\php81.bat`) | 8.1.30 NTS |
| Laravel | 8.83.1 |
| PHPUnit | 9.5.14 |
| Composer | 2.10.2 |
| Node / npm | 24.18.0 / 12.0.2 |
| MySQL | running, test schema `kozter_testing` |

`php81` resolves through `C:\scripts\php81.bat` and is **only reachable from PowerShell**, not from a POSIX shell (`C:\scripts` is not on the Bash PATH).

## Test suite: GREEN

```
PHPUnit 9.5.14 by Sebastian Bergmann and contributors.
...............................................................  63 / 179 ( 35%)
............................................................... 126 / 179 ( 70%)
.....................................................           179 / 179 (100%)

Time: 00:36.511, Memory: 92.00 MB

OK (179 tests, 593 assertions)
```

- **179 tests, 593 assertions, 0 failures, 0 errors, 0 skipped, 0 risky.** Runtime 36.5s.
- No PHP 8.1 deprecation notices surfaced.
- The stale `.phpunit.result.cache` (dated 2026-03-19, 110 defect entries) was deleted before this run. Those entries referenced tests that no longer exist and were **not** evidence of real failures. The cache has since been regenerated from this green run.
- Correction to the roadmap's earlier estimate: the suite is **179** executed cases, not ~184.

**Conclusion: Phase 1 starts from a genuinely green baseline.**

## Routing

- **91 routes total, 79 name registrations, 78 unique names.**
- `tests/Fixtures/route-contracts.json` covers **70** named routes. The 8 named routes it does not cover are all vendor-provided:
  - `debugbar.assets.js`, `debugbar.assets.css`, `debugbar.cache.delete`, `debugbar.clockwork`, `debugbar.openhandler` - present only because the dev-only Debugbar provider is hard-registered in `config/app.php` (see TODO 25).
  - `livewire.message`, `livewire.upload-file`, `livewire.preview-file` - **these change in Livewire 3** (see Phase 6).
- So the contract fixture covers **every application-owned named route**. That is a stronger safety net than the raw 70-of-78 ratio suggests.

### Duplicate route names: resolved empirically

The roadmap flagged two duplicate names. The live routing table settles what actually happens:

**`verification.verify` - the Fortify definition wins outright.**

```
name:   verification.verify
method: GET|HEAD
uri:    email/verify/{id}/{hash}
action: Laravel\Fortify\Http\Controllers\VerifyEmailController@__invoke
middleware: web, Authenticate:web, ValidateSignature, ThrottleRequests:6,1
```

Only **one** entry appears in the routing table. `Route::get()` overwrites by `method + domain + uri`, so the closure at `routes/web.php:122` is **dead code that never executes**. This upgrades TODO 26 from "ordering is fragile, whichever provider boots last wins" to a concrete finding: the `web.php` closure can be deleted, and doing so changes nothing at runtime. Verify the middleware stack above is the intended one before removing it.

**`password.confirm` - genuinely registered twice.**

```
password.confirm   GET|HEAD   confirm-password   -> Closure
password.confirm   POST       confirm-password   -> Closure
```

Both survive because they differ by HTTP method. This works for routing, but `route('password.confirm')` resolves against the **last** registration, so URL generation points at the POST route. Worth confirming that is intended (TODO 26).

## Scheduler

`php81 artisan schedule:list` shows **9 scheduled entries, of which 8 have an empty Command column** - they are inline closures with no description. Only `queue:work` is a named command.

| Interval | Command |
| --- | --- |
| `* * * * *` | `queue:work --name=kozteruletek-job-1 --queue=default --max-time=25 --max-jobs=100 --sleep=3 --tries=3 --backoff=20` |
| `50 * * * *` | (unnamed closure) |
| `*/5 * * * *` | (unnamed closure) |
| `0 7 * * *` | (unnamed closure) |
| `10 7 * * *` | (unnamed closure) |
| `0 0 * * *` | (unnamed closure) x2 |
| `* * * * *` | (unnamed closure) |
| `0 * * * *` | (unnamed closure) |

This is direct visual confirmation of TODO 06: the scheduler is opaque from the outside, and none of those 8 closures can be invoked or tested individually today.

## Artisan commands unavailable on this baseline

- `artisan about` - introduced in Laravel 9. Not available; runtime facts captured manually in `baseline-versions.txt` instead.
- `artisan config:show` - introduced in Laravel 11. Not available.

Re-capture both once the relevant hop lands, for a richer after-picture.

## Files in this snapshot

| File | Contents |
| --- | --- |
| `baseline-phpunit.txt` | Full test run output |
| `baseline-routes.json` | `route:list --json` - 91 routes, machine-readable |
| `baseline-routes.txt` | `route:list` - human-readable with middleware |
| `baseline-schedule.txt` | `schedule:list` |
| `baseline-composer-tree.txt` | `composer show --tree` |
| `baseline-composer-direct.txt` | `composer show --direct` - direct dependencies with installed versions |
| `baseline-versions.txt` | PHP / Laravel / Composer / Node / npm versions |

## How to compare after a hop

```powershell
php81 vendor/phpunit/phpunit/phpunit --colors=never    # or `php` once on Laravel 10+
php81 artisan route:list --json | Out-File -Encoding utf8 upgrade-notes/after-<phase>-routes.json
php81 artisan schedule:list      | Out-File -Encoding utf8 upgrade-notes/after-<phase>-schedule.txt
```

Note: `vendor/bin/phpunit` is a POSIX shell wrapper - calling it via `php81` just prints the script. Use `vendor/phpunit/phpunit/phpunit` (or `vendor/bin/phpunit.bat`).
