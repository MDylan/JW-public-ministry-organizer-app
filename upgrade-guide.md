# Upgrade guide: 1.x -> 2.0.0

**STATUS: INCOMPLETE. This is a work in progress, not release documentation.**

It is filled in one framework hop at a time and is finished only when the
Laravel 13 hop lands (`upgrade-roadmap.md`, Phase 10, TODO 64-67). Until then
**no host can be taken through this path end to end**, because the later
sections do not exist yet. Do not link it from a release manifest, and do not
paste it into a `description` field, before it is marked complete.

The last line of every section says which TODO last touched it, so a partially
filled section is visibly partial.

## What this document is, and what it is not

This is the **operator's** document: what has to happen on a deployed host to
carry it from the 1.x line to 2.0.0.

Three neighbouring documents own everything else, and this one deliberately
does not repeat them:

- `upgrade-roadmap.md` - the developer's plan. Why each hop happens, in what
  order, and what was measured. Nothing in there is an instruction to an
  operator.
- `release/README.md` - the release machinery: how an archive is built, what
  `previous_version` does, and why publishing a new major means publishing two
  manifests. **Read that one before publishing anything 2.x.**
- `release/upgrade.php` - the automated post-install hook for the **1.x line**,
  frozen since TODO 34 (see below).

## Why 2.0.0 cannot arrive through the auto-updater

This is already settled, and by code rather than by convention.
`App\Support\Updates\UpdateBranch` derives a ceiling from `version.txt`'s major
and refuses any release above it; `App\Http\Middleware\EnsureUpdateWithinBranch`
enforces the same on `/updater.update` itself, so a bookmarked URL cannot get
past it either. An install running 1.2.0 therefore answers **403** to a 2.0.0
release and renders a "manual update required" card instead.

That is the intended behaviour, and it is the reason this guide exists: the
1.x -> 2.0.0 step is a **manual** operation by design. Whether any part of it
can still be automated by a 2.0.0-specific `release/upgrade.php` hook is an
open question that Phase 12 (TODO 75) closes; both branches are prepared for
below.

## Why `release/upgrade.php` is frozen

The hook removes, one `laraupdater_upgrade_remove()` call per entry, whatever a
release orphaned - because `LaraUpdaterController::install()` only ever adds and
overwrites, never deletes. That worked while a release dropped one or two vendor
trees (TODO 33.2 through 33.8).

The framework hops are a different order of magnitude. Laravel 8 -> 13 replaces
essentially the whole `vendor/` tree, and listing every orphan hop by hop in one
`main()` body is not maintainable, not reviewable, and would never be exercised
until the one release that needs it.

So from TODO 34 onwards the hook's `main()` **does not grow**. Orphaned paths
are recorded in section 2 of this guide instead, and the 2.0.0 release decides
in one place what to do with the accumulated list.

The hook stays in the tree and stays shippable: it still serves the 1.x line,
and its `bootstrap/cache` cleanup is needed by every release regardless.

---

## 1. Runtime requirements

The PHP floor rises across the upgrade. This is the section that can make an
upgrade **impossible** rather than merely inconvenient, so it is first: a host
whose PHP cannot be raised cannot take 2.0.0 at all.

| Line | Laravel | PHP floor | Raised by |
|---|---|---|---|
| 1.2.0 (current release) | 8.83.x | `^8.0` | - |
| after Phase 4 | 9.52.x | **`^8.0.2`** | TODO 34 |
| after Phase 5 | 10.x | `^8.1` (planned) | TODO 39 |
| after Phase 8 | 11.x | `^8.2` (planned) | TODO 55 |
| after Phase 10 | 13.x | `^8.3` (planned) | TODO 64 |

Rows marked *planned* come from the roadmap's *Runtime and Version Matrix* and
have not been executed yet. Confirm each against the shipped `composer.json`
when its hop lands; do not quote a planned row to an operator.

**What 2.0.0 will require: PHP 8.3.** That is the only number an operator needs
from this table, and it is the one to verify with the hosting provider **before**
starting anything else.

Note for Phase 4 specifically: `^8.0` -> `^8.0.2` excludes only PHP 8.0.0 and
8.0.1. It is a real exclusion, not a formality, but no host that can run 8.0 at
all is likely to be pinned to those two patch releases.

TODO 35 raised nothing here: removing two packages cannot raise a floor.

*Last updated: TODO 35.*

## 2. Orphaned `vendor/` paths

Packages that a hop removed. They are inert once the new autoloader is in place
- nothing resolves a class from them - but they are dead code sitting in a
deployed tree, and `install()` cannot delete them.

