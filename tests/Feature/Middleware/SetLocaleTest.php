<?php

namespace Tests\Feature\Middleware;

use App\Models\Settings;
use App\Models\StaticPage;
use App\Models\User;
use App\Support\Settings\ApplicationSettings;
use Illuminate\Database\QueryException;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Config;
use Illuminate\Support\Facades\Log;
use Tests\Feature\FeatureTestCase;

/**
 * TODO 09: a SetLocale middleware.
 *
 * A web csoport tagja (Kernel.php:45), tehát MINDEN webes kérésen lefut - és
 * egyetlen handle()-ben négy különálló mellékhatást végez:
 *
 *   1. nyelvválasztás a ?lang= paraméterből, session-be és a user sorába írva
 *   2. karbantartási módban kilépteti a nem-admin felhasználót
 *   3. az oldalmenüt Cache::rememberForever-rel olvassa
 *   4. View::share-rel megosztja a nézetekkel
 *
 * Mindez ma nulla lefedettséggel. A tesztek valódi HTTP-kérésen mennek, mert
 * így a tényleges bekötés is igazolódik.
 */
class SetLocaleTest extends FeatureTestCase
{
    private const HOME = 'home';

    protected function setUp(): void
    {
        parent::setUp();

        // A ?lang= ág az available_languages configból dolgozik, amit
        // egyébként az AppServiceProvider::boot() tölt a Settings táblából.
        // A 'de' szándékosan rejtett: csak mainAdmin és translator kapja meg.
        Config::set('available_languages', [
            'hu' => ['name' => 'Magyar', 'visible' => true],
            'en' => ['name' => 'English', 'visible' => true],
            'de' => ['name' => 'Deutsch', 'visible' => false],
        ]);
        Config::set('settings_default_language', 'hu');

        // Since TODO 31 the maintenance switch no longer has to be forced off:
        // 'maintenance' => false is among the ApplicationSettings defaults, so
        // it resolves even without a row.

        app()->setLocale('hu');
    }

    private function homeUrl(): string
    {
        return route('static_page', ['slug' => self::HOME]);
    }

    private function makeStaticPage(int $status, string $slug): StaticPage
    {
        $locale = config('app.locale', 'hu');

        return StaticPage::create([
            'status'   => $status,
            'slug'     => $slug,
            'position' => 'menu',
            'user_id'  => User::where('email', 'owner@example.test')->value('id'),
            $locale    => ['title' => 'Oldal '.$slug, 'content' => 'Tartalom'],
        ]);
    }

    private function sharedMenuSlugs(): array
    {
        return collect(view()->shared('sidemenu'))->pluck('slug')->sort()->values()->all();
    }

    // =========================================================================
    // 1. Nyelvválasztás
    // =========================================================================

    public function test_a_visible_language_is_applied_and_stored_in_the_session(): void
    {
        $this->get($this->homeUrl().'?lang=en')->assertStatus(200);

        $this->assertSame('en', app()->getLocale());
        $this->assertSame('en', session('language'));
    }

    public function test_a_visible_language_is_also_persisted_on_the_user(): void
    {
        // A felhasználó sorába is beíródik ($user->save(), :36), tehát a
        // választás túléli a session lejártát. Mellékhatásként ez elindítja
        // a UserObserver name_index-újraszámoló jobját is - minden egyes
        // nyelvváltásnál.
        $user = $this->createUser(['email' => 'locale-user@example.test', 'language' => 'hu']);

        $this->actingAs($user)->get($this->homeUrl().'?lang=en')->assertStatus(200);

        $this->assertSame('en', $user->fresh()->language);
    }

    public function test_a_hidden_language_is_ignored_for_an_ordinary_user(): void
    {
        $user = $this->createUser([
            'email'    => 'locale-plain@example.test',
            'language' => 'hu',
            'role'     => 'activated',
        ]);

        $this->actingAs($user)->get($this->homeUrl().'?lang=de')->assertStatus(200);

        $this->assertSame('hu', app()->getLocale(), 'A rejtett nyelv nem alkalmazódik.');
        $this->assertNull(session('language'), 'És a session sem változik.');
        $this->assertSame('hu', $user->fresh()->language);
    }

