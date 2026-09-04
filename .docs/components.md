# Components Documentation

## Overview

UI logic is primarily implemented with **Livewire components**, supported by class-based and anonymous Blade components.

- Livewire classes: `app/Http/Livewire`
- Class-based Blade components: `app/View/Components`
- Blade templates: `resources/views/livewire` and `resources/views/components`

A shared Livewire base class exists:

- `App\Http\Livewire\AppComponent`
  - Uses `WithPagination`
  - Sets Bootstrap pagination theme
  - Provides shared `state` array and `showEditModal` flag

## Route-Level Livewire Components

These are mounted directly from `routes/web.php`.

| Component | Route(s) | Main Responsibility |
|---|---|---|
| `Home` | `/home` | Dashboard/home overview, group cards, quick event visibility, poster read toggles. |
| `Events\Events` | `/calendar/{year?}/{month?}` | Calendar page with group/date context and modal integration. |
| `Events\LastEvents` | `/lastevents` | Historical event list and service report editing. |
| `Groups\ListGroups` | `/groups` | Membership list, invitations, group creation, role request flow, group leave/remove flows. |
| `Admin\AdminNewsletters` | `/newsletters` | Newsletter list and read tracking for users/group servants/admin. |
| `Admin\Users\ListUsers` | `/admin/users` | Admin user management (create/edit/delete/search). |
| `Admin\Settings` | `/admin/settings` | Application settings UI, language setup, maintenance/system operations. |
| `Admin\StaticPages` | `/admin/staticpages` | Static page listing UI for admin. |
| `Admin\StaticPageEdit` | `/admin/staticpages/create`, `/admin/staticpages/edit/{staticPage}` | Static page create/edit/delete workflow with translation content. |
| `Admin\NewsletterEdit` | `/admin/newsletter_edit/{id?}` | Newsletter create/edit/delete and send-target configuration. |
| `Admin\Statistics` | `/admin/statistics` | Admin statistics dashboard. |
| `Admin\Translation` | `/admin/translate` | **The translation editor** (TODO 33.3 grew it from an 18-line link-out shim when `joedixon/laravel-translation` was removed). Source/target locale and group selectors, search, missing-only filter, per-key and per-page save, add key. Writes through `App\Support\Translation\LangFiles`. See the notes below. |
| `Groups\ListUsers` | `/groups/{group}/users` | Group membership management, role/sign controls, linking/detaching child-parent groups. |
| `Groups\NewsList` | `/groups/{group}/news` | Group news listing. |
| `Groups\UpdateGroupForm` | `/groups/{group}/edit` | Group configuration editor (rules, service days, literature, weather, future changes). **The only component that makes an outbound API call**, and it makes it synchronously - see the note below. |
| `Groups\DeleteGroup` | `/groups/{group}/delete` | Group delete confirmation/action UI. |
| `Groups\NewsEdit` | `/groups/{group}/news/create`, `/groups/{group}/news/edit/{new}` | Group news create/edit, localized fields, attachments. |
| `Groups\Statistics` | `/groups/{group}/statistics` | Per-group stats and monthly analytics. |
| `Groups\History` | `/groups/{group}/history` | Per-group history/audit-style timeline UI. |

### The weather call in `Groups\UpdateGroupForm`

This is the only place in the application that reaches an external API from a component, and it is
worth knowing before touching either the form or the calendar. Two call sites, both synchronous and
both into `pwbs_weather_api_call()` (`app/Helpers/helpers.php:84-164`):

| Site | Trigger | Effect |
|---|---|---|
| `updateGroup()` (`:215`) | saving the group with weather enabled | sets `state['city_id']` from the result, or `null` on error |
| `checkWeatherSettings()` (`:524`) | the "check settings" button | fills `$weather_messages` for the inline preview |

The helper serves from `weather_cities` when the row is under 59 minutes old, refuses within 15
minutes of the last attempt, and only otherwise calls OpenWeather - so most saves cost no network
at all. Three consequences that are easy to be surprised by:

- **A failed lookup makes the group unsavable.** The error path writes `city_id = null`, and the
  rules at `:251` then require it (`required_if:weather_enabled,1`). The validation error lands on a
  field that has no input in the form, so an admin who enables weather while the API key is missing
  or wrong simply cannot save - and the surfaced message is an empty string. Roadmap TODO 33.6.
