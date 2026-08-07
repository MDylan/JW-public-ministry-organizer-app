# Models Documentation

## Overview

The project uses Eloquent models for user/group scheduling, content publishing, messaging, statistics, and system settings.

### Cross-cutting model behaviors

- **Encrypted fields** - see the dedicated section below.
- **Translatable models** use `astrotomic/laravel-translatable`:
  - `StaticPage` + `StaticPageTranslation`
  - `GroupNews` + `GroupNewsTranslation`
  - `AdminNewsletter` + `AdminNewsletterTranslation`
- **Soft deletes** are used by `Event`, `Group`, `GroupNews`, and `GroupUser` (pivot).
- **GDPR traits** (`Dialect\Gdpr`) are used by `User`, `Group`, and `Event`.
  `User` **overrides** the `Anonymizable::anonymize()` method (trait alias
  `anonymizeAttributes`) to enforce the succession rule from
  `App\Support\Gdpr\AnonymizationPolicy`, returning `false` and doing nothing
  when blocked. The guard sits on the model because **five** separate code paths
  anonymize users - see `.docs/commands.md` for the list.
  `Group` and `Event` carry `Anonymizable` with an empty `$gdprAnonymizableFields`
  for a reason that is easy to miss: `User::$gdprWith` makes `anonymize()` recurse
  into `eventsOnly` and `groupsAccepted` and call `anonymize()` on each. Remove the
  trait from either model and the user anonymization fatals. Neither declares
  `$gdprWith`, so the cascade stops one level deep.
- **Pending e-mail changes** use `ProtoneMedia\LaravelVerifyNewEmail\MustVerifyNewEmail` on `User`.
  A requested address is parked in `pending_user_emails` (a vendor model, `PendingUserEmail`, related
  through `morphs('user')`) and only reaches `users.email` when the signed link is opened. The trait
  supplies `newEmail()`, `getPendingEmail()`, `clearPendingEmail()` and
  `resendPendingEmailVerificationMail()`; see `.docs/routes.md` for the route side.

  **Nothing cleans that table up, and it collides with anonymization.** There is no foreign key, no
  observer touches it, and `User::anonymize()` never calls `clearPendingEmail()` — even though
  `email` is in `$gdprAnonymizableFields`. Two measured consequences:

  1. After anonymization the user's **real** requested address stays in `pending_user_emails`
     indefinitely.
  2. Until the signed link expires, opening it writes that real address back onto the anonymized
     user **and marks it verified**, leaving a row that reads as anonymized while carrying real data.

  Deleting a user leaves the row orphaned for the same reason. All three are pinned as known defects
  by `tests/Feature/NewEmail/PendingEmailKnownGapsTest.php`; the first two are fixed with the GDPR
  replacement (roadmap TODO 33.2), the third with the package replacement (TODO 33.5).

### Encrypted columns

Nine columns across six models use the `encrypted` cast. Behaviour is covered by
`tests/Feature/Models/EncryptedAttributeTest.php`, the schema by
`tests/Feature/Models/EncryptedColumnSchemaTest.php`.

| Column | Type | Nullable |
|---|---|---|
| `users.name` | `text` | yes |
| `users.phone_number` | `text` | yes |
| `users.congregation` | `text` | yes |
| `groups.name` | `text` | **no** |
| `groups.replyTo` | `text` | yes |
| `events.comment` | `text` | yes |
| `group_user.note` | `text` | yes |
| `group_posters.info` | `mediumtext` | **no** |
| `group_messages.message` | `longtext` | yes |

Rules that follow from the cast, all measured:

- **These columns cannot be searched or sorted in SQL.** Encryption uses a random
  IV, so the same text yields a different ciphertext every write. `where('name', $x)`
  and `assertDatabaseHas(['name' => $x])` can never match. This is why duplicate
  group names are possible, and why `users.name_index` exists - maintained by
  `CalulcateUserNameIndexProcess` from `UserObserver`, it is the only sortable
  proxy for the encrypted name.
- **`null` bypasses the cast in both directions**; an empty string does not - it
  is encrypted and read back as `''`.
