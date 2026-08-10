<?php

namespace Tests\Feature\Assets;

use App\Models\User;
use Illuminate\Http\Request;
use Tests\Feature\FeatureTestCase;

/**
 * The asset-pipeline characterization suite - in the shape after TODO 33.8.
 *
 * WHAT CHANGED
 *
 * The file originally (TODO 21.1) recorded the behavior of
 * `eusonlito/laravel-packer`: packaged filenames, concatenation, timestamped
 * renaming, and a `local` / non-`local` dichotomy. With TODO 33.8 the package
 * is gone, replaced by the `pwbs_asset()` helper (app/Helpers/helpers.php),
 * and the measured surface changed with it: a `?v={filemtime}` query on the
 * original file, one tag per source, no concatenation, IDENTICAL REGARDLESS
 * OF ENVIRONMENT.
 *
 * THE LAST CLAUSE IS THE POINT
 *
 * The package was a passthrough under `local`, and packaged in every other
 * environment - and every measured defect (the mangled `data:` URIs, the
 * absolute URL that baked in the scheme, the write into the web root)
 * occurred EXCLUSIVELY on the non-`local` branch. That is why no one noticed
 * them during development, and why they blew up in production. Three of the
 * tests here deliberately measure that this dichotomy is gone - not just that
 * the tags look right.
 */
class AssetPipelineTest extends FeatureTestCase
{
    /** Temporary fixture under public/; cleaned up by tearDown. */
    private ?string $fixture = null;

    private ?User $user = null;

    protected function tearDown(): void
    {
        if ($this->fixture !== null && is_file($this->fixture)) {
            @unlink($this->fixture);
        }

        parent::tearDown();
    }

    // =========================================================================
    // The helper itself
    // =========================================================================

    public function test_the_helper_appends_the_files_modification_time(): void
    {
        $this->assertSame(
            asset('/css/style.css').'?v='.filemtime(public_path('css/style.css')),
            pwbs_asset('/css/style.css'),
            'Cache busting was the ONLY value the package actually delivered; '
            .'the helper produces the same thing, from a single filemtime() call, with no disk write.'
        );
    }

    public function test_the_helper_follows_a_changed_file(): void
    {
        $this->fixture = public_path('pwbs-asset-fixture.css');
        file_put_contents($this->fixture, 'a{}');

        touch($this->fixture, 1600000000);
        clearstatcache(true, $this->fixture);
        $this->assertStringEndsWith('?v=1600000000', pwbs_asset('/pwbs-asset-fixture.css'));

        // clearstatcache() is not decoration: without it, PHP's stat cache would
        // serve the SECOND filemtime() from the first one too, and the test
        // would lie green. A single request does a single stat, so this has no job in production.
        touch($this->fixture, 1700000000);
        clearstatcache(true, $this->fixture);
        $this->assertStringEndsWith('?v=1700000000', pwbs_asset('/pwbs-asset-fixture.css'));
    }

    public function test_the_helper_falls_back_to_a_plain_url_for_a_missing_file(): void
    {
        // A mistyped path should not kill a page: a URL without a token comes
        // back, not an exception. The browser gets a 404, which is visible and fixable.
        $this->assertSame(asset('/nincs-ilyen.css'), pwbs_asset('/nincs-ilyen.css'));
    }

    public function test_the_helper_treats_a_leading_slash_and_a_bare_path_alike(): void
    {
        // The old call sites used both forms interchangeably ('css/style.css'
        // in the concatenated list, '/dist/css/adminlte.min.css' as a single file).
        // The helper resolves both to the same URL, without a double slash.
        $this->assertSame(pwbs_asset('/css/style.css'), pwbs_asset('css/style.css'));
        $this->assertStringNotContainsString('//css/style.css', pwbs_asset('/css/style.css'));
    }

    public function test_the_helper_follows_the_scheme_of_the_current_request(): void
    {
        // THIS IS WHAT BROKE IN PRODUCTION. The Packer baked the scheme of the
        // generating request into the packaged CSS's absolute url()s, and
        // reused the file without limit - a file built under http caused
        // mixed content on https, and never healed itself, because the
        // filename came from the SOURCE file's filemtime, not from the
        // content. The helper recomputes on every request, so this class of
        // defect no longer exists.
        foreach (['https', 'http'] as $scheme) {
            $this->app['url']->setRequest(Request::create($scheme.'://kozter.test/home', 'GET'));

            $this->assertStringStartsWith(
                $scheme.'://kozter.test/',
                pwbs_asset('/css/style.css'),
                'The scheme belongs to the current request, not to a previous one.'
            );
        }
    }

    // =========================================================================
    // What the browser sees
    // =========================================================================

