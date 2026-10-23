# Components Documentation

## Overview

UI logic is primarily implemented with **Livewire components**, supported by class-based and anonymous Blade components.

> **The application is mid-migration to Livewire 3 (roadmap Phase 6).** The
> package is on `v3.8.8` since TODO 43, and the test suite is green again since
> TODO 51.1; the browser walk (TODO 47.1) still has to close the phase. Since
> TODO 51 the classes live in
> `App\Livewire`, Livewire 3's default namespace; the component names
> (`groups.list-users`) and the view paths did not change. See Configuration
> below.

- Livewire classes: `app/Livewire`
- Class-based Blade components: `app/View/Components`
- Blade templates: `resources/views/livewire` and `resources/views/components`

A shared Livewire base class exists:

- `App\Livewire\AppComponent`
  - Uses `WithPagination`
  - Declares `paginationView()` / `paginationSimpleView()` (`livewire::bootstrap`, `livewire::simple-bootstrap`) - see Pagination below
  - Provides shared `state` array and `showEditModal` flag

## Configuration (`config/livewire.php`)

Every key Livewire 3 reads is written out, at the package default except
for three project choices. Livewire merges its own defaults underneath, so a
missing key would behave the same; the file is explicit for the reader.

- `class_namespace` is **not** set: the classes are in `App\Livewire`, the
  default. Livewire turns a dotted name into a class under this namespace, so
  changing it breaks every `@livewire` / `<livewire:...>` tag.
- `layout` is `layouts.app`, not the stub's `components.layouts.app`. No
  component calls `->layout()`, so this key is what wraps every route-mounted
  component.
- `temporary_file_upload.preview_mimes` adds `pdf, doc, docx, xls, xlsx` to
  the package list - see `Groups\NewsEdit` below.
- `pagination_theme` is `bootstrap`. `AppComponent::paginationView()` wins over
  it, so it only matters for a component outside `AppComponent`.
- The Livewire 2 keys `middleware_group` and `manifest_path` are gone.

`LivewireNamespaceContractTest` pins the namespace (no `App\Http\Livewire`
left anywhere, every view tag resolving to a class, the tracked
`vendor/composer/autoload_classmap.php` up to date) and the removed keys.
After adding or moving a component class, run `composer dump-autoload`: the
classmap is optimized and ships in the release archive.

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
| `Groups\UpdateGroupForm` | `/groups/{group}/edit` | Group configuration editor (rules, service days, literature, weather, future changes). **The only component that makes an outbound API call**, and it makes it synchronously - see the note below. The service-day rows correct themselves as you edit them; that has its own note too. |
| `Groups\DeleteGroup` | `/groups/{group}/delete` | Group delete confirmation/action UI. |
| `Groups\NewsEdit` | `/groups/{group}/news/create`, `/groups/{group}/news/edit/{new}` | Group news create/edit, localized fields, attachments. **The only `WithFileUploads` component.** Every accepted file gets a `temporaryUrl()` preview link, which Livewire 3 refuses for an extension missing from `livewire.temporary_file_upload.preview_mimes` - so that list must keep `pdf, doc, docx, xls, xlsx` on top of the package default, or every non-image upload is a 500. `GroupNewsEditTest` checks each of the eight `$file_types` and that the list covers them. |
| `Groups\Statistics` | `/groups/{group}/statistics` | Per-group stats and monthly analytics. |
| `Groups\History` | `/groups/{group}/history` | Per-group history/audit-style timeline UI. |

### The service-day template in `Groups\UpdateGroupForm`

The seven service-day rows are not an ordinary bound form: `render()` rebuilds
each day's start and end option lists on every request and **rewrites values
that no longer fit**, so the component corrects the user rather than rejecting
them. Livewire re-renders after every property update, so this happens between
keystrokes, before anything is submitted.

- **The end list is built from the start as clamped**, so only the field the
  user got wrong moves. Until TODO 42.1 it was built from the start the loop
  was handed, and a mis-clicked start dragged the stored end along with it -
  what survived was `00:00 - 00:00`, the "no service" template, with nothing
  telling the user the day had been emptied.
