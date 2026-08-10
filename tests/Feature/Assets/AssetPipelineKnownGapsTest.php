<?php

namespace Tests\Feature\Assets;

use Tests\Feature\FeatureTestCase;

/**
 * The nine gaps measured by TODO 21.1 - ALL CLOSED by TODO 33.8.
 *
 * The file originally recorded the buggy behavior, so it would fail at the
 * moment of the fix, and that failure would be the reviewable diff - the same
 * discipline as the TODO 14 duplicate-route tripwires and the TODO 20.1
 * WeatherKnownGapsTest file. That failure occurred: all nine cases flipped,
 * and the file now guards against the gap reopening.
 *
 * The name is deliberately unchanged: this lets the git history track which
 * assertion took the place of which gap.
 *
 * The nine cases flipped across two commits, because TODO 33.8 shipped in two
 * commits: the first carried the application-level swap (call sites,
 * provider, alias), the second the package itself. After the first commit,
 * the five package-level cases here were deliberately STILL TRUE - they
 * measured the installed package, and `vendor/eusonlito` was still in place.
 * Measuring it was more honest than assuming it.
 */
class AssetPipelineKnownGapsTest extends FeatureTestCase
{
    // =========================================================================
    // 1. The CSS rewrite caused real production damage
    // =========================================================================

    public function test_the_embedded_icons_reach_the_browser_untouched(): void
    {
        // Providers/CSS.php:25 inserted the asset base and the source file's
        // directory in front of EVERY `url(`, unconditionally. For a relative
        // path this was correct; for a data: URI it produced a meaningless
        // absolute prefix, and the image never loaded. TODO 21 recorded four
        // such cases (the toastr icons); the real number is 181, because no
        // one ever looked at adminlte.min.css.
        $counts = [
            'dist/css/adminlte.min.css' => 177,
            'plugins/toastr/toastr.min.css' => 4,
        ];

        foreach ($counts as $file => $expected) {
            $contents = file_get_contents(public_path($file));

            $this->assertSame(
                $expected,
                preg_match_all('#url\(\s*["\']?data:#i', $contents),
                $file.' - the count of data: URIs has shifted; the test is no longer measuring what it was written for.'
            );

            $this->assertSame(
                0,
                preg_match_all('#url\(\s*["\']?https?://#i', $contents),
                $file.' contains an absolute reference. Nothing may rewrite the served CSS.'
            );
        }

        // And the project gained nothing from the rewrite: its two own CSS
        // files have zero `url(` in them, so there was nothing to fix on them.
        foreach (['css/style.css', 'css/public_style.css'] as $own) {
            $this->assertStringNotContainsString('url(', file_get_contents(public_path($own)));
        }
    }

    // =========================================================================
    // 2. The package didn't do what its name said
    // =========================================================================

    public function test_nothing_is_generated_so_there_is_nothing_to_minify(): void
    {
        // Both `css_minify` and `js_minify` were `false`, so the
        // "packer/minify" package in this project was a concatenator and a
        // timestamped renamer, nothing else. Its configuration is gone.
        $this->assertNull(config('packer'), 'config/packer.php is gone.');
        $this->assertFileDoesNotExist(base_path('config/packer.php'));

        // The one thing it actually delivered was cache busting.
        // That is now provided by a filemtime() call, with no generated file.
        $this->assertStringContainsString('?v=', pwbs_asset('/css/style.css'));
    }

    // =========================================================================
    // 3-4. The write into the web root and its clash with storage:link
    // =========================================================================

    public function test_the_two_layouts_now_reference_the_same_file_by_the_same_url(): void
    {
        $app = file_get_contents(base_path('resources/views/layouts/app.blade.php'));
        $setup = file_get_contents(base_path('resources/views/layouts/setup.blade.php'));

        // Previously the same jQuery was packaged into two DIFFERENT output
        // locations (`/cache/js/jquery.js` and `/storage/cache/js/jquery.js`),
        // so it existed twice on disk, under two URLs, in two cache entries.
        // There is no output location any more: both layouts reference the
        // same source file.
        foreach ([$app, $setup] as $layout) {
            $this->assertStringContainsString("pwbs_asset('/plugins/jquery/jquery.min.js')", $layout);
        }

        foreach (['/cache/js/', '/cache/css/', '/storage/cache/'] as $target) {
            $this->assertStringNotContainsString($target, $app);
            $this->assertStringNotContainsString($target, $setup);
        }
    }

