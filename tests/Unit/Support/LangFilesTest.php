<?php

namespace Tests\Unit\Support;

use App\Support\Settings\ApplicationSettings;
use App\Support\Translation\LangFiles;
use Illuminate\Support\Facades\File;
use InvalidArgumentException;
use RuntimeException;
use Tests\TestCase;

/**
 * TODO 33.3: the language file repository behind the in-house editor.
 *
 * Every test here works in a temporary language directory. That is not
 * tidiness: LangFiles writes real files, and under APP_ENV=testing
 * App::langPath() resolves to the application's own resources/lang - the same
 * trap TODO 07 hit when Admin\Settings::saveOthers() rewrote .env.testing. A
 * test that forgot the injected base path would shred the language tree it is
 * running against.
 *
 * The two load-bearing cases are the ones the vendor driver got wrong:
 * a key that contains a literal dot must survive an unrelated save, and an
 * unchanged value must not rewrite the file at all.
 */
class LangFilesTest extends TestCase
{
    private string $langPath;

    protected function setUp(): void
    {
        parent::setUp();

        $this->langPath = storage_path('framework/testing/lang-files');

        File::deleteDirectory($this->langPath);
        File::makeDirectory($this->langPath, 0755, true);
    }

    protected function tearDown(): void
    {
        File::deleteDirectory($this->langPath);

        parent::tearDown();
    }

    private function langFiles(array $languages = ['hu' => ['name' => 'Magyar', 'visible' => true]]): LangFiles
    {
        return new LangFiles(new FakeApplicationSettings($languages), $this->langPath);
    }

    private function writeGroup(string $locale, string $group, string $contents): string
    {
        $directory = $this->langPath.'/'.$locale;

        if (! is_dir($directory)) {
            File::makeDirectory($directory, 0755, true);
        }

        $path = $directory.'/'.$group.'.php';
        file_put_contents($path, $contents);

        return $path;
    }

    /**
     * Compare rendered language files by content, not by checkout artefact.
     *
     * LangFiles::renderArray() and renderJson() end every line with a literal
     * "\n" on purpose - the language tree is LF throughout, and writing
     * PHP_EOL would rewrite every file with CRLF the first time somebody saved
     * a translation on Windows.
     *
     * A heredoc in THIS file, however, carries whatever line endings the
     * checkout produced. .gitattributes says `* text=auto` with no eol, so on
     * a client with core.autocrlf=true the working copy is CRLF and the
     * expectation below arrives with CRLF while the file under test is LF.
     * That is a property of the checkout, not of the code, so both sides are
     * normalised rather than one of them being declared correct.
     */
    private function withUnixLineEndings(string $contents): string
    {
        return str_replace("\r\n", "\n", $contents);
    }

    // =========================================================================
    // 1. Reading - the path, not a flattened key
    // =========================================================================

    public function test_it_reads_entries_with_their_structural_path(): void
    {
        $this->writeGroup('hu', 'app', <<<'PHP'
<?php

return [
    'title' => 'Cím',
    'day_stats' => [
        'ready' => 'Kihasznált órák',
    ],
];

PHP);

        $entries = $this->langFiles()->read('hu', 'app');

        $this->assertSame(['title'], $entries[0]['path']);
        $this->assertSame('title', $entries[0]['key']);
        $this->assertSame('Cím', $entries[0]['value']);

        $this->assertSame(['day_stats', 'ready'], $entries[1]['path']);
        $this->assertSame('day_stats.ready', $entries[1]['key']);
    }

    public function test_a_key_containing_a_dot_keeps_its_own_path(): void
    {
        // The distinction the vendor driver could not make: these two entries
        // produce the SAME dotted label, and only the path tells them apart.
        $this->writeGroup('hu', 'setup', <<<'PHP'
<?php

return [
    'token.title' => 'Literál',
    'token' => [
        'title' => 'Beágyazott',
    ],
];

PHP);

        $entries = $this->langFiles()->read('hu', 'setup');

        $this->assertSame(['token.title'], $entries[0]['path']);
        $this->assertSame(['token', 'title'], $entries[1]['path']);
        $this->assertSame($entries[0]['key'], $entries[1]['key'], 'The label alone cannot address them.');
    }