    public function test_a_hidden_language_is_ignored_for_a_guest(): void
    {
        // Vendégre a rejtett ág egyáltalán nem fut le ($user !== null őr,
        // :40), tehát csendben elmarad.
        $this->get($this->homeUrl().'?lang=de')->assertStatus(200);

        $this->assertSame('hu', app()->getLocale());
        $this->assertNull(session('language'));
    }

    /**
     * @dataProvider privilegedRoleProvider
     */
    public function test_a_hidden_language_is_applied_for_privileged_roles(string $role): void
    {
        $user = $this->createUser([
            'email'    => 'locale-'.strtolower($role).'@example.test',
            'language' => 'hu',
            'role'     => $role,
        ]);

        $this->actingAs($user)->get($this->homeUrl().'?lang=de')->assertStatus(200);

        $this->assertSame('de', app()->getLocale());
        $this->assertSame('de', session('language'));
        $this->assertSame('de', $user->fresh()->language);
    }

    public function privilegedRoleProvider(): array
    {
        return [
            'mainAdmin'  => ['mainAdmin'],
            'translator' => ['translator'],
        ];
    }

    public function test_an_unknown_language_code_is_ignored(): void
    {
        $this->get($this->homeUrl().'?lang=xx')->assertStatus(200);

        $this->assertSame('hu', app()->getLocale());
        $this->assertNull(session('language'));
    }

    public function test_the_session_language_is_used_when_no_parameter_is_given(): void
    {
        $this->withSession(['language' => 'en'])->get($this->homeUrl())->assertStatus(200);

        $this->assertSame('en', app()->getLocale());
    }

    public function test_the_session_language_is_not_validated_against_the_visible_list(): void
    {
        // A session-ág (:51-53) NEM ellenőrzi sem a láthatóságot, sem azt,
        // hogy a nyelv egyáltalán szerepel-e az available_languages-ben -
        // a szűrés csak a ?lang= ágon van. Egy korábban beállított rejtett
        // nyelv tehát a jogosultság elvesztése után is érvényben marad.
        $this->withSession(['language' => 'de'])->get($this->homeUrl())->assertStatus(200);

        $this->assertSame('de', app()->getLocale());
    }

    public function test_the_default_language_is_used_when_nothing_else_applies(): void
    {
        Config::set('settings_default_language', 'en');

        $this->get($this->homeUrl())->assertStatus(200);

        $this->assertSame('en', app()->getLocale());
    }

    // =========================================================================
    // 2. Karbantartási mód
    // =========================================================================

    public function test_maintenance_mode_logs_out_an_ordinary_user_and_redirects_to_login(): void
    {
        // REVERSED by TODO 31.
        //
        // These four cases used to work with Config::set('settings_maintenance',
        // 1), and not out of whim: the key was filled by
        // AppServiceProvider::boot() from the Settings table BEFORE the request,
        // so a Settings::updateOrCreate() inside a test had no effect on the
        // current request - maintenance mode could not be switched on at
        // runtime.
        //
        // Since then the middleware asks ApplicationSettings, which is lazy and
        // cached, and SettingsObserver invalidates the write. The four cases
        // therefore write a REAL settings row now - and that is precisely the
        // acceptance criterion: were the workaround to come back, these would
        // fail.
        //
        // The middleware's line: redirect('login')->with(Auth::logout()).
        // Auth::logout() returns void, so with() receives a null key and an
        // empty-keyed flash entry is created. Harmless today, but the signature
        // of with() may become typed in a later Laravel version.
        Settings::updateOrCreate(['name' => 'maintenance'], ['value' => '1']);

        $user = $this->createUser(['email' => 'maintenance-user@example.test', 'role' => 'activated']);

        $this->actingAs($user)
            ->get($this->homeUrl())
            ->assertRedirect('login');

        $this->assertGuest();
    }

    public function test_maintenance_mode_lets_the_main_admin_through(): void
    {
        Settings::updateOrCreate(['name' => 'maintenance'], ['value' => '1']);

        $admin = $this->createUser(['email' => 'maintenance-admin@example.test', 'role' => 'mainAdmin']);

        $this->actingAs($admin)->get($this->homeUrl())->assertStatus(200);

        $this->assertAuthenticatedAs($admin);
    }

