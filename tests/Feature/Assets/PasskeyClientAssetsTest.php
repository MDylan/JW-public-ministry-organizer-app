<?php

namespace Tests\Feature\Assets;

use Tests\TestCase;

/**
 * Roadmap TODO 69: the vendored passkey client loads as plain ES modules.
 *
 * There is no build (TODO 52), so public/vendor/passkeys holds the npm
 * packages' module files as they are, and an import map resolves the one
 * bare import. A module graph that does not resolve fails only in the
 * browser, with nothing in a log - so this resolves it here: every import in
 * every vendored file is either relative and names a file that exists, or
 * bare and is mapped by the partial.
 */
class PasskeyClientAssetsTest extends TestCase
{
    private const ROOT = 'vendor/passkeys';

    public function test_every_import_of_the_vendored_modules_resolves(): void
    {
        $mapped = $this->importMap();
        $files = $this->moduleFiles();
        $bare = [];

        $this->assertGreaterThan(15, count($files), 'The vendored module tree is missing.');

        foreach ($files as $file) {
            preg_match_all('/(?:import|export)\s[^;]*?from\s*["\']([^"\']+)["\']/s', file_get_contents($file), $m);

            foreach ($m[1] as $specifier) {
                if (str_starts_with($specifier, '.')) {
                    $target = realpath(dirname($file).'/'.$specifier);
                    $this->assertNotFalse($target, "{$specifier} in ".basename($file).' does not exist.');
                    continue;
                }

                $bare[$specifier] = true;
                $this->assertArrayHasKey($specifier, $mapped, "The bare import {$specifier} is not in the import map.");
            }
        }

        $this->assertSame(['@simplewebauthn/browser'], array_keys($bare));
    }

    public function test_the_import_map_and_the_entry_point_name_existing_files(): void
    {
        foreach ($this->importMap() as $url) {
            $this->assertFileExists(public_path(ltrim(parse_url($url, PHP_URL_PATH), '/')));
        }

        $this->assertFileExists(public_path(self::ROOT.'/laravel-passkeys/index.js'));
    }

    public function test_each_vendored_package_carries_its_provenance(): void
    {
        $this->assertFileExists(public_path(self::ROOT.'/README.md'));

        foreach (['laravel-passkeys' => '@laravel/passkeys', 'simplewebauthn-browser' => '@simplewebauthn/browser'] as $dir => $name) {
            $package = json_decode(file_get_contents(public_path(self::ROOT."/{$dir}/package.json")), true);
            $this->assertSame($name, $package['name']);
            $this->assertSame('MIT', $package['license']);
            $this->assertStringContainsString($package['version'], file_get_contents(public_path(self::ROOT.'/README.md')));
        }
    }

    /** @return array<string, string> */
    private function importMap(): array
    {
        $html = view('partials.passkeys-client')->render();

        $this->assertMatchesRegularExpression('#<script type="importmap">\s*(\{.*?\})\s*</script>#s', $html);
        preg_match('#<script type="importmap">\s*(\{.*?\})\s*</script>#s', $html, $m);

        return json_decode($m[1], true)['imports'];
    }

    /** @return list<string> */
    private function moduleFiles(): array
    {
        $files = [];
        $iterator = new \RecursiveIteratorIterator(new \RecursiveDirectoryIterator(public_path(self::ROOT), \FilesystemIterator::SKIP_DOTS));
        foreach ($iterator as $file) {
            if ($file->getExtension() === 'js') {
                $files[] = $file->getPathname();
            }
        }

        return $files;
    }
}
