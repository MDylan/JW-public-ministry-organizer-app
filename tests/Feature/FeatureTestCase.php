<?php

namespace Tests\Feature;

use App\Models\User;
use App\Support\Settings\ApplicationSettings;
use App\Support\Translation\LangFiles;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\File;
use Tests\Concerns\BuildsDomainFixtures;
use Tests\TestCase;

abstract class FeatureTestCase extends TestCase
{
    use RefreshDatabase;
    use BuildsDomainFixtures;

    /**
     * The language directory every test in this suite sees.
     *
     * TODO 33.3. App\Support\Translation\LangFiles WRITES language files, and
     * under APP_ENV=testing App::langPath() resolves to the application's own
     * application's own language tree - so a test that forgets to point it
     * tree the suite is running against. That is the same trap TODO 07 recorded
     * for Admin\Settings::saveOthers() and .env.testing, and it is not one to
     * leave to each test's discipline: the binding is made here, once, so
     * reaching the real tree takes a deliberate act rather than an oversight.
     *
     * Tests that need their own fixtures rebind this with their own path; they
     * do not have to remember to protect anything.
     */
    protected string $suiteLangPath;

    protected function setUp(): void
    {
        parent::setUp();

        $this->suiteLangPath = storage_path('framework/testing/lang-suite');

        File::deleteDirectory($this->suiteLangPath);
        File::makeDirectory($this->suiteLangPath, 0755, true);

        $this->app->instance(
            LangFiles::class,
            new LangFiles($this->app->make(ApplicationSettings::class), $this->suiteLangPath)
        );

        $this->seedCoreSettings();

        $owner = User::factory()->asAdmin()->create([
            'email' => 'owner@example.test',
            'name'  => 'Test User',
        ]);

        $this->createHomeStaticPage($owner, 1);
    }

    protected function tearDown(): void
    {
        if (isset($this->suiteLangPath)) {
            File::deleteDirectory($this->suiteLangPath);
        }

        parent::tearDown();
    }
}
