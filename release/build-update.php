<?php

/*
|--------------------------------------------------------------------------
| Update-archive builder
|--------------------------------------------------------------------------
|
| Builds the incremental archive that MDylan\LaraUpdater\LaraUpdaterController
| unpacks on a deployed site, from the file difference between two git refs
| (by default v1 -> dev), plus the laraupdater.json manifest that advertises it.
|
|   php81 release/build-update.php
|   php81 release/build-update.php --base=v1 --head=dev --description="..."
|   php81 release/build-update.php --dry-run
|
| Output lands in release/dist/ (git-ignored):
|
|   RELEASE-<version>.zip           upload to the update channel
|   laraupdater.json                upload next to it
|   RELEASE-<version>.files.txt     what shipped, for auditing
|   RELEASE-<version>.deleted.txt   what install() CANNOT remove - see below
|
| BEFORE RUNNING THIS ON v2-dev: READ TODO 35.1
|
| The archive is built from a GIT DIFF, so anything git does not track cannot
| ship. .gitignore excludes /vendor while this repository commits vendor/ on
| purpose, and `git add -A` skips ignored paths - so every package added since
| TODO 24 is missing from the index. 1037 files as of 2026-08-11, including all
| 63 of symfony/mailer. A release built from this branch today would install a
| Laravel 9 framework without its mailer, and would list vendor/fruitcake/php-cors
| as a deletion. The dirty-tree check further down cannot see any of it, because
| git status does not report ignored files.
|
| THREE PROPERTIES OF install() THIS SCRIPT EXISTS TO SATISFY
|
| 1. Entry paths are relative to base_path(). install() does
|    $full_path = base_path().'/'.$filename, so there is no wrapper directory:
|    an entry is "app/Models/User.php", never "kozter/app/Models/User.php".
|
| 2. EVERY parent directory needs its own entry, ordered before the files in
|    it. install() creates a directory only when the zip entry itself is a
|    directory; for a file it calls File::move(), i.e. rename(), which raises a
|    warning when the target directory is missing. Laravel's HandleExceptions
|    turns that warning into an ErrorException, install()'s try/catch returns
|    false, and update() rolls the whole release back through restore(). A
|    release that adds a new directory - a new vendor package, a new app
|    namespace - and does not carry its directory entry therefore fails to
|    install. The archive is written directories-first, sorted, so parents
|    always precede their children, and verifyArchive() re-checks that
|    invariant against the finished file.
|
| 3. install() only ever adds and overwrites. It NEVER deletes. Files that
|    disappeared between the two refs cannot be expressed in the archive at
|    all. This script lists them, and flags the ones the current hook does not
|    already cover.
|
| WHERE UNCOVERED DELETIONS GO FROM TODO 34 ONWARDS
|
| The obvious reading of point 3 is "add a laraupdater_upgrade_remove() line to
| release/upgrade.php until the uncovered count reaches zero". That was right
| through Phase 3, and it is NOT right any more: the hook is frozen (see its own
| header), because the framework hops replace essentially the whole vendor/ tree
| and one main() cannot carry that list reviewably. TODO 35 is the first release
| to report uncovered deletions under the freeze - thirteen vendor files from
| fideloper/proxy and fruitcake/laravel-cors - and that report is EXPECTED
| OUTPUT, not a defect to fix here.
|
| Orphaned paths belong in upgrade-guide.md section 2 instead, which the 2.0.0
| release turns into one procedure. Read the count below as "what a deployed 1.x
| host will still be carrying", not as "what somebody forgot to write".
|
| THE upgrade.php TRAP
|
| install() matches the hook with strpos($filename, 'upgrade.php') !== false -
| ANY entry whose path contains that string. A second match would be swallowed
| (moved to tmp/ and deleted) instead of deployed, and the later match would
| overwrite the real hook. The build aborts if that ever happens.
|
| The hook itself is written into the archive as a root-level "upgrade.php" so
| the release/ directory is not created on production hosts.
*/

if (PHP_SAPI !== 'cli') {
    fwrite(STDERR, "This script is CLI only.\n");
    exit(1);
}

