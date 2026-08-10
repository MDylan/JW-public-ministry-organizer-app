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
     * Stops the suite if a cached configuration or route table exists.
     *
     * With a cached configuration, Laravel doesn't even load .env, and the
     * `phpunit.xml` <server> entries don't reach it either: at test runtime it
     * gets the APPLICATION's own settings - including the `kozter_live`
     * database, the real mail driver, and the real cache.
     *
     * The route cache lies in the same way: the `setup/*` group is registered
     * behind `Storage::exists('installed.txt')`, and the cache freezes that
     * decision. A forgotten `bootstrap/cache/routes-v7.php` once caused 110
     * false failures in this suite because of exactly this.
     *
     * `composer test` clears both before starting; this guard is for when
     * someone bypasses that.
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
     * Stops the suite if it would run against the application's OWN database.
     *
     * WHY THIS IS NEEDED
     *
     * `.env` gives the `kozter_live` database, `.env.testing` and
     * `phpunit.xml` give `kozter_testing`. If the suite gets the former for
     * any reason, RefreshDatabase's migration WIPES OUT the live data -
     * irreversibly, before it gives any error message.
     *
     * Not theoretical: this guard fired immediately the first time, because a
     * forgotten `bootstrap/cache/config.php` would have made the test run get
     * `kozter_live`. (`artisan test`, as MEASURED on this machine, correctly
     * runs against `kozter_testing`; the "artisan test runs against .env"
     * case often mentioned in the literature does not apply here - but the
     * guard protects regardless of which path led there.)
     *
     * The guard sits in createApplication() because that runs the earliest -
     * before RefreshDatabase touches anything at all.
     *
     * The comparison reads directly from the `.env` FILE, not from env():
     * during the test run, env() already returns `.env.testing`'s values, so
     * only the file can tell us the application's real database name.
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
