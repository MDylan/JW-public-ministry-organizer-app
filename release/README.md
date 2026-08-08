# Release artifacts

Files here are **not part of the running application**. They are packaged into the
update archives served from `config('laraupdater.update_baseurl')`
(`https://updates.teruletek.hu/v1`).

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
