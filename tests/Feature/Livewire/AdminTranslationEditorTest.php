<?php

namespace Tests\Feature\Livewire;

use App\Http\Livewire\Admin\Translation;
use App\Models\Settings;
use App\Models\User;
use App\Support\Settings\ApplicationSettings;
use App\Support\Translation\LangFiles;
use Illuminate\Support\Facades\File;
use Livewire\Livewire;
use Tests\Feature\FeatureTestCase;

/**
 * TODO 33.3: the in-house translation editor that replaced the Vue front-end of
 * joedixon/laravel-translation.
 *
 * Every test binds LangFiles to a temporary language directory. Without that
 * the component would edit the application's own language tree while the suite
 * runs - the editor writes real files, and under APP_ENV=testing App::langPath()
 * is the real path.
 */
class AdminTranslationEditorTest extends FeatureTestCase
{
    private User $translator;

    private string $langPath;

    protected function setUp(): void
    {
        parent::setUp();

        $this->translator = $this->createUser([
            'email' => 'translator-editor@example.test',
            'role' => 'translator',
        ]);

        $this->langPath = storage_path('framework/testing/lang-editor');

        File::deleteDirectory($this->langPath);
        File::makeDirectory($this->langPath.'/hu', 0755, true);
        File::makeDirectory($this->langPath.'/en', 0755, true);

        $this->registerLocales([
            'hu' => ['name' => 'Magyar', 'visible' => true],
            'en' => ['name' => 'English', 'visible' => true],
        ]);
    }

    protected function tearDown(): void
    {
        File::deleteDirectory($this->langPath);

        parent::tearDown();
    }

    private function registerLocales(array $locales): void
    {
        Settings::updateOrCreate(['name' => 'languages'], ['value' => json_encode($locales)]);

        // The repository memoizes its rows for the request, and the container
        // holds it as a singleton, so a row written mid-test needs the instance
        // dropped before the next read.
        $this->app->forgetInstance(ApplicationSettings::class);

        $this->app->instance(
            LangFiles::class,
            new LangFiles($this->app->make(ApplicationSettings::class), $this->langPath)
        );
    }

    private function writeGroup(string $locale, string $group, array $data): void
    {
        $lines = '';
        foreach ($data as $key => $value) {
            $lines .= '    '.var_export($key, true).' => '.var_export($value, true).",\n";
        }

        file_put_contents(
            $this->langPath.'/'.$locale.'/'.$group.'.php',
            "<?php\n\nreturn [\n".$lines."];\n"
        );
    }

    private function editor()
    {
        return Livewire::actingAs($this->translator)->test(Translation::class);
    }

    /**
     * The keys currently on the page.
     *
     * Asserted through the component state rather than the rendered HTML on
     * purpose: words like "title" and "save" appear in the markup itself
     * (card-title, wire:click="save"), so assertSee/assertDontSee cannot tell a
     * translation key from the chrome around it.
     *
     * @return array<int, string>
     */
    private function keysOf($component): array
    {
        return array_column($component->get('rows'), 'key');
    }

    // =========================================================================
    // 1. What the screen shows
    // =========================================================================

    public function test_it_renders_for_a_translator(): void
    {
        $this->writeGroup('hu', 'app', ['title' => 'Cím']);

        $this->editor()->assertOk();
    }

    public function test_only_registered_locales_are_offered(): void
    {
        // A directory that exists on disk but is not in settings.languages does
        // not appear. This is the deliberate consequence of reading the
        // registry rather than the filesystem - the settings screen is where a
        // locale becomes visible, and it now creates the directory too.
        File::makeDirectory($this->langPath.'/ro', 0755, true);
        $this->writeGroup('ro', 'auth', ['failed' => 'Eșuat']);

        $this->editor()
            ->assertSee('Magyar')
            ->assertSee('English')
            ->assertDontSee('Eșuat');
    }

    public function test_the_source_column_and_the_missing_keys_are_listed_together(): void
    {
        // The list is the UNION of source and target, which is what puts an
        // empty box in front of a translator instead of hiding the key.
        $this->writeGroup('hu', 'app', ['title' => 'Cím', 'save' => 'Mentés']);
        $this->writeGroup('en', 'app', ['title' => 'Title']);

        $component = $this->editor()
            ->set('sourceLocale', 'hu')
            ->set('targetLocale', 'en')
            ->set('group', 'app');

        $rows = $component->get('rows');

        $this->assertSame(['title', 'save'], array_column($rows, 'key'), 'Source order, both keys present.');
        $this->assertSame('Cím', $rows[0]['source']);
        $this->assertSame('Title', $rows[0]['value']);
        $this->assertSame('Mentés', $rows[1]['source']);
        $this->assertSame('', $rows[1]['value'], 'The key the target is missing gets an empty box.');
    }