If the 2.0.0 upgrade turns out to be a full `vendor/` replacement (see section
6), this whole list is handled by that replacement and needs no separate action.
It is kept anyway, because the list is also the evidence that a *partial*
upgrade would leave the tree inconsistent.

**TODO 34 (Laravel 8 -> 9):**

```
vendor/facade                     (ignition, flare-client-php, ignition-contracts)
vendor/swiftmailer                (replaced by symfony/mailer, which Laravel 9 requires)
vendor/opis                       (closure; laravel/serializable-closure replaced it)
vendor/maximebf                   (debugbar; renamed to php-debugbar/php-debugbar)
vendor/symfony/polyfill-iconv     (a SwiftMailer dependency)
vendor/symfony/polyfill-php73     (the platform is 8.1)
```

The first four are whole top-level namespace directories with nothing left
under them; the last two are single package directories inside `vendor/symfony`,
which itself stays.

`vendor/maximebf` is a `require-dev` package. That still matters here, because
this repository commits the dev packages too - the release archive carries
them - so they are on deployed hosts as well.

**TODO 35 (proxy and CORS packages):**

```
vendor/fideloper                  (proxy; framework-native since Laravel 9)
vendor/asm89                      (stack-cors; a fruitcake/laravel-cors dependency)
vendor/fruitcake/laravel-cors     (NOT the whole vendor/fruitcake - see below)
```

**`vendor/fruitcake` itself must survive.** Only the `laravel-cors` subdirectory
goes; `vendor/fruitcake/php-cors` stays and is now load-bearing - it is a direct
requirement of `laravel/framework` v9 and supplies the `Fruitcake\Cors\CorsService`
that the framework's `HandleCors` type-hints. Deleting the parent directory
because its name matches the removed package would fatal every request. This is
the first entry in this list that is a *partial* namespace removal, and it will
not be the last.

**`vendor/asm89` is invisible to the release tooling, and always was.**
`.gitignore` used to exclude `/vendor`, and the committed vendor tree was
force-added past it, so a package that was not present at that force-add has
never been tracked - `asm89` among them. `release/build-update.php` builds each
archive from a **git diff**, so a package git cannot see never shipped in an
update and can never be deleted by one either. It reached deployed hosts with
the original full installation and will only be cleared by a full `vendor/`
replacement (section 6, Branch B). Verified: zero `asm89` files in `v1`'s tree
and zero in `release/dist/RELEASE-1.2.0.files.txt`.

**TODO 35.1 closed the mechanism, but not this consequence.** The `/vendor` line
is gone from `.gitignore` and 1017 never-tracked files were committed, so the
tree this branch carries is now complete and a 2.0.0 archive built from it does
contain a working `vendor/`. What that does **not** fix is the deployed 1.x
hosts: they are still carrying whatever the original installation put there,
including packages no update ever shipped and therefore no update can name in a
deletion list. That is an argument for Branch B (full `vendor/` replacement) in
section 6, and it does not expire - it is a property of the hosts, not of this
repository.

The scale of what was missing is worth keeping, because it says how quietly this
failed: fourteen packages had no tracked file at all, `symfony/mailer` among
them in full, and the Laravel 8 -> 9 framework upgrade contributed a single added
file to git while 145 new ones sat on disk. Everything looked present, `git
status` was clean, and the suite was green throughout.

**How this list is derived**, so the next hop does not guess: diff the package
name lists of the old and the new `composer.lock`, reduce each removed name to
its vendor namespace, and keep a namespace only when no package remains under
it. Then verify on disk that the path is actually gone before writing it down.
TODO 35 is the case that shows why the last two steps matter: `fruitcake`
survives the reduction with a package still under it, and `asm89` only appears
at all because the check is done against `composer.lock` and the disk rather
than against the git diff.

**TODO 37: none, and the reason is worth a line.** The `s3` disk was removed
from `config/filesystems.php`, which looks like it should orphan an adapter -
but `league/flysystem-aws-s3-v3` was never installed. It is absent from
`composer.json`, from `composer.lock` and from `vendor/league/`, and so is
`aws/aws-sdk-php`. The entry had been unreachable configuration inherited from
the framework's own defaults, so nothing leaves the tree and nothing is left
behind on a host.

**TODO 38: none.** Moving `resources/lang` to `lang/` touches no package.

**TODO 39.1 (avatars stop being rendered):**

```
vendor/laravolt                   (avatar; replaced by App\Support\Avatar\InitialsAvatar)
vendor/intervention               (image; it was only there for the avatar package)
```

