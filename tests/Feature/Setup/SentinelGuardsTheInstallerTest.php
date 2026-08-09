<?php

namespace Tests\Feature\Setup;

use Illuminate\Support\Facades\Route;
use Illuminate\Support\Facades\Storage;
use Tests\Feature\FeatureTestCase;

/**
 * TODO 12: a sentinel másik iránya.
 *
 * Ez a fájl szándékosan a normál FeatureTestCase-re épül, nem a
 * SetupTestCase-re: itt a valódi storage van érvényben, benne az installed.txt
 * fájllal. Ez az ellenpróbája a SetupFlowTest-nek - a kettő együtt bizonyítja,
 * hogy a telepítő tényleg a fájl meglététől függ, és nem valami mástól.
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
        // Nem 403, nem átirányítás: az útvonal egyszerűen nem létezik.
        $this->get('/setup/start')->assertNotFound();
        $this->get('/setup/account')->assertNotFound();
    }

    public function test_the_route_contract_fixture_agrees(): void
    {
        // A TODO 01 route-szerződése sem tartalmaz setup bejegyzést - ez a
        // teszt köti össze a kettőt, hogy a fixture frissítésekor kiderüljön,
        // ha valaki telepítetlen állapotban vette fel a pillanatképet.
        $fixture = json_decode(
            file_get_contents(base_path('tests/Fixtures/route-contracts.json')),
            true
        );

        foreach (array_keys($fixture) as $name) {
            $this->assertStringStartsNotWith('setup.', $name);
        }
    }
}
