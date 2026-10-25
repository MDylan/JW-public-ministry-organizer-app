<?php

namespace Tests\Feature\Setup;

use App\Http\Middleware\EnsureInstallerToken;
use Illuminate\Support\Facades\Route;
use Illuminate\Support\Facades\Storage;
use Tests\Feature\FeatureTestCase;

/**
 * TODO 12: the other direction of the sentinel.
 *
 * This file deliberately builds on the normal FeatureTestCase, not on
 * SetupTestCase: the real storage is in effect here, with the installed.txt
 * file present in it. This is the counter-test of SetupFlowTest - together
 * the two prove that the installer really does depend on the presence of the
 * file, and not on something else.
 */
class SentinelGuardsTheInstallerTest extends FeatureTestCase
{
    public function test_the_sentinel_is_present_in_the_real_storage(): void
    {
        $this->assertTrue(Storage::exists('installed.txt'));
    }

    public function test_the_setup_routes_are_registered_whatever_the_install_state(): void
    {
        // REVERSED by roadmap TODO 72. The group used to be registered only
        // while the sentinel was absent, decided once while routes/web.php
        // loaded - so `route:cache` froze the install state into the route
        // table. The routes now always exist and `notInstalled` refuses them
        // at request time.
        foreach ($this->setupRoutes() as $name => $route) {
            $this->assertContains('notInstalled', $route->gatherMiddleware(), "A(z) {$name} nincs a notInstalled mögött.");
        }

        $this->assertArrayHasKey('setup.welcome', $this->setupRoutes());
        $this->assertArrayHasKey('setup.complete', $this->setupRoutes());
    }

    public function test_the_installer_urls_are_404(): void
    {
        // Not 403, not a redirect: an installed host has no installer. This
        // used to be the router's 404 for a missing route; it is now the
        // middleware's, for every route and method in the group. (A POST
        // without a CSRF token now meets VerifyCsrfToken first and gets 419;
        // the suite skips that check, so this measures the guard.)
        $this->assertNotEmpty($this->setupRoutes());

        foreach ($this->setupRoutes() as $route) {
            $method = $route->methods()[0];

            $this->call($method, '/'.$route->uri())->assertNotFound();
        }
    }

    public function test_an_installed_host_does_not_generate_an_installer_token(): void
    {
        // The welcome screen creates the token file lazily
        // (EnsureInstallerToken::currentToken()). The guard runs before the
        // controller, so an installed host never writes one. A fake disk,
        // because the real storage of a development host may hold a token
        // left from an earlier install.
        Storage::fake(config('filesystems.default'));
        Storage::put('installed.txt', 'test');

        $this->get('/setup/start')->assertNotFound();

        $this->assertFalse(Storage::exists(EnsureInstallerToken::TOKEN_FILE));
    }

    public function test_the_route_contract_fixture_agrees(): void
    {
        // REVERSED by roadmap TODO 72: the TODO 01 route contract now carries
        // every setup route, each behind `notInstalled`, on any host.
        $fixture = json_decode(
            file_get_contents(base_path('tests/Fixtures/route-contracts.json')),
            true
        );

        $setup = array_filter($fixture, fn (string $name) => str_starts_with($name, 'setup.'), ARRAY_FILTER_USE_KEY);

        $this->assertNotEmpty($setup);
        $this->assertSame(array_keys($this->setupRoutes()), array_keys($setup));

        foreach ($setup as $name => $contract) {
            $this->assertContains('notInstalled', $contract['middleware'], $name);
        }
    }

    /**
     * @return array<string, \Illuminate\Routing\Route>
     */
    private function setupRoutes(): array
    {
        $routes = array_filter(
            Route::getRoutes()->getRoutesByName(),
            fn (string $name) => str_starts_with($name, 'setup.'),
            ARRAY_FILTER_USE_KEY
        );
        ksort($routes);

        return $routes;
    }

    /**
     * TODO 37: where the bare Storage:: call actually lands.
     *
     * The sentinel is read with no disk argument at all - routes/web.php:104
     * and Handler.php:60 then, App\Support\Install\InstallState since roadmap
     * TODO 72 - and written the same way.
     * That resolves through filesystems.default, so the file's location is a
     * consequence of a configuration key rather than of anything written at
     * the call sites. Nothing said so before this test.
     *
     * The Flysystem 3 hop did not change this call: exists() on a name that is
     * simply absent behaved the same on Flysystem 1. It is pinned because the
     * route table's shape depends on it, and a change to filesystems.default
     * would move the file without touching a line of installer code.
     */
    public function test_the_sentinel_is_read_from_the_default_disk_under_storage_app(): void
    {
        $this->assertSame('local', config('filesystems.default'));

        $this->assertSame(
            storage_path('app'.DIRECTORY_SEPARATOR.'installed.txt'),
            Storage::disk(config('filesystems.default'))->path('installed.txt')
        );

        $this->assertSame(
            file_exists(storage_path('app/installed.txt')),
            Storage::exists('installed.txt')
        );
    }
}