    public function test_the_missing_counter_reports_the_untranslated_keys(): void
    {
        $this->writeGroup('hu', 'app', ['title' => 'Cím', 'save' => 'Mentés', 'cancel' => 'Mégsem']);
        $this->writeGroup('en', 'app', ['title' => 'Title']);

        $component = $this->editor()
            ->set('sourceLocale', 'hu')
            ->set('targetLocale', 'en')
            ->set('group', 'app');

        $component->assertSee(__('translation.missing_count', ['count' => 2]));
    }

    public function test_the_search_filters_the_rows(): void
    {
        $this->writeGroup('hu', 'app', ['title' => 'Cím', 'save' => 'Mentés']);

        $component = $this->editor()
            ->set('group', 'app')
            ->set('search', 'save');

        $this->assertSame(['save'], $this->keysOf($component));
    }

    public function test_the_search_also_matches_the_translated_text(): void
    {
        $this->writeGroup('hu', 'app', ['title' => 'Cím', 'save' => 'Mentés']);

        $component = $this->editor()
            ->set('group', 'app')
            ->set('search', 'Mentés');

        $this->assertSame(['save'], $this->keysOf($component));
    }

    public function test_only_missing_hides_the_translated_rows(): void
    {
        $this->writeGroup('hu', 'app', ['title' => 'Cím', 'save' => 'Mentés']);
        $this->writeGroup('en', 'app', ['title' => 'Title']);

        $component = $this->editor()
            ->set('sourceLocale', 'hu')
            ->set('targetLocale', 'en')
            ->set('group', 'app')
            ->set('onlyMissing', true);

        $this->assertSame(['save'], $this->keysOf($component));
    }

    // =========================================================================
    // 2. Writing
    // =========================================================================

    public function test_a_single_row_is_saved_to_the_target_locale_only(): void
    {
        $this->writeGroup('hu', 'app', ['title' => 'Cím']);
        $this->writeGroup('en', 'app', ['title' => 'Title']);

        $this->editor()
            ->set('sourceLocale', 'hu')
            ->set('targetLocale', 'en')
            ->set('group', 'app')
            ->set('rows.0.value', 'Heading')
            ->call('save', 0)
            ->assertDispatchedBrowserEvent('success');

        $files = $this->app->make(LangFiles::class);

        $this->assertSame('Heading', $files->raw('en', 'app')['title']);
        $this->assertSame('Cím', $files->raw('hu', 'app')['title'], 'The source locale must not be touched.');
    }

    public function test_a_missing_key_can_be_filled_in_from_the_source_row(): void
    {
        // The path of such a row exists only in the SOURCE file, which is why
        // writeRow() accepts a path known to either side.
        $this->writeGroup('hu', 'app', ['title' => 'Cím', 'save' => 'Mentés']);
        $this->writeGroup('en', 'app', ['title' => 'Title']);

        $component = $this->editor()
            ->set('sourceLocale', 'hu')
            ->set('targetLocale', 'en')
            ->set('group', 'app')
            ->set('onlyMissing', true);

        $component->set('rows.0.value', 'Save')->call('save', 0);

        $this->assertSame('Save', $this->app->make(LangFiles::class)->raw('en', 'app')['save']);
    }

    public function test_save_all_writes_every_row_on_the_page(): void
    {
        $this->writeGroup('hu', 'app', ['title' => 'Cím', 'save' => 'Mentés']);
        $this->writeGroup('en', 'app', ['title' => 'Title', 'save' => 'Save']);

        $this->editor()
            ->set('sourceLocale', 'hu')
            ->set('targetLocale', 'en')
            ->set('group', 'app')
            ->set('rows.0.value', 'Heading')
            ->set('rows.1.value', 'Store')
            ->call('saveAll')
            ->assertDispatchedBrowserEvent('success');

        $raw = $this->app->make(LangFiles::class)->raw('en', 'app');

        $this->assertSame('Heading', $raw['title']);
        $this->assertSame('Store', $raw['save']);
    }