- **The order of updates is significant.** Widening the end of the day is what
  makes a later start reachable; setting the start first clamps it straight
  back. Tests that drive this form have to set `end_time` before `start_time`.
- **The clamp is not a complete guard.** With `min_time = 120` and a start of
  `23:00` the end option list has exactly one entry, so the clamp produces the
  zero-length pair `23:00 - 23:00`, which `App\Rules\TimeCheck` then rejects on
  both fields. A group in that configuration cannot be saved at all - an open
  defect, not a fixed one.
- **The option lists are keyed by the day**, not by `day_number`, which is
  `false` for an unchecked day and therefore the array key `0`. Both
  `day_selects` and `disabled_selects` are reset at the top of `render()`;
  they are public properties and would otherwise carry stale entries across
  requests.
- **The day times have exactly one validator**, and its error keys are bare
  (`3.start_time`). See `.docs/validation.md` for the key shape and for why
  the first validator's `days.*` rules were deleted rather than repaired.

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

Three of them are mounted with Livewire tag syntax rather than `@livewire(...)`: `Events\Modal` from `layouts/app.blade.php` (on every page rendered through the application layout), `Events\EventEdit` from `livewire/events/modal.blade.php`, and `Groups\Messages` from `livewire/home.blade.php`. All three tags are self-closing. **The `wire:key` on a tag is compiled verbatim into `key(...)`**, so a bound `:wire:key` must hold a PHP expression (`:wire:key="'messages_'.$group['id']"`) and a literal key must be unbound (`wire:key="events_modal"`). A bound bare word compiles to an undefined constant, and on the layout tag that is a 500 on every page.

The rest use `@livewire('name', $params, key(...))`. **`$params` must be an array** - `[]` when there is nothing to pass, or leave it out. Livewire 3 matches it against the child's properties with `array_intersect_key()`, which throws on a string; `@livewire('groups.poster-edit-modal', '', ...)` in the calendar view did exactly that, and only for admins and rolers, the one audience that block renders for. `tests/Feature/Livewire/LivewireDirectiveParametersTest` checks every directive in `resources/views`, including those behind role checks.

| Component | Typical Usage |
|---|---|
| `Events\Modal` | Day modal for event creation/edit/accept/reject flows, poster and bulk actions. |
| `Events\EventEdit` | Event editor form used from modal/calendar workflows. |
| `Groups\Messages` | Embedded group message board block (priority + permission checks). **Touches no disk**: `render()` builds one `App\Support\Avatar\InitialsAvatar::dataUri()` per message author - an SVG data URI carrying the initials on a coloured circle - and hands the map to the view, which sets it as the `<img src>` directly. TODO 39.1 replaced the previous mechanism, where `laravolt/avatar` rendered a PNG through Intervention Image and stored it as `avatars/avatar-{user_id}.png` on the `web` disk behind a `Storage::exists()` guard; both packages are gone with it, and so is the Intervention Image 4 migration that the Laravel 10 hop would otherwise have forced - a prediction TODO 39 then confirmed, since the hop resolved with no imaging library in the tree at all. Two consequences of computing rather than storing: a renamed user's avatar now follows the new name (the guard used to freeze the first one written forever), and accented initials survive, because the package flattened the name to ASCII first. This is the generator's **only** call site. Pinned by `tests/Feature/Avatar/AvatarGenerationTest` and `tests/Unit/Support/InitialsAvatarTest`, the latter holding the colour compatibility measured against the package before removal. |
| `Groups\PosterEditModal` | Poster create/update/delete modal for group notices. **The only asset call inside a Livewire view**: `:87` pushes a `pwbs_asset()` tag for summernote into the `footer_scripts` section, one of the 16 call sites documented in `.docs/assets.md`. TODO 33.8 made that replacement; the `Packer::js()` call it describes is gone. |
| `Groups\SpecialDateModal` | Special day configuration modal for date-level overrides. |
| `Partials\NavBar` | Global top navigation with notifications and language switch. |
| `Partials\SideMenu` | Main sidebar navigation and role-aware menu sections. |
| `Partials\EventsBar` | Compact event list renderer with calendar links. |

