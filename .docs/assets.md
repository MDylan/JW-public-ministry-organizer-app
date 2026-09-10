# Assets Documentation

## Overview

There is **one** asset pipeline in this repository, and it is not a build step.

| Pipeline | State | What it serves |
|---|---|---|
| `pwbs_asset()` (`app/Helpers/helpers.php`) | **live** | The authenticated application and the installer wizard, via 21 blade tags |
| Raw `asset()` in `resources/views/public.blade.php` | **live** | The whole guest area - see below |
| Laravel Mix (`webpack.mix.js`) | **dead** | Nothing - see below |

Nothing is compiled at deploy time and nothing is generated at request time. The application's CSS and JS are **hand-placed files committed under `public/`** (`public/dist/` for AdminLTE, `public/plugins/` for jQuery, bootstrap, toastr, sweetalert2, summernote and fullcalendar, `public/css/` and `public/js/` for the project's own four files). The browser receives those files **byte for byte**, with a cache-busting query appended to the URL.

Until TODO 33.8 the pipeline was `eusonlito/laravel-packer`, which concatenated and rewrote these files during the request. The whole pipeline is pinned by `tests/Feature/Assets/`.

## The helper

```php
pwbs_asset('/dist/css/adminlte.min.css')
// => https://host/dist/css/adminlte.min.css?v=1786306980
```

`asset($path)` with `?v={filemtime}` appended when the file exists, and plain `asset($path)` when it does not. Roughly ten lines, no state, no configuration.

Three properties matter, and each one replaces a defect measured in TODO 21:

- **It never writes to disk.** Nothing is generated, so nothing can be stale, and a page render cannot create a file under `public/`.
- **It never touches file contents.** `data:` URIs, relative `url(../webfonts/...)` paths and everything else reach the browser exactly as committed.
- **It resolves the URL per request.** The scheme and host come from the current request through `asset()`, so a page served over HTTPS cannot emit an `http://` asset URL.

The one thing it deliberately keeps from the old pipeline is **cache busting**, which was the only value that pipeline genuinely delivered.

### Cache busting is on the query string

The token is `filemtime` of the source file. Two consequences worth knowing:

- A `git clone` or a release extraction rewrites modification times, so a deploy invalidates every asset URL even when the bytes are unchanged. That is wasteful but safe; the previous scheme had the same property.
- Query-string busting relies on the cache keying on the full URL. Browsers do; a badly configured CDN may not. There is no CDN in front of this application today.

### Caching policy: `public/.htaccess`

The versioning is what makes a long cache safe, so the two belong together. `public/.htaccess` sets, for **versioned URLs only**:

```
Cache-Control: public, max-age=31536000, immutable
```

The mechanism is two directives: a `RewriteRule` that sets `PWBS_VERSIONED_ASSET` when the request is for an existing `.css`/`.js` file **and** the query string carries a numeric `v=`, and a `Header always set ... env=PWBS_VERSIONED_ASSET` guarded by `<IfModule mod_headers.c>`.

**The query-string condition is load-bearing, not decoration.** `public.blade.php` serves the same `adminlte.min.css` and `jquery.min.js` to the guest area *without* a version. A blanket rule - `ExpiresByType text/css "access plus 1 year"` or an unconditional `Header set` - would freeze those copies in every visitor's browser for a year, with no way to invalidate them after a release. `AssetPipelineTest` fails on any cache directive that lacks the `env=` guard, and a second test pins the premise: if `public.blade.php` ever moves to `pwbs_asset()`, that test fails and the condition can be reconsidered - deliberately, and together with the layout.

`immutable` suppresses revalidation even on an explicit reload, which is only safe because the URL carries the file's modification time. `always` rather than the default table, so a `304` carries the policy too.

Measured against the running server (Apache 2.4, `mod_headers` loaded, `mod_expires` not):

| Request | `Cache-Control` |
|---|---|
| `/dist/css/adminlte.min.css?v=1786306980` | `public, max-age=31536000, immutable` |
| the same file, `304 Not Modified` | same |
| `/js/custom.js?v=1773838233` | same |
| `/dist/css/adminlte.min.css` (no version) | none |
| `/js/custom.js?v=abc` (non-numeric token) | none |
| `/pmo-favicon.png?v=123` (not css/js) | none |
| `/login` (HTML) | `no-cache, private`, from Laravel |