if (! class_exists(ZipArchive::class)) {
    fwrite(STDERR, "ext-zip is required.\n");
    exit(1);
}

/*
| Repository-internal paths. They are tracked by git and show up in the diff,
| but they are not the running application: they have no business on a
| production host. Everything under release/ is excluded here - the hook is
| added back explicitly, under its own name.
*/
const EXCLUDE_PREFIXES = [
    '.claude/',
    '.docs/',
    '.github/',
    'release/',
    'tests/',
    'upgrade-notes/',
];

const EXCLUDE_EXACT = [
    '.env.example',
    '.env.testing',
    '.gitignore',
    'AGENTS.md',
    'CLAUDE.md',
    'phpunit.xml',
    'upgrade-roadmap.md',
];

const HOOK_SOURCE = 'release/upgrade.php';
const HOOK_ENTRY = 'upgrade.php';

$root = dirname(__DIR__);
chdir($root);

$options = parseOptions($argv);
$base = $options['base'] ?? 'v1';
$head = $options['head'] ?? 'dev';
$outDir = rtrim($options['out'] ?? 'release/dist', '/');
$dryRun = isset($options['dry-run']);

$warnings = [];

// -----------------------------------------------------------------------
// Preconditions
// -----------------------------------------------------------------------

// Contents are read from the working tree rather than from git blobs, which
// keeps the script fast and simple - but only holds if the working tree IS the
// head ref, unmodified. Both halves are checked; neither is worth guessing at.
$baseSha = revParse($base);
$headSha = revParse($head);

if ($baseSha === null) {
    fail("Unknown base ref: {$base}");
}
if ($headSha === null) {
    fail("Unknown head ref: {$head}");
}
if (revParse('HEAD') !== $headSha) {
    fail("The working tree is not on {$head}. Run: git checkout {$head}");
}
// Only content that actually ships has to be clean. Editing this script, or
// leaving release/dist/ lying around, cannot change a single archive entry -
// blocking on those would make the builder impossible to run on the commit
// that introduces it.
$dirty = array_values(array_filter(workingTreeChanges(), function ($path) {
    return ! isExcluded($path);
}));

if ($dirty !== []) {
    fail(
        "Uncommitted changes in files that would ship:\n    ".
        implode("\n    ", array_slice($dirty, 0, 20)).
        (count($dirty) > 20 ? "\n    ... and ".(count($dirty) - 20).' more' : '')
    );
}
// Not a plain ancestry test: in this repository v1 carries the dev -> v1 merge
// commits, so it is never a literal ancestor of dev while still holding no
// content of its own. What actually matters is whether any NON-MERGE commit
// exists on the base side alone - that is content the release would revert.
$strandedOnBase = array_filter(explode("\n", trim(git(['rev-list', '--no-merges', $baseSha, '^'.$headSha]))));

if ($strandedOnBase !== []) {
    $warnings[] = count($strandedOnBase)." non-merge commit(s) exist on {$base} but not on {$head}: this release would revert them.";
}

$version = $options['version'] ?? trim((string) file_get_contents($root.'/version.txt'));
$baseVersion = trim(git(['show', $base.':version.txt']));

if ($version === '') {
    fail('version.txt is empty and no --version was given.');
}
if (version_compare($version, $baseVersion, '<=')) {
    fail("Version {$version} is not newer than {$base}'s {$baseVersion}: check() would never offer it.");
}
if (major($version) > major($baseVersion)) {
    // See release/README.md: an install still running a pre-ceiling release has
    // to be routed through the last 1.x release first, which means publishing a
    // laraupdater-<previous>.json alongside this manifest.
    $warnings[] = "Major bump {$baseVersion} -> {$version}: publish the previous_version chain as well (release/README.md).";
}

// -----------------------------------------------------------------------
// The file sets
// -----------------------------------------------------------------------

// --no-renames on purpose: the updater has no concept of a rename. The new
// path has to ship as an ordinary file and the old one has to be deleted by
// the hook, which is exactly the A + D pair this produces.
$diff = git(['-c', 'core.quotepath=false', 'diff', '--name-status', '--no-renames', '-z', $baseSha.'..'.$headSha]);

