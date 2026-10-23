<?php

namespace Tests\Feature\Livewire;

use RecursiveDirectoryIterator;
use RecursiveIteratorIterator;
use SplFileInfo;
use Tests\Feature\FeatureTestCase;

/**
 * TODO 46: the page-side JavaScript speaks Livewire 3.
 *
 * None of this is visible to a PHP test by behaviour - the suite runs no
 * JavaScript - so it is pinned on the source and on the rendered layout:
 *
 * - Livewire 3 removed `Livewire.onPageExpired()`. The application layout
 *   called it inside `livewire:init`, which threw a TypeError on every page
 *   load and left an expired session to Livewire's own English dialog. The
 *   replacement is the `request` hook's fail() callback on status 419.
 * - Livewire 3 bundles Alpine and starts it itself. Three views also pulled
 *   Alpine 3 from unpkg, so those pages ran two Alpine instances.
 * - `@livewireScripts` switches Livewire's asset auto-injection off for styles
 *   too, so the two directives must appear together or not at all; the setup
 *   layout renders no component and carries neither.
 *
 * What it cannot see: whether the browser actually shows the dialog, the
 * upload progress bar or a working modal. That is TODO 47.1's walk.
 */
class JavaScriptBridgeContractTest extends FeatureTestCase
{
    /**
     * Version 2 client APIs that version 3 removed or renamed.
     */
    private const REMOVED_APIS = [
        '/\blivewire\.(?:emit|emitTo|emitUp|emitSelf|onPageExpired|onError)\b/i',
        '/livewire:(?:load|update)\b/',
        '/[\'"](?:message\.(?:sent|failed|received|processed)|element\.(?:initialized|updating|updated|removed)|component\.initialized)[\'"]/',
    ];

    public function test_no_version_2_livewire_client_api_is_left(): void
    {
        $offenders = [];

        foreach ($this->sources() as $file => $source) {
            foreach (self::REMOVED_APIS as $pattern) {
                if (preg_match_all($pattern, $source, $matches, PREG_OFFSET_CAPTURE)) {
                    foreach ($matches[0] as [$text, $offset]) {
                        $offenders[] = $file.':'.$this->lineOf($source, $offset).' '.$text;
                    }
                }
            }
        }

        $this->assertSame([], $offenders, 'Livewire 3 has no such client API.');
    }

    public function test_alpine_is_only_the_copy_livewire_bundles(): void
    {
        $offenders = [];

        foreach ($this->sources() as $file => $source) {
            if (! str_ends_with($file, '.blade.php')) {
                continue;
            }

            if (preg_match_all('/<script\b[^>]*\bsrc="[^"]*alpine[^"]*"/i', $source, $matches, PREG_OFFSET_CAPTURE)) {
                foreach ($matches[0] as [$text, $offset]) {
                    $offenders[] = $file.':'.$this->lineOf($source, $offset);
                }
            }
        }

        $this->assertSame([], $offenders, 'Livewire 3 starts its own Alpine; a second copy runs two instances on the page.');
    }

    public function test_the_asset_directives_come_in_pairs_and_the_setup_layout_has_none(): void
    {
        $sources = $this->sources();
        $unpaired = [];

        foreach ($sources as $file => $source) {
            if (str_contains($source, '@livewireStyles') !== str_contains($source, '@livewireScripts')) {
                $unpaired[] = $file;
            }
        }

        $this->assertSame([], $unpaired, '@livewireScripts turns off auto-injection of the styles too; use both directives or neither.');
        $this->assertStringContainsString('@livewireScripts', $sources['resources/views/components/admin-layout.blade.php']);
        $this->assertStringNotContainsString('@livewire', $sources['resources/views/layouts/setup.blade.php']);
    }

    public function test_the_error_toast_ignores_the_browsers_own_error_event(): void
    {
        // TODO 47.2: the application dispatches a browser event named `error`,
        // and the browser fires its own `error` event on window for every
        // uncaught exception - an ErrorEvent with no detail. The toast handler
        // read event.detail.message off it and threw a TypeError of its own on
        // top of whatever had failed. The browser walk found it that way.
        $layout = $this->sources()['resources/views/components/admin-layout.blade.php'];

        $this->assertMatchesRegularExpression(
            "/window\\.addEventListener\\('error', event => \\{\\s*if \\(! \\(event instanceof CustomEvent\\)\\) \\{\\s*return;\\s*\\}/",
            $layout
        );
    }

    public function test_the_application_layout_handles_an_expired_page_with_the_translated_prompt(): void
    {
        $user = $this->createUser(['email' => 'js-bridge@example.test']);

        $html = $this->actingAs($user)
            ->get(route('home.home'))
            ->assertOk()
            ->getContent();

        $this->assertStringContainsString("Livewire.hook('request'", $html);
        $this->assertStringContainsString('status === 419', $html);
        // @json escapes with Laravel's default flags (15).
        $this->assertStringContainsString(json_encode(__('app.page_expired'), 15), $html);
        $this->assertStringNotContainsString('onPageExpired', $html);

        // One Livewire script and no other Alpine on a page built from the layout.
        $this->assertSame(1, preg_match_all('#<script\b[^>]*\bsrc="[^"]*/livewire(?:\.min)?\.js[^"]*"#', $html));
        $this->assertStringNotContainsString('alpinejs', $html);
    }

    /**
     * Blade views and the hand-written scripts under public/js, keyed by their
     * path from the project root, with Blade comments blanked out line for
     * line so reported line numbers stay those of the file.
     *
     * @return array<string, string>
     */
    private function sources(): array
    {
        $sources = [];
        $files = glob(public_path('js').'/*.js') ?: [];

        foreach (new RecursiveIteratorIterator(new RecursiveDirectoryIterator(resource_path('views'))) as $file) {
            /** @var SplFileInfo $file */
            if ($file->isFile() && str_ends_with($file->getFilename(), '.blade.php')) {
                $files[] = $file->getPathname();
            }
        }

        foreach ($files as $path) {
            $relative = str_replace('\\', '/', substr($path, strlen(base_path()) + 1));

            // The vendored Alpine build is a third-party file nothing loads.
            if ($relative === 'public/js/alpine.min.js') {
                continue;
            }

            $sources[$relative] = preg_replace_callback(
                '/\{\{--.*?--\}\}/s',
                fn ($comment) => str_repeat("\n", substr_count($comment[0], "\n")),
                file_get_contents($path)
            );
        }

        ksort($sources);

        return $sources;
    }

    private function lineOf(string $source, int $offset): int
    {
        return substr_count(substr($source, 0, $offset), "\n") + 1;
    }
}