    public function test_nothing_occupies_the_storage_symlink_path_any_more(): void
    {
        // Packer::checkDir() created a real directory exactly where
        // `php artisan storage:link` would place the symlink - because of the
        // setup layout's `/storage/cache/...` targets, on the installer
        // wizard's FIRST render. From then on the command skipped the link
        // with a "The [public/storage] link already exists" error (--force
        // doesn't help either, because it only deletes for is_link()), and
        // every URL on the public disk gave a 404.
        $this->assertStringNotContainsString(
            '/storage/',
            file_get_contents(base_path('resources/views/layouts/setup.blade.php')),
            'The setup layout no longer directs anything to the symlink location.'
        );

        // The full suite renders setup pages (tests/Feature/Setup/), so if
        // anything writes there again, this directory would appear during the run.
        $this->assertDirectoryDoesNotExist(
            public_path('storage/cache'),
            'Something is writing to the web root storage path again.'
        );
    }

    // =========================================================================
    // 5. The nine .gitignore lines that only throttled the output
    // =========================================================================

    public function test_the_nine_gitignore_lines_are_gone(): void
    {
        $gitignore = array_map('trim', file(base_path('.gitignore')));

        $lines = [
            'public/dist/css/*-cache_adminlte.min.css',
            'public/dist/js/*-cache_adminlte.js',
            'public/plugins/bootstrap/js/*-cache_bootstrap.js',
            'public/plugins/fontawesome-free/css/*-cache_fontawesome.css',
            'public/plugins/sweetalert2/*-cache_sweetalert2.js',
            'public/plugins/toastr/*-cache_toastr.js',
            'public/plugins/summernote/*-cache_summernote-bs4.min.js',
            '/public/cache',
            // The ninth one was a typo: no such directory exists or ever existed.
            '/public/storages/cache/*',
        ];

        foreach ($lines as $line) {
            $this->assertNotContains(
                $line,
                $gitignore,
                'This line existed solely because the Packer wrote into the web root mid-request.'
            );
        }

        // The tenth remains: that one belongs to the symlink, not to the package.
        $this->assertContains('/public/storage', $gitignore);
    }

    // =========================================================================
    // 6-7. The dead dependency and the dead provider
    // =========================================================================

    public function test_neither_the_packer_nor_imagecow_is_installed_any_more(): void
    {
        $lock = json_decode(file_get_contents(base_path('composer.lock')), true);
        $json = json_decode(file_get_contents(base_path('composer.json')), true);

        foreach (['eusonlito/laravel-packer', 'imagecow/imagecow'] as $package) {
            $this->assertNull(
                collect($lock['packages'])->firstWhere('name', $package),
                $package.' is still present in the lock.'
            );

            $this->assertArrayNotHasKey($package, $json['require']);
        }

        // Imagecow was pulled in by a SINGLE package, and even that only
        // because of the never-called Packer::img() - i.e., a dependency for
        // the sake of a dead API.
        $this->assertSame(
            [],
            collect($lock['packages'])
                ->filter(fn ($package) => isset($package['require']['imagecow/imagecow']))
                ->pluck('name')
                ->values()
                ->all()
        );

        $this->assertDirectoryDoesNotExist(base_path('vendor/eusonlito'));
        $this->assertDirectoryDoesNotExist(base_path('vendor/imagecow'));
    }