$shipped = [];
$deleted = [];
$excluded = [];

$fields = explode("\0", $diff);
for ($i = 0; $i + 1 < count($fields); $i += 2) {
    $status = $fields[$i];
    $path = $fields[$i + 1];

    if ($status === '' || $path === '') {
        continue;
    }

    if ($status === 'D') {
        // A deletion under an excluded prefix is not worth a hook line: the
        // path is not shipped either, so whatever the host still has there is
        // already stale and inert.
        if (! isExcluded($path)) {
            $deleted[] = $path;
        }
        continue;
    }

    // A (added), M (modified), T (type change) all ship their head-side content.
    if (! in_array($status[0], ['A', 'M', 'T'], true)) {
        $warnings[] = "Unhandled diff status '{$status}' for {$path}: skipped.";
        continue;
    }

    if (isExcluded($path)) {
        $excluded[] = $path;
        continue;
    }

    if (! is_file($root.'/'.$path)) {
        fail("Listed by git but missing from the working tree: {$path}");
    }

    $shipped[] = $path;
}

sort($shipped);
sort($deleted);
sort($excluded);

// -----------------------------------------------------------------------
// Safety checks on the resulting set
// -----------------------------------------------------------------------

foreach ($shipped as $path) {
    if (strpos($path, HOOK_ENTRY) !== false) {
        fail(
            "{$path} contains the string '".HOOK_ENTRY."'. install() would treat it as the release hook ".
            "instead of deploying it, and it would overwrite the real hook. Rename the file."
        );
    }
    if ($path === '.env' || strpos($path, '.env') === 0) {
        fail("{$path} would overwrite environment configuration on every deployed site.");
    }
    if (strpos($path, '\\') !== false || strpos($path, '..') === 0 || $path[0] === '/') {
        fail("Refusing an unsafe archive path: {$path}");
    }
}

$hookAvailable = is_file($root.'/'.HOOK_SOURCE);

if (! $hookAvailable) {
    $warnings[] = HOOK_SOURCE.' is missing: this release will delete nothing on the target.';
}

$uncoveredDeletions = uncoveredDeletions($deleted, $hookAvailable ? file_get_contents($root.'/'.HOOK_SOURCE) : '');

// -----------------------------------------------------------------------
// Report
// -----------------------------------------------------------------------

$archiveName = 'RELEASE-'.$version.'.zip';

line('');
line('  '.$base.' ('.$baseVersion.', '.substr($baseSha, 0, 8).')  ->  '.$head.' ('.$version.', '.substr($headSha, 0, 8).')');
line('');
line('  files shipped     '.count($shipped).'  ('.formatBytes(totalSize($root, $shipped)).' uncompressed)');
line('  files excluded    '.count($excluded).'  (repository-internal)');
line('  files deleted     '.count($deleted).'  install() cannot remove these');
line('    not yet covered '.count($uncoveredDeletions).'  by '.HOOK_SOURCE);
line('  release hook      '.($hookAvailable ? HOOK_SOURCE.' -> '.HOOK_ENTRY : 'MISSING'));
line('');

if ($uncoveredDeletions !== []) {
    line('  Deletions the current hook does not cover, by package:');
    foreach (groupPaths($uncoveredDeletions) as $group => $count) {
        line('    '.str_pad((string) $count, 5, ' ', STR_PAD_LEFT).'  '.$group);
    }
    line('');
}

foreach ($warnings as $warning) {
    line('  WARNING: '.$warning);
}
if ($warnings !== []) {
    line('');
}

// Manifest-stage warnings are raised further down, after the archive exists.
$warningsAlreadyShown = count($warnings);

if ($dryRun) {
    line('  --dry-run: nothing written.');
    line('');
    exit(0);
}

// -----------------------------------------------------------------------
// Build
// -----------------------------------------------------------------------

if (! is_dir($outDir) && ! mkdir($outDir, 0755, true) && ! is_dir($outDir)) {
    fail("Could not create {$outDir}");
}

$archivePath = $outDir.'/'.$archiveName;