Before this rule the server sent no `Cache-Control` and no `Expires` at all - only `ETag` and `Last-Modified` - so the browser held the bytes but revalidated all 12 of the app layout's assets on every page load.

**Not covered, deliberately:** the webfonts under `public/plugins/fontawesome-free/webfonts/`. They are referenced by relative `url()` from inside the stylesheet, so they carry no version and cannot get a long cache safely. They still revalidate.

`Header` requires `AllowOverride FileInfo`, which every working install already grants - the `RewriteRule` directives in the same file need exactly the same override.

### Call sites

| File | Tags | Notes |
|---|---|---|
| `resources/views/layouts/app.blade.php` | 12 (5 css, 7 js) | The Livewire default layout (`config/livewire.php:42`), so every route-level component renders through it |
| `resources/views/layouts/setup.blade.php` | 8 (5 css, 3 js) | The installer wizard |
| `resources/views/livewire/groups/poster-edit-modal.blade.php` | 1 (js) | Summernote, pushed into the `footer_scripts` section - the only asset call inside a Livewire view |

21 tags from the 16 calls the packer had: the three multi-file calls expanded to one tag per source, in the same order. **Concatenation was dropped on purpose** - it affected 3 of 16 call sites, bought nothing measurable over HTTP/2, and the machinery behind it was what wrote into the web root.

### The guest area does not use the helper

`resources/views/public.blade.php` is a **fourth** asset-emitting layout, and it never went through the packer. It serves login, register, password reset, the two-factor challenge, `main.blade.php` and the 404 page - the entire logged-out surface. It links the same vendor files with a raw `asset()`, unversioned, except for one hand-rolled `?ver={{ filemtime(public_path('js/custom.js')) }}` on `custom.js`.

Two consequences worth knowing:

- **The guest pages were never affected by any of the four defects below.** They linked the committed sources directly all along, which is why the mixed-content failure only ever appeared behind a login.
- That inline `filemtime()` is `pwbs_asset()` written by hand for one file. Converting this layout is the obvious follow-up, but it is deliberately **not** part of TODO 33.8: the roadmap scoped that item to the 16 packer call sites, and this layout has no test covering its emission.

## Why the previous pipeline went

All four defects were production-only: `config/packer.php` listed `local` in `ignore_environments`, so development never saw any of them. The first one to surface in the browser did so on 2026-08-09, when a workstation was switched to `APP_ENV=production`.

