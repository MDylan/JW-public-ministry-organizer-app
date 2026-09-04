<?php

namespace Tests\Feature\Setup;

use Illuminate\Support\Facades\App;

/**
 * TODO 38: the installer's language list, before and after the language
 * directory moves to the project root.
 *
 * Both installer screens that offer a language build the list themselves,
 * from the JSON files sitting directly in the language directory plus a
 * hardcoded 'en' (MetaController::welcome():24, BasicsController::languages()
 * :37). They are the only two places in the application that address that
 * directory by a literal path, so they are the only two the move can break.
 *
 * SetupFlowTest already checks that 'en' is among the offered languages. This
 * file pins the whole list instead, because the point of the move is that it
 * changes nothing a user sees - and "nothing changed" is only checkable
 * against the complete set.
 *
 * The expected list is a fact about the repository (five root JSON files plus
 * the hardcoded English), not about any installation's data.
 */
class SetupLanguageListTest extends SetupTestCase
{
    private const OFFERED_LOCALES = ['de', 'en', 'fr', 'hu', 'ro', 'sk'];

    /**
     * The list the landing screen offers.
     *
     * Read through the view rather than by calling the private method, so the
     * assertion covers the path the installer actually takes.
     */
    public function test_the_welcome_page_offers_every_root_json_locale_plus_english(): void
    {
        $this->get(route('setup.welcome'))
            ->assertOk()
            ->assertViewHas('languages', function ($languages) {
                // asort() preserves keys, so the controller hands the view an
                // array whose keys are out of order; only the values matter.
                $values = array_values($languages);
                sort($values);

                return $values === self::OFFERED_LOCALES;
            });
    }

    /**
     * The second screen must offer the same list as the first.
     *
     * The two controllers carry byte-identical copies of the same loop. That
     * duplication is not this item's to fix, but it is this test's to pin:
     * with both lists asserted equal, changing the path in one place and
     * forgetting the other fails here rather than in the browser.
     */
    public function test_the_basics_page_offers_the_same_language_list_as_the_welcome_page(): void
    {
        $this->get(route('setup.basics'))
            ->assertOk()
            ->assertViewHas('languages', function ($languages) {
                $values = array_values($languages);
                sort($values);

                return $values === self::OFFERED_LOCALES;
            });
    }

    /**
     * Where the framework says the language files live.
     *
     * Application::bindPathsInContainer() (:349-355) picks resources/lang when
     * that directory exists and falls back to base_path('lang') otherwise, so
     * this value is decided by what is on disk rather than by any
     * configuration. Nothing asserted it before, which is why the two
     * controllers could hardcode the old path without anything noticing.
     *
     * The two agree because the controllers now read lang_path() instead of
     * spelling the old location out. Before the move they agreed by accident -
     * the literal happened to name the directory the framework had picked.
     */
    public function test_the_language_directory_the_application_resolves_is_the_one_the_installer_reads(): void
    {
        $this->assertSame(base_path('lang'), App::langPath());
        $this->assertSame(lang_path(), App::langPath());
        $this->assertDirectoryExists(App::langPath());
    }
}
