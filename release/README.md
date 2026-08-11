# Release artifacts

Files here are **not part of the running application**. They are packaged into the
update archives served from `config('laraupdater.update_baseurl')`
(`https://updates.teruletek.hu/v1`).

## `build-update.php`

Builds the archive and the manifest from the file difference between two git
refs — by default `v1` (the last published release) and `dev`.

```
php81 release/build-update.php                       # v1 -> dev
php81 release/build-update.php --dry-run             # report only, writes nothing
php81 release/build-update.php --base=v1 --head=dev --description="..." --previous=1.1.5
```

Output goes to `release/dist/` (git-ignored):

| File | Purpose |
|---|---|
| `RELEASE-<version>.zip` | upload to the channel |
| `laraupdater.json` | upload next to it — this is what every install checks |
| `laraupdater-<version>.json` | byte-identical copy, upload as well |
| `RELEASE-<version>.files.txt` | what shipped, for auditing |
| `RELEASE-<version>.deleted.txt` | what `install()` **cannot** remove |

Upload all three. `laraupdater.json` is overwritten by every release and only ever
describes the latest one, so without the versioned copy an older release becomes
unaddressable the moment the next one is published — and the versioned file is
exactly what `getLastVersion()` fetches when a later release names this version
in its `previous_version` chain.

The version comes from `version.txt` on the head ref; the build refuses to
produce a release that is not newer than the base's, since `check()` would never
offer it.

### What it excludes

`.claude/`, `.docs/`, `.github/`, `release/`, `tests/`, `upgrade-notes/`,
`AGENTS.md`, `CLAUDE.md`, `upgrade-roadmap.md`, `phpunit.xml`, `.gitignore`,
`.env.example`, `.env.testing`. These are tracked by git and appear in the diff,
but they are not the running application. `release/upgrade.php` is added back
explicitly, as a **root-level `upgrade.php`** entry, so no `release/` directory
is created on production hosts. `composer.json` and `composer.lock` **do** ship:
they describe the `vendor/` tree that ships with them.

Only files that would actually ship have to be committed. Editing the builder
itself, or leaving `release/dist/` around, does not block a build.

> **This rule is currently violated, and the build does not notice — see TODO 35.1
> in `upgrade-roadmap.md` before publishing anything from `v2-dev`.**
> `.gitignore` excludes `/vendor` while this repository commits `vendor/` on
> purpose, so `git add -A` skips every newly added package. As of 2026-08-11,
> 1037 of the 9465 files under `vendor/` are untracked, `symfony/mailer` among
> them in full. An archive built from this branch would carry a Laravel 9
> framework without the mailer it requires, and would additionally list
> `vendor/fruitcake/php-cors` as a deletion — a package the framework's
> `HandleCors` cannot resolve without. The dirty-tree check below cannot catch
> this: `git status --porcelain` does not report ignored files.

### The directory-entry requirement

`install()` creates a directory only when the **zip entry itself** is a
directory. For a file it calls `File::move()`, i.e. `rename()`, which raises a
warning when the target directory does not exist — `HandleExceptions` turns that
into an `ErrorException`, `install()`'s `try/catch` returns `false`, and
`update()` rolls the entire release back through `restore()`.

So a release that adds a new directory (a new vendor package, a new app
namespace) and does not carry that directory's own entry **fails to install**.
The builder writes all directory entries first, sorted so a parent always
precedes its children, and then re-opens the finished archive to verify that
invariant before reporting success.

This was measured, not assumed: a one-file archive without the directory entry
fails the move and the file never lands; with the entry it succeeds. The
1.1.5 → 1.2.0 archive needs 87 such directories.

### The `upgrade.php` trap, enforced

`install()` matches the hook with `strpos($filename, 'upgrade.php') !== false` —
**any** entry whose path contains that string. A second match would be swallowed
instead of deployed, and would overwrite the real hook. The build aborts if any
shipped path contains it.

### Deletions are reported, never automated

`install()` only adds and overwrites. Files that disappeared between the two refs
cannot be expressed in the archive at all, so the builder lists them in
`RELEASE-<version>.deleted.txt` and prints the ones the current `upgrade.php`
does **not** already cover, grouped by package. Acting on that list is a manual
decision: it is the hook's job, and deleting 400 paths on a live host is not
something a build script should decide by itself.

For 1.1.5 → 1.2.0 that list is 407 paths, and the eight **fully removed**
packages are the part worth adding to the hook:

```
vendor/pcinaglia/laraupdater          (already covered)
vendor/phpdocumentor/reflection-common
vendor/phpdocumentor/reflection-docblock
vendor/phpdocumentor/type-resolver
vendor/phpspec/prophecy
vendor/symfony/debug
vendor/symfony/polyfill-php72
vendor/webmozart/assert
```