    public function test_the_app_layout_emits_a_versioned_tag_for_every_asset(): void
    {
        $html = $this->renderHome();

        foreach ($this->appLayoutAssets() as $path) {
            $this->assertMatchesRegularExpression(
                '#'.preg_quote($path, '#').'\?v=\d+#',
                $html,
                $path.' is either not emitted, or has no cache-busting token on it.'
            );
        }

        // The three multi-file calls each split into a separate tag -
        // concatenation was deliberately dropped (TODO 21's decision): it
        // affected 3 of 16 call sites, gained nothing over HTTP/2, and in
        // exchange it was the one writing into the web root.
        $this->assertStringContainsString('/js/custom.js?v=', $html);
        $this->assertStringContainsString('/js/modal.js?v=', $html);

        // And there is no trace of the old, packaged filenames.
        $this->assertDoesNotMatchRegularExpression('#/cache/(js|css)/\d+-#', $html);
        $this->assertStringNotContainsString('cache_fontawesome', $html);
        $this->assertStringNotContainsString('cache_adminlte', $html);
    }

    public function test_the_layout_emits_the_same_assets_in_every_environment(): void
    {
        $inTesting = $this->assetUrls($this->renderHome());

        $this->app['env'] = 'production';
        $inProduction = $this->assetUrls($this->renderHome());

        // Under the Packer these two lists DIFFERED, and all four measured
        // defects lived in that difference. If they ever diverge again,
        // someone has brought back an environment-dependent asset branch.
        $this->assertSame($inTesting, $inProduction);
        $this->assertNotSame([], $inTesting);
    }

    public function test_rendering_a_page_writes_nothing_into_the_web_root(): void
    {
        $before = $this->generatedArtifacts();

        $this->renderHome();

        // The Packer's process() ran mkdir + tempnam + fopen + rename + chmod
        // under public/ mid-request, in EVERY environment except `local` -
        // including the test run. That is how a `composer test` overwrote the
        // files served to the browser.
        $this->assertSame(
            $before,
            $this->generatedArtifacts(),
            'Rendering a page must not create a file in the web root.'
        );

        // Specifically, the setup layout wrote to this exact path, and thereby
        // occupied the spot `storage:link` should hold. We assert nothing
        // about public/storage itself: on a properly linked install it DOES
        // exist, just as a symlink.
        $this->assertDirectoryDoesNotExist(public_path('storage/cache'));
    }

    public function test_the_emitted_tags_have_the_shapes_the_layouts_depend_on(): void
    {
        $html = $this->renderHome();

        $this->assertMatchesRegularExpression('#<link rel="stylesheet" href="[^"]+\?v=\d+">#', $html);
        $this->assertMatchesRegularExpression('#<script src="[^"]+\?v=\d+"></script>#', $html);
    }

    // =========================================================================
    // What the browser gets THROUGH the tags
    // =========================================================================

    public function test_no_stylesheet_the_page_links_contains_an_absolute_url(): void
    {
        foreach ($this->linkedStylesheets() as $path => $contents) {
            $this->assertDoesNotMatchRegularExpression(
                '#url\(\s*["\']?https?://#i',
                $contents,
                $path.' contains an absolute reference. This is exactly what caused the mixed content: '
                .'the scheme was baked into the served file.'
            );
        }
    }

    public function test_no_stylesheet_the_page_links_has_a_mangled_data_uri(): void
    {
        $seen = 0;

        foreach ($this->linkedStylesheets() as $path => $contents) {
            preg_match_all('#url\(\s*["\']?([^)"\']*data:)#i', $contents, $matches);

            foreach ($matches[1] as $match) {
                $seen++;

                $this->assertSame(
                    'data:',
                    $match,
                    $path.' had a prefix added in front of one of its data: URIs. The Packer broke 181 of these '
                    .'this way (177 in adminlte.min.css, 4 in toastr.min.css) - as many embedded '
                    .'icons as appear throughout the application\'s forms and buttons.'
                );
            }
        }

        $this->assertGreaterThan(
            170,
            $seen,
            'If this number drops, the test is no longer measuring what it was written for.'
        );
    }

    // =========================================================================
    // The caching policy
    // =========================================================================

