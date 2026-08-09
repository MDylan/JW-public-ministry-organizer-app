<?php

namespace Tests\Feature;

use App\Models\Settings;
use App\Providers\AppServiceProvider;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * TODO 25: a `require-dev` csomagok nem szivároghatnak a production bootba.
 *
 * A PROBLÉMA, AMIT EZ AZ ŐR VÉD
 *
 * A `config/app.php` kézzel regisztrálta a `Barryvdh\Debugbar\ServiceProvider`-t,
 * ami `require-dev` csomag. Egy `composer install --no-dev` telepítésen az
 * osztály nincs meg, a provider-lista feloldása pedig `Error`-t dob - tehát az
 * alkalmazás el sem indul. A csomagnak van auto-discovery bejegyzése
 * (`extra.laravel.providers`), úgyhogy a kézi regisztráció fejlesztés alatt sem
 * adott semmit; csak a `--no-dev` telepítést törte el.
 *
 * A provider törlésével viszont a `\Debugbar` alias is eltűnik ugyanott, és
 * `AppServiceProvider::boot()` meghívta rajta az `enable()`-t, ha az adatbázis
 * `debugbar` beállítása 1. Ez a hívás egy `catch (\Exception)` blokkon belül
 * ül - ami NEM fogja el a hiányzó osztály `Error`-ját -, tehát a beállítás
 * bekapcsolva minden kérést megölt volna azon a hoston, ahol a csomag nincs.
 * A javítás egyik fele a törlés, a másik az őr; külön-külön egyik sem elég.
 *
 * MIÉRT NINCS ITT FUTÁSIDEJŰ SZIMULÁCIÓ A HIÁNYZÓ CSOMAGRA
 *
 * Mert nem lehet, és ezt mérés mondja ki, nem feltételezés. Három kísérlet
 * futott, mindegyik zöld maradt akkor is, amikor az őrt kivettem a bootból -
 * vagyis egyik sem mért semmit:
 *
 *  1. a konténer `debugbar` kulcsának törlése: a facade a saját
 *     `$resolvedInstance` gyorsítótárából szolgált ki, a konténerhez el sem
 *     jutott;
 *  2. `Facade::clearResolvedInstance()` mellé véve: a `Debugbar` facade
 *     accessora nem a `debugbar` alias, hanem `LaravelDebugbar::class`, amit
 *     a törlés nem érintett;
 *  3. azt a kulcsot is törölve: az **osztály ott van a vendor fában**, tehát a
 *     konténer egyszerűen példányosítja - a `__construct($app = null)`
 *     feloldható. Márpedig pontosan ez az osztály hiányozna `--no-dev` alatt.
 *
 * A hibamód tehát a vendor fa jelenlétén múlik, azt pedig egy futó folyamatban
 * nem lehet elvenni. Ami marad, az két valóban mérhető dolog: hogy a
 * provider-listába nem kerül vissza dev-csomag (dinamikus, erős), és hogy a
 * debugbar-ág nem hívja közvetlenül a facade-ot (forrásalapú, gyenge, de a
 * regressziót elkapja). A `--no-dev` boot igazi próbája a
 * `composer install --no-dev --dry-run`, ami a TODO 25 verifikációja.
 */
class DevDependencyIsolationTest extends TestCase
{
    use RefreshDatabase;

    public function test_no_require_dev_package_is_hard_registered_as_a_provider(): void
    {
        $composer = json_decode(file_get_contents(base_path('composer.json')), true);
        $devPackages = array_keys($composer['require-dev'] ?? []);
        $this->assertNotEmpty($devPackages, 'A require-dev blokk üres - az őr így semmit nem mérne.');

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

                // A lista sztringet és `::class`-t is tartalmazhat (a packer
                // sora ma is sztring), ezért nyers összehasonlítás kell.
                if (in_array($provider, $registered, true)) {
                    $leaked[] = $package.' -> '.$provider;
                }
            }
        }

        // Enélkül az őr elnémulna attól, hogy egy csomag elveszti az
        // auto-discovery bejegyzését vagy a vendor fa hiányos.
        $this->assertGreaterThan(0, $checked, 'Egyetlen dev-csomag providere sem került ellenőrzésre.');

        $this->assertSame([], $leaked, 'require-dev csomag providere a config/app.php listájában: '.implode(', ', $leaked));
    }

    public function test_the_debugbar_branch_never_calls_the_facade_directly(): void
    {
        $source = file_get_contents(app_path('Providers/AppServiceProvider.php'));

        $this->assertStringNotContainsString(
            '\Debugbar::',
            $source,
            'A `\Debugbar` facade hívása feltétel nélkül fatalt ad ott, ahol a dev-csomag nincs telepítve.'
        );

        $this->assertStringContainsString(
            "bound('debugbar')",
            $source,
            'A debugbar-ág elé konténer-ellenőrzés kell: hiányzó osztálynál `Error` jön, amit a boot `catch (\Exception)` ága nem fog el.'
        );
    }

    public function test_the_debugbar_setting_still_enables_the_bar_when_the_package_is_present(): void
    {
        Settings::updateOrCreate(['name' => 'debugbar'], ['value' => '1']);

        $this->assertTrue($this->app->bound('debugbar'), 'A dev-környezetben az auto-discovery-nek regisztrálnia kell a Debugbart.');
        $this->app['debugbar']->disable();
        $this->assertFalse($this->app['debugbar']->isEnabled());

        (new AppServiceProvider($this->app))->boot();

        $this->assertTrue(
            $this->app['debugbar']->isEnabled(),
            'A `debugbar` beállítás bekapcsolva továbbra is be kell kapcsolja a sávot - az őr nem szűkíthet a kelleténél többet.'
        );
    }

    public function test_the_boot_completes_past_the_debugbar_branch(): void
    {
        Settings::updateOrCreate(['name' => 'debugbar'], ['value' => '1']);

        // A jelzőt üríteni kell, különben a teszt semmit nem mér: az app
        // felépítésekor a boot már egyszer lefutott, és a `settings_*` kulcsok
        // ott beálltak.
        config(['settings_debugbar' => null]);

        (new AppServiceProvider($this->app))->boot();

        // A debugbar-ág UTÁN következik a `$defaults` visszaírása a configba
        // (`AppServiceProvider.php:95-97`), és a köré vont `catch (\Exception)`
        // némán nyeli, ami odáig eljut. Ez a jelző tehát azt méri, hogy a boot
        // maradéka lefutott-e - nem csak azt, hogy a teszt nem szállt el.
        $this->assertSame('1', config('settings_debugbar'), 'A boot a debugbar-ág után is végig kell fusson.');
    }
}