Both namespaces empty out completely - each held exactly one package - so the
reduction described above keeps both. `intervention/image` was never declared in
`composer.json`; it arrived as `laravolt/avatar`'s dependency and leaves with it,
which is why removing one package orphans two paths.

*Last updated: TODO 39.1.*

## 3. Application files that move or disappear

Config files, published views, language directories and generated content that a
hop relocated or deleted. Unlike section 2 these are **application** paths, so
getting one wrong can take live data with it - each entry names what it is safe
to assume.

**TODO 34: nothing.** The Laravel 9 hop changed `composer.json`, `composer.lock`,
`vendor/` and the test layer only. No application file moved.

**TODO 35: nothing.** `config/cors.php` stays exactly where and as it is - the
framework's `HandleCors` reads the same keys the removed package did, so there
is no config file to move, republish or edit. `config/trustedproxy.php` never
existed in this application; the value it would have held came from the removed
package's own default and was `null`, which is also what a missing key reads as.

**TODO 36: nothing.** Two configuration files changed content - `config/mail.php`
and `config/failed-job-monitor.php` - and both are tracked, so both ship in the
archive and overwrite in place. Nothing moves, nothing has to be republished,
and nothing has to be hand-edited on the host.

One dependency worth naming, because it is easy to read this as "no action":
a host that has run `config:cache` keeps serving the OLD values until the cache
is rebuilt. Section 6 step 10 (`php artisan optimize:clear`) is what makes these
two changes take effect, so it is not optional for this hop either.

**TODO 37: nothing moves, two configuration files change content.**
`config/filesystems.php` loses its `s3` disk and `config/livewire.php` loses an
example mentioning it. Both are tracked, both ship in the archive, both
overwrite in place. The same `config:cache` dependency as TODO 36 applies:
step 10 (`optimize:clear`) is what makes them take effect.

### TODO 38: `resources/lang/` -> `lang/`, and this one needs a hand

**This is the first entry in this section that is not "nothing", and skipping
it breaks translations silently.**

122 files move from `resources/lang/` to `lang/` at the project root - 90 PHP
files, five root JSON files (`de`, `fr`, `hu`, `ro`, `sk`) and the 27 published
`vendor/cookie-consent/` files.

**The old directory must be deleted on the host.** Not tidied up later:
deleted, before the first request after the upgrade.

Why, in one mechanism: Laravel decides where the language files are by looking
at the disk, not at configuration. `Application::bindPathsInContainer()`
(`vendor/laravel/framework/src/Illuminate/Foundation/Application.php:349-355`)
reads

```php
if (is_dir($directory = $this->resourcePath('lang'))) {
    return $directory;
}

return $this->basePath('lang');
```

so **`resources/lang/` wins whenever it exists.** An update archive overwrites
files and adds them; it never removes a directory. A host that unpacks 2.0.0
therefore ends up with both directories, and the framework keeps reading the
old one. The new `lang/` tree sits there unread, every translation stays at its
pre-upgrade content, and the in-house translation editor writes into the old
directory as well.

Nothing announces this. There is no exception, no log line and no error page:
keys that exist only in the new tree render as raw keys, and keys whose text
changed keep the old text. It looks like the upgrade simply did not include
translation changes.

The repository's own half of this is guarded -
`tests/Unit/Support/LangPathTest::test_the_repository_carries_exactly_one
_language_directory` fails if both directories ever exist here - but no test
can reach a deployed host. This paragraph is the only thing that can.

`release/build-update.php` lists all 122 old paths in
`RELEASE-<version>.deleted.txt`. That file is the evidence, not the remedy:
nothing executes it, because the hook has been frozen since TODO 34.

Two related notes:

- `resources/lang/vendor/translation/` - the directory `release/upgrade.php`
  removes on the 1.x line - is a special case of the same deletion. A host that
  never ran that hook still has it, and it goes with the rest of
  `resources/lang/`.
- `resources/` itself stays. Only its `lang/` subdirectory goes; `views/` and
  the rest of the tree are untouched.

**TODO 39.1: one directory disappears, and nothing breaks if it lingers.**
`config/laravolt/` (holding `avatar.php`) goes with the package. It is the same
shape of problem as TODO 38 - an archive cannot express a deletion - but not the
same severity: no code reads `config('laravolt.*')` any more, so a host that
keeps the stale directory only carries a dead file. Laravel loads every file
under `config/` into the config repository, so the keys reappear unused; nothing
resolves them.

Delete it anyway when convenient, for the same reason as the rest of section 2:
a partial tree is what makes the *next* upgrade hard to reason about.

*Last updated: TODO 39.1.*

## 4. `.env` changes