## Browser Events (Server -> JavaScript)

Components talk to page scripts through window events: toasts, SweetAlert dialogs and Bootstrap modals are all opened and closed this way. The server side is `$this->dispatch('<name>', key: $value, ...)`; the event bubbles from the component's element to `window`, and its `detail` **is** the named-parameter list, so `dispatch('success', message: $m)` is read as `event.detail.message`.

| Event | Keys | Receiver |
|---|---|---|
| `success`, `error` | `message` | `layouts/app.blade.php` (toastr) |
| `sweet-error` | `title`, `message` | `layouts/app.blade.php` (SweetAlert) |
| `show-deletion-confirmation` | `title`, `text`, `emit` | `layouts/app.blade.php`; `emit` names the Livewire event fired on confirm, via `Livewire.dispatch(event.detail.emit)` |
| `show-modal` | `id`, optionally `livewire` and `parameters_back` | `public/js/modal.js`; with `livewire` set, closing the modal calls back `hideModal` / `hiddenModal` on that component through `Livewire.dispatchTo(..., { parameters_back })` (only `hiddenModal` has listeners: `Events\Modal`, and `Groups\PosterEditModal`, which takes `parameters_back` by that name) |
| `hide-modal` | `id`, optionally `message` and `savedMessage` | `public/js/modal.js` |
| `show-*-confirmation` (`logout`, `reject`, `languageRemove`, `literature`, `newsDelete`, `pageDelete`, `fileDelete`) | per event (`groupId`, `lang`, `fileName`, or none) | a script in the component's own view |
| `poster-editor-opened` | `id`, `info` | `livewire/groups/poster-edit-modal.blade.php`; starts summernote on the textarea `Groups\PosterEditModal::openModal()` just rendered, and loads `info` into it - a modal reopened after Cancel keeps its editor (`wire:ignore`), which would otherwise still show the abandoned edit |
| `calendar-group-changed` | none | `livewire/events/events.blade.php`; binds the weather widget's scroll buttons after `Events\Events::changeGroup()` |

**Always pass named parameters.** `dispatch('success', ['message' => $m])` is legal PHP and dispatches an event of the right name, but Livewire 3 treats the array as one positional parameter: the browser receives `event.detail = [{message: ...}]`, every `event.detail.message` is undefined, and nothing reports an error. Component tests that assert only the event name pass either way. `tests/Feature/Livewire/BrowserEventContractTest` is what fails: it reads the receiver names from the views and `public/js`, and rejects any `dispatch()` of those events that is not named-parameter only. Livewire-to-Livewire events are not browser events and are not covered by this rule. Until TODO 47 this was `dispatchBrowserEvent($name, $array)`, which Livewire 3 removed.

**`error` shares its name with the browser's own event.** The browser fires an `ErrorEvent` named `error` on `window` for every uncaught exception, with no `detail`; the toast listener therefore returns early unless the event is a `CustomEvent` (TODO 47.2, pinned by `JavaScriptBridgeContractTest`).

**A `<script>` that a re-render adds never runs.** Livewire 2 `eval()`-ed every script element a morph added; Livewire 3 does not. A script in the component's first render still runs (the browser parses it with the page), so the rule is: in component markup, outside `@section` / `@push`, no inline `<script>` inside `@if`, `@foreach` or any other Blade block. Code that has to run when markup appears is started by a browser event the component dispatches - dispatched events are processed after the morph, unlike `$this->js()`, which runs before it. `poster-editor-opened` and `calendar-group-changed` above are the two cases (TODO 47.3); `InlineScriptContractTest` enforces the rule.

### Polling and the root element