- **Any write that bypasses Eloquent stores plain text**, and the next read
  through the model throws `DecryptException`. That covers query-builder writes,
  `Model::insert()` (three such call sites exist - see `.docs/commands.md` on the
  statistics commands), and raw SQL in migrations. `NotifyUpcomingAnonymization`
  reads such columns from a raw join and therefore decrypts by hand with
  `Crypt::decryptString()`.
- **A wrong `APP_KEY` fails loudly** with `DecryptException` on read - it does not
  return null or garbage. The danger is overwriting the row afterwards, not the
  read itself.
- **The columns are all `text` or wider on purpose.** A short value already
  encrypts to ~200 characters and a 100-character value passes 255, so a
  `varchar(255)` would hold roughly 30 characters of plain text and the original
  `varchar(100)` on `events.comment` could hold none at all. Four of the nine
  columns were widened by `->change()` migrations, which is what makes the schema
  test a prerequisite for the Laravel 11 upgrade.

**Pivot columns depend on `using()`.** `group_user.note` is encrypted on write
only because the relation declares `using(GroupUser::class)`:
`InteractsWithPivotTable::castAttributes()` runs the attach payload through
`newPivot()->fill()` when a custom pivot class is set, and returns it untouched
otherwise. `Group::groupUsers()`, `Group::groupUsersAll()` and `User::userGroups()`
declare it; **`User::groupsAcceptedFiltered()` does not**, so reading `note`
through that relation yields the raw ciphertext. Harmless today - no caller reads
it there - but dropping `using()` from a *writing* relation would put plain text
into the column undetectably.

## Core Identity Models

| Model | Purpose | Key Relations | Notes |
|---|---|---|---|
| `User` | Authenticated users and profile data. | `belongsToMany(Group)` variants, `hasMany(Event)`, `hasMany(GroupPosterRead)` | Implements email verification, preferred locale, 2FA confirmation, GDPR portability/anonymization, role checks, and notification routing safeguards. |
| `GroupUser` (pivot) | Membership record between user and group. | `belongsTo(User)`, morph-many `LogHistory` | Custom pivot (`group_user`) with soft delete, encrypted `note`, JSON `signs`, membership role/flags. |

## Group & Scheduling Models

| Model | Purpose | Key Relations | Notes |
|---|---|---|---|
| `Group` | Main scheduling unit (territory/group). | Many relations: members, days, dates, events, stats, news, posters, futureChanges, weather | Heavy domain model with role-aware helpers (`editors`, `currentUser`, etc.), encrypted `name/replyTo`, dynamic `colors` accessor. `groupAdmins()` lists every admin pivot row; `activeAdmins()` narrows it to non-anonymized, accepted admins and is what `pwbs_check_group_other_admins()` treats as a possible successor. |
| `GroupDay` | Weekly service-day template. | `belongsTo(Group)`, morph-many `LogHistory` | Stores weekday start/end; accessors normalize time formatting. |
| `GroupDayDisabledSlots` | Disabled time slots for service days. | `belongsTo(Group)` | Provides timestamp accessor for slot calculations. |
| `GroupDate` | Date-specific generated service day config. | `belongsTo(Group)` | Stores daily start/end, status, limits, and JSON `disabled_slots`. |
| `GroupFutureChange` | Future scheduled config snapshot for a group. | `belongsTo(Group)`, `belongsTo(User)` | JSON casts for future group/day/slot payloads. |
| `Event` | Service event booking by a user in a group. | `belongsTo(Group)`, `belongsTo(User)`, `belongsTo(accepted_by User)`, `hasMany(EventServiceReport)`, morph-many `LogHistory` | Converts `start/end` attributes to Unix timestamps via accessors, appends computed fields (`full_time`, `day_name`, `service_hour`), exposes calendar links (Google/ICS). |
| `EventServiceReport` | Post-service reporting values linked to an event. | `belongsTo(Event)`, `belongsTo(GroupLiterature)` | Tracks placements, videos, return visits, studies, notes. |
| `DayStat` | Per-day/per-slot aggregate statistics. | `belongsTo(Group)` | Used for occupancy/stat rendering and recalculation jobs. |