New keys, renamed keys, and keys whose meaning changed.

**TODO 34: none.** No environment variable was added, renamed or reinterpreted.

One older item is still outstanding on deployed hosts and belongs here rather
than in a framework section, because it is per-install file state:
**`OPENWAETHER_API_KEY`** is still the spelling in deployed `.env` files.
`config/openweather.php` reads `OPENWEATHER_API_KEY` first and falls back to the
misspelling for exactly one release. Rename it on the host, and only then may
the fallback be dropped from the config.

Nothing else. TODO 28 deliberately moved `env()` calls into config **without**
renaming a single variable, precisely so that no deployed `.env` would need
editing. **TODO 35: none** either.

**TODO 36: none, and one existing key stopped being able to hurt.** The restored
certificate exception keys off `APP_ENV`, which every install already has, and no
new variable was introduced. Separately, a `.env` carrying the literal
`MAIL_FROM_ADDRESS=null` - the line `.env.example` ships - used to make the
failed-job monitor raise a `TypeError` on the first failed queue job, because
`env()` resolves that to a real null and an `env()` default only covers an
*absent* key. That is now handled in `config/failed-job-monitor.php`, so the
`.env` on the host needs no edit. Setting a real address is still the right thing
to do; it is simply no longer load-bearing.

**TODO 37: four keys stop being read, and no host has to do anything.**
`AWS_ACCESS_KEY_ID`, `AWS_SECRET_ACCESS_KEY`, `AWS_DEFAULT_REGION` and
`AWS_BUCKET` left `.env.example` with the `s3` disk. Nothing reads them any
more, so a `.env` that still carries them is not wrong, only misleading -
deleting the four lines is optional housekeeping, not an upgrade step. This is
the opposite of the `MAIL_FROM_ADDRESS` case above precisely because that key
*is* read.

**TODO 38: none.** The language directory move introduces no environment
variable and reinterprets none.

*Last updated: TODO 38.*

## 5. Per-install state to repair

State that lives on the host and is not expressible in a release archive.

- **`bootstrap/cache/packages.php` and `bootstrap/cache/services.php`** must be
  deleted whenever a service provider class disappears or is renamed. They name
  providers by FQCN, so a stale manifest boots the next request straight into a
  "class not found" fatal. Every framework hop from TODO 34 onwards triggers
  this: the Laravel 9 hop alone removed `Facade\Ignition\IgnitionServiceProvider`
  and added `Spatie\LaravelIgnition\IgnitionServiceProvider`, and TODO 35 removed
  `Fideloper\Proxy\TrustedProxyServiceProvider` and
  `Fruitcake\Cors\CorsServiceProvider` without adding anything - a removal is
  just as fatal as a rename, because the manifest still names the class. The 1.x
  hook already does this; a manual upgrade must do it by hand, **before** the
  first request.

  **It is not only framework hops.** TODO 39.1 is not one - it removed a single
  application dependency on Laravel 9 - and it still orphaned two providers
  (`Laravolt\Avatar\ServiceProvider` and `Intervention\Image\ImageServiceProvider`).
  The failure showed up immediately in development, on the first command run
  after the removal: `Class "Intervention\Image\ImageServiceProvider" not found`,
  thrown before anything else could run. Read the rule as "whenever a package
  leaves or arrives", not "whenever the framework moves".
- **`public/storage`** may be a real directory rather than the symlink it should
  be, on any host that ever rendered a page while `eusonlito/laravel-packer` was
  installed. `php artisan storage:link` silently skips when the path exists, so
  the directory has to go first - and only when it holds nothing but the
  packer's `cache/` subtree. Anything else in there is somebody's data and the
  repair must refuse to run. `release/upgrade.php` implements exactly that
  guard; a manual procedure has to reproduce it, not shortcut it.
- **`resources/lang/`** must be deleted after the archive is unpacked, because
  no archive can express a deletion. Section 3 has the mechanism and why it
  fails silently; it is repeated here because this is the list an operator
  works through, and it is the only per-install repair on it that costs
  translations rather than a boot.
- **`public/avatars/`** holds one PNG per user who ever posted on a group
  message board, and after TODO 39.1 nothing reads them: the board computes an
  SVG per render. They are orphaned data, not broken state - leaving them costs
  disk space and nothing else, and no page 404s because no URL points at them
  any more. Safe to delete the directory's contents; safe to ignore. It is
  listed because an operator finding a directory full of avatars after the
  upgrade should know which of the two it is.