    // =========================================================================
    // 2. Writing - the two cases the vendor driver got wrong
    // =========================================================================

    public function test_a_key_containing_a_dot_survives_an_unrelated_save(): void
    {
        // THE control case for this whole class. The roadmap specified
        // Arr::dot() for read and Arr::set() for write; measuring the real
        // language tree showed several files whose literal-dot keys do not
        // survive that round trip - among them two hu files, and hu is the only
        // complete locale.
        //
        // CONTROL: swap renderPhp()'s input for Arr::set()-rebuilt data and
        // this test fails on the dotted key while the neighbouring one passes.
        $this->writeGroup('hu', 'laraupdater', <<<'PHP'
<?php

return [
    'ACTION_NOT_ALLOWED.' => 'Nem engedélyezett',
    'Update_downloading_..' => 'Letöltés',
    'plain' => 'Egyszerű',
];

PHP);

        $files = $this->langFiles();

        $files->write('hu', 'laraupdater', ['plain'], 'Módosítva');

        $raw = $files->raw('hu', 'laraupdater');

        $this->assertSame('Nem engedélyezett', $raw['ACTION_NOT_ALLOWED.'] ?? null);
        $this->assertSame('Letöltés', $raw['Update_downloading_..'] ?? null);
        $this->assertSame('Módosítva', $raw['plain']);
        $this->assertSame(
            ['ACTION_NOT_ALLOWED.', 'Update_downloading_..', 'plain'],
            array_keys($raw),
            'A dotted key must stay one key, not become a nested branch.'
        );
    }

    public function test_an_unchanged_value_does_not_rewrite_the_file(): void
    {
        // Comments cannot survive a rewrite - the file is regenerated from its
        // parsed array - so the cheapest protection is not to rewrite at all
        // when nothing changed. Several hu files carry a comment header, and
        // simply opening the editor must not cost them.
        $original = <<<'PHP'
<?php

// TODO 33.3: ennek a kommentnek túl kell élnie egy érdemi változás nélküli mentést.
return [
    'title' => 'Cím',
];

PHP;
        $path = $this->writeGroup('hu', 'app', $original);

        $wrote = $this->langFiles()->write('hu', 'app', ['title'], 'Cím');

        $this->assertFalse($wrote, 'An unchanged value must report that it wrote nothing.');
        $this->assertSame($original, file_get_contents($path));
    }

    public function test_saving_keeps_the_key_order_and_the_project_format(): void
    {
        // The package ksort()ed every file on every save and emitted the old
        // array() syntax. The hu tree is hand-written, four-space, short
        // syntax, and grouped by meaning rather than alphabet.
        $path = $this->writeGroup('hu', 'app', <<<'PHP'
<?php

return [
    'zebra' => 'Zebra',
    'alma' => 'Alma',
    'nested' => [
        'inner' => 'Belső',
    ],
];

PHP);

        $this->langFiles()->write('hu', 'app', ['alma'], 'Körte');

        $this->assertSame($this->withUnixLineEndings(<<<'PHP'
<?php

return [
    'zebra' => 'Zebra',
    'alma' => 'Körte',
    'nested' => [
        'inner' => 'Belső',
    ],
];

PHP), $this->withUnixLineEndings(file_get_contents($path)));
    }

    public function test_integer_keys_stay_integers(): void
    {
        // List-shaped entries such as weekday names. Rebuilding them through a
        // dotted string turns the keys into numeric strings, and var_export()
        // would then emit '0' => instead of 0 =>.
        $path = $this->writeGroup('hu', 'event', <<<'PHP'
<?php

return [
    'weekdays_short' => [
        0 => 'H',
        1 => 'K',
    ],
];

PHP);

        $files = $this->langFiles();
        $files->write('hu', 'event', ['weekdays_short', 1], 'Ke');

        $this->assertStringContainsString('0 => ', file_get_contents($path));
        $this->assertStringNotContainsString("'0' => ", file_get_contents($path));
        $this->assertSame([0 => 'H', 1 => 'Ke'], $files->raw('hu', 'event')['weekdays_short']);
    }