1. **It corrupted 181 embedded `data:` URIs.** The `url(` rewrite was unconditional, so `url(data:image/svg+xml,...)` came out as `url(http://host/dist/css/data:image/svg+xml,...)` and never loaded. 177 in `adminlte.min.css` (Bootstrap's checkboxes, select arrows, close buttons, accordion chevrons) and 4 in `toastr.min.css`. Measured across every packed stylesheet: **199 `url()` in total, 181 of them `data:`, 18 relative** - and the 18 relative ones did not need rewriting either, because the packed output landed in the same directory as its source.

2. **It baked the request scheme into a cached file.** The rewrite produced absolute URLs, and the generated file was reused indefinitely, since its name derived from the *source* file's `filemtime` rather than from its own contents. A stylesheet generated during one HTTP request kept serving `http://` font URLs to every later HTTPS request - blocked as mixed content, with no self-healing and no cache invalidation.

3. **It wrote into the web root during a request** - in every environment except `local`, including `testing`. Running the test suite regenerated the very files the browser was being served, which is how defect 2 was triggered in practice.

4. **It occupied the `storage:link` path.** `setup.blade.php` targeted `/storage/cache/...`, so rendering the installer created `public/storage` as a real directory. `php artisan storage:link` then reported *"The [public/storage] link already exists"* and skipped the link (`--force` does not help, it only removes an `is_link()`).

Defects 1 to 3 are gone by construction: there is no rewrite, no generated file and no write. Defect 4 is gone from the repository, but the stray `public/storage` directory is **per-install state** on hosts that ran the old code, so `release/upgrade.php` repairs it: the directory is removed **only** when it is not a link and holds nothing except the packer's `cache/` subtree, then `storage:link` runs. Anything else is left alone and reported - a missing symlink is worth far less than someone's data.

The same hook removes `vendor/eusonlito`, `vendor/imagecow`, `config/packer.php` and the generated `*-cache_*` artifacts, because the updater's `install()` only ever adds and overwrites. `release/build-update.php` cross-checks every tracked deletion against the hook and warns about any it does not cover.

## The dead pipeline: Laravel Mix

`webpack.mix.js` and `laravel-mix ^6.0.6` are present, and the roadmap once treated migrating them to Vite as mandatory. Measured state:

- **Zero `mix()` calls** anywhere in the project.
- `resources/css/app.css` is **0 bytes**; `resources/js/app.js` is the untouched 25-byte Laravel default (`require('./bootstrap');`).
- The outputs `public/js/app.js` and `public/css/app.css` **do not exist**, and nothing references them.
- No `node_modules/`, no `package-lock.json`.

It does not run, it never ran here, and nothing consumes its output. Roadmap Phase 7 has been re-scoped accordingly: a Vite migration is a choice about whether to bring the hand-placed vendor assets under a build graph, not a repair of something broken. TODO 52 would replace the `pwbs_asset()` tags with `@vite` in the same three files.

## Livewire's own JavaScript is not served by the helper

`pwbs_asset()` has nothing to do with it, and neither does any build step. It
is worth its own section because the mechanism is not the one the paths
suggest.

`public/vendor/livewire/` holds a **published copy** of the files the installed
`livewire/livewire` package ships in its `dist/` directory, and all of them are
committed. It looks like a cache. It is not:

- Livewire checks `file_exists(public_path('vendor/livewire/manifest.json'))`
  **before** it considers its own `livewire/livewire.js` route, and when that
  file is present it emits a `<script src="/vendor/livewire/livewire.js?id=...">`
  built from the published manifest instead. This is the same rule in both
  versions - `LivewireManager::styles()` in version 2,
  `FrontendAssets::usePublishedAssetsIfAvailable()` in version 3.
- **When the published copy disagrees with the installed package, the only
  symptom is a `console.warn`.** Pages render, every route answers 200, and
  nothing server-side reports a problem. A PHP test suite cannot see it either,
  because it executes no JavaScript.

That combination - authoritative, committed, and silent when wrong - is why it
carries a guard rather than a convention.

### The composer hook that used to keep it in step

`composer.json`'s `post-autoload-dump` used to run:

```
@php artisan vendor:publish --force --tag=livewire:assets --ansi
```

It was removed in roadmap TODO 43. **Not because it breaks** - the tag is still
registered in Livewire 3 and the command exits 0 - but because a hook that
writes **tracked** files into the web root is a hazard in this repository: the
release archive is built from a git diff, so any developer's `composer install`
could change what ships. It also only ever synchronised the two sides as a side
effect of installing, and said nothing when they drifted for any other reason.

`tests/Feature/Assets/LivewirePublishedAssetsTest.php` replaces it with four
assertions: the published manifest is byte-identical to the package's own,
every file the package ships is published with a matching hash, the directory
carries nothing the package does not ship, and every path the manifest names
exists. It fails loudly at the moment the two sides separate.

**So after any change to `livewire/livewire`, republish by hand:**

```
php artisan vendor:publish --force --tag=livewire:assets
```

and commit the result. If the new version stops shipping a file the old one
did, delete the leftover - publishing only ever writes.

**What the guard cannot see:** it compares two directories on disk. A host
serving these files from a CDN through `livewire.asset_url`, or one whose
`public/vendor/livewire/` was edited after deployment, is outside anything this
repository can assert. `upgrade-guide.md` sections 3 and 5 own that half.

## Covered by

| Area | Test |
|---|---|
| The helper: token, missing file, path normalisation, request scheme | `tests/Feature/Assets/AssetPipelineTest.php` |
| The emitted tags, and that they are identical in every environment | `tests/Feature/Assets/AssetPipelineTest.php` |
| That rendering writes nothing into the web root | `tests/Feature/Assets/AssetPipelineTest.php` |
| That no served stylesheet carries an absolute URL or a mangled `data:` URI | `tests/Feature/Assets/AssetPipelineTest.php` |
| The nine closed gaps, the dead Mix pipeline, the call-site count | `tests/Feature/Assets/AssetPipelineKnownGapsTest.php` |
| The published Livewire assets against the package's own `dist/` | `tests/Feature/Assets/LivewirePublishedAssetsTest.php` |

The layout-rendering tests use the real `public/` directory, because only that proves what the browser receives. The two stylesheet guards start from the **rendered HTML** rather than a hand-written list, so if a generating pipeline ever returns, they inspect its output rather than the untouched source.