    /**
     * The `public/.htaccess` rule is run by Apache, so it cannot be measured
     * from PHPUnit - the headers were verified with curl on the real server.
     * What these two tests guard is the RATIONALE for the rule, because that
     * is what is easiest to lose.
     */
    public function test_the_long_cache_applies_only_to_versioned_urls(): void
    {
        $htaccess = file_get_contents(public_path('.htaccess'));

        $this->assertMatchesRegularExpression(
            '#Header always set Cache-Control "[^"]*max-age=\d+[^"]*" env=PWBS_VERSIONED_ASSET#',
            $htaccess,
            'The long cache may only be issued tied to the PWBS_VERSIONED_ASSET environment variable.'
        );

        $this->assertMatchesRegularExpression(
            '#RewriteCond %\{QUERY_STRING\} \(\^\|&\)v=\[0-9\]\+#',
            $htaccess,
            'The variable is set by the presence of the `?v={filemtime}` query, nothing else.'
        );

        // The dangerous form: any unconditional cache directive. An
        // `ExpiresByType text/css "access plus 1 year"` line would produce
        // the same effect - and would also apply to unversioned URLs, which
        // could then not be invalidated in visitors' browsers for a year.
        foreach (explode("\n", $htaccess) as $line) {
            $line = trim($line);

            if ($line === '' || str_starts_with($line, '#')) {
                continue;
            }

            if (preg_match('#^(Header\s|ExpiresByType\s|ExpiresDefault\s)#i', $line)) {
                $this->assertStringContainsString(
                    'env=PWBS_VERSIONED_ASSET',
                    $line,
                    'Unconditional cache directive: '.$line
                );
            }
        }
    }

    public function test_the_guest_layout_still_serves_unversioned_assets(): void
    {
        // This is the PREMISE of the query-string condition. public.blade.php
        // serves the same adminlte.min.css to the logged-out UI,
        // unversioned - which is why it cannot get a one-year cache.
        $guest = file_get_contents(base_path('resources/views/public.blade.php'));

        $this->assertStringContainsString("asset('dist/css/adminlte.min.css')", $guest);
        $this->assertStringNotContainsString('pwbs_asset(', $guest);

        // IF THIS TEST FAILS because someone rewrote the layout to use
        // pwbs_asset(): that is good news. At that point every asset is
        // versioned, and the .htaccess's query-string condition can be
        // dropped - but the two must be reviewed together, not separately.
    }

    // =========================================================================
    // Helpers
    // =========================================================================

    /** @return list<string> the asset paths emitted by the app layout, in order */
    private function appLayoutAssets(): array
    {
        return [
            '/plugins/fontawesome-free/css/all.min.css',
            '/dist/css/adminlte.min.css',
            '/css/style.css',
            '/plugins/toastr/toastr.min.css',
            '/css/public_style.css',
            '/plugins/jquery/jquery.min.js',
            '/plugins/bootstrap/js/bootstrap.bundle.min.js',
            '/dist/js/adminlte.min.js',
            '/plugins/toastr/toastr.min.js',
            '/plugins/sweetalert2/sweetalert2.all.min.js',
            '/js/custom.js',
            '/js/modal.js',
        ];
    }

    private function renderHome(): string
    {
        return $this->actingAs($this->pageUser())->get('/home')->assertStatus(200)->getContent();
    }

    /** @return list<string> the asset URLs present in the HTML, in order */
    private function assetUrls(string $html): array
    {
        preg_match_all('#(?:href|src)="([^"]+\?v=\d+)"#', $html, $matches);

        return $matches[1];
    }

    /**
     * The content of the linked stylesheets, path => content.
     *
     * Deliberately starts from the RENDERED HTML, not from a hand-written
     * list: if a generated file ever ends up in the tag again, these two
     * tests examine that, not the untouched source.
     *
     * @return array<string, string>
     */
    private function linkedStylesheets(): array
    {
        preg_match_all('#<link rel="stylesheet" href="([^"]+)"#', $this->renderHome(), $matches);

        $this->assertNotEmpty($matches[1], 'The layout did not link a single stylesheet.');

        $files = [];

        foreach ($matches[1] as $url) {
            $path = ltrim(parse_url($url, PHP_URL_PATH) ?? '', '/');
            $file = public_path($path);

            $this->assertFileExists($file, $url.' does not exist under public/.');

            $files[$path] = file_get_contents($file);
        }

        return $files;
    }

    /** @return list<string> the Packer-era generated artifacts, in case something brings them back */
    private function generatedArtifacts(): array
    {
        $found = [];

        foreach ([
            public_path('cache/css/*'),
            public_path('cache/js/*'),
            public_path('dist/css/*-cache_*'),
            public_path('dist/js/*-cache_*'),
            public_path('plugins/*/*-cache_*'),
            public_path('plugins/*/*/*-cache_*'),
            public_path('storage/cache/*'),
        ] as $pattern) {
            $found = array_merge($found, glob($pattern) ?: []);
        }

        sort($found);

        return $found;
    }

    /**
     * A plain, activated user who gets a 200 for /home.
     *
     * Memoized: two tests render twice each (the environment comparison and
     * the stylesheet reader), and with a fixed email address the second
     * creation would run into the uniqueness constraint.
     */
    private function pageUser(): User
    {
        return $this->user ??= $this->createUser([
            'email' => 'asset-pipeline@example.test',
            'email_verified_at' => now(),
        ]);
    }
}
