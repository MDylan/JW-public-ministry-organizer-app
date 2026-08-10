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
}
