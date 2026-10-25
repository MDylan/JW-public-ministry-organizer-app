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

**Corrected by TODO 54: the Phase 3 half of the hook has never run on any
host.** The paragraph above says the hook "worked" through TODO 33.8. It was
written, but it never shipped: the last 1.x release is **1.2.0** (`v1` and the
`v.1.2.0` tag are the same commit), and that release's hook stops at
`vendor/rakibdevs`. Everything `main()` gained in Phase 3 - the packer, the GDPR,
translation and pending-email packages, their published files, the packer's
generated output and the `public/storage` repair - exists only on the 2.x
branch, and 2.x never reaches `install()`. So every deployed host still runs
those packages, and none of that cleanup has happened anywhere. Sections 2, 3
and 5 now carry it as their **"Before TODO 34"** entries; it is not covered by
the hook, whichever branch section 6 ends up taking.

---

## 1. Runtime requirements

The PHP floor rises across the upgrade. This is the section that can make an
upgrade **impossible** rather than merely inconvenient, so it is first: a host
whose PHP cannot be raised cannot take 2.0.0 at all.

| Line | Laravel | PHP floor | Raised by |
|---|---|---|---|
| 1.2.0 (current release) | 8.83.x | `^8.0` | - |
| after Phase 4 | 9.52.x | **`^8.0.2`** | TODO 34 |
| after Phase 5 | **10.50.x** | **`^8.1`** | TODO 39 |
| after Phase 8 | **11.57.x** | **`^8.2`** | TODO 55 |
| after Phase 9 | **12.70.x** | `^8.2` (unchanged) | - (TODO 61) |
| after Phase 10 | **13.38.x** | **`^8.3`** | TODO 64 |

Rows marked *planned* come from the roadmap's *Runtime and Version Matrix* and
have not been executed yet. Confirm each against the shipped `composer.json`
when its hop lands; do not quote a planned row to an operator.

**What 2.0.0 will require: PHP 8.3.** That is the only number an operator needs
from this table, and it is the one to verify with the hosting provider **before**
starting anything else.

**How that number is chosen, from 2026-10-24 on:** the minimum is always the
oldest PHP branch that still has at least one more year of security support
(php.net/supported-versions). PHP 8.3's ends on 2027-12-31, so it qualifies
until 2026-12-31. **From 2027-01-01 the minimum is PHP 8.4** - a release made
after that date requires it. An operator choosing a PHP version for a host now
should take 8.4 or newer and skip one migration.

Note for Phase 4 specifically: `^8.0` -> `^8.0.2` excludes only PHP 8.0.0 and
8.0.1. It is a real exclusion, not a formality, but no host that can run 8.0 at
all is likely to be pinned to those two patch releases.

TODO 35 raised nothing here: removing two packages cannot raise a floor.

**TODO 55 raised it to `^8.2`**, Laravel 11's own floor. It does not change the
answer above - 2.0.0 needs 8.3 regardless - but from this hop on a host on PHP
8.1 cannot run the tree at all.

**TODO 61 (Laravel 11 -> 12) raised nothing**: Laravel 12 keeps `^8.2`, and
`composer.json` keeps it too.

**TODO 64 raised it to `^8.3`**, Laravel 13's own floor - the number this
section has been announcing since TODO 34 is now the shipped one. A host on
PHP 8.2 cannot run the tree at all from this hop on.

*Last updated: TODO 64.*

## 2. Orphaned `vendor/` paths

Packages that a hop removed. They are inert once the new autoloader is in place
- nothing resolves a class from them - but they are dead code sitting in a
deployed tree, and `install()` cannot delete them.

If the 2.0.0 upgrade turns out to be a full `vendor/` replacement (see section
6), this whole list is handled by that replacement and needs no separate action.
It is kept anyway, because the list is also the evidence that a *partial*
upgrade would leave the tree inconsistent.

**Before TODO 34 - TODO 23, 24 and Phase 3, added by TODO 54.** This list used to
start at TODO 34, on the assumption that what left earlier was handled by
`release/upgrade.php`. It was not (see "Why `release/upgrade.php` is frozen"):
these packages are on every 1.2.0 host today.

```
vendor/laravelcollective          (html; TODO 23)
vendor/doctrine/cache             (TODO 24's re-resolution - NOT the whole vendor/doctrine)
vendor/dialect                    (gdpr-compliance; TODO 33.2)
vendor/joedixon                   (laravel-translation; TODO 33.3)
vendor/protonemedia               (laravel-verify-new-email; TODO 33.5)
vendor/eusonlito                  (laravel-packer; TODO 33.8)
vendor/imagecow                   (the packer's image library; TODO 33.8)
```

Derived by the method described below, against `v1`'s tree rather than a lock
diff: every vendor namespace and package directory present in 1.2.0 and absent
from this branch, minus the ones the later entries already list. `vendor/doctrine`
stays (dbal lives there until TODO 57), and so does `vendor/symfony`.
`vendor/dialect` is the one with a consequence outside `vendor/`: section 3's
first "Before TODO 34" file extends a class from it.

**`vendor/symfony/polyfill-php81` was on this list until TODO 69 - do not delete
it.** TODO 24 had made it dead weight; the passkey library (`spomky-labs/cbor-php`)
requires it again, and the 2.x tree ships it.

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

**TODO 39.2: none.** Lifting the `laravel/fortify` ceiling from `~1.11.2` to
`>=1.19.1 <1.37.0` moved one package and added none: 0 installs, 1 update, 0
removals. The `league/commonmark` advisory bump in the same change set is the
same shape. Nothing leaves a vendor namespace, so nothing is orphaned on a host.

**TODO 39 (Laravel 9 -> 10): two partial paths, and no whole namespace.**

```
vendor/doctrine/instantiator            (a PHPUnit 9 dependency)
vendor/sebastian/resource-operations    (a PHPUnit 9 dependency)
```

These are the lock's only two removals against 71 additions and updates. Both
namespaces **stay** - `doctrine` still holds dbal and its own dependencies,
`sebastian` still holds fifteen packages - so the reduction described above
keeps neither, and these are package paths rather than namespace paths. That is
the same shape as `vendor/fruitcake/laravel-cors` at TODO 35, and the same
warning applies: deleting the namespace rather than the package would take
working code with it.

Both are `require-dev`, and both still shipped, because this repository commits
the dev tree too - see the TODO 34 entry above for why.

**TODO 41: none.** The deprecation sweep changed no dependency at all - no
`composer.json` edit, no lock movement - so nothing orphans and nothing arrives.

**TODO 43 (Livewire 2 -> 3): none, and that is worth stating rather than
skipping.** The lock moved - `livewire/livewire` v2.12.8 -> v3.8.8 - but the
resolution is a single entry: 0 installs, 1 update, 0 removals. Every package
Livewire 3 requires was already locked, so no vendor namespace empties and no
vendor package directory is left behind. `vendor/livewire/livewire` is
**replaced in place**, which is the one shape this section has not carried
before: a major version change that orphans nothing.