    public function test_maintenance_mode_does_not_affect_guests(): void
    {
        // A teljes ág Auth::check() mögött van (:59), tehát a karbantartási
        // mód a kijelentkezett látogatókat egyáltalán nem érinti - a
        // nyilvános oldalak elérhetők maradnak.
        Settings::updateOrCreate(['name' => 'maintenance'], ['value' => '1']);

        $this->get($this->homeUrl())->assertStatus(200);
    }

    public function test_a_translator_is_logged_out_in_maintenance_mode_too(): void
    {
        // A kivétel KIZÁRÓLAG a mainAdmin (:60) - a translator, aki egyébként
        // az is-translator és is-groupservant gate-eken átmegy, itt nem
        // kivételezett.
        Settings::updateOrCreate(['name' => 'maintenance'], ['value' => '1']);

        $translator = $this->createUser(['email' => 'maintenance-tr@example.test', 'role' => 'translator']);

        $this->actingAs($translator)
            ->get($this->homeUrl())
            ->assertRedirect('login');

        $this->assertGuest();
    }

    public function test_maintenance_mode_can_be_switched_on_and_off_between_requests(): void
    {
        // The acceptance criterion of TODO 31, stated in one case. The switch
        // takes effect immediately in BOTH directions, because SettingsObserver
        // binds the cache flush to the source of the setting - no application
        // reboot and no manual Cache::forget are needed.
        $user = $this->createUser(['email' => 'maintenance-toggle@example.test', 'role' => 'activated']);

        $this->actingAs($user)->get($this->homeUrl())->assertStatus(200);

        Settings::updateOrCreate(['name' => 'maintenance'], ['value' => '1']);
        $this->actingAs($user)->get($this->homeUrl())->assertRedirect('login');

        Settings::updateOrCreate(['name' => 'maintenance'], ['value' => '0']);
        $this->actingAs($user)->get($this->homeUrl())->assertStatus(200);
    }

    // =========================================================================
    // 3. Az oldalmenü megosztása
    // =========================================================================

    public function test_a_guest_sees_only_the_publicly_visible_menu_entries(): void
    {
        // A vendég-lekérdezés szűrője: status IN (1,2). A FeatureTestCase
        // már létrehozott egy 'home' oldalt status = 1 értékkel.
        $this->makeStaticPage(0, 'piszkozat');
        $this->makeStaticPage(2, 'csak-vendegnek');
        $this->makeStaticPage(3, 'csak-belepve');

        $this->get($this->homeUrl())->assertStatus(200);

        $this->assertSame(['csak-vendegnek', 'home'], $this->sharedMenuSlugs());
    }

    public function test_an_authenticated_user_sees_a_different_menu(): void
    {
        // A bejelentkezett lekérdezés szűrője: status IN (0,1,3). A kettes
        // státusz tehát KIZÁRÓLAG vendégnek szól, a nulla és a hármas
        // kizárólag belépve - a két halmaz metszete csak az egyes.
        $this->makeStaticPage(0, 'piszkozat');
        $this->makeStaticPage(2, 'csak-vendegnek');
        $this->makeStaticPage(3, 'csak-belepve');

        $user = $this->createUser(['email' => 'menu-user@example.test']);

        $this->actingAs($user)->get($this->homeUrl())->assertStatus(200);

        $this->assertSame(['csak-belepve', 'home', 'piszkozat'], $this->sharedMenuSlugs());
    }

    public function test_the_menu_is_cached_under_separate_keys_for_guests_and_users(): void
    {
        $this->get($this->homeUrl())->assertStatus(200);
        $this->assertTrue(Cache::has('sidemenu_guest'));
        $this->assertFalse(Cache::has('sidemenu_auth'));

        $user = $this->createUser(['email' => 'menu-cache@example.test']);
        $this->actingAs($user)->get($this->homeUrl())->assertStatus(200);

        $this->assertTrue(Cache::has('sidemenu_auth'));
    }