`Home`, `Events\Events` and `Events\Modal` poll every 30 seconds while `$polling` is on; opening the day modal switches the page's poll off and the modal's on, and `Home` switches back on when the modal closes (its `refresh` listener is `pollingOn`; the calendar's is `render`, so the calendar stays off until reloaded - unchanged since v1).

- **Never put `wire:key` on a component's root element.** The root is keyed by its `wire:id`; Livewire 3's morph treats a changed root key as a different element and replaces the root, which leaves it outside the component and throws "Could not find Livewire component in DOM tree". The three views used to toggle `wire:key` together with `wire:poll` on the root (a Livewire 2 device to restart the poll). `ComponentRootElementContractTest` checks every component view.
- **A poll on the root has to be there at load.** Livewire 3 registers `wire:poll` when an element initialises; afterwards removing the attribute pauses the poll and restoring it resumes it, but an attribute that first appears later never starts. `Home` and the calendar start with `$polling = true`, so their poll sits on the root; `Events\Modal` starts with `false`, so its poll is a keyed empty `<div>` inside the root, created and removed with `@if($polling)`.

## Component Events (Livewire -> Livewire)

Components also signal each other - a partial re-rendering after a save, the day modal opening from the calendar. Each component declares what it answers to in a **`protected $listeners`** array (`'event'` or `'event' => 'method'`). All 19 are `protected` since TODO 44: Livewire 3 treats a `public` property declared on the component as client state, so a public `$listeners` was serialised into every snapshot and could be rewritten from the browser.

**How to send one, and what each form reaches.** A Livewire 3 event is a DOM event, and that decides the choice:

| Form | Reaches | Used for |
|---|---|---|
| `$this->dispatch('event', ...$args)->to('component.name')` | every instance of that component on the page, and nothing else | all server-side sends; arguments are positional, exactly as `emitTo()` passed them |
| `$this->dispatch('event')` with no `to()` | **every** component on the page listening for that name | not used for component events - `refresh` alone has six listeners (nav bar, side menu, events bar, home, calendar, day modal) |
| `wire:click="method(args)"` | the component itself | what `$emitSelf('method', args)` became - a listener on self is a direct call |
| `wire:click="$parent.method()"` | the enclosing component | `Events\EventEdit`'s cancel buttons |
| `wire:click="$dispatchTo('name', 'event', { param: value })"` / `Livewire.dispatchTo(...)` | as `->to()` | Blade and page scripts; **one** params argument, so it is an object whose keys are the listener method's parameter names |
| `Livewire.dispatch('event'[, { ... }])` | every listener on the page | the confirmation dialogs' `...Confirmed` callbacks, as `Livewire.emit()` did |

**There is no `emitUp()` in Livewire 3, and its replacement names the parent.** `Events\EventEdit` sends `refresh` to `events.modal`, `Groups\SpecialDateModal` sends `pollingOn` to `events.events`, and `Groups\PosterEditModal` - nested both in the calendar and on the home page - sends to both through `dispatchToParent()`; the one not on the page receives nothing. Nesting one of these somewhere new means adding the new parent there. `Events\Modal` passes `refresh` on to whichever component opened it (`$refreshUp`, set by `openModal()`; only `home` does today). Note that Livewire 3 resolves a `to()` name when the response is built and **throws on a name that is not a component**, where `emitTo()` quietly delivered nothing.

The tests pin the targets with `assertDispatchedTo()`, which also proves the event is not a page-wide broadcast.

## Pagination

Four components paginate. `Admin\Users\ListUsers` and `Groups\ListGroups` use the framework's `paginate(20)`; `Groups\ListUsers` (10 per page, `onEachSide(1)`) and `Admin\Translation` (25 per page) build a `LengthAwarePaginator` by hand.

- **The cursor is `$paginators['page']`, and nothing else.** Livewire 3's `setPage()` - behind `gotoPage()`, `nextPage()`, `previousPage()` and `resetPage()` - writes only that array; Livewire 2 also wrote a `$page` property. A hand-built paginator therefore reads `max(1, (int) $this->getPage())`: `getPage()` falls back to the `?page=` query string on the first render, and the clamp is needed because `$paginators` is public state the browser can rewrite. **Do not declare a `public $page`** - it would never move, and the list would sit on page 1 without an error (`GroupUserListPaginationTest` pins its absence). The `?page=` URL binding is Livewire's own; no component lists `page` in `$queryString`.
- **The markup is the project's own.** `livewire::bootstrap` resolves to `resources/views/vendor/livewire/bootstrap.blade.php` before the package's view. It is the Livewire 2 template, kept because Livewire 3's bootstrap view targets Bootstrap 5 (the application runs Bootstrap 4.6 under AdminLTE), adds a "Showing X to Y of Z results" line that `lang/hu.json` renders as nonsense, and scrolls to the top of the page on every click. `PaginationBehaviorTest` fails if the package view comes back. The buttons call `previousPage('page')`, `nextPage('page')` and `gotoPage(N, 'page')`, so those method names are part of the view contract.
- `simplePaginate()` is not used anywhere, so `simple-bootstrap` is the package's view, unchanged.

## Form Bindings (`wire:model`)

**Key every looped `<option>` of a bound `<select>`** - `<option wire:key="{{ $value }}" value="{{ $value }}">`. When a re-render changes the option list (the group form's end times follow the start, the special-date and event-edit times likewise), Livewire 3's morph patches unkeyed options in place by position, and the browser keeps the selection on the same element - so the select shows a value the component does not hold (seen: 23:30 on screen, 19:00 on the server). `wire:model` only rewrites the select when its property changes. Livewire 2 rewrote every bound field after each response, which hid it. Keyed, the morph matches options by value. The rule covers all 28 bound selects with looped options, fixed lists included (TODO 47.4); `SelectOptionKeyContractTest` enforces it.

Livewire 3 inverted the default: a plain `wire:model` is **deferred** - the value travels with the next action (a `wire:click`, a `wire:submit`) - and `wire:model.live` sends every change at once. Livewire 2 was the other way round.

- **Plain `wire:model`** (88) - every form field that is only read when the form is submitted.
- **`wire:model.live`** (35) - every binding that was live under Livewire 2. **23 of them must stay live**, because the view reacts to the value: an `@if` on it, a `render()` that rebuilds option lists from it (the group form's day and time selects, the special-date modal), or an `updated*()` hook that resets the page of a filtered list (search boxes, the translation editor's filters). The other 12 are only read by an action and were kept live on purpose: it is exactly the old behaviour. A text field on `.live` is debounced by 150 ms; `admin/translation`'s search sets its own `.debounce.400ms`.
- **`wire:model.change`** (8, the mail and API-key fields in `admin/settings` and the poster text) - commits on the `change` event, which is what Livewire 2's `.lazy` did. Not `.blur`: a select bound with `.blur` sends nothing until focus leaves it.
- `wire:submit` needs no `.prevent` in Livewire 3; none is left.
- A file input ignores every modifier - Livewire uploads it on selection - and a field hidden behind summernote syncs through `@this.set()`, not through its binding.

`tests/Feature/Livewire/WireModelBindingContractTest` lists the 23 with the reason each needs to be live, and allows only `.live`, `.change` and `.debounce.Nms` as modifiers. Livewire 3 hands any other modifier to Alpine, which ignores it, so a typo such as `.ignore` would otherwise pass silently.

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
to `livewire/update` (route `default.livewire.update`), whose own middleware group is
only `web`. What re-applies the original route's guards there is
`Livewire::getPersistentMiddleware()`, a hardcoded list inside the package: the
component's snapshot remembers the path it was rendered on, and the update request
re-runs those of that route's middleware whose class is on the list.
`Illuminate\Auth\Middleware\Authorize` is, so `can:is-translator` is re-applied.

**There are two independent layers, and each was measured on its own** (Livewire 3,
TODO 46.1): with `Authorize` taken off the list, a plain `registered` user posting a
valid snapshot taken from a translator's page is still refused with **403** by the
component's gate; with the component's gate disabled instead, the persistent
middleware alone answers **403**; with both gone, the same request saves and the
endpoint answers **200**. Livewire 3 kept the list, its API and `Authorize`'s place on
it from version 2 - the expectation that it would rework them did not hold - but the
component-level check stays, because it does not depend on that list at all.

`AdminTranslationEditorTest` pins it over real HTTP, speaking the version 3 protocol:
it scrapes the signed `wire:snapshot` from the rendered page and posts it back
unchanged with `updates` and `calls`. A translator can save, a `registered` user gets
403, a guest gets 401/403/419, and the middleware list still contains `Authorize`.

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
