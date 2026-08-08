<?php

namespace Tests;

use Dotenv\Dotenv;
use Illuminate\Contracts\Console\Kernel;
use Illuminate\Contracts\Foundation\Application;
use RuntimeException;

trait CreatesApplication
{
    /**
     * Creates the application.
     *
     * @return \Illuminate\Foundation\Application
     */
    public function createApplication()
    {
        $app = require __DIR__.'/../bootstrap/app.php';

        $app->make(Kernel::class)->bootstrap();

        $this->refuseToRunWithCachedConfiguration($app);
        $this->refuseToRunAgainstTheApplicationDatabase($app);

        return $app;
    }

    /**
     * Megállítja a suite-ot, ha van gyorsítótárazott konfiguráció vagy route-tábla.
     *
     * Gyorsítótárazott konfiguráció mellett a Laravel be sem tölti a .env-et,
     * és a `phpunit.xml` <server> bejegyzései sem érnek el hozzá: a teszt
     * futásidőben az ALKALMAZÁS beállításait kapja - köztük a `kozter_live`
     * adatbázist, a valódi mail-drivert és a valódi cache-t.
     *
     * A route-gyorsítótár ugyanígy hazudik: a `setup/*` csoport a
     * `Storage::exists('installed.txt')` mögött regisztrálódik, és a
     * gyorsítótár ezt a döntést befagyasztja. Egy bennfelejtett
     * `bootstrap/cache/routes-v7.php` emiatt egyszer már 110 hamis bukást
     * okozott ebben a suite-ban.
     *
     * A `composer test` mindkettőt kiüríti indulás előtt; ez az őr arra van,
     * amikor valaki megkerüli.
     */
    private function refuseToRunWithCachedConfiguration(Application $app): void
    {
        $stale = [];

        if (is_file($app->getCachedConfigPath())) {
            $stale[] = 'bootstrap/cache/config.php';
        }

        if (is_file($app->getCachedRoutesPath())) {
            $stale[] = basename($app->getCachedRoutesPath());
        }

        if ($stale === []) {
            return;
        }

        throw new RuntimeException(
            'Gyorsítótárazott build-fájl van a helyén ('.implode(', ', $stale).'), ezért a '
            .'tesztek nem a phpunit.xml környezetét kapnák, hanem az alkalmazásét - az éles '
            .'adatbázissal együtt. Futtasd: `php81 artisan optimize:clear`, vagy indítsd a '
            .'suite-ot `composer test` paranccsal, ami ezt magától megteszi.'
        );
    }

    /**
     * Megállítja a suite-ot, ha az alkalmazás SAJÁT adatbázisára futna.
     *
     * MIÉRT KELL EZ
     *
     * A `.env` a `kozter_live` adatbázist adja, a `.env.testing` és a
     * `phpunit.xml` a `kozter_testing`-et. Ha a suite bármilyen okból az
     * elsőt kapja, a RefreshDatabase migrációja LETAROLJA az éles adatokat -
     * visszavonhatatlanul, mielőtt bármi hibaüzenetet adna.
     *
     * Nem elmélet: ez az őr elsőre azonnal elsült, mert egy bennfelejtett
     * `bootstrap/cache/config.php` miatt a teszt-futás a `kozter_live`-ot
     * kapta volna. (Az `artisan test` ezen a gépen MÉRVE helyesen a
     * `kozter_testing`-re fut; a szakirodalomban gyakran emlegetett
     * "az artisan test a .env-re fut" eset itt nem áll fenn - az őr viszont
     * attól függetlenül véd, hogy melyik út vezetett oda.)
     *
     * Az őr a createApplication()-ben ül, mert ez fut le a legkorábban -
     * még azelőtt, hogy a RefreshDatabase bármihez hozzányúlna.
     *
     * Az összehasonlítás közvetlenül a `.env` FÁJLBÓL olvas, nem env()-ből:
     * a teszt-futás alatt az env() már a `.env.testing` értékeit adja, tehát
     * az alkalmazás igazi adatbázisnevét csak a fájl mondja meg.
     */
    private function refuseToRunAgainstTheApplicationDatabase(Application $app): void
    {
        $connection = $app['config']->get('database.default');
        $inUse = $app['config']->get('database.connections.'.$connection.'.database');

        $envFile = $app->basePath('.env');

        if ($inUse === null || ! is_file($envFile)) {
            return;
        }

        $values = Dotenv::createArrayBacked(dirname($envFile), basename($envFile))->safeLoad();
        $application = $values['DB_DATABASE'] ?? null;

        if ($application === null || $application !== $inUse) {
            return;
        }

        throw new RuntimeException(
            'A tesztek az alkalmazás saját adatbázisára futnának ('.$inUse.'), amit a '
            .'RefreshDatabase letarolna. Ez akkor fordul elő, ha a suite-ot `artisan test` '
            .'indítja: az előbb betölti a .env-et, és az felülírja a phpunit.xml '
            .'beállítását. Használd helyette a `composer test` parancsot.'
        );
    }
}
