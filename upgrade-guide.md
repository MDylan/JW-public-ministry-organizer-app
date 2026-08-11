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
`.gitignore` excludes `/vendor`, and the committed vendor tree was force-added,
so a package that was not present at that force-add has never been tracked -
`asm89` among them. `release/build-update.php` builds each archive from a **git
diff**, so a package git cannot see never shipped in an update and can never be
deleted by one either. It reached deployed hosts with the original full
installation and will only be cleared by a full `vendor/` replacement
(section 6, Branch B).

**That is not a footnote about one package - the tree is missing 1037 files, and
TODO 35.1 owns it.** Fourteen packages have no tracked file at all, `symfony/mailer`
included, and the Laravel 8 -> 9 framework upgrade contributed a single added
file to git while 145 new ones sit untracked on disk. **No release can currently
be built from this branch that would boot**, which makes TODO 35.1 a
prerequisite of section 6 rather than a cleanup task. Read that entry before
planning the 2.0.0 package, whichever branch is chosen - the manual
full-`vendor/` route needs a complete `vendor/` to hand out just as much as the
automated one does.

**How this list is derived**, so the next hop does not guess: diff the package
name lists of the old and the new `composer.lock`, reduce each removed name to
its vendor namespace, and keep a namespace only when no package remains under
it. Then verify on disk that the path is actually gone before writing it down.
TODO 35 is the case that shows why the last two steps matter: `fruitcake`
survives the reduction with a package still under it, and `asm89` only appears
at all because the check is done against `composer.lock` and the disk rather
than against the git diff.

*Last updated: TODO 35.*

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

Known to be coming: `resources/lang/` -> `lang/` (TODO 38). Laravel 9 still
accepts the old location, which is why it did not happen in this hop.

*Last updated: TODO 35.*

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

*Last updated: TODO 35.*

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
- **`public/storage`** may be a real directory rather than the symlink it should
  be, on any host that ever rendered a page while `eusonlito/laravel-packer` was
  installed. `php artisan storage:link` silently skips when the path exists, so
  the directory has to go first - and only when it holds nothing but the
  packer's `cache/` subtree. Anything else in there is somebody's data and the
  repair must refuse to run. `release/upgrade.php` implements exactly that
  guard; a manual procedure has to reproduce it, not shortcut it.
- **`optimize:clear`** after every upgrade, and before measuring anything. A
  leftover `bootstrap/cache/config.php` makes the application read the previous
  release's configuration.

*Last updated: TODO 35.*

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
   that can clear the untracked packages section 2 describes, which the
   incremental updater has never been able to touch.
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

*Last updated: TODO 35.*

---

## Guide change log

| Hop | TODO | What it added |
|---|---|---|
| Laravel 8 -> 9 | 34 | The document itself; the PHP `^8.0.2` floor, six orphaned vendor paths, the `bootstrap/cache` manifest rule. Sections 3 and 4 gained nothing, which is itself the finding. |
| (no framework hop) | 35 | Three more orphaned paths, the first **partial** namespace removal among them (`vendor/fruitcake/laravel-cors` goes, `vendor/fruitcake/php-cors` must stay). Two facts that change how section 6 reads: a removed service provider breaks a stale `bootstrap/cache` manifest exactly like a renamed one, and untracked `vendor/` packages are unreachable by the incremental updater at all. |
