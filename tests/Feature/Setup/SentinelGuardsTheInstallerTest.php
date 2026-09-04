<?php

namespace Tests\Feature\Setup;

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

    public function test_not_a_single_setup_route_is_registered(): void
    {
        foreach ([
            'setup.welcome',
            'setup.requirements',
            'setup.basics',
            'setup.save-basics',
            'setup.database',
            'setup.save-database',
            'setup.mail',
            'setup.save-mail',
            'setup.account',
            'setup.save-account',
            'setup.complete',
        ] as $name) {
            $this->assertFalse(Route::has($name), "A(z) {$name} nem létezhet telepített állapotban.");
        }
    }

    public function test_the_installer_urls_are_404(): void
    {
        // Not 403, not a redirect: the route simply does not exist.
        $this->get('/setup/start')->assertNotFound();
        $this->get('/setup/account')->assertNotFound();
    }

    public function test_the_route_contract_fixture_agrees(): void
    {
        // The TODO 01 route contract also contains no setup entry - this test
        // ties the two together, so that if someone captures the snapshot in
        // an uninstalled state, it surfaces when the fixture is updated.
        $fixture = json_decode(
            file_get_contents(base_path('tests/Fixtures/route-contracts.json')),
            true
        );

        foreach (array_keys($fixture) as $name) {
            $this->assertStringStartsNotWith('setup.', $name);
        }
    }

    /**
     * TODO 37: where the bare Storage:: call actually lands.
     *
     * The sentinel is read with no disk argument at all - routes/web.php:104
     * and Handler.php:60 - and written the same way (Setup/MetaController:89).
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