    public function test_a_tampered_path_is_refused(): void
    {
        // The row state travels to the browser and back, so the path is checked
        // against the files before anything is written.
        $this->writeGroup('hu', 'app', ['title' => 'Cím']);

        $this->editor()
            ->set('group', 'app')
            ->set('rows.0.path', ['injected'])
            ->set('rows.0.value', 'Nope')
            ->call('save', 0)
            ->assertDispatchedBrowserEvent('error');

        $this->assertArrayNotHasKey('injected', $this->app->make(LangFiles::class)->raw('hu', 'app'));
    }

    // =========================================================================
    // 3. Adding keys
    // =========================================================================

    public function test_a_new_key_is_added_to_the_target_locale(): void
    {
        $this->writeGroup('hu', 'app', ['title' => 'Cím']);

        $this->editor()
            ->set('group', 'app')
            ->set('state.newKey', 'menu.settings')
            ->set('state.newValue', 'Beállítások')
            ->call('addKey')
            ->assertDispatchedBrowserEvent('success');

        $raw = $this->app->make(LangFiles::class)->raw('hu', 'app');

        $this->assertSame(['settings' => 'Beállítások'], $raw['menu']);
    }

    public function test_an_existing_key_is_rejected(): void
    {
        $this->writeGroup('hu', 'app', ['title' => 'Cím']);

        $this->editor()
            ->set('group', 'app')
            ->set('state.newKey', 'title')
            ->set('state.newValue', 'Másik')
            ->call('addKey')
            ->assertDispatchedBrowserEvent('error');

        $this->assertSame('Cím', $this->app->make(LangFiles::class)->raw('hu', 'app')['title']);
    }

    public function test_an_empty_key_fails_validation(): void
    {
        $this->editor()
            ->set('state.newKey', '')
            ->call('addKey')
            ->assertHasErrors(['newKey']);
    }

    // =========================================================================
    // 4. Authorization
    //
    // Livewire\Testing bypasses HTTP entirely, so none of the tests above prove
    // anything about who may reach the component. That matters more here than
    // usual: a Livewire ACTION does not travel over the route it was rendered
    // from - it POSTs to livewire/message, whose own middleware group is only
    // `web`. What re-applies the original route's guards is
    // Livewire::getPersistentMiddleware(), a framework-internal list.
    //
    // These two tests therefore go over real HTTP.
    // =========================================================================

    /**
     * The initial payload Livewire embedded for a component on a rendered page.
     * Taking it from a real render means the checksum is genuine, so the tests
     * below measure authorization and nothing else.
     */
    private function initialDataFor(string $html, string $component): array
    {
        preg_match_all('/wire:initial-data="([^"]*)"/', $html, $matches);

        foreach ($matches[1] as $raw) {
            $decoded = json_decode(html_entity_decode($raw, ENT_QUOTES), true);

            if (($decoded['fingerprint']['name'] ?? null) === $component) {
                return $decoded;
            }
        }

        $this->fail("No Livewire payload found for {$component}.");
    }

    private function renderedEditorPayload(): array
    {
        $html = $this->actingAs($this->translator)
            ->withSession($this->passwordConfirmedSession())
            ->get(route('admin.translate'))
            ->assertOk()
            ->getContent();

        return $this->initialDataFor($html, 'admin.translation');
    }

    /**
     * The serverMemo is covered by a checksum, so a value cannot be injected
     * into it - it travels as a syncInput update, exactly as wire:model.defer
     * sends it from the browser.
     */
    private function postUpdates(array $payload, array $updates)
    {
        return $this->withHeaders(['X-Livewire' => 'true'])->postJson(
            '/livewire/message/'.$payload['fingerprint']['name'],
            [
                'fingerprint' => $payload['fingerprint'],
                'serverMemo' => $payload['serverMemo'],
                'updates' => $updates,
            ]
        );
    }

    private function postAction(array $payload, string $method, array $params = [])
    {
        return $this->postUpdates($payload, [[
            'type' => 'callMethod',
            'payload' => ['id' => 'abcd', 'method' => $method, 'params' => $params],
        ]]);
    }