    // =========================================================================
    // 3. The root JSON files
    // =========================================================================

    public function test_the_json_group_round_trips_and_keeps_escaped_slashes(): void
    {
        // The existing root JSON files carry escaped slashes, so
        // JSON_UNESCAPED_SLASHES is deliberately absent from the writer -
        // adding it would rewrite every one of them on the first save.
        $path = $this->langPath.'/hu.json';
        file_put_contents($path, json_encode([
            'City / Postal Code' => 'Város \\/ irányítószám',
            'Action' => 'Akció',
        ], JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT)."\n");

        $files = $this->langFiles();
        $files->write('hu', LangFiles::JSON_GROUP, ['Action'], 'Művelet');

        $contents = file_get_contents($path);

        $this->assertStringContainsString('\\/', $contents, 'Escaped slashes must survive.');
        $this->assertStringContainsString('Város', $contents, 'Accents stay unescaped.');
        $this->assertSame('Művelet', $files->raw('hu', LangFiles::JSON_GROUP)['Action']);
    }

    public function test_a_json_key_is_never_split_on_dots(): void
    {
        // JSON keys are whole sentences in this project, dots included.
        $files = $this->langFiles();
        $files->addKey('hu', LangFiles::JSON_GROUP, 'Are you sure? This cannot be undone.', 'Biztos?');

        $raw = $files->raw('hu', LangFiles::JSON_GROUP);

        $this->assertArrayHasKey('Are you sure? This cannot be undone.', $raw);
    }

    // =========================================================================
    // 3b. The file must never be left malformed
    // =========================================================================

    public function test_a_value_json_cannot_encode_is_refused_instead_of_emptying_the_file(): void
    {
        // json_encode() returns FALSE on malformed UTF-8. Writing that result
        // straight out would put an empty file where a root JSON language file
        // used to be - hundreds of keys gone, and the next save would then
        // "restore" a file containing one key.
        //
        // CONTROL: drop the json_encode check from renderJson() and this test
        // fails with the file reduced to a newline.
        $path = $this->langPath.'/hu.json';
        $original = json_encode(['Action' => 'Akció', 'Cancel' => 'Mégsem'], JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT)."\n";
        file_put_contents($path, $original);

        $files = $this->langFiles();

        try {
            $files->write('hu', LangFiles::JSON_GROUP, ['Action'], "Akci\xB1\x31o");
            $this->fail('A value that cannot be encoded must not reach the file.');
        } catch (RuntimeException $e) {
            // expected
        }

        $this->assertSame($original, file_get_contents($path), 'The file must be untouched.');
    }

    public function test_a_save_leaves_no_temporary_file_behind(): void
    {
        // The write goes through a temporary file and a rename, so a crash
        // cannot leave a half-written language file behind - a truncated
        // {locale}/{group}.php is a fatal on every request that loads it.
        $this->writeGroup('hu', 'app', "<?php\n\nreturn ['title' => 'Cím'];\n");

        $this->langFiles()->write('hu', 'app', ['title'], 'Fejléc');

        $left = array_values(array_diff(scandir($this->langPath.'/hu'), ['.', '..']));

        $this->assertSame(['app.php'], $left, 'Only the language file itself may remain.');
    }

    public function test_the_written_php_file_is_verified_before_it_replaces_the_original(): void
    {
        // Every write re-reads the generated file and compares it to the array
        // it was built from, so a file is only ever replaced by one that parses
        // and round-trips. This asserts the guarantee from the outside: a value
        // full of the characters most likely to break a generator survives.
        $nasty = "It's a \"quote\", a \\backslash\\, a \$dollar, {\$brace} and a\nnewline";

        $this->writeGroup('hu', 'app', "<?php\n\nreturn ['title' => 'Cím'];\n");

        $files = $this->langFiles();
        $files->write('hu', 'app', ['title'], $nasty);

        $this->assertSame($nasty, $files->raw('hu', 'app')['title']);
    }

