<?php
/*
|--------------------------------------------------------------------------
| Release upgrade hook - laraupdater package rename (TODO 18 / TODO 33.4)
|--------------------------------------------------------------------------
|
| HOW THIS RUNS
|
| MDylan\LaraUpdater\LaraUpdaterController::install() scans every entry of the
| release archive and treats ANY path containing the string "upgrade.php" as
| this hook: it moves the file to <base_path>/tmp/upgrade.php, `include`s it,
| and calls main(). A truthy return prints a success line, a falsy one prints
| an error - neither aborts the update. The file is deleted afterwards, so it
| never lands in the deployed tree.
|
| The hook runs INSIDE install(), i.e. after the new files are on disk but
| BEFORE setCurrentVersion(), optimize:clear and `artisan up`. Laravel is fully
| booted, so base_path() and the File facade are available - but the class map
| in memory is still the OLD autoloader's, so do filesystem work here, nothing
| that resolves classes from the new package.
|
| WHY IT EXISTS
|
| install() only ever adds and overwrites files. It NEVER deletes. The 1.1.6
| release renames pcinaglia/laraupdater to mdylan/laraupdater, so without this
| hook every deployed site would keep a dead vendor/pcinaglia/ tree forever.
|
| WHEN TO REMOVE IT
|
| This is a one-off for the release that carries the rename. It is idempotent
| and safe to run twice, but once 1.1.6 has reached every install, empty the
| body of main() or drop this file so later archives stop carrying it.
*/

if (! function_exists('main')) {

    /**
     * @return bool true when every target is gone (or was never there)
     */
    function main()
    {
        $ok = true;

        // 1) The renamed package's old install path. Inert once the new
        //    autoloader is in place, but it must not linger.
        $ok = laraupdater_upgrade_remove(base_path('vendor/pcinaglia')) && $ok;

        // 2) The published copy of the package's sample check-update view.
        //    Byte-identical to the vendor original, referenced by nothing, and
        //    removed from the repository in the same change set.
        $ok = laraupdater_upgrade_remove(base_path('resources/views/vendor/laraupdater')) && $ok;

        // 3) The package discovery manifest. It names service provider classes
        //    by FQCN, and this release renames one of them - a stale manifest
        //    would boot the next request into "class not found". update() calls
        //    optimize:clear a few lines later, which reaches clear-compiled and
        //    deletes these two anyway; doing it here as well means the rename
        //    survives even if that Artisan call fails.
        $ok = laraupdater_upgrade_remove(base_path('bootstrap/cache/packages.php')) && $ok;
        $ok = laraupdater_upgrade_remove(base_path('bootstrap/cache/services.php')) && $ok;

        return $ok;
    }
}

if (! function_exists('laraupdater_upgrade_remove')) {

    /**
     * Delete a file or a directory tree, reporting into the updater's output.
     *
     * Never throws: install() wraps the whole hook in a try/catch that turns an
     * exception into a full restore() of the backup, and a leftover directory
     * is not worth rolling a release back for.
     */
    function laraupdater_upgrade_remove($path)
    {
        try {
            if (! file_exists($path)) {
                echo '<li>UPGRADE => '.$path.' [ already gone ]</li>';

                return true;
            }

            $removed = is_dir($path)
                ? \Illuminate\Support\Facades\File::deleteDirectory($path)
                : \Illuminate\Support\Facades\File::delete($path);

            // deleteDirectory() can report false while still having emptied the
            // tree on Windows, so trust the filesystem over the return value.
            $removed = $removed || ! file_exists($path);

            echo '<li>UPGRADE => '.$path.' [ '.($removed ? 'removed' : 'FAILED').' ]</li>';

            return $removed;
        } catch (\Throwable $e) {
            echo '<li>UPGRADE => '.$path.' [ FAILED: '.$e->getMessage().' ]</li>';

            return false;
        }
    }
}
