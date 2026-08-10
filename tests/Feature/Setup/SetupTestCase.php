<?php

namespace Tests\Feature\Setup;

use App\Http\Middleware\EnsureInstallerToken;
use Illuminate\Contracts\Console\Kernel;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Concerns\BuildsDomainFixtures;
use Tests\TestCase;

/**
 * TODO 12: make the installer testable.
 *
 * The setup/* route group in routes/web.php:78 sits behind a condition: it
 * only registers if Storage::exists('installed.txt') is false. This file
 * EXISTS in the development instance, so the group has never been created in
 * the tests so far - there is not a single setup. entry in
 * tests/Fixtures/route-contracts.json either.
 *
 * We do not touch the real sentinel file. storage/app/.gitignore excludes
 * everything, so if an interrupted test left it deleted, the development
 * app would get stuck in an "uninstalled" state, and it would not be
 * recoverable from git.
 *
 * Instead we point the entire storage path at a temporary directory - BEFORE
 * bootstrap, because the route file runs during boot. The compiled Blade
 * views path is restored to the real one afterwards: TODO 07 measured that an
 * empty view cache stretches the suite from 50 seconds to 10 minutes.
 */
abstract class SetupTestCase extends TestCase
{
    use RefreshDatabase;
    use BuildsDomainFixtures;

    protected string $temporaryStorage;

    /**
     * The installer's token gate is UNLOCKED by default.
     *
     * Since v1-patch D2, the `setup/*` group (except the landing screen) sits
     * behind the `installer` middleware: whoever runs the install must enter
     * the server-generated token. The group otherwise does the same thing it
     * always did, so subclasses here start from an unlocked state by
     * default - this way every existing test keeps measuring what it was
     * written for.
     *
     * THE GATE ITSELF is measured by InstallerAccessTest, which deliberately
     * does not call this.
     */
    protected function setUp(): void
    {
        parent::setUp();

        $this->unlockInstaller();
    }

    protected function unlockInstaller(): void
    {
        $this->withSession([
            EnsureInstallerToken::SESSION_KEY => EnsureInstallerToken::currentToken(),
        ]);
    }

    public function createApplication()
    {
        $app = require __DIR__.'/../../../bootstrap/app.php';

        // The real paths, before the override.
        $realCompiledViews = $app->basePath().'/storage/framework/views';

        $this->temporaryStorage = $this->prepareTemporaryStorage();
        $app->useStoragePath($this->temporaryStorage);

        $app->make(Kernel::class)->bootstrap();

        $app['config']->set('view.compiled', $realCompiledViews);

        return $app;
    }

    /**
     * Empty storage skeleton: the installer also checks the logs directory's
     * writability (RequirementsController::checkRequirements), and the
     * framework subdirectories are needed for the framework to run.
     *
     * installed.txt is deleted before every test: setup.complete writes it
     * along the way, and if it stayed behind, the route group would no
     * longer register on the next test's boot.
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

        foreach (['installed.txt', EnsureInstallerToken::TOKEN_FILE] as $file) {
            $full = $path.DIRECTORY_SEPARATOR.'app'.DIRECTORY_SEPARATOR.$file;

            if (file_exists($full)) {
                unlink($full);
            }
        }

        return $path;
    }

    protected function sentinelPath(): string
    {
        return $this->temporaryStorage.DIRECTORY_SEPARATOR.'app'.DIRECTORY_SEPARATOR.'installed.txt';
    }

    /**
     * setEnvironment::setEnvironmentValue() writes to
     * app()->environmentFilePath(), which under APP_ENV=testing is
     * .env.testing - so a naive test would overwrite its own configuration.
     * This helper redirects the write to a copy, then restores it afterwards.
     *
     * @return string the temp env file's contents after the callback runs
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