    // =========================================================================
    // 4. Locales and groups
    // =========================================================================

    public function test_locales_normalizes_every_blob_shape(): void
    {
        // Three writers, three shapes. The bare string is CoreSettingsSeeder's,
        // and reaching $value['visible'] on it is a TypeError on PHP 8.
        $files = $this->langFiles([
            'hu' => ['name' => 'Magyar', 'visible' => true],
            'en' => ['name' => '', 'visible' => false],
            'de' => 'de',
        ]);

        $this->assertSame([
            'hu' => ['name' => 'Magyar', 'visible' => true],
            'en' => ['name' => 'en', 'visible' => false],
            'de' => ['name' => 'de', 'visible' => true],
        ], $files->locales());
    }

    public function test_groups_lists_the_php_basenames_plus_the_json_pseudo_group(): void
    {
        $this->writeGroup('hu', 'user', "<?php\n\nreturn [];\n");
        $this->writeGroup('hu', 'app', "<?php\n\nreturn [];\n");

        $this->assertSame(['app', 'user', 'json'], $this->langFiles()->groups('hu'));
    }

    public function test_the_json_group_is_offered_even_without_a_file(): void
    {
        $this->assertSame([LangFiles::JSON_GROUP], $this->langFiles()->groups('hu'));
    }

    // =========================================================================
    // 5. ensureLocale - the half of the split brain this class owns
    // =========================================================================

    public function test_ensure_locale_creates_a_missing_directory(): void
    {
        $this->langFiles()->ensureLocale('ro');

        $this->assertDirectoryExists($this->langPath.'/ro');
    }

    public function test_ensure_locale_leaves_an_existing_directory_and_its_files_alone(): void
    {
        // This project has locale directories that predate the registry, so
        // registering one later must not cost it its translations.
        $path = $this->writeGroup('ro', 'auth', "<?php\n\nreturn ['failed' => 'Eșuat'];\n");

        $files = $this->langFiles();
        $files->ensureLocale('ro');
        $files->ensureLocale('ro');

        $this->assertFileExists($path);
        $this->assertSame('Eșuat', $files->raw('ro', 'auth')['failed']);
    }

    // =========================================================================
    // 6. Adding keys, and the guards
    // =========================================================================

    public function test_add_key_splits_a_group_key_on_dots(): void
    {
        $files = $this->langFiles();
        $files->addKey('hu', 'app', 'menu.settings', 'Beállítások');

        $this->assertSame(['menu' => ['settings' => 'Beállítások']], $files->raw('hu', 'app'));
    }

    public function test_add_key_refuses_a_key_that_already_exists(): void
    {
        $this->writeGroup('hu', 'app', "<?php\n\nreturn ['title' => 'Cím'];\n");

        $this->expectException(InvalidArgumentException::class);

        $this->langFiles()->addKey('hu', 'app', 'title', 'Másik');
    }

    public function test_a_traversing_locale_name_is_rejected(): void
    {
        // Locale and group names arrive from Livewire state, i.e. from the
        // browser, and become filesystem paths.
        $this->expectException(InvalidArgumentException::class);

        $this->langFiles()->read('../../config', 'app');
    }

    public function test_a_traversing_group_name_is_rejected(): void
    {
        $this->expectException(InvalidArgumentException::class);

        $this->langFiles()->read('hu', '../../../composer');
    }
}

/**
 * A settings repository that answers from a literal array, so these tests need
 * no database at all.
 */
class FakeApplicationSettings extends ApplicationSettings
{
    private array $languages;

    public function __construct(array $languages)
    {
        $this->languages = $languages;
    }

    public function languages(): array
    {
        return $this->languages;
    }
}
