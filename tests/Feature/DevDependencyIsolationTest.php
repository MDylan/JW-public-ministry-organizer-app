<?php

namespace Tests\Feature;

use App\Models\Settings;
use App\Providers\AppServiceProvider;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * TODO 25: `require-dev` packages must not leak into the production boot.
 *
 * THE PROBLEM THIS GUARD PROTECTS AGAINST
 *
 * `config/app.php` manually registered `Barryvdh\Debugbar\ServiceProvider`,
 * which is a `require-dev` package. On a `composer install --no-dev` install
 * the class does not exist, and resolving the provider list throws an
 * `Error` - so the application does not even start. The package has an
 * auto-discovery entry (`extra.laravel.providers`), so the manual
 * registration gave nothing even during development; it only broke the
 * `--no-dev` install.
 *
 * Deleting the provider, however, also removes the `\Debugbar` alias in the
 * same place, and `AppServiceProvider::boot()` called `enable()` on it if
 * the database's `debugbar` setting was 1. This call sits inside a
 * `catch (\Exception)` block - which does NOT catch the missing class's
 * `Error` - so the setting being turned on would have killed every request
 * on a host where the package is absent. One half of the fix is the
 * deletion, the other is the guard; neither is enough on its own.
 *
 * WHY THERE IS NO RUNTIME SIMULATION HERE FOR THE MISSING PACKAGE
 *
 * Because it cannot be done, and this is stated by measurement, not
 * assumption. Three experiments were run, and each stayed green even when I
 * removed the guard from the boot - meaning none of them measured anything:
 *
 *  1. deleting the container's `debugbar` key: the facade was served from
 *     its own `$resolvedInstance` cache, never reaching the container;
 *  2. adding `Facade::clearResolvedInstance()`: the `Debugbar` facade's
 *     accessor is not the `debugbar` alias but `LaravelDebugbar::class`,
 *     which the deletion did not touch;
 *  3. deleting that key too: the **class is present in the vendor tree**, so
 *     the container simply instantiates it - `__construct($app = null)` can
 *     be resolved. And that is exactly the class that would be missing
 *     under `--no-dev`.
 *
 * The failure mode thus hinges on the vendor tree's presence, and that
 * cannot be taken away in a running process. What remains are two things
 * that really can be measured: that a dev package doesn't get put back into
 * the provider list (dynamic, strong), and that the debugbar branch doesn't
 * call the facade directly (source-based, weak, but catches the
 * regression). The true test of the `--no-dev` boot is
 * `composer install --no-dev --dry-run`, which is TODO 25's verification.
 */
class DevDependencyIsolationTest extends TestCase
{
    use RefreshDatabase;

    public function test_no_require_dev_package_is_hard_registered_as_a_provider(): void
    {
        $composer = json_decode(file_get_contents(base_path('composer.json')), true);
        $devPackages = array_keys($composer['require-dev'] ?? []);
        $this->assertNotEmpty($devPackages, 'The require-dev block is empty - the guard would measure nothing this way.');

        $registered = config('app.providers');
        $checked = 0;
        $leaked = [];

        foreach ($devPackages as $package) {
            $manifest = base_path('vendor/'.$package.'/composer.json');
            if (! file_exists($manifest)) {
                continue;
            }

            $providers = json_decode(file_get_contents($manifest), true)['extra']['laravel']['providers'] ?? [];

            foreach ($providers as $provider) {
                $checked++;

                // The list can contain both a string and `::class` (the
                // packer's line is still a string today), so a raw comparison is needed.
                if (in_array($provider, $registered, true)) {
                    $leaked[] = $package.' -> '.$provider;
                }
            }
        }

        // Without this, the guard would go silent if a package lost its
        // auto-discovery entry or the vendor tree were incomplete.
        $this->assertGreaterThan(0, $checked, 'Not a single dev package provider was checked.');

        $this->assertSame([], $leaked, 'require-dev package provider present in config/app.php\'s list: '.implode(', ', $leaked));
    }

    public function test_the_debugbar_branch_never_calls_the_facade_directly(): void
    {
        $source = file_get_contents(app_path('Providers/AppServiceProvider.php'));

        $this->assertStringNotContainsString(
            '\Debugbar::',
            $source,
            'Calling the `\Debugbar` facade unconditionally fatals where the dev package is not installed.'
        );

        $this->assertStringContainsString(
            "bound('debugbar')",
            $source,
            'The debugbar branch needs a container check in front of it: a missing class throws an `Error`, which the boot\'s `catch (\Exception)` branch does not catch.'
        );
    }

    public function test_the_debugbar_setting_still_enables_the_bar_when_the_package_is_present(): void
    {
        Settings::updateOrCreate(['name' => 'debugbar'], ['value' => '1']);

        $this->assertTrue($this->app->bound('debugbar'), 'In the dev environment, auto-discovery must register the Debugbar.');
        $this->app['debugbar']->disable();
        $this->assertFalse($this->app['debugbar']->isEnabled());

        (new AppServiceProvider($this->app))->boot();

        $this->assertTrue(
            $this->app['debugbar']->isEnabled(),
            'The `debugbar` setting, when on, must still enable the bar - the guard must not restrict more than necessary.'
        );
    }

    public function test_the_boot_completes_past_the_debugbar_branch(): void
    {
        Settings::updateOrCreate(['name' => 'debugbar'], ['value' => '1']);

        // The flag must be cleared, otherwise the test measures nothing: the
        // boot already ran once when the app was built, and the `settings_*`
        // keys were set at that point.
        config(['settings_debugbar' => null]);

        (new AppServiceProvider($this->app))->boot();

        // Writing `$defaults` back into the config follows AFTER the
        // debugbar branch (`AppServiceProvider.php:95-97`), and the
        // `catch (\Exception)` wrapped around it silently swallows whatever
        // reaches it. This flag therefore measures whether the rest of the
        // boot ran - not just that the test did not blow up.
        $this->assertSame('1', config('settings_debugbar'), 'The boot must run to completion past the debugbar branch too.');
    }
}
