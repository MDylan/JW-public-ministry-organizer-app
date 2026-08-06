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

        $this->get(route('setup.complete'))
            ->assertStatus(200)
            ->assertViewIs('setup.complete');

        $this->assertFileExists($this->sentinelPath());
        $this->assertStringContainsString(
            'DO NOT DELETE',
            file_get_contents($this->sentinelPath())
        );
    }

    public function test_nothing_in_the_installer_requires_authentication(): void
    {
        // KARAKTERIZÁLÁS: a teljes csoporton nincs se auth, se gate, se
        // aláírás. A telepítési ablakban tehát bárki végigviheti a folyamatot
        // és létrehozhatja a mainAdmin fiókot - és bárki le is zárhatja a
        // telepítőt egy sima GET-tel a setup.complete-re, még mielőtt a
        // tulajdonos hozzáférne.
        $this->assertGuest();

        foreach (['setup.welcome', 'setup.requirements', 'setup.account', 'setup.complete'] as $name) {
            $this->get(route($name))->assertStatus(200);
        }

        foreach (Route::getRoutes()->getRoutesByName() as $name => $route) {
            if (! str_starts_with($name, 'setup.')) {
                continue;
            }

            $middleware = $route->gatherMiddleware();

            $this->assertNotContains('auth', $middleware, "A(z) {$name} mégis auth mögött van.");
            $this->assertNotContains('signed', $middleware, "A(z) {$name} mégis aláírt.");
        }
    }
}