    public function test_a_translator_can_invoke_a_save_over_real_http(): void
    {
        // The positive half, and it has to come first: a negative result proves
        // nothing if the endpoint refuses everyone. This is the path the browser
        // takes when the save button is pressed.
        $this->writeGroup('hu', 'app', ['title' => 'Cím']);

        $payload = $this->renderedEditorPayload();

        $this->postUpdates($payload, [
            [
                'type' => 'syncInput',
                'payload' => ['id' => 'ab01', 'name' => 'rows.0.value', 'value' => 'Fejléc'],
            ],
            [
                'type' => 'callMethod',
                'payload' => ['id' => 'ab02', 'method' => 'save', 'params' => [0]],
            ],
        ])->assertOk();

        $this->assertSame('Fejléc', $this->app->make(LangFiles::class)->raw('hu', 'app')['title']);
    }

    public function test_a_user_without_the_translator_role_cannot_invoke_a_save(): void
    {
        // The payload is valid and freshly minted by a real translator's page
        // load - only the caller is wrong. If the gate were not re-applied on
        // the livewire/message endpoint, any authenticated user could rewrite
        // the application's language files.
        $this->writeGroup('hu', 'app', ['title' => 'Cím']);

        $payload = $this->renderedEditorPayload();

        $intruder = $this->createUser([
            'email' => 'not-a-translator@example.test',
            'role' => 'registered',
        ]);

        // A fresh session, because the intruder is a different person on their
        // own browser - not a user swapped inside somebody else's session.
        // Keeping the translator's session answers 401 rather than 403, because
        // `AuthenticateSession` sits in the `web` group and notices the swapped
        // password hash. That is a genuine second layer, but it is not the one
        // this test is about.
        $this->flushSession();
        $this->actingAs($intruder);

        $this->postAction($payload, 'save', [0])->assertForbidden();
        $this->assertSame(
            'Cím',
            $this->app->make(LangFiles::class)->raw('hu', 'app')['title'],
            'The language file must be untouched.'
        );
    }

    public function test_a_guest_cannot_invoke_a_save(): void
    {
        $this->writeGroup('hu', 'app', ['title' => 'Cím']);

        $payload = $this->renderedEditorPayload();

        // app('auth')->forgetGuards() is what actingAs leaves behind; a fresh
        // request without a session is the honest guest case.
        $this->app['auth']->forgetGuards();
        $this->flushSession();

        $response = $this->postAction($payload, 'save', [0]);

        $this->assertContains(
            $response->getStatusCode(),
            [401, 403, 419],
            'A guest must not be able to call the action.'
        );

        $this->assertSame('Cím', $this->app->make(LangFiles::class)->raw('hu', 'app')['title']);
    }

    public function test_the_authorization_gate_is_on_livewires_persistent_middleware_list(): void
    {
        // The mechanism the two tests above depend on, asserted directly
        // because it is exactly the kind of framework internal an upgrade
        // moves: Livewire 3 reworks persistent middleware entirely. If this
        // ever stops holding, the editor needs its own authorize() call.
        $this->assertContains(
            \Illuminate\Auth\Middleware\Authorize::class,
            \Livewire\Livewire::getPersistentMiddleware(),
            'Without this, can:is-translator would not be re-applied to component actions.'
        );

        $middleware = app('router')->getRoutes()->getByName('admin.translate')->gatherMiddleware();

        $this->assertContains('can:is-translator', $middleware);
    }

    // =========================================================================
    // 5. The root JSON group
    // =========================================================================

    public function test_the_root_json_group_is_editable(): void
    {
        file_put_contents(
            $this->langPath.'/hu.json',
            json_encode(['Action' => 'Akció'], JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT)."\n"
        );

        $this->editor()
            ->set('group', LangFiles::JSON_GROUP)
            ->set('rows.0.value', 'Művelet')
            ->call('save', 0)
            ->assertDispatchedBrowserEvent('success');

        $this->assertSame(
            'Művelet',
            $this->app->make(LangFiles::class)->raw('hu', LangFiles::JSON_GROUP)['Action']
        );
    }

    public function test_a_json_key_with_a_dot_stays_one_key(): void
    {
        // Roughly one key in five of the real root JSON files contains a dot,
        // because the keys are English sentences. Splitting them would shred
        // the file - see LangFilesTest for the control.
        $this->editor()
            ->set('group', LangFiles::JSON_GROUP)
            ->set('state.newKey', 'This cannot be undone.')
            ->set('state.newValue', 'Ez nem vonható vissza.')
            ->call('addKey')
            ->assertDispatchedBrowserEvent('success');

        $raw = $this->app->make(LangFiles::class)->raw('hu', LangFiles::JSON_GROUP);

        $this->assertArrayHasKey('This cannot be undone.', $raw);
    }
}