The file it does orphan is not under `vendor/` at all, and section 3 has it.

**TODO 55 (Laravel 10 -> 11): none.** 5 installs, 46 updates, **0 removals**:
no vendor namespace or package directory empties. Files inside updated packages
come and go - `laravel/framework` alone changed shape considerably - which a
full `vendor/` replacement (section 6, Branch B) handles and an incremental
overwrite would not; that is the general argument for Branch B again, not a new
path to list.

**TODO 55.2 (ignition removed): five package paths, and the namespace stays.**

```
vendor/spatie/laravel-ignition
vendor/spatie/ignition
vendor/spatie/flare-client-php
vendor/spatie/error-solutions
vendor/spatie/backtrace
```

**`vendor/spatie` itself must survive** - `calendar-links`, `laravel-activitylog`,
`laravel-cookie-consent`, `laravel-failed-job-monitor` and `laravel-package-tools`
live there and are load-bearing. The same warning as `vendor/fruitcake` at
TODO 35: delete the five directories, not their parent.

**TODO 57 (doctrine/dbal removed): three package paths (four until TODO 69), and
both namespaces stay.**

```
vendor/doctrine/dbal
vendor/doctrine/event-manager
vendor/psr/cache
```

**`vendor/doctrine` and `vendor/psr` must survive** - `doctrine/inflector` (the
framework's) and `doctrine/lexer` live in the first, `psr/log`, `psr/container`,
`psr/simple-cache` and five more PSR interfaces in the second, and all are
load-bearing. Delete the four directories, not their parents. `vendor/bin/`
also loses `doctrine-dbal` and `doctrine-dbal.bat`; they are inert.

**`vendor/doctrine/deprecations` was the fourth path here until TODO 69 - do not
delete it.** The passkey library's `phpdocumentor/*` dependencies require it
again, and the 2.x tree ships it.

**TODO 61 (Laravel 11 -> 12): none.** 2 installs, 24 updates, **0 removals**:
`symfony/polyfill-php84` and `staabm/side-effects-detector` arrive, and the
framework, `nunomaduro/collision` and the PHPUnit 11 family (`phpunit/*`,
`sebastian/*`) are replaced in place. As at TODO 55, files inside the updated
packages come and go, which Branch B's full `vendor/` replacement handles.

**TODO 64 (PHPUnit 12, then Laravel 12 -> 13): two package paths, and the
namespace stays.**

```
vendor/sebastian/code-unit
vendor/sebastian/code-unit-reverse-lookup
```

PHPUnit 12 no longer needs them. **`vendor/sebastian` itself must survive** -
PHPUnit's other dependencies live there. The framework hop itself removed
nothing (1 install, 3 updates).

**TODO 66 (tinker 3, Debugbar 4): none.** Both are replaced in place; Debugbar
4 changed its PHP namespace, not its package directory
(`vendor/barryvdh/laravel-debugbar`), and `php-debugbar/symfony-bridge`
arrives.

**TODO 69 (Fortify 1.41, passkeys): none.** 16 installs, 1 update, 0 removals.
`doctrine/deprecations` and `symfony/polyfill-php81` arrive again - required by
the passkey library's dependencies this time - so on a host that removed them
after TODO 57 or TODO 23 they are simply back, and on one that did not they are
no longer orphaned. Delete neither from now on.

**TODO 70 (authorization fixes, HTMLPurifier): none.** 1 install
(`ezyang/htmlpurifier`), 0 updates, 0 removals.

**TODO 71 (API surface and CORS removed): none.** The lock does not move.
`vendor/fruitcake/php-cors` is no longer load-bearing - `HandleCors` left the
middleware stack - but it is still a hard requirement of `laravel/framework`,
so the TODO 35 rule above stands: keep it.

*Last updated: TODO 71.*

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

**TODO 39.2: nothing moves, but a database column disappears - and that is the
first entry in this section that is not a file.** The two-factor confirmation
moved from a `two_factor_confirmed` boolean to a nullable
`two_factor_confirmed_at` timestamp. No application file was relocated or
deleted; the change is entirely inside `users`.

It matters here because a schema change is as invisible to an update archive as
a moved directory, and cuts the other way: the archive carries the migration,
so it applies itself as soon as `migrate` runs. What an operator has to know is
that it is **not additive**. See section 5 for the rollback consequence.

**TODO 39: nothing moves, and one class of file changes content that an
administrator can also edit.** No application file was relocated or deleted by
the Laravel 10 hop. But `lang/<locale>/validation.php` changed in all six
locales - the password-strength messages moved to the key Laravel 10 resolves -
and those files are exactly the ones the built-in translation editor writes:
`App\Support\Translation\LangFiles` globs every `*.php` in a locale
directory.

So on a host where an administrator has edited a `validation.php` through that
editor, the archive overwrites their version. It has to: the old content makes
every password-strength error render as `validation.password.mixed` and its
siblings, in every language. Anything else customised in those six files goes
with it, so it is worth exporting them before the upgrade and re-applying the
wanted changes through the editor afterwards.

**TODO 41: none.** Fourteen files under `app/` changed content, one of them
the HTTP kernel, whose middleware alias property was renamed - but no file
moves, no file disappears, and none of them is editable from inside the
application the way the `validation.php` files above are. An archive that
overwrites them is the whole of the change.

### TODO 43: `public/vendor/livewire/livewire.js.map`, and why this one matters

**One file is deleted from the repository and a host cannot be made to delete
it.** `LaraUpdaterController::install()` adds and overwrites; it never removes.
So after the 2.0.0 archive lands, a deployed host keeps a 485 KB
`public/vendor/livewire/livewire.js.map` that belongs to Livewire 2 and that
nothing references any more.

**It is safe to leave and safe to delete.** Nothing loads it: a browser fetches
a source map only when devtools are open and only when the JavaScript file
names it, and the Livewire 3 `livewire.js` names its own. Delete it by hand if
you would rather not carry it:

```
rm public/vendor/livewire/livewire.js.map
```

**What this directory is, because it is not what its path suggests.** It is not
a cache and it is not optional. Livewire checks whether
`public/vendor/livewire/manifest.json` exists *before* it considers its own
`livewire/livewire.js` route, and when the file is there it serves the
published copy instead. That is true in both versions
(`LivewireManager::styles()` in 2, `FrontendAssets::usePublishedAssetsIfAvailable()`
in 3). **If the published copy and the installed package disagree, the only
symptom is a `console.warn`** - pages render, routes answer 200, and the
application quietly behaves as though half of it had not been upgraded.

The 2.0.0 archive ships the correct version 3 files, so an ordinary install
needs no action. The case to watch is a host where this directory was edited by
hand, or where a deployment process regenerates it.

**TODO 51: `app/Http/Livewire/` disappears, and nothing breaks if it lingers.**
The 28 Livewire component classes moved to `app/Livewire/` and the
`App\Livewire` namespace, Livewire 3's default. The archive carries the new
files; it cannot remove the old directory, so a host ends up with both.

The old copy is inert. Livewire finds a component by turning its name into a
class under `App\Livewire`, the routes name the new classes, and the archive's
`vendor/composer/autoload_classmap.php` no longer lists the old ones - nothing
resolves a class from that directory. Delete it anyway, for the reason given
at TODO 39.1:

```
rm -r app/Http/Livewire
```

Leave the rest of `app/Http/` alone - `Controllers`, `Middleware` and
`Requests` stay where they are. (So did `Kernel.php` until TODO 56, below.)

**TODO 52: the Laravel Mix files disappear, and nothing breaks if they linger.**
`webpack.mix.js`, `package.json`, `resources/js/` and `resources/css/` were
removed from the repository. They were a build pipeline that never produced
anything - the application's CSS and JS were always the committed files under
`public/`, and they still are. The archive cannot delete, so a host keeps the
old copies.

They are inert: no PHP code, view or configuration reads them, and a host never
ran `npm` for this application. Delete them for the same reason as above:

```
rm webpack.mix.js package.json
rm -r resources/js resources/css
```

Leave `resources/views/` alone - it is the only directory `resources/` still
needs.

**Before TODO 34 - the Phase 3 files, added by TODO 54. One of them stops every
Artisan command.** Phase 3 replaced four packages with project code and deleted
what they had put into the application. The 1.x hook was meant to remove those
files and never shipped (see the preamble), so a 1.2.0 host has all of them.

**`app/Console/Commands/PackageAnonymizeInactiveUsers.php` must be deleted
before the first `php artisan` command** - in section 6's Branch B that is
step 7, ahead of step 8's `migrate`. The console kernel loads every class it
finds in `app/Console/Commands/`, and the 1.x copy of this file extends
`Dialect\Gdpr\Commands\AnonymizeInactiveUsers`. Once `vendor/dialect` is gone,
every Artisan command - `migrate`, `optimize:clear`, the scheduler's
`schedule:run` - dies on boot with:

```
Class "Dialect\Gdpr\Commands\AnonymizeInactiveUsers" not found
```

Reproduced at TODO 54 by putting the 1.2.0 file back into a Laravel 10 tree:
`php artisan list` fails exactly like that, and works again once the file is
removed. **Web pages keep working**, because an HTTP request never loads the
console kernel - so a host can look healthy while its scheduler, and with it
the GDPR anonymization and every other scheduled job, has silently stopped. Do not
rely on `vendor/dialect` still being there either: the file was a workaround
for that package's scheduling, its command name is not the one the scheduler
runs any more, and Branch B removes the package regardless.

The rest are inert - nothing loads, renders or serves them for the application
- and go for the reason given at TODO 39.1:

- `app/Http/Middleware/RedirectIfUnansweredTerms.php` - never registered, and
  the `gdpr-terms` route it redirects to no longer exists.
- `config/packer.php`, `config/translation.php` - loaded into the configuration
  on every request, like every file in `config/`, but neither runs code when
  loaded and nothing reads their keys.
- `resources/views/gdpr/`, `resources/views/vendor/translation/`,
  `resources/views/vendor/verify-new-email/` - views of packages that no longer
  register a namespace. The two e-mails moved to `resources/views/emails/`.
- `public/vendor/translation/` - the old editor's compiled front-end, publicly
  served and referenced by nothing.
- `resources/lang/vendor/translation/` - already covered: TODO 38 deletes the
  whole of `resources/lang/`.

```
rm app/Console/Commands/PackageAnonymizeInactiveUsers.php
rm app/Http/Middleware/RedirectIfUnansweredTerms.php
rm config/packer.php config/translation.php
rm -r resources/views/gdpr resources/views/vendor/translation resources/views/vendor/verify-new-email
rm -r public/vendor/translation
```

**Keep** `config/gdpr.php` and `config/verify-new-email.php` - both are
project-owned now and still read - and the other directories under
`resources/views/vendor/` (`cookie-consent`, `installer`, `livewire`).

**TODO 56: the Laravel 10 skeleton classes disappear; they are inert if they
linger.** The application moved to Laravel 11's slim skeleton:
`bootstrap/app.php` now configures routing, middleware and exceptions, the
application's service providers are listed in the new `bootstrap/providers.php`,
and the schedule lives in `routes/console.php`. `bootstrap/app.php`,
`bootstrap/providers.php`, `public/index.php`, `artisan`, `routes/console.php`
and `config/app.php` all ship in the archive and overwrite in place - and they
must all arrive together, which an unpack of the whole archive guarantees.

Six class files were deleted. After an unpack they are still on disk, and
nothing reads them: the kernels and the exception handler are now the
framework's own classes, the provider list no longer names any of them, and
the only directories Laravel 11 scans are `app/Console/Commands` and
`app/Listeners`. **That holds only once the cached configuration is gone** -
see section 5, TODO 56. Then remove them:

```
rm app/Http/Kernel.php app/Console/Kernel.php
rm -r app/Exceptions
rm app/Providers/RouteServiceProvider.php app/Providers/EventServiceProvider.php
rm app/Providers/BroadcastServiceProvider.php
```

`app/Exceptions/` held nothing but `Handler.php`. **Keep** every other file
under `app/Providers/` and `app/Http/`.

**TODO 59: the application layout moves; two files are left behind, inert.**
`resources/views/layouts/app.blade.php` became
`resources/views/components/admin-layout.blade.php` (the archive ships the new
file), and `app/Providers/BladeComponentServiceProvider.php` was deleted -
`bootstrap/providers.php`, which overwrites in place, no longer names it.
Remove both:

```
rm app/Providers/BladeComponentServiceProvider.php
rm resources/views/layouts/app.blade.php
```

**Keep** the rest of `resources/views/layouts/` (`setup.blade.php`,
`staticpage.blade.php`, `partials/`). Until step 10, compiled views under
`storage/framework/views` still name `layouts.app`; that is harmless while the
site is down, and step 10's `optimize:clear` removes them before step 11 brings
it back up. **Do not skip step 10**: once the old layout is removed, a stale
compiled view that still names it answers 500.

**TODO 67: five language files change one key.** `lang/{de,fr,hu,ro,sk}.json`
rename `Reset Password Notification` to `Reset your password`, the key
Laravel 13's password reset mail asks for; the translations are unchanged.
The files overwrite in place. A customisation of the old key made through
the translation editor is lost with them - and had stopped showing anyway,
because Laravel 13 no longer reads that key.

**TODO 69: two class files are left behind, and three language files and a
few views change.** `app/Actions/Fortify/DisableTwoFactorAuthentication.php`
and `app/Actions/Fortify/RedirectIfTwoFactorConfirmed.php` are deleted - Fortify's
own classes do their job now. On a host they stay inert (nothing names them);
remove them by hand:

```
rm app/Actions/Fortify/DisableTwoFactorAuthentication.php
rm app/Actions/Fortify/RedirectIfTwoFactorConfirmed.php
```

`lang/{hu,en,de}/user.php` gain the passkey and setup-key strings and overwrite
in place, like every language file. `public/vendor/passkeys/` is new and ships
in the archive.

**TODO 70: one class file is left behind.** The impersonation middleware was
generalised and renamed; `bootstrap/app.php` no longer names the old class, so
on a host it stays inert. Remove it by hand:

```
rm app/Http/Middleware/RequirePasswordForImpersonation.php
```

**TODO 71: two files are left behind.** `routes/api.php` (the unused
`/api/user` stub) and `config/cors.php` are deleted. On a host both stay
inert: `bootstrap/app.php` no longer loads the route file, and with
`HandleCors` out of the middleware stack nothing reads the CORS
configuration. `config/auth.php` loses its `api` guard and overwrites in
place. Remove the two files by hand:

```
rm routes/api.php
rm config/cors.php
```

*Last updated: TODO 71.*

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

**TODO 39.2: none.** The Fortify constraint change and the two-factor storage
migration introduce no environment variable and reinterpret none.

**TODO 39: none.** The Laravel 10 hop adds, renames and reinterprets no
environment variable. `FILESYSTEM_DRIVER` keeps its Laravel 8 spelling on
purpose: `config/filesystems.php` still reads that name, so no deployed `.env`
needs editing. Renaming it to `FILESYSTEM_DISK` is a later decision, and it has
to move the config and the `.env` together or it moves nothing.

**TODO 41: none.** No environment variable is added, renamed or
reinterpreted. Worth naming because one was considered and rejected: Laravel
reads `LOG_DEPRECATIONS_WHILE_TESTING` to decide whether to log deprecations
under test, and TODO 41 solved that inside the test suite instead, so no
deployed `.env` gains a key that only ever mattered to a test run.

**TODO 43: none.** The Livewire 3 upgrade adds, renames and reinterprets no
environment variable. `config/livewire.php` is deliberately untouched by that
item, so not even an indirect key changes; when it is finalised (roadmap
TODO 51) this section gets re-checked, because Livewire 3 introduces an
`app_url` key and an `asset_url` that a host behind a CDN may already be
setting.

**TODO 55: none - and do not take Laravel 11's `.env.example` as a guide.** The
Laravel 11 skeleton renamed several keys (`CACHE_STORE` for `CACHE_DRIVER`,
among others) and changed defaults (`SESSION_DRIVER=database`, sqlite as the
default connection). None of that reaches this application: its config files
are the complete Laravel 10 ones, they still read the old names, and the
framework's own config is deliberately **not** merged underneath them
(`bootstrap/app.php`). A deployed `.env` keeps working unchanged; renaming its
keys to the Laravel 11 spelling would make them silently ignored.

**TODO 56: none.** The move to the slim skeleton kept the framework's config
unmerged on purpose, so the warning above still stands word for word.

**TODO 59: none.**

**TODO 61: none.** Laravel 12 renames no key this application reads, and the
TODO 55 warning about the skeleton's `.env.example` still applies unchanged.

**TODO 69: no new key, but `APP_URL` and `APP_KEY` now matter more.** Passkeys
take their relying party and their only allowed origin from `APP_URL`, so it
must be the exact `https://` address users open - a site reached under a second
name, or over plain http, cannot use passkeys there. The passkey user handle is
derived from `APP_KEY`: rotating it invalidates every registered passkey, on top
of making the encrypted columns unreadable.

*Last updated: TODO 69.*

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
  **"Any host that ever rendered a page" is every production host** (TODO 54):
  1.2.0 still runs the packer, and its `ignore_environments` exempts only
  `local`, and the hook's repair has never run anywhere. Expect the directory.
- **The packer's generated files** sit next to the assets on the same hosts, for
  the same reason, and keep being regenerated until the moment 1.2.0 stops
  serving: a mirror under `public/cache/`, and `{filemtime}-cache_*` copies
  beside the sources. After the upgrade nothing references them - the layouts
  link the committed files through `pwbs_asset()` - so they are stale, publicly
  served copies rather than a fault. The names differ per install; the
  patterns are the ones the 1.x hook would have used:

  ```
  rm -r public/cache
  rm -f public/dist/css/*-cache_* public/dist/js/*-cache_*
  rm -f public/plugins/*/*-cache_* public/plugins/*/*/*-cache_*
  ```
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

- **The TODO 39.2 migration drops a column, and this is the first hop in this
  guide whose migration is not additive.** `two_factor_confirmed` is replaced by
  `two_factor_confirmed_at`, with confirmed rows carrying their answer across.
  Nothing has to be done for the upgrade itself - `migrate` applies it - but a
  **rollback does not work by restoring the old code alone**: the previous
  release reads a column that is no longer there, and every two-factor page
  fatals. The migration's `down()` puts the boolean back and refills it from the
  timestamp, so the recovery is `migrate:rollback` **before** the old release
  goes back, not after. Both directions were executed rather than read.
  The backfilled timestamp is a marker, not a measurement: the old schema stored
  *whether* a second factor was confirmed and never *when*, so confirmed rows get
  their own `updated_at`. Nothing reads it as a date; everything asks whether it
  is null.

- **The Laravel 10 hop triggers the `bootstrap/cache` rule above**, and it is
  the plainest case of it so far: two packages arrived (`laravel/prompts`,
  `spatie/error-solutions`) and two left, while `spatie/laravel-ignition`
  crossed a major. The provider class names happen not to have changed, but the
  manifests still name a package set that no longer matches the tree, and the
  rule is "whenever a package leaves or arrives" rather than "whenever a class
  is renamed". Delete both files before the first request.
- **`php artisan migrate` is required by this release**, and section 3 explains
  which migration and why its rollback is not symmetrical.
- **TODO 41 adds nothing here.** No package leaves or arrives, so no
  `bootstrap/cache` manifest goes stale on its account; there is no migration;
  and no per-install state changes. The usual `optimize:clear` at step 10 is
  enough, for the ordinary reason that `config:cache` predates the release.
- **TODO 43 adds two checks, both about served JavaScript rather than about
  state on disk.**
  - **`optimize:clear` at step 10 is not optional for this release.** Livewire
    2 and 3 compile Blade differently, and every `wire:` directive in the
    application went through a rewrite; a warm `bootstrap/cache` serving
    version 2 compiled views against a version 3 runtime produces errors that
    look like application bugs.
  - **If this install sets `LIVEWIRE_ASSET_URL`, or serves
    `public/vendor/livewire/` from a CDN or a reverse proxy, re-check it after
    the upgrade.** Section 3 explains why: the published copy wins over the
    package's own route, and a stale copy announces itself only in the
    browser console. Clear the CDN's cache for that path, or confirm the host
    is serving the files the archive delivered.
  - No package leaves or arrives, so no `bootstrap/cache` manifest goes stale
    on Livewire's account, and there is no migration.
- **TODO 51 makes step 10 load-bearing once more.** A cached route table
  (`route:cache`) names the 21 component routes by their old class names, and a
  cached configuration (`config:cache`) still says `App\Http\Livewire`. Either
  one left warm serves "class not found" or "Unable to find component" on the
  first request. `optimize:clear` removes both; nothing else is needed.
- **TODO 51.1: if anything in front of the application filters by URL, it has
  to learn one new path.** Livewire 2 posted every component update to
  `/livewire/message/{name}` (and a localized variant); Livewire 3 posts all of
  them to **`POST /livewire/update`**. A web-application firewall, a proxy rule
  or a rate limit written for the old path no longer matches, and one that
  allowlists Livewire by path blocks every interaction. The upload routes
  (`/livewire/upload-file`, `/livewire/preview-file/{filename}`) are unchanged.
  The script is served from `/livewire/livewire.min.js` when `APP_DEBUG` is off,
  unless the published copy under `public/vendor/livewire/` takes over (see
  section 3, TODO 43).
- **TODO 55 triggers the `bootstrap/cache` rule above** - five packages
  arrived and the framework crossed a major - so delete both manifests before
  the first request. The PHP floor is now `^8.2` (section 1). The republished
  `public/vendor/livewire/` files ship in the archive and overwrite in place;
  nothing to do beyond the usual step 10 `optimize:clear`.
- **TODO 55.2 makes the `bootstrap/cache` rule fatal again**: the manifests
  name `Spatie\LaravelIgnition\IgnitionServiceProvider`, which no longer
  exists. Delete both files before the first request - the same failure TODO 35
  and TODO 39.1 describe above.
- **TODO 56 adds the cached configuration, routes and events to that rule.**
  A host that ever ran `php artisan optimize` or `config:cache` has a
  `bootstrap/cache/config.php` whose `app.providers` still names
  `App\Providers\RouteServiceProvider` and `EventServiceProvider`. While that
  file exists, Laravel 11 does not even read `bootstrap/providers.php`. With
  the two class files still on disk, the old route provider loads every route
  file a second time and the old event provider runs each listener twice next
  to the framework's; once section 3's `rm` has run, the first request dies on
  "class not found". `routes-v7.php` and `events.php` are stale for the same
  reason. **Delete `config.php`, `routes-v7.php` and `events.php` together with
  the two manifests, before the first request and before `php artisan migrate`**
  - Branch B's step 6, not step 10. `php artisan optimize:clear` removes all
  five, provided it can still boot; deleting the files by hand always works.
- **TODO 59: nothing beyond the usual step 10.** A cached `config.php` would
  still say `livewire.layout` is `layouts.app`, but TODO 56 already deletes it
  at step 6; the compiled views are step 10's (section 3).
- **TODO 60: the `jobs` table - drain it on the old release.** Rows the 1.x
  release queued run on 2.x, measured (`DatabaseQueueWorkerTest`), with one
  exception: the e-mail confirmations, which 1.x queued as
  `ProtoneMedia\LaravelVerifyNewEmail\Mail\VerifyFirstEmail` / `VerifyNewEmail`.
  2.x has no such class (TODO 33.5, section 2), so each such row fails after
  three attempts, lands in `failed_jobs` and sends one failed-job mail, and the
  user has to request the confirmation again. Branch B step 3 avoids it: with
  the site down, `php artisan queue:work --stop-when-empty --force` - still the
  1.x code - sends what is queued. `--force` is needed because the worker
  pauses in maintenance mode. Rows waiting for a retry delay are not due and
  stay; they are the queued notifications, which run on 2.x. To check
  afterwards:

  ```
  SELECT COUNT(*) FROM jobs WHERE payload LIKE '%ProtoneMedia%';
  ```

  If a host was upgraded without draining, the `failed_jobs` rows naming
  `ProtoneMedia` can be removed with `php artisan queue:forget <uuid>`; nothing
  else is affected.
- **TODO 61 triggers the `bootstrap/cache` rule above** - the framework
  crossed a major and two packages arrived - so delete both manifests before
  the first request. Nothing else: no migration, no `.env` key, and
  `public/vendor/livewire/` did not change. The installer's "database already
  has data" check was fixed in the same item; it matters only for a fresh
  install, not for an upgraded host.
- **TODO 66 makes the `bootstrap/cache` rule fatal again**: Debugbar 4 moved
  to the `Fruitcake\LaravelDebugbar` namespace, so a manifest naming
  `Barryvdh\Debugbar\ServiceProvider` names a class that no longer exists.
  Delete both manifests before the first request. **Operator-visible:** the
  admin "debugbar" setting no longer switches the bar on where
  `APP_ENV=production` - Debugbar 4 refuses production and requires
  `APP_DEBUG` - which is the intended state for a live site.
- **TODO 64 triggers the rule as well** (the framework crossed a major). The
  PHP floor is now `^8.3` (section 1).
- **TODO 69: one migration, and the `bootstrap/cache` rule again.** The
  `passkeys` table arrives (`2026_10_24_120000_create_passkeys_table`), which
  Branch B's `php artisan migrate` creates; it is additive, so rolling back the
  code without rolling it back is harmless. A stale package manifest does not
  list `Laravel\Passkeys\PasskeysServiceProvider`, so passkeys fail until both
  manifests are deleted. **Two user-visible changes:** a user halfway through
  setting up two-factor authentication (secret saved, never confirmed) finishes
  on the same page as before - the confirmation now asks for the password first
  if it was not confirmed in the last few hours - and a settings page left open
  across the deploy posts to the old `/user/2fa-confirm` once and gets a 404;
  reloading fixes it.
- **TODO 70: no migration; one writable directory; two user-visible changes.**
  HTMLPurifier keeps its definition cache in `storage/framework/cache/htmlpurifier`,
  created on first use - `storage/` must be writable, as it already has to be
  (without it the filter still works, uncached). Two routes changed method
  (leaving a group is a POST, deleting a news item a DELETE), so a cached route
  table must be rebuilt, which step 10's `optimize:clear` does. **Users:** a
  groups or news page left open across the deploy answers 405 once on "leave"
  or "delete"; reloading fixes it. Group news and poster text is now filtered
  on display, so a stored script or event-handler attribute no longer runs -
  and no longer shows; ordinary formatting is unchanged. **Not repaired, and
  not repairable from data:** a membership someone created for themselves
  through the uninvited-accept hole looks like any accepted membership. An
  operator who suspects it can review group member lists by hand.
- **TODO 70.1: the same fixes ship to the 1.x line as 1.2.1.** A host that
  took 1.2.1 has the closed holes, `ezyang/htmlpurifier` and
  `RequirePasswordBeforeAction` already, and its hook removed the old
  middleware file; nothing above changes for it. A host still on 1.2.0 is
  exposed until it updates - take 1.2.1 first rather than waiting for 2.0.0.
- **TODO 71: no migration; the caches matter once more.** A route table cached
  before the deploy still lists `/api/user` with `auth:api`, and the new
  `config/auth.php` has no `api` guard, so that path would answer 500 instead
  of 404 until step 10's `optimize:clear` rebuilds the caches. No user-visible
  change: nothing in the application called the API. **Not changed on 1.x:**
  the `api` guard and route stay on the 1.x line, where the TokenGuard
  advisory has no fixed release and was not measured (see the TODO 61 row).

*Last updated: TODO 71.*

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
3. `php artisan down` (maintenance mode), then, still on 1.x,
   `php artisan queue:work --stop-when-empty --force` (section 5, TODO 60).
4. Unpack the 2.0.0 archive over the application directory.
5. **Delete `vendor/` entirely and replace it with the archive's copy.** This is
   what makes section 2 unnecessary and is the reason this branch is simpler
   than it looks: no orphan list has to be correct. It is also the only step
   that can clear the packages section 2 describes, which never shipped in any
   1.x update and which the incremental updater therefore cannot name in a
   deletion list. TODO 35.1 fixed the repository so this cannot grow further;
   it could not retroactively fix the hosts.
6. Delete `bootstrap/cache/packages.php` and `bootstrap/cache/services.php` -
   and, from TODO 56 on, `config.php`, `routes-v7.php` and `events.php` in the
   same directory (section 5).
7. Apply sections 3 and 4 (moved files, `.env` keys).
8. `php artisan migrate --force`.
9. Section 5's `public/storage` repair, then `php artisan storage:link`.
10. `php artisan optimize:clear`.
11. `php artisan up`, then smoke-test login, the calendar and one group page.
12. Update `version.txt` if the archive did not.

Rollback is step 2's backup restored wholesale. A partial rollback is not safe
once step 8 has run: `install()`'s own `restore()` brings back files but not
migrations, which is the same reason the major ceiling exists.

*Last updated: TODO 60.*

---

## Guide change log

| Hop | TODO | What it added |
|---|---|---|
| Laravel 8 -> 9 | 34 | The document itself; the PHP `^8.0.2` floor, six orphaned vendor paths, the `bootstrap/cache` manifest rule. Sections 3 and 4 gained nothing, which is itself the finding. |
| (no framework hop) | 35 | Three more orphaned paths, the first **partial** namespace removal among them (`vendor/fruitcake/laravel-cors` goes, `vendor/fruitcake/php-cors` must stay). Two facts that change how section 6 reads: a removed service provider breaks a stale `bootstrap/cache` manifest exactly like a renamed one, and untracked `vendor/` packages are unreachable by the incremental updater at all. |
| (no framework hop) | 35.1 | The committed `vendor/` tree was 1017 files short and no archive built from this branch would have booted. Fixed at the source, so section 2's untracked-package problem stops growing - but the deployed 1.x hosts still carry what never shipped, which keeps Branch B's step 5 the only thing that can clear it. Section 6 is no longer blocked. |
| (no framework hop) | 36 | Sections 3 and 4 gained their first entries since TODO 34, and both say "nothing" - but section 3 now also names what that depends on: two configuration files changed content, and a host with a warm `config:cache` keeps the old values until step 10 runs. The verification also found that a `.env` carrying `MAIL_FROM_ADDRESS=null` broke the failed-job monitor outright; fixed in configuration, so no deployed `.env` needs editing. |
| (no framework hop) | 37-38 | **Section 3 gained its first entry that is not "nothing", and section 5 its first repair that costs data rather than a boot.** Moving `resources/lang/` to `lang/` is invisible to an update archive, and Laravel picks the OLD directory whenever it still exists (`Application.php:349-355`), so a host that keeps it freezes every translation at the pre-upgrade content with no error anywhere. TODO 37 contributed nothing to sections 2 and 5, and four now-unread `AWS_*` keys to section 4 - the `s3` disk it removed never had an adapter package to orphan. |
| (no framework hop) | 39.1 | **Row added retroactively at TODO 39.2, which found it missing.** Removing `laravolt/avatar` orphaned two vendor namespaces in section 2 (`vendor/laravolt`, `vendor/intervention` - one declared package taking its dependency with it), and gave section 5 two entries: `public/avatars/` becomes orphaned data rather than broken state, and a cached config still carrying `laravolt.avatar.*` keys is a second reason for `optimize:clear`. It also supplied the rule's sharpest example - a non-framework change that still breaks a stale `bootstrap/cache` manifest. |
| (no framework hop) | 39.2 | **Section 3 gained its first entry that is not a file: a database column.** Lifting the `laravel/fortify` ceiling was the price of Laravel 10 resolving at all - the `~1.11.2` pin admitted no release that supports it - and the two-factor confirmation moved from a NOT NULL boolean to Fortify's own nullable `two_factor_confirmed_at`. Sections 2 and 4 gained nothing. Section 5 gained the first non-additive migration in this guide: rolling back needs `migrate:rollback` **before** the old release goes back, not after. |
| **Laravel 9 -> 10** | **39, 40** | The first hop since 34, and it moves the runtime with it: PHP `^8.1`, and the interpreter on the development machine switched from `php81` to the default `php` 8.3 one commit ahead of the framework. Section 2 gains two **partial** paths whose namespaces both stay. Section 3 gains a kind of entry it did not have: no file moves, but six `validation.php` files change content **and are editable through the application's own translation editor**, so an administrator's customisations there are overwritten - and have to be, because the old content renders every password-strength error as a raw key. Section 4 is empty, deliberately: `FILESYSTEM_DRIVER` keeps its Laravel 8 spelling so that no deployed `.env` needs editing. |
| (no framework hop) | 41 | **Every section says "none", and the reason is worth more than the rows.** TODO 41 was supposed to be a small refactor; the sweep that was meant to confirm nothing was left instead found that the test suite had never been able to see a PHP deprecation at all - Laravel's own error handler drops them under test, and PHPUnit's handler steps aside once Laravel's is installed. Sixteen deprecation sites in the application's own code were closed as a result, all of them behaviour-neutral, none of them visible to an operator. Nothing orphans, nothing moves, no `.env` key changes and there is no migration - the first delivered item in this guide that costs a deployed host nothing at all. |
| (no framework hop) | 42 | **Every section says "none" again, and for a duller reason than TODO 41.** No package moves, no file is deleted or relocated, no `.env` key changes, and there is no migration. The two custom validation rules move off a contract that carries only an `@deprecated` docblock - no runtime deprecation, so the guard TODO 41 installed never had anything to report and this is purely preventive. The one operator-visible change is a message that stops appearing, and only for a request the form itself cannot produce: a service-day row submitted without its counterpart time used to attach the literal string `validation.` to the field that was fine. Nothing that used to be blocked now saves. |
| (no framework hop) | 42.1 | **Every section still says "none", and this is the first such row that is nevertheless not invisible to an operator.** No package moves, no file is deleted or relocated, no `.env` key changes and there is no migration. Three behaviour changes a user can meet, all in the group edit form's service-day rows: a `18:00 - 00:00` template becomes savable on the pending-changes URL, where a dead-looking wildcard rule had been rejecting it and discarding the pending change with it; a mis-clicked start time no longer drags the day's end down with it and leaves the "no service" template behind; and a group configured with `min_time = 120` and a day ending at midnight now shows a blocking error **on the field it belongs to** where it previously saved a silently emptied day. The last one is an open defect rather than a fixed one - that configuration cannot be saved at all, and this row exists so an operator meeting it recognises it. Nothing here needs an action on a deployed host beyond the `optimize:clear` step section 6 already runs: a warm `bootstrap/cache` holds compiled Blade, and the day rows are Blade. |
| (no framework hop) | 43 | **The first Phase 6 row, and the first one that reports a red test suite as an intended state.** Livewire 2.12.8 -> 3.8.8. Section 2 says none - the resolution is 0 installs, 1 update, 0 removals and `vendor/livewire/livewire` is replaced in place, so a major version change orphans no vendor path at all. Section 3 gains one file that is not under `vendor/`: the Livewire 2 `livewire.js.map`, deleted here and undeletable on a host. That entry carries the finding the whole item turned on - `public/vendor/livewire/` is served **in preference to** the package's own route whenever its `manifest.json` exists, in both versions, and a mismatch announces itself only as a `console.warn`. Section 4 says none. Section 5 gains two checks, both about served JavaScript: `optimize:clear` stops being optional because every `wire:` directive was rewritten, and a host serving these assets from a CDN has to be re-pointed. Nothing else here is operator-visible yet: the application is mid-migration until roadmap TODO 51 closes the phase, and no 2.x release can be cut before then. |
| (no framework hop) | 51 | **The Livewire components change namespace, and the archive cannot say so on its own.** `App\Http\Livewire` -> `App\Livewire` (28 classes). Section 2 says none: no package moves. Section 3 gains `app/Http/Livewire/`, left behind on every host and inert there, with the one-line removal. Section 4 says none. Section 5 records that a warm `route:cache` or `config:cache` names the old classes, so step 10's `optimize:clear` is what makes the move take effect. The archive also has to carry a regenerated `vendor/composer/autoload_classmap.php`, which this repository commits. |
| (no framework hop) | 51.1 | **The test suite is green again, and the one route change is the one an operator can trip over.** Livewire's component endpoint moved from `/livewire/message/{name}` to `POST /livewire/update`; section 5 asks anyone filtering by URL in front of the application to follow it. Sections 2, 3 and 4 say none: no package, file or `.env` key moves. Phase 6 still waits on the browser walk (roadmap TODO 47.1) before a 2.x release can be cut. |
| (no framework hop) | 47.1 - 47.5 | **Phase 6 closes; nothing here for an operator beyond what is already required.** The browser walk fixed four Livewire 3 behaviours in views, one component class and the layout - compiled views, so section 6 step 10 (`optimize:clear`) is what makes them take effect, as for every view change. No package, file, `.env` key or per-install state moves. One local finding is worth knowing for a Windows/Laragon install using mod_fcgid: a vhost without `FcgidMaxRequestLen` refuses any upload over 128 KB with a bare 500 before Laravel sees it. |
| (no framework hop) | 52, 53 | **Phase 7 resolves to a removal, and the archive leaves four dead paths behind.** The Laravel Mix pipeline never built anything, so it was deleted rather than ported to Vite, and TODO 53's reproducible build went with it. Section 3 gains `webpack.mix.js`, `package.json`, `resources/js/` and `resources/css/` - inert on every host, with the two-line removal. Sections 2, 4 and 5 say none: no package, `.env` key or per-install state moves, and no host ever needed Node or `npm`, before or after. |
| (no framework hop) | 54 | **The 1.x hook's Phase 3 half never shipped, so this guide now carries it - including the one leftover that breaks Artisan.** The last 1.x release (1.2.0) predates Phase 3, and 2.x never reaches `install()`, so the packer, GDPR, translation and pending-email removals have run on no host. Section 2 gains eight vendor paths from before TODO 34 (TODO 23, 24 and 33.x). Section 3 gains the Phase 3 application files, one of them load-bearing: the 1.x `PackageAnonymizeInactiveUsers.php` makes every Artisan command fatal once `vendor/dialect` is gone, while web pages keep working. Section 5 says the `public/storage` repair applies to every production host and adds the packer's generated files. Section 4 says none. |
| (no framework hop) | 55.1 | **Nothing for an operator: the date arithmetic is made to survive Carbon 3 before the hop brings it.** Four call sites now return the same values on Carbon 2 and 3; on the installed Carbon 2 nothing changes. No package, file, `.env` key or per-install state moves. Compiled code only - the usual step 10 `optimize:clear` covers it. |
| (no framework hop) | 57.1 | **Nothing for an operator: the installer's "database already has data" check stops using doctrine/dbal before Laravel 11 removes the call it relied on.** No package, file, `.env` key or per-install state moves; the check behaves as before. |
| **Laravel 10 -> 11** | **55** | **The second framework hop, and the PHP floor moves with it: `^8.2`.** `laravel/framework` 11.57.0; 5 packages in, 46 updated, 0 out, so section 2 says none. Section 3 says none: `bootstrap/app.php` and the republished Livewire assets ship and overwrite in place. Section 4 says none, with a warning not to adopt Laravel 11's renamed `.env` keys - the application's config still reads the old ones, and the framework's defaults are deliberately not merged in. Section 5: the `bootstrap/cache` manifests must go. A fourth `laravel/framework` advisory (PKSA-d5tc-s1qs-h781, low, debug-mode only, fixed in 12.69.0) is exempted from resolution like the other three; `composer audit` still reports all four. |
| (no framework hop) | 55.2 | **The debug error page changes hands; five vendor paths orphan.** `spatie/laravel-ignition` and the four packages only it required are removed - Laravel 11 renders the debug page itself. Section 2 gains the five paths under a `vendor/spatie` that must stay. Section 5: the `bootstrap/cache` manifests name the removed provider, so deleting them is mandatory. Sections 3 and 4 say none. |
| (no framework hop) | 55.3 | **Nothing for an operator: a new user gets one verification mail again, not two.** Laravel 11 started sending its own `VerifyEmail` on registration next to the application's mail; the application's mail is now the only one, and the verify-email page's resend button sends that same mail. No package, file, `.env` key or per-install state moves. |
| (no framework hop) | 56 | **The Laravel 11 application skeleton; one new pre-first-request deletion.** Section 3: `bootstrap/app.php`, the new `bootstrap/providers.php`, `public/index.php`, `artisan`, `routes/console.php` and `config/app.php` overwrite in place; six deleted class files (`app/Http/Kernel.php`, `app/Console/Kernel.php`, `app/Exceptions/Handler.php`, the Route, Event and Broadcast service providers) linger inert and can be removed by hand. Section 4 says none: the framework config stays unmerged. Section 5 and Branch B step 6: a cached `bootstrap/cache/config.php` still lists the deleted providers and hides `bootstrap/providers.php`, so it goes - with `routes-v7.php` and `events.php` - together with the two manifests, before the first request. No package moves. |
| (no framework hop) | 57 | **doctrine/dbal leaves; four vendor paths orphan.** Nothing called it any more - Laravel 11's `change()` is native, and TODO 57.1 moved the installer off it. Section 2 gains `vendor/doctrine/dbal`, `vendor/doctrine/deprecations`, `vendor/doctrine/event-manager` and `vendor/psr/cache`, under two namespaces that must stay. Sections 3, 4 and 5 say none: no Laravel provider moved, so the `bootstrap/cache` manifests are unaffected. |
| (no framework hop) | 58 | **Nothing for an operator.** The Laravel 11 blocker list is closed: the GDPR and translation packages left in Phase 3, and the self-updater fork (v2.0.0) already admits Laravel 11, 12 and 13. A test now guards the fork's range. No package, file, `.env` key or per-install state moves. |
| (no framework hop) | 59 | **The application layout becomes an anonymous component; two inert files are left behind.** `resources/views/layouts/app.blade.php` moved to `resources/views/components/admin-layout.blade.php` and `BladeComponentServiceProvider` is gone; the rendered pages are byte-identical. Section 3 gains two `rm` lines and makes step 10's `optimize:clear` mandatory before step 11 (stale compiled views name the old layout). Sections 4 and 5 say none. |
| (no framework hop) | 60 | **Drain the queue on the old release.** 1.x `jobs` rows run on 2.x, measured, except the e-mail confirmations 1.x queued through protonemedia/laravel-verify-new-email: 2.x has no such class, so each fails after three tries and triggers a failed-job mail. Branch B step 3 gains `php artisan queue:work --stop-when-empty --force`, run on 1.x after `down`; section 5 has the check and the cleanup. Sections 2, 3 and 4 say none. |
| **Laravel 11 -> 12** | **61** | **The fourth framework hop, and the first that needs no exemption.** `laravel/framework` 12.70.1 and PHPUnit 11; 2 packages in, 24 updated, 0 out, so section 2 says none. The PHP floor stays `^8.2` (section 1) and section 4 says none. Section 5: the `bootstrap/cache` manifests must go. All four `laravel/framework` advisories exempted at TODO 34 and TODO 55 are fixed in 12.70.1, so the exemption block is gone and `composer audit` reports nothing. **One more advisory an operator of a 1.x host should know about:** PKSA-b41t-wbzk-9q51 (critical, reported 2026-10-23) is a `TokenGuard` bypass with no Laravel 8 fix. This application's `api` guard uses that driver, but its `users` table has no `api_token` column, and on Laravel 11 that made the bypass unreachable (measured; not measured on 1.x). The installer no longer reports an empty database as full when the MySQL server holds other databases - a fresh-install fix only. |
| (no framework hop) | 62 | **Nothing for an operator.** The Laravel 12 upgrade guide, walked entry by entry, changes nothing else here: the local disk keeps its `storage/app` root - the framework's new `storage/app/private` default is never merged in - so `installed.txt` and the installer token stay where every host has them; no route name is shared, so the new first-registered precedence has nothing to decide. No package, file, `.env` key or per-install state moves. |
| (no framework hop) | 63 | **Nothing for an operator.** The package matrix is re-measured on Laravel 12; no package moves. For Phase 10 it means two constraint edits (`laravel/tinker`, `barryvdh/laravel-debugbar`) and no lock bumps, and that staying on Guzzle 7 is now a choice rather than a requirement. |
| (no framework hop) | 64 (PHPUnit 12) | **Two dev-package paths orphan.** PHPUnit 12 drops `sebastian/code-unit` and `sebastian/code-unit-reverse-lookup` (section 2, under a `vendor/sebastian` that must stay). Nothing else for an operator. |
| (no framework hop) | 66 | **Debugbar 4 and tinker 3; one pre-first-request deletion, and one setting that stops working on live sites.** No vendor path orphans (section 2). Section 5: Debugbar's provider class moved namespace, so the `bootstrap/cache` manifests are fatal until deleted; the admin "debugbar" setting no longer turns the bar on where `APP_ENV=production`. |
| **Laravel 12 -> 13** | **64** | **The fifth and last framework hop, and the PHP floor reaches the 2.0.0 number: `^8.3`.** `laravel/framework` 13.38.0; 1 package in, 3 updated, 0 out, so section 2 adds nothing for the hop itself. Sections 3 and 4 say none for the hop. Section 5: the `bootstrap/cache` manifests must go. `composer audit` reports nothing. |
| (no framework hop) | 67 | **The reset mail is translated again.** Laravel 13 renamed the password reset subject's key, and the five translated locales had been sending it in English since the hop; section 3 records the five language files. CSRF now also accepts a same-origin browser request without a token - no operator action. Cache and session serialization stay as they were: the Laravel 13 skeleton's stricter defaults are not merged into this application's configuration. |
| (no framework hop) | 69 | **Passkeys, and Fortify owns the two-factor confirmation.** `laravel/fortify` 1.41.0 with `laravel/passkeys` (16 packages in, none out; section 2 says two old paths are back). Section 3: two inert class files to delete, `public/vendor/passkeys/` new. Section 4: no key, but `APP_URL` must be the real `https://` address and `APP_KEY` now also anchors every passkey. Section 5: the `passkeys` migration and the `bootstrap/cache` rule; users can now add passkeys on the 2FA settings page and sign in or confirm their password with one, and download their recovery codes as a .txt file there (a new route, nothing for the operator to do). |
| (no framework hop) | 70 | **Authorization behind the middleware, and stored XSS.** Livewire actions do not pass the page route's `groupMember`/`groupAdmin`, and component ids were client-writable; the fixes are code only. Section 2: `ezyang/htmlpurifier` in, nothing out. Section 3: one renamed middleware file to delete. Section 4: none. Section 5: a writable cache directory, two routes changed method (rebuild the route cache), a page left open answers 405 once; memberships created through the closed hole cannot be told apart. The v1 backport is TODO 70.1. |
| (no framework hop) | 70.1 | **The fixes reach 1.x as 1.2.1** - built on branch `v1-authz`, released by the developer. Section 5 says hosts should take it before 2.0.0; nothing else changes for the 2.0.0 path. |
| (no framework hop) | 71 | **The API surface and the CORS layer are gone.** The stock `/api/user` stub never authenticated anyone (no `api_token` column) and only kept a TokenGuard reachable; the route file, the `api` guard, the `throttle:api` limiter, `config/cors.php` and `HandleCors` are removed. Section 2: none. Section 3: two inert files to delete. Section 4: none. Section 5: a stale route cache answers 500 on the old path until `optimize:clear`. The 1.x line keeps the guard, unmeasured. |