if (is_file($archivePath) && ! unlink($archivePath)) {
    fail("Could not replace the existing {$archivePath}");
}

$entries = $shipped;
if ($hookAvailable) {
    $entries[] = HOOK_ENTRY;
}

$zip = new ZipArchive();
if ($zip->open($archivePath, ZipArchive::CREATE) !== true) {
    fail("Could not open {$archivePath} for writing");
}

// Directories first, sorted, so a parent is always registered before anything
// inside it - see the header note on install()'s ordering requirement.
foreach (ancestorDirectories($entries) as $directory) {
    if (! $zip->addEmptyDir($directory)) {
        fail("Could not add directory entry {$directory}");
    }
}

foreach ($shipped as $path) {
    if (! $zip->addFile($root.'/'.$path, $path)) {
        fail("Could not add {$path}");
    }
}

if ($hookAvailable && ! $zip->addFile($root.'/'.HOOK_SOURCE, HOOK_ENTRY)) {
    fail('Could not add the release hook');
}

if ($zip->close() !== true) {
    fail("Could not finalize {$archivePath}");
}

verifyArchive($archivePath, count($shipped) + ($hookAvailable ? 1 : 0));

// -----------------------------------------------------------------------
// Manifest and side reports
// -----------------------------------------------------------------------

// The description is shown to admins verbatim and is usually hand-written into
// the manifest after a build. Rebuilding the same release must not silently
// throw that text away, so an existing manifest is read back and its
// description and previous_version survive unless the CLI overrides them.
$existing = [];
$manifestPath = $outDir.'/laraupdater.json';

if (is_file($manifestPath)) {
    $previousManifest = json_decode((string) file_get_contents($manifestPath), true) ?: [];

    // Only a manifest for THIS version may seed defaults. laraupdater.json
    // always describes the latest release, so after a version bump its text
    // belongs to the release before this one - inheriting it would publish the
    // wrong changelog under a new version number.
    if (($previousManifest['version'] ?? null) === $version) {
        $existing = $previousManifest;
    } elseif (isset($previousManifest['version'])) {
        $warnings[] = $manifestPath.' describes '.$previousManifest['version'].
            ', not '.$version.': its description was not carried over.';
    }
}

$description = $options['description'] ?? ($existing['description'] ?? '');
$placeholder = false;

if ($description === '') {
    $description = 'Version '.$version;
    $placeholder = true;
}

$previous = $options['previous'] ?? ($existing['previous_version'] ?? null);

$manifest = ['version' => $version, 'archive' => $archiveName];

if ($previous !== null && $previous !== '') {
    $manifest['previous_version'] = $previous;

    // getLastVersion() follows this key by fetching laraupdater-<previous>.json,
    // and that fetch has no try/catch on the update() path: if the file is not
    // on the channel, every install older than <previous> gets an uncaught
    // ErrorException instead of an update prompt.
    $warnings[] = "previous_version {$previous} is set: laraupdater-{$previous}.json must exist on the channel too.";
}

$manifest['description'] = $description;

$manifestJson = json_encode($manifest, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE)."\n";

file_put_contents($outDir.'/laraupdater.json', $manifestJson);

// The same manifest under its version number. laraupdater.json is overwritten
// by every release and only ever describes the latest one; this copy is what
// keeps older releases addressable on the channel, and it is the file
// getLastVersion() fetches when a future release names this version in its
// previous_version chain.
$versionedManifest = 'laraupdater-'.$version.'.json';

file_put_contents($outDir.'/'.$versionedManifest, $manifestJson);

file_put_contents($outDir.'/RELEASE-'.$version.'.files.txt', implode("\n", $shipped)."\n");
file_put_contents(
    $outDir.'/RELEASE-'.$version.'.deleted.txt',
    $deleted === [] ? "\n" : implode("\n", $deleted)."\n"
);

line('  written  '.$archivePath.'  ('.formatBytes(filesize($archivePath)).')');
line('  written  '.$outDir.'/laraupdater.json');
line('  written  '.$outDir.'/'.$versionedManifest);
line('  written  '.$outDir.'/RELEASE-'.$version.'.files.txt');
line('  written  '.$outDir.'/RELEASE-'.$version.'.deleted.txt');
line('');

