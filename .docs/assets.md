# Assets Documentation

## Overview

There is **one** asset pipeline in this repository, and it is not a build step.

| Pipeline | State | What it serves |
|---|---|---|
| `pwbs_asset()` (`app/Helpers/helpers.php`) | **live** | Every stylesheet and script on every page, via 21 blade tags |
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

### Call sites

| File | Tags | Notes |
|---|---|---|
| `resources/views/layouts/app.blade.php` | 12 (5 css, 7 js) | The Livewire default layout (`config/livewire.php:42`), so every route-level component renders through it |
| `resources/views/layouts/setup.blade.php` | 8 (5 css, 3 js) | The installer wizard |
| `resources/views/livewire/groups/poster-edit-modal.blade.php` | 1 (js) | Summernote, pushed into the `footer_scripts` section - the only asset call inside a Livewire view |

21 tags from the 16 calls the packer had: the three multi-file calls expanded to one tag per source, in the same order. **Concatenation was dropped on purpose** - it affected 3 of 16 call sites, bought nothing measurable over HTTP/2, and the machinery behind it was what wrote into the web root.

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

## Covered by

| Area | Test |
|---|---|
| The helper: token, missing file, path normalisation, request scheme | `tests/Feature/Assets/AssetPipelineTest.php` |
| The emitted tags, and that they are identical in every environment | `tests/Feature/Assets/AssetPipelineTest.php` |
| That rendering writes nothing into the web root | `tests/Feature/Assets/AssetPipelineTest.php` |
| That no served stylesheet carries an absolute URL or a mangled `data:` URI | `tests/Feature/Assets/AssetPipelineTest.php` |
| The nine closed gaps, the dead Mix pipeline, the call-site count | `tests/Feature/Assets/AssetPipelineKnownGapsTest.php` |

The layout-rendering tests use the real `public/` directory, because only that proves what the browser receives. The two stylesheet guards start from the **rendered HTML** rather than a hand-written list, so if a generating pipeline ever returns, they inspect its output rather than the untouched source.