- **This form is the only writer of the weather cache.** There is no scheduled refresh and no job;
  `Events\Events` is a pure reader. Between two admin saves the calendar shows arbitrarily stale
  forecasts. Roadmap TODO 33.7 adds the `weather:refresh` command.
- **The call cannot be intercepted with `Http::fake()`**, because the vendor client constructs its
  own Guzzle instance. That is why `tests/Feature/Weather/` exercises every branch *except* the
  successful API response, and why the replacement in TODO 33.6 is what finally makes it testable.

The whole feature is additionally gated on `config('weather')`, which is injected at boot from the
`settings` table and ships off.

## Embedded/Nested Livewire Components

These are used from other Livewire views or layouts.

| Component | Typical Usage |
|---|---|
| `Events\Modal` | Day modal for event creation/edit/accept/reject flows, poster and bulk actions. |
| `Events\EventEdit` | Event editor form used from modal/calendar workflows. |
| `Groups\Messages` | Embedded group message board block (priority + permission checks). **Writes files while rendering**: for every message author without one, `render()` generates a `laravolt/avatar` PNG and stores it as `avatars/avatar-{user_id}.png` on the `web` disk, guarded by a `Storage::exists()` check so it happens once per user. The view addresses the same file as `asset('avatars/avatar-{id}.png')`, and the `web` disk's root is `public_path()` since TODO 30 - the two only line up together. The `exists()` guard is deliberate and stays: what follows it is `put()`, which the disk's `'throw' => false` covers, so TODO 37's move to `fileExists()` does not apply here. This is the project's **only** `laravolt/avatar` call site, and roadmap TODO 39.1 rewrites it for Intervention Image 4. Pinned by `tests/Feature/Avatar/AvatarGenerationTest`. |
| `Groups\PosterEditModal` | Poster create/update/delete modal for group notices. **The only asset call inside a Livewire view**: `:87` pushes a `pwbs_asset()` tag for summernote into the `footer_scripts` section, one of the 16 call sites documented in `.docs/assets.md`. TODO 33.8 made that replacement; the `Packer::js()` call it describes is gone. |
| `Groups\SpecialDateModal` | Special day configuration modal for date-level overrides. |
| `Partials\NavBar` | Global top navigation with notifications and language switch. |
| `Partials\SideMenu` | Main sidebar navigation and role-aware menu sections. |
| `Partials\EventsBar` | Compact event list renderer with calendar links. |

## Class-Based Blade Components

Located in `app/View/Components`.

| Component Class | Blade View | Responsibility |
|---|---|---|
| `Footer` | `components.footer` | Footer static page links (role-aware visibility in view). |
| `Modal` | `components.modal` | Generic modal wrapper with slots and size/id inputs. |
| `SideStaticPages` | `components.side-static-pages` | Side-menu static page links. |
| `UpdateNotification` | `components.update-notification` | Displays updater info and online user count when an update is available. Mounted from `layouts/app.blade.php` inside `@can('is-admin')`, so it renders on every admin page load. Instantiates `MDylan\LaraUpdater\LaraUpdaterController` directly (not through the container) and calls `check()`; if that returns a version it also calls `getDescription()`, which is served from the same 15-minute cache entry, so the pair costs one channel read. Returns an empty string when the system is current. Pinned by `tests/Feature/Updater/UpdaterContractTest.php`. |

## Anonymous/Utility Blade Components

Used without backing PHP class:

- `components/loading-indicator.blade.php`
- `components/offline.blade.php`

## Blade Alias Registration

In `app/Providers/BladeComponentServiceProvider.php`:

- `Blade::component('layouts.app', 'admin-layout')`

This allows using `<x-admin-layout>` for the main app layout.

## The Translation Editor (`Admin\Translation`)

Replaced the Vue 2 / Tailwind front-end of `joedixon/laravel-translation` in
**TODO 33.3**. All file access goes through `App\Support\Translation\LangFiles`,
which resolves its base directory from `App::langPath()` - never
`resource_path('lang')` - so the Laravel 9 relocation of the language directory
is a no-op for this code.

