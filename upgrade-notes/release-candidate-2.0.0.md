# Release-candidate report: 2.0.0 (TODO 73)

Opened: **2026-10-25**, branch `v2-dev`. This is the pass/fail record the
roadmap's Phase 12 asks for. It has two halves:

- **Automated** - run on the development machine and recorded below. Done.
- **Manual smoke checks** - a browser walk by the developer. The matrix in
  section 3 is filled in as the walk goes; an item is closed only with a
  result in its row.

The advisory ledger (TODO 73.1) is a separate item and lands in section 4
once it has run. It belongs after the final `composer update`, so that it
describes the lock that ships.

## 1. The stack under test

| Component | Version |
| --- | --- |
| Laravel | 13.38.0 |
| PHP | 8.3.16 (floor `^8.3`) |
| Livewire | 3.8.11 |
| Laravel Fortify | 1.41.0 (with `laravel/passkeys`) |
| PHPUnit | 12.5.40 |
| Composer | 2.10.2 |

Full snapshots, taken on the same day:

| File | Contents |
| --- | --- |
| `rc-2.0.0-about.txt` | `artisan about` |
| `rc-2.0.0-routes.json` | `route:list --json` - 95 routes, 92 names (Debugbar's routes absent: `APP_ENV=production`) |
| `rc-2.0.0-schedule.txt` | `schedule:list` - 16 entries, every one a named command |
| `rc-2.0.0-composer-direct.txt` | `composer show --direct` |

Compared with the TODO 03 baseline (`baseline-*.txt`, Laravel 8.83.1): the
scheduler's eight anonymous closures are all named commands now (TODO 06),
so `schedule:list` says what each line does.

`version.txt` still reads `1.2.0` on this branch. The 2.0.0 number is set
with the release itself (TODO 75), not here.

**Finding, half fixed the same day: an install of this branch was offered
the 1.x update.** `UpdateBranch::allows()` admits any remote version whose
major is not above the installed one, the installed one is read from
`version.txt` (`1.2.0`, major 1), and `config/laraupdater.php` defaulted the
channel to `https://updates.teruletek.hu/v1`, where 1.2.1 is published - so the
updater card offered 1.2.1, and accepting it would have overwritten a Laravel
13 install with the Laravel 8 archive.

- **Fixed:** the channel default is `https://updates.teruletek.hu/v2`, the
  developer's 2.x channel (`UpdateChannelTest`). Nothing is published there
  yet, so the card offers nothing - unless the install's `.env` sets
  `UPDATE_CHANNEL` by hand.
- **Still open, with rc1:** `version.txt`. Until it reads 2.x, a 2.x release on
  `v2` is a higher major to this install and is refused as an automatic
  update. The number moves when rc1 is cut, after the developer's local test
  of every function; `UpdateBranchCeilingTest` assumes major 1 and moves with
  it.

## 2. Automated results

| # | Check | How | Result |
| --- | --- | --- | --- |
| A1 | Full suite | `composer test` on `5d0aa1e6` | **PASS** - 1747 tests, 5286 assertions, 0 failures, 0 errors, 4:28 (inside Composer's default 300-second process timeout, but not by much) |
| A2 | No PHP deprecation from `app/` | `FailsOnApplicationDeprecations`, part of A1 | **PASS** |
| A3 | Every cache builds | `artisan optimize` (config, events, routes, views), then `optimize:clear` | **PASS** |
| A4 | Migrations | `migrate:status` on the local application database (read only) | **PASS** - nothing pending, the TODO 69 `passkeys` table included |
| A5 | Scheduler dispatches | `schedule:run` against `kozter_testing` | **PASS** - the due entries ran: `queue:work`, `events:expire-pending`, `groups:apply-future-changes`, `newsletters:send-due`, `scheduler:heartbeat`; the heartbeat row was written |
| A6 | Queue worker processes a job | a `CalulcateUserNameIndexProcess` queued on the `database` connection, then A5's `queue:work` | **PASS** - queue empty afterwards, `failed_jobs` empty |
| A7 | Every scheduled command boots and exits 0 | each of the other eleven run by hand against `kozter_testing` | **PASS** - `users:purge-unverified`, `gdpr:anonymize-inactive`, `gdpr:notify-anonymization`, `activitylog:clean --force`, `gdpr:purge-old-events`, `maintenance:purge-old-group-data`, `maintenance:purge-log-history`, `maintenance:daily-cleanup`, `statistics:record-daily-users`, `statistics:record-active-users`, `weather:refresh` |

**How A5-A7 were isolated.** The local `.env` points at `kozter_live`, and
several of these commands delete, anonymise or send mail. They were run with
`DB_DATABASE=kozter_testing`, `CACHE_DRIVER=array` (the file cache is shared
with the local site, and the settings cache would otherwise have taken the
test database's rows), `MAIL_MAILER=log` and `QUEUE_CONNECTION=database` set
in the shell. The scheduler's child processes inherit them - the heartbeat
landed in `kozter_testing`, and the local database's heartbeat was unchanged
afterwards.

**What A5-A7 do not show.** On an empty database the commands find nothing to
work on, so this proves they boot and run on Laravel 13 - their logic is the
suite's (`tests/Feature/Commands`, `tests/Feature/Jobs`,
`tests/Unit/Scheduler`, `tests/Feature/Queue`). **The scheduler has not run on
this machine's own database since 2026-08-08** (the `last_schedule_run`
setting), i.e. no cron is wired up locally; section 3, group H, is where a
real host's scheduler is watched for a while.

## 3. Manual smoke checks

### How to run the walk

- **On a test install, not on live data.** Most of the checks below write:
  registrations, events, invitations, deletions. A copy of the database on a
  separate install (as for the 1.2.1 pre-release test) is the safe ground; the
  TODO 47.1 walk could stay read-only, this one cannot.
- **Over https.** Passkeys (A5 below) need a secure origin; `http://kozter.test`
  redirects to https, which is what the browser needs.
- **Mail caught**, as before (Mailtrap): registration, verification, reset,
  invitation, newsletter and GDPR mails are all in the list.
- **Two browsers or profiles**: an administrator and a plain member side by side.
- **`APP_ENV=production`, as on a live host.** The admin "debugbar" switch is
  hidden there by design since TODO 72.2. Do one pass with `APP_DEBUG=false`
  for group I.
- **Keep the browser console open throughout.** Any `TypeError`, a 500 on
  `livewire/update`, or a `ComponentNotFoundException` is a failure of the row
  it happened in.

Result: **PASS**, **FAIL** (with a note), or **N/A** (with the reason).

### A. Authentication

| # | Check | Expected | Result | Note |
| --- | --- | --- | --- | --- |
| A1 | Log in, log out | Lands on home; logout returns to login | | |
| A2 | Forgot password, reset by the mailed link | Mail subject is translated (TODO 67); new password works | | |
| A3 | Register a new account, verify by the mailed link | Unverified user sees the notice; the link verifies; the profile prompt follows | | |
| A4 | Two-factor: enable, confirm with a code, download recovery codes | The confirm step asks for the password if not confirmed recently (TODO 69); `.txt` download works | | |
| A5 | Two-factor login challenge, with a code and with a recovery code | Both log in; a used code is refused the second time | | |
| A6 | Passkey: add one, log in with it, confirm the password with it | All three ceremonies succeed on https (TODO 69) | | |

### B. Profile

| # | Check | Expected | Result | Note |
| --- | --- | --- | --- | --- |
| B1 | Change name and phone | Saved; shown in the header | | |
| B2 | Change e-mail address | A pending address; the mailed link confirms it | | |
| B3 | Change password; log out other devices | The other browser is logged out | | |
| B4 | GDPR export | The download opens and holds the user's data | | |
| B5 | Ask to delete personal data | The signed link arrives by mail (do not follow it on an account you need) | | |

### C. Groups

| # | Check | Expected | Result | Note |
| --- | --- | --- | --- | --- |
| C1 | Create a group; the group-creator request | Created; the request mail is rate-limited (TODO 70) | | |
| C2 | Invite a new and an existing address | Finish-registration link works and is valid for seven days, not longer (TODO 70) | | |
| C3 | Accept and reject an invitation from `/groups` | Only an actual pending invitation can be accepted (TODO 70) | | |
| C4 | Leave a group | Asks for the password, returns to `/groups` and **reopens the exit dialog** (TODO 70.1); the last admin cannot leave | | |
| C5 | Member list as admin: change a role, remove a member; as a plain member: search | Changes stick; a plain member does not see hidden members or private addresses (TODO 70) | | |
| C6 | Group edit form: service days | An `18:00 - 00:00` day saves as `00:00`; a mis-clicked start moves only the start (TODO 42.1, 72.3) | | |
| C7 | Group edit form: a future change, then the pending-changes screen | Saved for the chosen date; the screen shows and can remove it | | |
| C8 | Special-date dialog | Time lists as in C6; save and delete work | | |
| C9 | Posters | Text formatting survives; a `<script>` typed into the editor does not run (TODO 70) | | |
| C10 | Message board | Post, delete own; the board's own settings cannot be changed by a member | | |
| C11 | News: create with formatting and a PDF and a `.docx` attachment; delete | Both attachments list with a working preview; deleting asks for the password and **reopens the dialog** (TODO 70.1) | | |
| C12 | Group statistics and history pages | Render with the group's own data only | | |
| C13 | Delete a group | The confirmation page asks for the password; the group and its data go | | |

### D. Calendar and events

| # | Check | Expected | Result | Note |
| --- | --- | --- | --- | --- |
| D1 | Calendar: month navigation, jump-to-calendar link | Renders; polling keeps it current | | |
| D2 | Day modal: slots, next and previous day | The next-day arrow stops at `max_extend_days` (TODO 72.3) | | |
| D3 | Create an event, in a group without and with approval | Accepted at once / pending | | |
| D4 | Edit and delete an own event | Saved / gone; another group's event cannot be touched (TODO 70) | | |
| D5 | As a servant: bulk accept and reject in the day modal | Only the open day's pending events change | | |
| D6 | As a servant: open a member's event | The member's statistics list shows the last 30 and next 15 days | | |
| D7 | Service report; `/lastevents`; home page polling | Report saves with a literature of the group only; pages render | | |

### E. Administration

| # | Check | Expected | Result | Note |
| --- | --- | --- | --- | --- |
| E1 | `/admin/users`: search, edit, log in as a user, step back | Impersonation asks for the password; the way back works (TODO 70.1 note) | | |
| E2 | `/admin/settings`: every switch, the mail test, reCAPTCHA keys | Each saves and takes effect; the mail field sends one request on leaving it; no debugbar switch on production (TODO 72.2) | | |
| E3 | Static pages: list, create, edit | The side menu updates at once (no stale menu cache, TODO 72.1) | | |
| E4 | Newsletters: edit and send | The scheduled send goes out (H2) | | |
| E5 | `/admin/statistics` | Renders | | |
| E6 | Translation editor `/admin/translate` | Edit a key, page through the list | | |
| E7 | Updater card - **look, do not press Update** | Reads the `v2` channel; with nothing published there it offers nothing. If it offers 1.x, the install's `.env` sets `UPDATE_CHANNEL` - remove it (section 1) | | |

### F. Installer

| # | Check | Expected | Result | Note |
| --- | --- | --- | --- | --- |
| F1 | A fresh copy on an empty database: token unlock, every step, the first admin, complete | Finishes; `storage/app/installed.txt` written, the token file gone | | |
| F2 | After F1: `/setup/start` | 404 (TODO 72) | | |

### G. Refusals

| # | Check | Expected | Result | Note |
| --- | --- | --- | --- | --- |
| G1 | An outsider opens a group's URLs | 403 | | |
| G2 | A plain member opens the group's edit, users and statistics pages | 403 | | |
| G3 | `GET /groups/{group}/logout` typed into the address bar | 405 (TODO 70) | | |
| G4 | `/api/user` | 404, no `Access-Control-Allow-Origin` header (TODO 71) | | |

### H. Scheduler and queue on the host

| # | Check | Expected | Result | Note |
| --- | --- | --- | --- | --- |
| H1 | Cron calls `php artisan schedule:run` every minute; leave it for an hour | The admin panel's scheduler indicator stays current | | |
| H2 | A change that queues work (a group edit, a newsletter) | The queue drains; `failed_jobs` stays empty | | |

### I. Error pages (`APP_DEBUG=false`)

| # | Check | Expected | Result | Note |
| --- | --- | --- | --- | --- |
| I1 | An unknown URL | The application's 404 page | | |
| I2 | An expired page (delete the session cookie), then an action | The translated page-expired prompt; OK reloads (TODO 46) | | |
| I3 | A server error | The `errors/500` page, no raw message | | |

## 4. Advisory ledger (TODO 73.1)

Not run yet - see the roadmap. It is recorded here once the final lock exists.

## 5. Verdict

Open. The automated half passes; the release candidate is accepted when
every row in section 3 carries a result and none is an unexplained **FAIL**.
