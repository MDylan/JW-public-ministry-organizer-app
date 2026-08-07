# Assets Documentation

## Overview

There are **two** asset pipelines in this repository. Only one of them runs.

| Pipeline | State | What it serves |
|---|---|---|
| `eusonlito/laravel-packer` | **live** | Every stylesheet and script on every page, via 16 blade call sites |
| Laravel Mix (`webpack.mix.js`) | **dead** | Nothing - see below |

Nothing is compiled at deploy time. The application's CSS and JS are **hand-placed files committed under `public/`** (`public/dist/` for AdminLTE, `public/plugins/` for jQuery, bootstrap, toastr, sweetalert2, summernote and fullcalendar, `public/css/` and `public/js/` for the project's own four files), and Packer concatenates and copies them **during the request**.

Roadmap TODO 21 decided to remove Packer; TODO 33.8 executes it. The whole pipeline is pinned by `tests/Feature/Assets/`.

## The live pipeline: `eusonlito/laravel-packer`

Registered as a **string literal** provider at `config/app.php:185` with a `Packer` facade alias at `:238`, configured by `config/packer.php`.

### Call sites

Only two methods are ever used, `Packer::css()` and `Packer::js()`. Both take `(source|sources, output path)` and return an object the layouts string-cast with `{!! !!}`.

| File | Calls | Notes |
|---|---|---|
| `resources/views/layouts/app.blade.php` | 9 (3 css, 6 js) | The Livewire default layout (`config/livewire.php:42`), so every route-level component renders through it |
| `resources/views/layouts/setup.blade.php` | 6 (3 css, 3 js) | The installer wizard. Targets `/storage/cache/...` where the app layout targets `/cache/...` |
| `resources/views/livewire/groups/poster-edit-modal.blade.php:87` | 1 (js) | Summernote, pushed into the `footer_scripts` section - the only asset call inside a Livewire view |

`Packer::img()`, `jsDir()` and `cssDir()` are **never called**. `img()` is the only reason the `imagecow/imagecow` dependency exists.

### What it actually does

- **13 of the 16 calls pass a single, already-minified file.** For those, Packer copies the file to a `{filemtime}-` prefixed name and nothing else. Only 3 calls concatenate more than one source: the two layouts' `all_style.css` (3 stylesheets) and the app layout's `all.js` (`public/js/custom.js` + `public/js/modal.js`).
- **It never minifies.** `config/packer.php` sets both `css_minify` and `js_minify` to `false`. The one thing it genuinely delivers is **cache busting**, via the `filemtime` prefix.
- **The JS packer prepends a `;` to every file**, so packed output is never byte-identical to its source.
- **The CSS packer rewrites every `url(`** to the asset base plus the source file's directory.
- **`local` is a passthrough.** `config/packer.php` `ignore_environments` lists only `local`; there, Packer emits the individual source tags and writes nothing. `production` **and `testing`** both pack.

### It writes into the web root during a request

`Packer::process()` runs `mkdir` + `tempnam` + `fopen` + `rename` + `chmod 0644` under `public/` while a page is rendering. Twelve artifacts across six directories result, contained by **nine `.gitignore` lines** that exist for no other purpose - seven `*-cache_*` globs next to the vendor assets, `/public/cache`, and a misspelled `/public/storages/cache/*` naming a directory that has never existed.

The test suite writes them too: `SetupFlowTest` renders the setup layout under `APP_ENV=testing`, which is not in `ignore_environments`.

## Traps

All four are pinned by `tests/Feature/Assets/AssetPipelineKnownGapsTest.php`; the TODO that owns each is named there.

1. **`data:` URIs in packed CSS are corrupted.** The `url(` rewrite is unconditional, so `toastr.min.css`'s four `data:image/png;base64,...` icons come out as `url(http://host/plugins/toastr/data:image/png;base64,...)`. **All four toastr icons are broken in every non-`local` environment** - and fine locally, because `local` skips packing. The project gains nothing from the rewrite: both of its own stylesheets contain zero `url(`. Roadmap TODO 33.8.

2. **`public/storage` is a real directory, not a symlink.** `setup.blade.php` sends its packed output to `/storage/cache/...`, and Packer creates the missing directory. `php artisan storage:link` then reports *"The [public/storage] link already exists"* and **skips the link** - `--force` does not help, since it only removes an `is_link()` - so every public-disk URL 404s. On a fresh deploy the installer wizard renders before anyone runs `storage:link`, which reproduces it. The repair is per-install, not per-release. Roadmap TODO 33.8.

3. **The provider's deferral has been inert since Laravel 5.8.** `PackerServiceProvider` uses the removed `protected $defer = true;` with a `provides()` method, but does not implement `DeferrableProvider`, so it registers eagerly on every request.

4. **`imagecow/imagecow` is installed for dead code.** Nothing else in the dependency tree requires it, and the API it serves (`Packer::img()`) has zero call sites.

## The dead pipeline: Laravel Mix

`webpack.mix.js` and `laravel-mix ^6.0.6` are present, and the roadmap once treated migrating them to Vite as mandatory. Measured state:

- **Zero `mix()` calls** anywhere in the project.
- `resources/css/app.css` is **0 bytes**; `resources/js/app.js` is the untouched 25-byte Laravel default (`require('./bootstrap');`).
- The outputs `public/js/app.js` and `public/css/app.css` **do not exist**, and nothing references them.
- No `node_modules/`, no `package-lock.json`.

It does not run, it never ran here, and nothing consumes its output. Roadmap Phase 7 has been re-scoped accordingly: a Vite migration is a choice about whether to bring the hand-placed vendor assets under a build graph, not a repair of something broken.

## Covered by

| Area | Test |
|---|---|
| The emitted tags, packed and passthrough | `tests/Feature/Assets/AssetPipelineTest.php` |
| Concatenation order, single-file copy, `filemtime` prefix, directory creation | `tests/Feature/Assets/AssetPipelineTest.php` |
| The four traps, the dead Mix pipeline, the call-site count | `tests/Feature/Assets/AssetPipelineKnownGapsTest.php` |

The two layout-rendering tests use the real `public/` directory, because only that proves what the browser receives. Everything else runs against a temporary `public_path` with a directly instantiated `Packer` and cleans up after itself.