if ($placeholder) {
    $warnings[] = 'laraupdater.json carries a placeholder description; it is shown to admins verbatim.';
}

foreach (array_slice($warnings, $warningsAlreadyShown) as $warning) {
    line('  WARNING: '.$warning);
}
if (count($warnings) > $warningsAlreadyShown) {
    line('');
}

line('  Upload to the update channel (config/laraupdater.php -> update_baseurl):');
line('    '.$archiveName);
line('    laraupdater.json        (what every install checks)');
line('    '.$versionedManifest.'  (keeps this release addressable later)');
line('');

exit(0);

// =======================================================================
// Helpers
// =======================================================================

/**
 * Parse --key=value and --flag arguments. Positional arguments are ignored.
 */
function parseOptions(array $argv): array
{
    $options = [];

    foreach (array_slice($argv, 1) as $argument) {
        if (strpos($argument, '--') !== 0) {
            continue;
        }

        $argument = substr($argument, 2);
        $separator = strpos($argument, '=');

        if ($separator === false) {
            $options[$argument] = true;
            continue;
        }

        $options[substr($argument, 0, $separator)] = substr($argument, $separator + 1);
    }

    return $options;
}

/**
 * Run git and return its raw stdout. Raw, not line-split: the diff is read
 * with -z and NUL separators do not survive exec()'s line splitting.
 */
function git(array $arguments): string
{
    $command = 'git '.implode(' ', array_map('escapeshellarg', $arguments));

    $process = proc_open($command, [1 => ['pipe', 'w'], 2 => ['pipe', 'w']], $pipes);

    if (! is_resource($process)) {
        fail('Could not run: '.$command);
    }

    $stdout = stream_get_contents($pipes[1]);
    $stderr = stream_get_contents($pipes[2]);

    fclose($pipes[1]);
    fclose($pipes[2]);

    if (proc_close($process) !== 0) {
        fail('git failed: '.$command."\n".$stderr);
    }

    return $stdout;
}

function gitSucceeds(array $arguments): bool
{
    $command = 'git '.implode(' ', array_map('escapeshellarg', $arguments));

    $process = proc_open($command, [1 => ['pipe', 'w'], 2 => ['pipe', 'w']], $pipes);

    if (! is_resource($process)) {
        return false;
    }

    stream_get_contents($pipes[1]);
    stream_get_contents($pipes[2]);

    fclose($pipes[1]);
    fclose($pipes[2]);

    return proc_close($process) === 0;
}

function revParse(string $ref): ?string
{
    $command = 'git '.implode(' ', array_map('escapeshellarg', ['rev-parse', '--verify', '--quiet', $ref.'^{commit}']));

    $process = proc_open($command, [1 => ['pipe', 'w'], 2 => ['pipe', 'w']], $pipes);

    if (! is_resource($process)) {
        return null;
    }

    $stdout = trim(stream_get_contents($pipes[1]));
    stream_get_contents($pipes[2]);

    fclose($pipes[1]);
    fclose($pipes[2]);

    return proc_close($process) === 0 && $stdout !== '' ? $stdout : null;
}

/**
 * Every path git reports as changed, staged or not, tracked or not.
 *
 * Parsed from --porcelain -z because a filename with a space or an accent is
 * quoted and mangled in the human-readable form. A rename record carries two
 * paths; both are returned, since either one being dirty matters.
 */
function workingTreeChanges(): array
{
    $records = explode("\0", git(['status', '--porcelain', '-z', '--untracked-files=all']));
    $paths = [];

    for ($i = 0; $i < count($records); $i++) {
        $record = $records[$i];

        if (strlen($record) < 4) {
            continue;
        }

        $status = substr($record, 0, 2);
        $paths[] = substr($record, 3);

        // "R" and "C" records are followed by a second field: the source path.
        if (strpos($status, 'R') !== false || strpos($status, 'C') !== false) {
            $i++;
            if (isset($records[$i]) && $records[$i] !== '') {
                $paths[] = $records[$i];
            }
        }
    }

    return $paths;
}