**What the list shows.** The union of the source locale and the target locale,
in source order. A key the target is missing appears with an empty box rather
than not at all, and the card reports how many such keys the group has. That is
what makes a mostly-untranslated locale workable.

**Which locales are offered.** Only those registered in `settings.languages`,
read through `App\Support\Settings\ApplicationSettings::languages()`. This is a
deliberate narrowing: the removed package treated every directory under the
language path as a locale and knew nothing about the registry. A directory that
exists on disk but is not registered is therefore invisible here - registering
it in the admin settings makes it editable **with its existing files intact**,
because `LangFiles::ensureLocale()` never touches a directory that is already
there. `Admin\Settings::languageAdd()` calls that method, which is what closed
the split brain: registering a locale and creating its directory now happen in
one place.

**Three behaviours worth knowing before editing this component:**

- **A key is addressed by its structural path, never by its dotted label.** Many
  keys in this project contain a literal dot - in the root JSON files, where the
  keys are English sentences, roughly one in five - so a dotted label is
  ambiguous by construction. `Arr::dot()` and `Arr::set()` are not inverses of
  each other and are deliberately not used; `tests/Unit/Support/LangFilesTest.php`
  pins that with the file shapes that break under them.
- **Saving rewrites the whole file, so comments in it are lost.** Preserving
  them would need a real PHP parser. `LangFiles::write()` therefore writes
  nothing at all when the value is unchanged, and the screen says so.
- **`$rows` holds the current page only**, rebuilt from disk whenever the
  locale, group, filter or page changes. Livewire serializes public properties
  into every response and the root JSON group runs to several hundred keys. The
  consequence is that changing page or locale without saving discards the edits
  on screen; the view states this.

The row state travels to the browser and back, so `save()` checks the submitted
path against the files before writing - accepted when it exists in the target,
or in the source and the target is missing it, which is the fill-in case.

### Who may edit a translation, and where that is enforced

The route `admin.translate` carries `can:is-translator` (satisfied by `translator`
and `mainAdmin`), and the component **also** calls `Gate::authorize('is-translator')`
in `mount()` and in every writing action. That is not belt-and-braces for its own
sake - it was measured.

A Livewire **action** does not travel over the route it was rendered from. It POSTs
to `livewire/message`, whose own middleware group is only `web`. What re-applies the
original route's guards there is `Livewire::getPersistentMiddleware()`, a hardcoded
list inside the package. Removing `Illuminate\Auth\Middleware\Authorize` from that
list was tried against a real HTTP request: a plain `registered` user, posting a
valid payload taken from a translator's page, could then call `save()` and rewrite a
language file - the endpoint answered **200**. With the component's own gate in
place the same request is refused with **403**, whether or not the middleware list
still contains `Authorize`.

Livewire 3 reworks persistent middleware, so the component-level check is what makes
this survive the upgrade. `AdminTranslationEditorTest` pins all three: a translator
can save over real HTTP, a `registered` user gets 403, and the middleware list still
contains `Authorize`.

Two guards that are **not** re-applied to component actions, and this is normal for
the whole application rather than specific to this screen: `verified`/`profileFull`
(checked when the page is rendered) and `password.confirm` (a session-level state
with its own lifetime). A third layer sits in front of both: `AuthenticateSession` is
in the `web` group, so a session whose user was swapped is rejected outright.

### Why a save cannot leave a broken language file

A language file is loaded on every request that renders a translated string, so a
truncated or unparseable one is not a bad save - it is an outage. `LangFiles` writes
through three steps:

1. The content is generated in full before anything on disk is touched.
2. It goes to a temporary file, which is then **read back and compared** to the array
   it was built from. A file replaces a working one only if it parses *and*
   round-trips.
3. The swap is a `rename()`, atomic on the same filesystem, so a concurrent reader
   sees either the old file or the new one - never a partial write.

Any failure deletes the temporary file and throws; the editor turns that into an
error message and the original file is untouched.

One case is worth naming because it was found by testing rather than by reading:
`json_encode()` returns `false` rather than throwing - on malformed UTF-8 above all.
Concatenating that result would have written an **empty** root JSON file, losing every
key in it, and the next save would then "restore" a file holding a single key. The
encoder result is checked and the save refused instead.
