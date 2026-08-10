<?php

namespace Tests\Feature\Setup;

use Illuminate\Support\Facades\Route;
use Illuminate\Support\Facades\Storage;

/**
 * TODO 12: the installer's routes and the sentinel.
 *
 * The group's existence hinges on a single file's presence. This test
 * measures that SetupTestCase's infrastructure really produces what it
 * promises - without it, all the other setup tests would only prove that
 * they themselves run.
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
        // The counter-check lives on the FeatureTestCase side
        // (SentinelGuardsTheInstallerTest); here we record that we did not
        // touch the real storage.
        $this->assertNotSame(
            base_path('storage'),
            storage_path(),
            'A teszt nem a valódi storage-ban dolgozik.'
        );
        $this->assertFileExists(base_path('storage/app/installed.txt'));
    }

    // =========================================================================
    // The GET pages
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
    // Closing out the installer
    // =========================================================================

    public function test_the_complete_page_writes_the_sentinel(): void
    {
        $this->assertFileDoesNotExist($this->sentinelPath());

        // Since v1-patch D2, writing the sentinel is conditional on an
        // administrator account existing - the file's meaning is, after all,
        // "the installation has completed". The unconditional write is
        // measured by InstallerAccessTest, from both directions.
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
        // REVERSED by the v1-patch D2 fix, with the user's approval.
        //
        // Previously the whole group had NEITHER auth, NOR a gate, NOR a
        // signature: during the installation window anyone could run through
        // the process and create the mainAdmin account - and anyone could
        // also lock down the installer with a plain GET to setup.complete,
        // before the owner ever got access.
        //
        // `auth` is still absent, and this is DELIBERATE: the installation is
        // exactly the phase where there is not yet a user to bind to. The
        // protection proves filesystem access with a token; that is what
        // InstallerAccessTest measures.
        $this->assertGuest();

        // The subclass starts from an unlocked state by default
        // (SetupTestCase), so these still return 200 here - the gate itself
        // is measured by the other file.
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

            // gatherMiddleware() returns the ALIAS, not the class name.
            if (in_array('installer', $middleware, true)) {
                $guarded[] = $name;
            }
        }

        sort($guarded);

        // The landing screen and submitting the token DELIBERATELY stay
        // outside: that is where the token must be entered. Everything else
        // is behind the gate.
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