    public function test_the_provider_and_the_facade_alias_are_gone(): void
    {
        $config = file_get_contents(base_path('config/app.php'));

        // TODO 25's second point would have replaced these two string
        // literals with `::class`. TODO 25 deliberately left them alone,
        // because 33.8 was going to delete them anyway - so no one edited the
        // same two lines twice, and there was no need to review a change on
        // its way to deletion.
        $this->assertStringNotContainsString('Eusonlito', $config);

        $this->assertNotContains('Eusonlito\LaravelPacker\PackerServiceProvider', config('app.providers'));
        $this->assertArrayNotHasKey('Packer', config('app.aliases'));

        // Since Laravel 5.8 the provider used the dead `protected $defer = true;`
        // form together with `provides()`, without DeferrableProvider - so its
        // deferral had been ineffective across six major versions, and the
        // package registered eagerly on every request. The class is gone now.
        $this->assertFalse(class_exists('Eusonlito\LaravelPacker\PackerServiceProvider'));
        $this->assertFalse(class_exists('Eusonlito\LaravelPacker\Packer'));
    }

    // =========================================================================
    // 8-9. TODO 52 / 53 - correction of Phase 7's premise
    // =========================================================================

    public function test_gap_the_mix_pipeline_is_dead_and_the_packer_is_the_only_asset_pipeline(): void
    {
        // According to the roadmap's Phase 7, the Vite migration is mandatory
        // because Mix 6 does not build on Node 24, and per TODO 52, `mix()`
        // helpers need to be replaced with `@vite` in the two layouts.
        // Neither statement holds.
        $this->assertSame(
            [],
            $this->grepProjectSources('mix('),
            'There are ZERO `mix()` calls in the project - not in the layouts, not anywhere else.'
        );

        $this->assertSame(0, filesize(base_path('resources/css/app.css')), 'The CSS entry point is empty.');
        $this->assertSame(
            ["require('./bootstrap');"],
            array_map('trim', file(base_path('resources/js/app.js'))),
            'The JS entry point is the untouched Laravel default.'
        );

        // And the Mix outputs don't even exist, so the build never ran here.
        $this->assertFileDoesNotExist(base_path('public/js/app.js'));
        $this->assertFileDoesNotExist(base_path('public/css/app.css'));

        // Per TODO 21's ordering correction, Vite could NOT have made the
        // Packer redundant, because the Packer was the application's only
        // working asset pipeline, and TODO 52 didn't touch a single one of
        // its 16 call sites. After TODO 33.8 the order reversed: TODO 52 now
        // needs to replace `pwbs_asset()` tags with `@vite`, not `Packer::` calls.
        $this->assertSame([], $this->grepProjectSources('Packer::'));

        $callSites = array_values(array_filter(
            $this->grepProjectSources('pwbs_asset('),
            fn (string $hit) => str_starts_with($hit, 'resources')
        ));

        $this->assertCount(
            21,
            $callSites,
            'app.blade.php 12 + setup.blade.php 8 + poster-edit-modal.blade.php 1. The 16 calls became '
            .'21 tags because the three multi-file calls each split into a separate tag per source.'
        );

        // The remaining hit is the definition itself - without filtering it
        // would be 22, because the helper's source also contains the
        // searched-for text. We assert nothing about the line number: that
        // would shift with any modification above it in helpers.php, and the
        // question isn't which line it's on.
        foreach (array_diff($this->grepProjectSources('pwbs_asset('), $callSites) as $hit) {
            $this->assertStringStartsWith('app'.DIRECTORY_SEPARATOR.'Helpers', $hit);
        }
    }

    // =========================================================================
    // Helpers
    // =========================================================================

    /** @return list<string> the hits in `file:line` form */
    private function grepProjectSources(string $needle): array
    {
        $hits = [];

        foreach ([base_path('app'), base_path('resources'), base_path('config'), base_path('routes')] as $root) {
            $iterator = new \RecursiveIteratorIterator(new \RecursiveDirectoryIterator($root));

            foreach ($iterator as $file) {
                if (! $file->isFile() || ! preg_match('/\.(php|blade\.php)$/', $file->getFilename())) {
                    continue;
                }

                foreach (file($file->getPathname()) as $number => $line) {
                    if (strpos($line, $needle) !== false) {
                        $hits[] = str_replace(base_path().DIRECTORY_SEPARATOR, '', $file->getPathname()).':'.($number + 1);
                    }
                }
            }
        }

        sort($hits);

        return $hits;
    }
}
