<?php

namespace Tests\Feature\Setup;

use Illuminate\Support\Facades\Route;
use Illuminate\Support\Facades\Storage;

/**
 * TODO 12: a telepítő útvonalai és a sentinel.
 *
 * A csoport léte egyetlen fájl meglétén múlik. Ez a teszt méri, hogy a
 * SetupTestCase infrastruktúrája tényleg azt állítja elő, amit ígér - enélkül
 * az összes többi setup-teszt csak azt bizonyítaná, hogy ő maga nem fut.
 */
class SetupFlowTest extends SetupTestCase
{
    public function test_the_setup_routes_exist_without_the_sentinel(): void
    {
        $this->assertFalse(
            Storage::exists('installed.txt'),
            'Az ideiglenes storage-ban nincs sentinel.'
        );

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
            $this->assertTrue(Route::has($name), "Hiányzik a(z) {$name} útvonal.");
        }
    }

    public function test_the_temporary_storage_is_not_the_real_one(): void
    {
        // Az ellenpróba a FeatureTestCase oldalán van
        // (SentinelGuardsTheInstallerTest), itt azt rögzítjük, hogy a valódi
        // storage-hoz nem nyúltunk.
        $this->assertNotSame(
            base_path('storage'),
            storage_path(),
            'A teszt nem a valódi storage-ban dolgozik.'
        );
        $this->assertFileExists(base_path('storage/app/installed.txt'));
    }

    // =========================================================================
    // A GET oldalak
    // =========================================================================

    public function test_the_welcome_page_lists_the_available_languages(): void
    {
        $this->get(route('setup.welcome'))
            ->assertStatus(200)
            ->assertViewIs('setup.welcome')
            ->assertViewHas('languages', fn ($languages) => in_array('en', $languages, true));
    }

    public function test_the_welcome_page_switches_language_from_the_query_string(): void
    {
        $this->get(route('setup.welcome', ['lang' => 'hu']))->assertStatus(200);

        $this->assertSame('hu', session('language'));
    }

    public function test_an_unknown_language_is_ignored(): void
    {
        $this->get(route('setup.welcome', ['lang' => 'klingon']))->assertStatus(200);

        $this->assertNotSame('klingon', session('language'));
    }

    public function test_the_requirements_page_reports_every_check(): void
    {
        $this->get(route('setup.requirements'))
            ->assertStatus(200)
            ->assertViewHas('results', function ($results) {
                foreach ([
                    'php_version',
                    'allow_url_fopen',
                    'extension_ctype',
                    'extension_json',
                    'extension_mbstring',
                    'extension_openssl',
                    'extension_pdo_mysql',
                    'extension_tokenizer',
                    'extension_xml',
                    'extension_intl',
                    'env_writable',
                    'storage_writable',
                ] as $key) {
                    if (! array_key_exists($key, $results)) {
                        return false;
                    }
                }

                return true;
            })
            ->assertViewHas('success');
    }

    public function test_the_remaining_forms_render(): void
    {
        foreach (['setup.basics', 'setup.database', 'setup.mail', 'setup.account'] as $name) {
            $this->get(route($name))->assertStatus(200);
        }
    }

    // =========================================================================
    // A telepítő lezárása
    // =========================================================================

    public function test_the_complete_page_writes_the_sentinel(): void
    {
        $this->assertFileDoesNotExist($this->sentinelPath());

        // A v1-patch D2 óta a sentinel kiírásának feltétele, hogy létezzen
        // adminisztrátori fiók - a fájl jelentése ugyanis "a telepítés
        // befejeződött". A feltétel nélküli kiírást az InstallerAccessTest
        // méri, mindkét irányból.
        \App\Models\User::factory()->create([
            'role'  => 'mainAdmin',
            'email' => 'flow-admin@example.test',
        ]);

        $this->get(route('setup.complete'))
            ->assertStatus(200)
            ->assertViewIs('setup.complete');

        $this->assertFileExists($this->sentinelPath());
        $this->assertStringContainsString(
            'DO NOT DELETE',
            file_get_contents($this->sentinelPath())
        );
    }

    public function test_the_installer_is_guarded_by_a_token_not_by_authentication(): void
    {
        // MEGFORDÍTVA a v1-patch D2 javításával, a felhasználó jóváhagyásával.
        //
        // Korábban a teljes csoporton NEM volt se auth, se gate, se aláírás: a
        // telepítési ablakban bárki végigvihette a folyamatot és létrehozhatta
        // a mainAdmin fiókot - és bárki le is zárhatta a telepítőt egy sima
        // GET-tel a setup.complete-re, még mielőtt a tulajdonos hozzáfért volna.
        //
        // `auth` továbbra sincs, és ez SZÁNDÉKOS: a telepítés pontosan az a
        // szakasz, amikor még nincs felhasználó, akihez kötni lehetne. A
        // védelem fájlrendszer-hozzáférést bizonyíttat egy tokennel; ezt méri
        // az InstallerAccessTest.
        $this->assertGuest();

        // A leszármazott alapból feloldott állapotból indul (SetupTestCase),
        // ezért ezek most is 200-at adnak - a kaput a másik fájl méri.
        foreach (['setup.welcome', 'setup.requirements', 'setup.account'] as $name) {
            $this->get(route($name))->assertStatus(200);
        }

        $guarded = [];

        foreach (Route::getRoutes()->getRoutesByName() as $name => $route) {
            if (! str_starts_with($name, 'setup.')) {
                continue;
            }

            $middleware = $route->gatherMiddleware();

            $this->assertNotContains('auth', $middleware, "A(z) {$name} nem lehet auth mögött.");
            $this->assertNotContains('signed', $middleware, "A(z) {$name} nem lehet aláírt.");

            // A gatherMiddleware() az ALIAST adja vissza, nem az osztálynevet.
            if (in_array('installer', $middleware, true)) {
                $guarded[] = $name;
            }
        }

        sort($guarded);

        // A nyitóképernyő és a token beküldése SZÁNDÉKOSAN marad kívül: oda
        // kell beírni a tokent. Minden más a kapun belül van.
        $this->assertSame(
            [
                'setup.account',
                'setup.basics',
                'setup.complete',
                'setup.database',
                'setup.mail',
                'setup.requirements',
                'setup.save-account',
                'setup.save-basics',
                'setup.save-database',
                'setup.save-mail',
            ],
            $guarded
        );
    }
}