The rest is file-level churn inside packages that still exist (`doctrine/inflector`
moving `lib/` to `src/`, Carbon's Doctrine types moving to `carbonphp/`). Those
leftovers are dead weight, not a fault: the shipped `vendor/composer/` autoload
maps point at the new paths.

### `previous_version` is not free

Setting it makes `getLastVersion()` fetch `laraupdater-<previous>.json` for any
install older than that version — and on the `update()` path that fetch has **no
`try/catch`**. If the file is not on the channel, those installs get an uncaught
`ErrorException` instead of an update prompt. The builder warns whenever the key
is present. See the manifest section below for when the chain is actually needed.

A hand-edited `description` or `previous_version` in `release/dist/laraupdater.json`
survives a rebuild: the builder reads the existing manifest back and only the CLI
options override it. That carry-over is deliberately limited to a manifest for the
**same version** — after a version bump the file still holds the previous release's
changelog, and publishing that text under a new version number would be worse than
an obvious placeholder. `previous_version` is never inherited across a bump either:
a key that can 500 older installs has to be named explicitly.

## `upgrade.php`

The per-release hook that `MDylan\LaraUpdater\LaraUpdaterController::install()`
executes while unpacking an archive.

### How the updater finds it

`install()` iterates the archive entries and matches with
`strpos($filename, 'upgrade.php') !== false` — **any** path containing that string
qualifies, so it works whether the file sits at the archive root or under
`release/`. The matched entry is moved to `<base_path>/tmp/upgrade.php`,
`include`d, and `main()` is called. Its return value only decides which line is
printed; a falsy return does **not** abort the update. The file is deleted
afterwards, so it never reaches the deployed tree.

### What you can rely on inside `main()`

| Available | Not available |
|---|---|
| A fully booted Laravel: `base_path()`, the `File` facade, config, DB | Classes from packages **added** by this release — the autoloader in memory is still the pre-update one |
| The new files, already written to disk | `version.txt` updated — `setCurrentVersion()` runs after the hook |

Order inside `update()`: `download()` → `Artisan::call('down')` → **`install()` →
hook** → `setCurrentVersion()` → `optimize:clear` → `Artisan::call('up')`.

Anything thrown out of the hook is caught by `install()`'s `try/catch`, which
returns `false` and sends `update()` into `restore()` — a full rollback from the
`backup_YYYYMMDD` directory. Keep the hook non-throwing.

### Why the current one exists

`install()` only ever **adds and overwrites**. It never deletes. Release **1.1.6**
renames `pcinaglia/laraupdater` to `mdylan/laraupdater` (roadmap TODO 18 /
TODO 33.4), so without the hook every deployed site would keep a dead
`vendor/pcinaglia/` tree forever. The hook also clears
`bootstrap/cache/packages.php`, which names service providers by FQCN and would
otherwise boot the next request into a "class not found" fatal. `optimize:clear`
does that too, a few lines later — the hook repeats it so the rename survives even
if that Artisan call fails.

### After 1.1.6 has reached every install

The hook is idempotent and safe to run repeatedly, but it is a one-off. Empty the
body of `main()` or delete the file so later archives stop carrying it.

## Building an archive — the constraint that matters

`vendor/` is committed to this repository (force-added past the `/vendor` line in
`.gitignore`) **because there is no `composer install` on the target host**. The
archive has to carry the full `vendor/` tree, and anything Composer adds needs an
explicit `git add -f` or it will be missing from the release and every updated
site will fatal on boot.

**But `git add -f -A vendor` sweeps in more than Composer put there.** The VS Code
Laravel extension writes `vendor/_laravel_ide/` — 16 generated `discover-*.php`
files that are not part of any package, churn on every IDE run, and have no
business in a release archive. `.gitignore` cannot stop it: `-f` is exactly the
flag that overrides `.gitignore`. Check `git status --porcelain vendor/` for
directories Composer did not name before committing a dependency bump. This was
caught after the fact once, on `v1-patch H`.

## Manifest shape — what the major ceiling depends on

Since the update branch ceiling landed (`App\Support\Updates\UpdateBranch`), an
install refuses to auto-install a release whose **major** is higher than its own.
It renders a "manual update required" card instead, and `/updater.update` answers
403.

That guard only protects installs **already running the release that contains it**.
Older ones have to be routed through it first, and the package's `previous_version`
chain is the mechanism — so publishing a new major on the `/v1` channel means
publishing two manifests, not one:

```json
// /v1/laraupdater.json
{ "version": "2.0.0", "archive": "RELEASE-2.0.0.zip",
  "previous_version": "1.2.0", "description": "…" }

// /v1/laraupdater-1.2.0.json
{ "version": "1.2.0", "archive": "RELEASE-1.2.0.zip", "description": "…" }
```

where `1.2.0` is the last 1.x release — the one carrying the ceiling.

| Installed | `check()` resolves to | Result |
|---|---|---|
| 1.1.5 (pre-ceiling) | `1.2.0` (chain steps back) | auto-updates onto the ceiling release |
| 1.2.0 (has the ceiling) | `2.0.0` | blocked → manual update card |

Drop the `previous_version` key and every pre-ceiling install jumps straight to
2.0.0 — the exact accident the ceiling exists to prevent. **Ship the ceiling
release before any 2.x manifest reaches the `/v1` channel.**

The `description` of the blocked release is rendered verbatim on the manual card,
so the upgrade instructions are edited on the server, not in the application.