## Content & Communication Models

| Model | Purpose | Key Relations | Notes |
|---|---|---|---|
| `StaticPage` | CMS-like static page entry. | (translation relation via translatable package) | Fillable: `slug`, `position`, icon, status, user ownership. |
| `StaticPageTranslation` | Localized title/content for static pages. | package-managed | No timestamps. |
| `GroupNews` | Group-level announcements. | `belongsTo(Group)`, `belongsTo(User)`, `hasMany(GroupNewsFile)`, morph-many `LogHistory` | Translatable title/content, soft deletes, scheduled date/status. |
| `GroupNewsTranslation` | Localized news content. | morph-many `LogHistory` | No timestamps. |
| `GroupNewsFile` | Attachment metadata for a news item. | `belongsTo(GroupNews)` | Appends download `url` and file `size` from `news_files` disk. |
| `GroupNewsUserLogs` | Last-seen marker for group news per user. | `belongsTo(Group)`, `belongsTo(User)` | Used to calculate unread status. |
| `AdminNewsletter` | Global newsletter entries for privileged audiences. | `belongsTo(User)`, `hasOne(AdminNewsletterRead)` | Translatable subject/content, scheduled send date/time and recipient segmenting. |
| `AdminNewsletterTranslation` | Localized newsletter content. | package-managed | No timestamps. |
| `AdminNewsletterRead` | Read-tracking for newsletters. | `belongsTo(User)`, `belongsTo(AdminNewsletter)` | Per-user newsletter seen state. |
| `GroupMessage` | Group message board posts. | `belongsTo(Group)`, `belongsTo(User)` | Encrypted message body, priority flag. |
| `GroupPosters` | Poster-style pinned notices per group/date range. | `belongsTo(Group)`, `hasMany(GroupPosterRead)` | Encrypted poster info, read-state helper for current user. |
| `GroupPosterRead` | Poster read acknowledgment. | `belongsTo(GroupPosters)` | Stores user read markers per poster. |

## Survey & Auxiliary Models

| Model | Purpose | Key Relations | Notes |
|---|---|---|---|
| `GroupSurvey` | Survey definition storage. | none declared | Unguarded model. |
| `GroupSurveyAnswer` | Survey answer records. | none declared | Unguarded, no timestamps. |
| `GroupSurveyStatistics` | Survey aggregate statistics. | none declared | Unguarded model. |
| `GroupLiterature` | Literature types available for reporting. | `belongsTo(Group)`, morph-many `LogHistory` | Used by event service reports. |
| `WeatherCity` | Cached weather and forecast metadata. | `hasMany(Group)` | JSON weather payloads and last try timestamp. |

## System/Audit Models

| Model | Purpose | Key Relations | Notes |
|---|---|---|---|
| `Settings` | Key-value system settings storage. | none declared | Activity logging enabled (Spatie), logs dirty changes for `name/value`. |
| `Statistics` | Time-series counters (`active_users`, `dialy_users`, etc.). | none declared | Populated by scheduler jobs. |
| `LogHistory` | Polymorphic audit trail across domain models. | `morphTo(model)`, `belongsTo(User as causer)` | Appends normalized model name, decoded change payload, and formatted timestamp. |

## Observer Bindings (Active)

Registered in `app/Providers/EventServiceProvider.php`:

- `User` -> `UserObserver`
- `Event` -> `EventObserver`
- `Group` -> `GroupObserver`
- `GroupUser` -> `GroupUserObserver`
- `GroupLiterature` -> `GroupLiteratureObserver`
- `GroupNews` -> `GroupNewsObserver`
- `GroupNewsTranslation` -> `GroupNewsTranslationObserver`

### Important note

`GroupDayObserver` exists in `app/Observers/GroupDayObserver.php`, but is **not currently registered** in `EventServiceProvider`. Any logic there (including queue dispatches) will not run unless observer registration is added.
