<?php

namespace Tests\Unit\Support;

use Illuminate\Support\Facades\App;
use Tests\TestCase;

/**
 * TODO 38: where this application's language files live, as an assertion
 * rather than as a convention.
 *
 * Laravel 9 moved the language directory from resources/lang to the project
 * root. The framework accepts either: Application::bindPathsInContainer()
 * (:349-355) uses resources/lang when that directory exists and falls back to
 * base_path('lang') when it does not.
 *
 * That auto-detection is convenient in the repository and dangerous on a
 * deployed host. An update archive overwrites files and adds them; it never
 * removes a directory. So a host that keeps its old resources/lang after the
 * move has BOTH, the old one wins, and every translation freezes at the
 * pre-upgrade content - with no error, no exception and no log line. New keys
 * simply render as raw keys.
 *
 * The repository cannot fix that for the host - upgrade-guide.md section 3 is
 * where the operator's step lives - but it can guarantee that only one of the
 * two directories exists here, so a bad merge or a half-applied move fails in
 * the suite instead of shipping.
 */
class LangPathTest extends TestCase
{
    /**
     * The path the framework resolves, and therefore the one LangFiles,
     * FileLoader and every trans() call use.
     */
    public function test_the_language_path_is_the_resources_lang_directory()
    {
        $this->assertSame(resource_path('lang'), App::langPath());
    }

    /**
     * Exactly one language directory exists in the tree.
     *
     * This is the guard the deployed-host risk has no equivalent for. It is
     * written as a pair of assertions rather than a count, so a failure names
     * which of the two is wrong.
     */
    public function test_the_repository_carries_exactly_one_language_directory()
    {
        $this->assertDirectoryExists(resource_path('lang'));
        $this->assertDirectoryDoesNotExist(base_path('lang'));
    }

    /**
     * The published package overrides resolve under the language path too.
     *
     * FileLoader::loadNamespaceOverrides() builds "{$this->path}/vendor/
     * {$namespace}/{$locale}/{$group}.php" from the same container binding, so
     * the cookie-consent translations move with the directory rather than
     * needing a step of their own.
     *
     * The file-existence assertion is the load-bearing one. A trans() call
     * could not tell the override from the package's own copy: the published
     * files are identical to the ones under vendor/spatie/, so the override
     * layer is inert today and only its LOCATION is worth pinning.
     */
    public function test_the_vendor_overrides_resolve_under_the_language_path()
    {
        $this->assertFileExists(App::langPath('vendor/cookie-consent/hu/texts.php'));
        $this->assertDirectoryExists(App::langPath('vendor/cookie-consent'));
    }
}
