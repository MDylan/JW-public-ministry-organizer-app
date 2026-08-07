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
| `Admin\Translation` | `/admin/translate` | **Link-out page only.** Reads the `settings.languages` JSON row and renders a locale table whose links are hardcoded URLs (`/languages/{code}/translations`, `/languages`) pointing at the `joedixon/laravel-translation` vendor UI. It calls no package API itself. Roadmap TODO 33.3 grows it into the actual editor and removes the package. |
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
| `Groups\Messages` | Embedded group message board block (priority + permission checks). |
| `Groups\PosterEditModal` | Poster create/update/delete modal for group notices. **The only asset call inside a Livewire view**: `:87` pushes a `Packer::js()` tag for summernote into the `footer_scripts` section, one of the 16 call sites documented in `.docs/assets.md`. Roadmap TODO 33.8 replaces it with a `pwbs_asset()` tag. |
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
