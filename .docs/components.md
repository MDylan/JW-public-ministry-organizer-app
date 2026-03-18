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
| `Admin\Translation` | `/admin/translate` | Translation management view integration. |
| `Groups\ListUsers` | `/groups/{group}/users` | Group membership management, role/sign controls, linking/detaching child-parent groups. |
| `Groups\NewsList` | `/groups/{group}/news` | Group news listing. |
| `Groups\UpdateGroupForm` | `/groups/{group}/edit` | Group configuration editor (rules, service days, literature, weather, future changes). |
| `Groups\DeleteGroup` | `/groups/{group}/delete` | Group delete confirmation/action UI. |
| `Groups\NewsEdit` | `/groups/{group}/news/create`, `/groups/{group}/news/edit/{new}` | Group news create/edit, localized fields, attachments. |
| `Groups\Statistics` | `/groups/{group}/statistics` | Per-group stats and monthly analytics. |
| `Groups\History` | `/groups/{group}/history` | Per-group history/audit-style timeline UI. |

## Embedded/Nested Livewire Components

These are used from other Livewire views or layouts.

| Component | Typical Usage |
|---|---|
| `Events\Modal` | Day modal for event creation/edit/accept/reject flows, poster and bulk actions. |
| `Events\EventEdit` | Event editor form used from modal/calendar workflows. |
| `Groups\Messages` | Embedded group message board block (priority + permission checks). |
| `Groups\PosterEditModal` | Poster create/update/delete modal for group notices. |
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
| `UpdateNotification` | `components.update-notification` | Displays updater info and online user count when update is available. |

## Anonymous/Utility Blade Components

Used without backing PHP class:

- `components/loading-indicator.blade.php`
- `components/offline.blade.php`

## Blade Alias Registration

In `app/Providers/BladeComponentServiceProvider.php`:

- `Blade::component('layouts.app', 'admin-layout')`

This allows using `<x-admin-layout>` for the main app layout.
