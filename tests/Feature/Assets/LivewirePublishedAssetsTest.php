<?php

namespace Tests\Feature\Assets;

use Tests\TestCase;

/**
 * TODO 43: the published Livewire assets against the package's own `dist/`.
 *
 * WHY THIS IS NEEDED
 *
 * `public/vendor/livewire/` is not a cache and it is not optional. Measured
 * from the installed tree rather than from the documentation:
 * `LivewireManager::styles()` (`LivewireManager.php:266-276`) checks
 * `file_exists(public_path('vendor/livewire/manifest.json'))` FIRST, and when
 * that file is there it serves the published copy and never touches the
 * package route. Livewire 3 keeps the same rule under a different name
 * (`FrontendAssets::usePublishedAssetsIfAvailable()`).
 *
 * The manifest exists here and is committed, so the published copy is what
 * every browser actually loads. And when it disagrees with the installed
 * package, Livewire's entire response is a `console.warn` - the page still
 * renders, every route still answers 200, and nothing in a PHP test suite is
 * in a position to notice, because the suite executes no JavaScript.
 *
 * That silence is what this file exists to break. Phase 6 replaces the
 * package wholesale; without a guard here, a bump that forgets to republish
 * would serve version 2 JavaScript to a version 3 backend, and the first
 * report would come from a user.
 *
 * WHY IT IS A TEST RATHER THAN A COMPOSER HOOK. It used to be a hook:
 * `composer.json`'s `post-autoload-dump` ran
 * `vendor:publish --force --tag=livewire:assets` on every autoload dump.
 * That kept the two sides in step only as a side effect of installing, wrote
 * TRACKED files while doing it - `public/` is committed here, and
 * `release/build-update.php` builds the release archive from a git diff - and
 * reported nothing when the copies drifted for any other reason. An assertion
 * says the same thing out loud, at a point where somebody is looking.
 *
 * WHAT IT DOES NOT CATCH, so nobody reads more into a green run than is
 * there: it compares two directories on disk. A host serving these files from
 * a CDN through `livewire.asset_url`, or one whose `public/vendor/livewire/`
 * was edited after deployment, is outside what any assertion in this
 * repository can see. `upgrade-guide.md` section 5 owns that half.
 */
class LivewirePublishedAssetsTest extends TestCase
{
    /**
     * The manifest is the switch: its presence is what makes the published
     * copy authoritative, and its contents are what Livewire compares.
     */
    public function test_the_published_manifest_is_the_one_the_installed_package_ships(): void
    {
        $published = $this->publishedPath('manifest.json');
        $shipped = $this->distPath('manifest.json');

        $this->assertFileExists($shipped, 'The installed livewire/livewire package ships no dist/manifest.json.');
        $this->assertFileExists(
            $published,
            'public/vendor/livewire/manifest.json is missing. Livewire falls back to its own route silently, so nothing else in the suite would report this.'
        );

        $this->assertSame(
            file_get_contents($shipped),
            file_get_contents($published),
            'The published manifest disagrees with the installed package. Livewire answers this with a console.warn and keeps serving the stale asset.'
        );
    }

    /**
     * Every file the package ships is published, and published unchanged.
     */
    public function test_every_file_the_package_ships_is_published_byte_for_byte(): void
    {
        $shipped = $this->fileNamesIn($this->distPath());

        $this->assertNotSame([], $shipped, 'The installed package ships no dist/ files at all.');

        foreach ($shipped as $file) {
            $this->assertFileExists(
                $this->publishedPath($file),
                $file.' is shipped by the package but is not published under public/vendor/livewire/.'
            );

            $this->assertSame(
                hash_file('sha256', $this->distPath($file)),
                hash_file('sha256', $this->publishedPath($file)),
                $file.' differs from the copy the installed package ships.'
            );
        }
    }

    /**
     * Nothing is left over from an earlier version of the package.
     *
     * `vendor:publish` only ever writes; it does not remove a file a later
     * release stopped shipping. On a deployed host neither does the updater,
     * so a stale asset can outlive the version it belonged to.
     */
    public function test_the_published_directory_carries_nothing_the_package_does_not_ship(): void
    {
        $this->assertSame(
            $this->fileNamesIn($this->distPath()),
            $this->fileNamesIn($this->publishedPath()),
            'public/vendor/livewire/ holds a file the installed package does not ship. Publishing never deletes, so this is a leftover.'
        );
    }

    /**
     * The manifest's own entries resolve to files that are actually there.
     *
     * This is the lookup `LivewireManager.php:269` performs
     * (`$publishedManifest['/livewire.js']`), so a manifest naming a path that
     * was never published produces a 404 on the script tag itself.
     */
    public function test_every_path_the_manifest_names_is_published(): void
    {
        // Asserted before reading, so a missing manifest reports the same
        // sentence here as it does in the first case rather than surfacing as
        // a file_get_contents() warning.
        $this->assertFileExists($this->publishedPath('manifest.json'));

        $manifest = json_decode(file_get_contents($this->publishedPath('manifest.json')), true);

        $this->assertIsArray($manifest, 'The published manifest is not valid JSON.');
        $this->assertNotSame([], $manifest, 'The published manifest names no assets.');

        foreach (array_keys($manifest) as $path) {
            $this->assertFileExists(
                $this->publishedPath(ltrim($path, '/')),
                $path.' is named by the published manifest but no such file was published.'
            );
        }
    }

    /**
     * The directory Composer installs the package into.
     */
    private function distPath(string $file = ''): string
    {
        return base_path('vendor/livewire/livewire/dist'.($file === '' ? '' : '/'.$file));
    }

    /**
     * The directory `vendor:publish --tag=livewire:assets` writes into, and
     * the one Livewire prefers over its own route.
     */
    private function publishedPath(string $file = ''): string
    {
        return public_path('vendor/livewire'.($file === '' ? '' : '/'.$file));
    }

    /**
     * Plain file names in a directory, sorted, without the two dot entries.
     *
     * Sorted because both sides are compared as arrays: `scandir()` order is
     * platform-dependent, and a difference in ordering is not a difference in
     * content.
     *
     * @return array<int, string>
     */
    private function fileNamesIn(string $directory): array
    {
        $this->assertDirectoryExists($directory);

        $names = array_values(array_filter(
            scandir($directory),
            fn (string $entry) => $entry !== '.' && $entry !== '..' && is_file($directory.'/'.$entry)
        ));

        sort($names);

        return $names;
    }
}