- **`optimize:clear`** after every upgrade, and before measuring anything. A
  leftover `bootstrap/cache/config.php` makes the application read the previous
  release's configuration. TODO 39.1 adds a second reason for this hop: a cached
  config still carries the `laravolt.avatar.*` keys from the deleted
  `config/laravolt/avatar.php`, which is harmless but misleading when reading
  `config:show`.

*Last updated: TODO 39.1.*

## 6. The upgrade procedure

**Not decided yet - Phase 12 (TODO 75) closes this.** Both branches are
recorded here so that whichever is chosen, the inputs already exist.

### Branch A - a 2.0.0-specific `release/upgrade.php`

The hook would carry one large `main()` built from sections 2 through 5. It
would run inside `install()`, after the new files are on disk and before
`setCurrentVersion()`, with the OLD autoloader still in memory - so filesystem
work only, nothing that resolves a class from a new package.

What makes this branch hard is not the hook, it is the delivery: the update
would have to reach `install()` at all, and `EnsureUpdateWithinBranch` is
specifically built to stop that. Publishing 2.0.0 on the `/v1` channel with the
ceiling lifted would re-open the exact accident the ceiling prevents.

### Branch B - a manual `vendor/` replacement

The operator does what the auto-updater refuses to do. Draft sequence, to be
verified against a real host before it is published:

1. Confirm the PHP version against section 1. **Stop here if it cannot be met.**
2. Back up the database and the whole application directory. Not "the changed
   files" - the whole directory, because step 5 deletes one.
3. `php artisan down` (maintenance mode).
4. Unpack the 2.0.0 archive over the application directory.
5. **Delete `vendor/` entirely and replace it with the archive's copy.** This is
   what makes section 2 unnecessary and is the reason this branch is simpler
   than it looks: no orphan list has to be correct. It is also the only step
   that can clear the packages section 2 describes, which never shipped in any
   1.x update and which the incremental updater therefore cannot name in a
   deletion list. TODO 35.1 fixed the repository so this cannot grow further;
   it could not retroactively fix the hosts.
6. Delete `bootstrap/cache/packages.php` and `bootstrap/cache/services.php`.
7. Apply sections 3 and 4 (moved files, `.env` keys).
8. `php artisan migrate --force`.
9. Section 5's `public/storage` repair, then `php artisan storage:link`.
10. `php artisan optimize:clear`.
11. `php artisan up`, then smoke-test login, the calendar and one group page.
12. Update `version.txt` if the archive did not.

Rollback is step 2's backup restored wholesale. A partial rollback is not safe
once step 8 has run: `install()`'s own `restore()` brings back files but not
migrations, which is the same reason the major ceiling exists.

*Last updated: TODO 35.1.*

---

## Guide change log

| Hop | TODO | What it added |
|---|---|---|
| Laravel 8 -> 9 | 34 | The document itself; the PHP `^8.0.2` floor, six orphaned vendor paths, the `bootstrap/cache` manifest rule. Sections 3 and 4 gained nothing, which is itself the finding. |
| (no framework hop) | 35 | Three more orphaned paths, the first **partial** namespace removal among them (`vendor/fruitcake/laravel-cors` goes, `vendor/fruitcake/php-cors` must stay). Two facts that change how section 6 reads: a removed service provider breaks a stale `bootstrap/cache` manifest exactly like a renamed one, and untracked `vendor/` packages are unreachable by the incremental updater at all. |
| (no framework hop) | 35.1 | The committed `vendor/` tree was 1017 files short and no archive built from this branch would have booted. Fixed at the source, so section 2's untracked-package problem stops growing - but the deployed 1.x hosts still carry what never shipped, which keeps Branch B's step 5 the only thing that can clear it. Section 6 is no longer blocked. |
| (no framework hop) | 36 | Sections 3 and 4 gained their first entries since TODO 34, and both say "nothing" - but section 3 now also names what that depends on: two configuration files changed content, and a host with a warm `config:cache` keeps the old values until step 10 runs. The verification also found that a `.env` carrying `MAIL_FROM_ADDRESS=null` broke the failed-job monitor outright; fixed in configuration, so no deployed `.env` needs editing. |
| (no framework hop) | 37-38 | **Section 3 gained its first entry that is not "nothing", and section 5 its first repair that costs data rather than a boot.** Moving `resources/lang/` to `lang/` is invisible to an update archive, and Laravel picks the OLD directory whenever it still exists (`Application.php:349-355`), so a host that keeps it freezes every translation at the pre-upgrade content with no error anywhere. TODO 37 contributed nothing to sections 2 and 5, and four now-unread `AWS_*` keys to section 4 - the `s3` disk it removed never had an adapter package to orphan. |
