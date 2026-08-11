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
| The laraupdater rename half is a one-off for the release that carries it, and
| so is the vendor/rakibdevs removal added with the v1-patch C group, the
| packer removal added with TODO 33.8 and the vendor/protonemedia removal added
| with TODO 33.5. The hook is idempotent and safe to run twice, but once the
| release has reached every install, empty the body of main() or drop this file
| so later archives stop carrying it.
|
| The public/storage repair is the exception: it is per-INSTALL state, not
| per-release, so it stays useful for as long as any host might still carry the
| directory the packer created.
|
| FROZEN FROM TODO 34 ONWARDS
|
| main() does NOT grow any more. The framework hops of Phase 4 through Phase 10
| replace essentially the whole vendor/ tree, and listing every orphaned path
| here, hop by hop, would be neither reviewable nor exercised until the single
| release that finally needs it. Those paths are recorded in upgrade-guide.md
| (section 2) instead, and the 2.0.0 release decides in one place what to do
| with the accumulated list.
|
| This is a deliberate reversal of what the TODO 33.5 and 33.8 entries say. It
| applies from Phase 4 onwards; everything already in main() below belongs to
| Phase 3 and stays.
|
| The file itself stays shippable, and the reason is item 3: deleting the two
| bootstrap/cache manifests is needed by EVERY release that moves a service
| provider, which from here on means every hop. The hook still serves the 1.x
| line, which is the only line it can reach - App\Support\Updates\UpdateBranch
| refuses to auto-install a higher major, so 2.0.0 never arrives through
| install() at all.
|
| NOTE for whoever ships the OpenWeather change: deployed .env files still carry
| the misspelled OPENWAETHER_API_KEY. config/openweather.php reads the new
| OPENWEATHER_API_KEY first and falls back to the old name for exactly one
| release, so nothing breaks on upgrade - but that fallback has to be removed
| once the key has been renamed everywhere.
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

        // 4) The OpenWeather package removed by the v1-patch C group. Its two
        //    consumed endpoints moved into App\Support\Weather, so the vendor
        //    tree is dead weight - and install() never deletes, so without this
        //    line every deployed host would keep it forever.
        $ok = laraupdater_upgrade_remove(base_path('vendor/rakibdevs')) && $ok;

        // 5) The asset packer removed by TODO 33.8, and imagecow, which nothing
        //    else in the tree requires. The application no longer references
        //    either, so the vendor trees and the config file are dead weight.
        $ok = laraupdater_upgrade_remove(base_path('vendor/eusonlito')) && $ok;
        $ok = laraupdater_upgrade_remove(base_path('vendor/imagecow')) && $ok;
        $ok = laraupdater_upgrade_remove(base_path('config/packer.php')) && $ok;

        // 5b) The GDPR package removed by TODO 33.2. Its two traits and one
        //     FormRequest now live in app/Support/Gdpr and app/Http/Requests,
        //     and the consent half went with it: a published view that always
        //     returned a 500, and a middleware that was never registered.
        //     config/gdpr.php stays - it is project-owned and still read.
        //
        //     The GdprServiceProvider FQCN is in the two bootstrap/cache
        //     manifests removed above, so an install that fails its Artisan
        //     call still boots.
        $ok = laraupdater_upgrade_remove(base_path('vendor/dialect')) && $ok;
        $ok = laraupdater_upgrade_remove(base_path('resources/views/gdpr')) && $ok;
        $ok = laraupdater_upgrade_remove(
            base_path('app/Http/Middleware/RedirectIfUnansweredTerms.php')
        ) && $ok;
        $ok = laraupdater_upgrade_remove(
            base_path('app/Console/Commands/PackageAnonymizeInactiveUsers.php')
        ) && $ok;

        // 5c) The translation package removed by TODO 33.3, and everything it
        //     had published into the application. The editor is now
        //     App\Http\Livewire\Admin\Translation plus
        //     App\Support\Translation\LangFiles, so the Vue/Tailwind front-end,
        //     the vendor views and the vendor language files are all dead
        //     weight - and install() never deletes, so only this hook can clear
        //     them from a deployed host.
        //
        //     config/translation.php goes with them: unlike config/gdpr.php it
        //     described the package's own routes and driver, and nothing in the
        //     application reads it any more.
        //
        //     NOTE: resources/lang/vendor/cookie-consent stays - that is a
        //     different package, and only the translation subdirectory is
        //     removed here.
        $ok = laraupdater_upgrade_remove(base_path('vendor/joedixon')) && $ok;
        $ok = laraupdater_upgrade_remove(base_path('public/vendor/translation')) && $ok;
        $ok = laraupdater_upgrade_remove(base_path('resources/views/vendor/translation')) && $ok;
        $ok = laraupdater_upgrade_remove(base_path('resources/lang/vendor/translation')) && $ok;
        $ok = laraupdater_upgrade_remove(base_path('config/translation.php')) && $ok;

        // 5d) The pending-email package removed by TODO 33.5. Its trait, model,
        //     controller and two Mailables now live in app/Support/Email,
        //     app/Models, app/Http/Controllers/User and app/Mail, and the two
        //     published Blade views moved to resources/views/emails - the first
        //     of which was the package's untranslated English stub in a
        //     22-locale application.
        //
        //     config/verify-new-email.php STAYS: it is project-owned now and
        //     still read for the redirect target, the model and the two mailable
        //     classes. The `pending_user_emails` table and its migration stay
        //     too, unchanged in shape - there is no data migration here.
        //
        //     The ProtoneMedia service provider FQCN is in the two
        //     bootstrap/cache manifests removed above, so an install that fails
        //     its Artisan call still boots.
        $ok = laraupdater_upgrade_remove(base_path('vendor/protonemedia')) && $ok;
        $ok = laraupdater_upgrade_remove(base_path('resources/views/vendor/verify-new-email')) && $ok;

        // 6) Everything the packer generated inside the web root while rendering
        //    pages. None of it is tracked by git, so the release diff cannot
        //    carry the deletions - only this hook can. The files are inert once
        //    the layouts stop referencing them, but they are stale copies of
        //    application assets sitting in a public directory, so they go.
        $ok = laraupdater_upgrade_remove(base_path('public/cache')) && $ok;

        foreach ([
            'public/dist/css/*-cache_*',
            'public/dist/js/*-cache_*',
            'public/plugins/*/*-cache_*',
            'public/plugins/*/*/*-cache_*',
        ] as $pattern) {
            $ok = laraupdater_upgrade_remove_glob(base_path($pattern)) && $ok;
        }

        // 7) The stray public/storage directory the packer created, and the
        //    symlink that could never be made while it was there.
        $ok = laraupdater_upgrade_repair_public_storage() && $ok;

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