    public function test_a_page_created_outside_the_editor_reaches_the_cached_menu(): void
    {
        // MEGFORDÍTVA a v1-patch B4 javításával.
        //
        // A Cache::rememberForever kulcsai (sidemenu_guest, sidemenu_auth)
        // MINDÖSSZE két helyen ürültek: Admin\StaticPageEdit és a telepítő
        // AccountController. Bármely más úton - seeder, konzol, közvetlen
        // modellírás, adatimport - létrehozott vagy módosított statikus oldal
        // tehát SOHA nem jelent meg a menüben, amíg valaki kézzel nem
        // szerkesztett egy oldalt a felületen. Lejárat nincs: a
        // rememberForever örökre szól.
        //
        // Az ürítés most a StaticPageObserverben van, tehát a menü FORRÁSÁHOZ
        // kötve, nem a hívási helyekhez.
        $this->get($this->homeUrl())->assertStatus(200);
        $this->assertSame(['home'], $this->sharedMenuSlugs());

        $this->makeStaticPage(1, 'uj-oldal');

        $this->get($this->homeUrl())->assertStatus(200);

        $this->assertSame(
            ['home', 'uj-oldal'],
            $this->sharedMenuSlugs(),
            'Az új oldal a szerkesztő megkerülésével is megjelenik.'
        );
    }

    public function test_a_deleted_page_leaves_the_cached_menu(): void
    {
        // A B4 másik iránya: a törlésnek is ürítenie kell, különben egy már
        // nem létező oldal marad a menüben - lejárat nélkül, örökre.
        $page = $this->makeStaticPage(1, 'mulando');

        $this->get($this->homeUrl())->assertStatus(200);
        $this->assertSame(['home', 'mulando'], $this->sharedMenuSlugs());

        $page->delete();

        $this->get($this->homeUrl())->assertStatus(200);
        $this->assertSame(['home'], $this->sharedMenuSlugs());
    }

    public function test_renaming_a_page_title_alone_also_clears_the_menu(): void
    {
        // A menü a CÍMEKET mutatja, azok pedig a static_page_translations
        // táblában élnek - egy puszta címátírás a StaticPage sorát nem is
        // érinti. Ezért figyeli az observer a fordítást is.
        $page = $this->makeStaticPage(1, 'atnevezendo');

        $this->get($this->homeUrl())->assertStatus(200);
        $this->assertTrue(Cache::has('sidemenu_guest'));

        $translation = $page->translations()->firstOrFail();
        $translation->title = 'Új cím';
        $translation->save();

        $this->assertFalse(
            Cache::has('sidemenu_guest'),
            'A fordítás mentése is üríti a gyorsítótárat.'
        );
    }

    // =========================================================================
    // 4. The menu's failure branch (TODO 31)
    // =========================================================================

    public function test_a_failing_menu_lookup_is_logged_and_leaves_an_empty_menu(): void
    {
        // The block used to be an EMPTY catch (\Throwable), and it caused two
        // failures at once: the original exception vanished without a trace, and
        // View::share never ran - while four Blade files @foreach over the
        // $sidemenu variable. So instead of the real error we saw a second one.
        //
        // The exception is injected at the cache boundary, because that is where
        // the query lives: StaticPage::whereIn() runs inside the rememberForever
        // closure. The settings key branch MUST be let through, otherwise this
        // would not measure one single thing - SetLocale's maintenance check
        // goes through the very same facade.
        Log::spy();
        Cache::spy();
        Cache::shouldReceive('rememberForever')
            ->with(ApplicationSettings::CACHE_KEY, \Mockery::any())
            ->andReturnUsing(fn ($key, $callback) => $callback());
        Cache::shouldReceive('rememberForever')
            ->with('sidemenu_guest', \Mockery::any())
            ->andThrow(new QueryException('select * from `static_pages`', [], new \Exception('Table not found')));

        $this->get($this->homeUrl())->assertStatus(200);

        $this->assertSame([], $this->sharedMenuSlugs(), 'The menu is empty but present - the views do not fail.');
        Log::shouldHaveReceived('error')->once();
    }

    public function test_a_successful_menu_lookup_logs_nothing(): void
    {
        // Control experiment: the log assertion above only measures anything if
        // the successful path is silent.
        Log::spy();

        $this->get($this->homeUrl())->assertStatus(200);

        Log::shouldNotHaveReceived('error');
    }
}