function isExcluded(string $path): bool
{
    foreach (EXCLUDE_PREFIXES as $prefix) {
        if (strpos($path, $prefix) === 0) {
            return true;
        }
    }

    return in_array($path, EXCLUDE_EXACT, true);
}

/**
 * Every directory that appears anywhere in the given entry paths, sorted so a
 * parent always sorts before its children.
 */
function ancestorDirectories(array $entries): array
{
    $directories = [];

    foreach ($entries as $entry) {
        $segments = explode('/', $entry);
        array_pop($segments);

        $current = '';
        foreach ($segments as $segment) {
            $current = $current === '' ? $segment : $current.'/'.$segment;
            $directories[$current] = true;
        }
    }

    $directories = array_keys($directories);
    sort($directories);

    return $directories;
}

/**
 * Re-open the finished archive and assert the two invariants install() relies
 * on: exactly one entry matches the hook, and every file entry is preceded by
 * a directory entry for its parent.
 */
function verifyArchive(string $path, int $expectedFiles): void
{
    $zip = new ZipArchive();

    if ($zip->open($path) !== true) {
        fail("Could not re-open {$path} for verification");
    }

    $seenDirectories = [];
    $hookMatches = 0;
    $files = 0;

    for ($i = 0; $entry = $zip->statIndex($i); $i++) {
        $name = $entry['name'];

        if (substr($name, -1) === '/') {
            $seenDirectories[rtrim($name, '/')] = true;
            continue;
        }

        $files++;

        if (strpos($name, HOOK_ENTRY) !== false) {
            $hookMatches++;
            continue;
        }

        $parent = dirname($name);

        if ($parent !== '.' && ! isset($seenDirectories[$parent])) {
            fail("{$name} has no preceding directory entry for {$parent}: install() would fail and roll back.");
        }
    }

    $zip->close();

    if ($files !== $expectedFiles) {
        fail("Archive holds {$files} files, expected {$expectedFiles}.");
    }

    if ($hookMatches > 1) {
        fail("{$hookMatches} entries match '".HOOK_ENTRY."': install() would deploy none of them.");
    }
}

/**
 * Deletions the current hook does not already remove, judged by the base_path()
 * arguments it names. A prefix match, because the hook deletes directory trees.
 */
function uncoveredDeletions(array $deleted, string $hook): array
{
    preg_match_all("/base_path\(\s*'([^']+)'\s*\)/", $hook, $matches);

    $covered = $matches[1] ?? [];
    $uncovered = [];

    foreach ($deleted as $path) {
        foreach ($covered as $prefix) {
            if ($path === $prefix || strpos($path, rtrim($prefix, '/').'/') === 0) {
                continue 2;
            }
        }

        $uncovered[] = $path;
    }

    return $uncovered;
}

/**
 * Collapse paths to vendor/<vendor>/<package> or <top>/<second> for reporting.
 */
function groupPaths(array $paths): array
{
    $groups = [];

    foreach ($paths as $path) {
        $segments = explode('/', $path);
        $depth = ($segments[0] === 'vendor') ? 3 : 2;
        $group = implode('/', array_slice($segments, 0, $depth));

        $groups[$group] = ($groups[$group] ?? 0) + 1;
    }

    arsort($groups);

    return $groups;
}

function totalSize(string $root, array $paths): int
{
    $total = 0;

    foreach ($paths as $path) {
        $total += (int) filesize($root.'/'.$path);
    }

    return $total;
}

function formatBytes(int $bytes): string
{
    return $bytes >= 1048576
        ? sprintf('%.1f MB', $bytes / 1048576)
        : sprintf('%.1f kB', $bytes / 1024);
}

function major(string $version): int
{
    return (int) explode('.', $version)[0];
}

function line(string $text): void
{
    fwrite(STDOUT, $text.PHP_EOL);
}

function fail(string $message): void
{
    fwrite(STDERR, PHP_EOL.'  ERROR: '.$message.PHP_EOL.PHP_EOL);
    exit(1);
}