if (! function_exists('laraupdater_upgrade_remove_glob')) {

    /**
     * Delete every path matching a glob pattern.
     *
     * The packer named its output `{filemtime}-cache_*.ext`, so the exact names
     * differ per install and cannot be listed here.
     *
     * @return bool true when every match is gone (or there were none)
     */
    function laraupdater_upgrade_remove_glob($pattern)
    {
        $matches = glob($pattern);

        if ($matches === false || $matches === []) {
            echo '<li>UPGRADE => '.$pattern.' [ nothing to remove ]</li>';

            return true;
        }

        $ok = true;

        foreach ($matches as $match) {
            $ok = laraupdater_upgrade_remove($match) && $ok;
        }

        return $ok;
    }
}

if (! function_exists('laraupdater_upgrade_repair_public_storage')) {

    /**
     * Turn the stray public/storage directory back into the symlink it should be.
     *
     * The packer wrote the setup layout's output to `/storage/cache/...`, so
     * rendering the installer wizard created `public/storage` as a REAL
     * directory. From then on `php artisan storage:link` reported "The
     * [public/storage] link already exists" and skipped the link - `--force`
     * does not help, it only removes an `is_link()` - so every public-disk URL
     * 404'd on that host.
     *
     * Deleting a directory under the web root is the most dangerous thing this
     * hook does, so the guard is deliberately narrow: the directory is removed
     * ONLY when it is not a link and contains nothing except the `cache` subtree
     * the packer created. Anything else - a real symlink, someone's uploads, a
     * manually placed file - is left alone and reported, because a missing
     * symlink is worth far less than someone's data.
     *
     * @return bool
     */
    function laraupdater_upgrade_repair_public_storage()
    {
        $path = base_path('public/storage');

        try {
            if (is_link($path)) {
                echo '<li>UPGRADE => '.$path.' [ already a link ]</li>';

                return true;
            }

            if (is_dir($path)) {
                $entries = array_values(array_diff(scandir($path) ?: [], ['.', '..']));

                if ($entries !== [] && $entries !== ['cache']) {
                    echo '<li>UPGRADE => '.$path.' [ SKIPPED: holds more than the packer cache ]</li>';

                    return true;
                }

                if (! laraupdater_upgrade_remove($path)) {
                    return false;
                }
            }

            \Illuminate\Support\Facades\Artisan::call('storage:link');

            echo '<li>UPGRADE => '.$path.' [ linked ]</li>';

            return true;
        } catch (\Throwable $e) {
            echo '<li>UPGRADE => '.$path.' [ FAILED: '.$e->getMessage().' ]</li>';

            return false;
        }
    }
}
