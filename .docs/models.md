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
- **GDPR traits** (`App\Support\Gdpr\Portable`, `App\Support\Gdpr\Anonymizable`)
  are used by `User`, `Group`, and `Event`. They were `Dialect\Gdpr` until
  **TODO 33.2** moved them in-house and removed the package.
  `User` **overrides** the `Anonymizable::anonymize()` method (trait alias
  `anonymizeAttributes`) to enforce the succession rule from
  `App\Support\Gdpr\AnonymizationPolicy`, returning `false` and doing nothing
  when blocked. The guard sits on the model because **four** separate code paths
  anonymize users - see `.docs/commands.md` for the list.
  `Group` and `Event` carry `Anonymizable` with an empty `$gdprAnonymizableFields`
  for a reason that is easy to miss: `User::$gdprWith` makes `anonymize()` recurse
  into `eventsOnly` and `groupsAccepted` and call `anonymize()` on each. Remove the
  trait from either model and the user anonymization fatals. Neither declares
  `$gdprWith`, so the cascade stops one level deep.
- **What anonymization writes** is declared in two lists on `User`:
  `$gdprAnonymizableFields` for columns that get a replacement value, and
  `$gdprNullFields` (new in TODO 33.2) for the thirteen columns that are simply
  emptied because there is nothing worth keeping in them. The full matrix is in
  `.docs/commands.md`. Two mechanics are worth knowing before editing either
  list: the trait writes with `forceFill()->save()`, so a column outside
  `$fillable` is still cleared; and a keyless entry needs a matching
  `getAnonymized{Column}()` method on the model or the trait raises a
  `LogicException`.
- **`Portable::portable()` calls `setHidden()`, which REPLACES `$hidden`** rather
  than extending it. Anything `$hidden` conceals but `$gdprHidden` does not list
  therefore appears in the export (`language`, `created_at`, `updated_at`,
  `isAnonymized`). That is intended - the data subject is entitled to them - and
  `DataExportTest::test_the_export_reveals_fields_the_normal_api_hides` pins it.
- **Data retention (v1-patch E).** Four models are now purged by age; every floor
  comes from `App\Support\Retention\RetentionWindow`, which is the only place a
  cutoff date is computed. Do not recompute one inline - the commands and the
  UI clamps must not drift apart.

  | Model | Floor | Gate |
  |---|---|---|
  | `Event` (+ `EventServiceReport` via FK cascade) | `retention.events_months` = 13 | `gdpr.enabled` |
  | `DayStat`, `GroupDate` | `settings.group_data_retention` (`0` / `12` / `24` months) | the setting itself |
  | Spatie `Activity` (`activity_log`) | `activitylog.delete_records_older_than_days` = 90 | `gdpr.enabled`, via a scheduler `when()` |

  `DayStat` and `GroupDate` are deliberately **not** on the GDPR switch: they
  carry group, day, time slot and a count, no personal data, so their retention
  answers to size. `Event` and `activity_log` are personal data and follow GDPR.
  `LogHistory` keeps its own separate three-month rule in
  `maintenance:purge-log-history`.

  Two consequences worth knowing: `day_stats` is derived from `events`
  (`GenerateStatProcess`), so once the events are gone it cannot be regenerated -
  and the job now refuses to try below the floor, because it would write all-zero
  rows. And `User::$gdprWith` exports `eventsOnly` with no date filter, so the
  Article 20 export naturally shrinks to the retained window.
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

- **The weather cache** is `WeatherCity` plus two columns on `groups`: `weather_enabled` and
  `city_id`. `Group::weather()` is a `belongsTo(WeatherCity::class, 'city_id')`, and the whole
  feature is additionally gated on `config('weather')`, which is not a config file - it is injected
  at boot from the `settings` table (`App\Support\Settings\ApplicationSettings::applyToConfig()`,
  called by `AppServiceProvider::boot()`) and ships **off**.

  Four measured traps live here, all pinned by `tests/Feature/Weather/`:

  1. **`WeatherCity::groups()` is a broken stub.** It is a plain `hasMany(Group::class)`, so it looks
     for `groups.weather_city_id`, which does not exist - the real column is `groups.city_id`.
     Calling it throws. No caller does.
  2. **`groups.city_id` has no foreign key.** The migration
     (`2024_12_04_194500_add_city_id_to_groups_table.php:17`) calls `->constrained()` on an
     `unsignedBigInteger()` column, where it is a silent no-op - no constraint and no
     `onDelete('set null')` were ever created. A deleted `weather_cities` row therefore leaves a
     dangling `city_id`, which the calendar then dereferences unguarded.
  3. **The two JSON columns are encoded twice in production.** `pwbs_weather_api_call()`
     (`helpers.php:116-117`) calls `json_encode()` and the `json` cast then encodes again, so
     reading the model back yields a **string**, not an array, and every reader decodes a second
     time. `WeatherCityFactory::withWeatherData()` writes plain arrays instead - a shape production
     cannot produce, which is what `ModelFactoryTest` asserts against.
  4. **Nothing refreshes the table.** Rows are written only when a group admin saves the group form;
     the calendar is a pure reader. See `.docs/components.md`.

  Traps 1, 2 and 4 are fixed by roadmap TODO 33.7, trap 3 by TODO 33.6.

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
| `GroupUser` (pivot) | Membership record between user and group. | `belongsTo(User)`, morph-many `LogHistory` | Custom pivot (`group_user`) with soft delete, encrypted `note`, JSON `signs`, membership role/flags. `created_at`/`updated_at` carry explicit `datetime` casts (TODO 29): the pivot takes its `$timestamps` value from the loaded attributes (`AsPivot`), so the casting is not implicit the way it is on a plain model. `deleted_at` is cast by `SoftDeletes` on both this model and `Group`. |

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
| `WeatherCity` | Cached OpenWeather payloads for one city. | `hasMany(Group)` - **broken, see below** | `current_weather` / `forecast_weather` under a `json` cast, plus `last_try`. Unique on `(city, country)`. |

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
