<?php

namespace Tests\Feature\Setup;

use Illuminate\Contracts\Console\Kernel;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Concerns\BuildsDomainFixtures;
use Tests\TestCase;

/**
 * TODO 12: a telepítő tesztelhetővé tétele.
 *
 * A setup/* route-csoport a routes/web.php:78-ban egy feltétel mögött áll:
 * csak akkor regisztrálódik, ha a Storage::exists('installed.txt') hamis. Ez a
 * fájl a fejlesztői példányban LÉTEZIK, ezért a csoport a tesztekben eddig nem
 * is jött létre - a tests/Fixtures/route-contracts.json-ban sincs egyetlen
 * setup. bejegyzés sem.
 *
 * A valódi sentinel fájlhoz nem nyúlunk. A storage/app/.gitignore mindent
 * kizár, tehát ha egy megszakadt teszt törölve hagyná, a fejlesztői alkalmazás
 * "telepítetlen" állapotban ragadna, és git-ből nem lenne visszaállítható.
 *
 * Helyette az egész storage útvonalat ideiglenes könyvtárra állítjuk - a
 * bootstrap ELŐTT, mert a route-fájl a boot során fut le. A fordított Blade
 * nézetek útját utána visszatesszük az igazira: a TODO 07 mérte, hogy az üres
 * nézet-cache 50 másodpercről 10 percre nyújtja a suite-ot.
 */
abstract class SetupTestCase extends TestCase
{
    use RefreshDatabase;
    use BuildsDomainFixtures;

    protected string $temporaryStorage;

    public function createApplication()
    {
        $app = require __DIR__.'/../../../bootstrap/app.php';

        // A valódi útvonalak, még a felülírás előtt.
        $realCompiledViews = $app->basePath().'/storage/framework/views';

        $this->temporaryStorage = $this->prepareTemporaryStorage();
        $app->useStoragePath($this->temporaryStorage);

        $app->make(Kernel::class)->bootstrap();

        $app['config']->set('view.compiled', $realCompiledViews);

        return $app;
    }

    /**
     * Üres storage-váz: a telepítő a logs könyvtár írhatóságát is ellenőrzi
     * (RequirementsController::checkRequirements), a framework alkönyvtárak
     * pedig a keretrendszer futásához kellenek.
     *
     * Az installed.txt minden teszt előtt törlődik: a setup.complete útközben
     * kiírja, és ha bennmaradna, a következő teszt bootolásakor a route-csoport
     * már nem regisztrálódna.
     */
    private function prepareTemporaryStorage(): string
    {
        $path = sys_get_temp_dir().DIRECTORY_SEPARATOR.'kozter-setup-storage';

        foreach ([
            '',
            DIRECTORY_SEPARATOR.'app',
            DIRECTORY_SEPARATOR.'app'.DIRECTORY_SEPARATOR.'public',
            DIRECTORY_SEPARATOR.'framework',
            DIRECTORY_SEPARATOR.'framework'.DIRECTORY_SEPARATOR.'views',
            DIRECTORY_SEPARATOR.'framework'.DIRECTORY_SEPARATOR.'cache',
            DIRECTORY_SEPARATOR.'framework'.DIRECTORY_SEPARATOR.'sessions',
            DIRECTORY_SEPARATOR.'logs',
        ] as $directory) {
            $full = $path.$directory;

            if (! is_dir($full)) {
                mkdir($full, 0777, true);
            }
        }

        $sentinel = $path.DIRECTORY_SEPARATOR.'app'.DIRECTORY_SEPARATOR.'installed.txt';

        if (file_exists($sentinel)) {
            unlink($sentinel);
        }

        return $path;
    }

    protected function sentinelPath(): string
    {
        return $this->temporaryStorage.DIRECTORY_SEPARATOR.'app'.DIRECTORY_SEPARATOR.'installed.txt';
    }

    /**
     * A setEnvironment::setEnvironmentValue() az app()->environmentFilePath()-ra
     * ír, ami APP_ENV=testing alatt a .env.testing - egy naiv teszt tehát a
     * saját konfigurációját írná át. Ez a helper egy másolatra irányítja az
     * írást, és utána visszaállít.
     *
     * @return string a temp env fájl tartalma a callback lefutása után
     */
    protected function withTemporaryEnvFile(callable $callback): string
    {
        $originalPath = $this->app->environmentPath();
        $originalFile = $this->app->environmentFile();

        $directory = sys_get_temp_dir().DIRECTORY_SEPARATOR.'kozter-setup-env';

        if (! is_dir($directory)) {
            mkdir($directory, 0777, true);
        }

        $file = 'env.'.uniqid();
        $target = $directory.DIRECTORY_SEPARATOR.$file;

        copy($originalPath.DIRECTORY_SEPARATOR.$originalFile, $target);

        $this->app->useEnvironmentPath($directory);
        $this->app->loadEnvironmentFrom($file);

        try {
            $callback();

            return file_get_contents($target);
        } finally {
            $this->app->useEnvironmentPath($originalPath);
            $this->app->loadEnvironmentFrom($originalFile);
            @unlink($target);
        }
    }
}
