<?php

namespace Tests\Feature\Middleware;

use App\Models\StaticPage;
use App\Models\User;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Config;
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
        Config::set('settings_maintenance', 0);

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
        // FIGYELEM: a settings_maintenance config-kulcsot az
        // AppServiceProvider::boot() tölti a Settings táblából, KÉRÉS ELŐTT.
        // Egy teszten belüli Settings::updateOrCreate() ezért nem hatna az
        // aktuális kérésre - a karbantartási mód futás közben nem
        // kapcsolható be. Ez maga a TODO 31 tárgya.
        //
        // A middleware sora: redirect('login')->with(Auth::logout()).
        // Az Auth::logout() void, tehát a with() null kulcsot kap és egy
        // üres kulcsú flash bejegyzés keletkezik. Ma ártalmatlan, de a
        // with() szignatúrája a későbbi Laravel-verziókban tipizálódhat.
        Config::set('settings_maintenance', 1);

        $user = $this->createUser(['email' => 'maintenance-user@example.test', 'role' => 'activated']);

        $this->actingAs($user)
            ->get($this->homeUrl())
            ->assertRedirect('login');

        $this->assertGuest();
    }

    public function test_maintenance_mode_lets_the_main_admin_through(): void
    {
        Config::set('settings_maintenance', 1);

        $admin = $this->createUser(['email' => 'maintenance-admin@example.test', 'role' => 'mainAdmin']);

        $this->actingAs($admin)->get($this->homeUrl())->assertStatus(200);

        $this->assertAuthenticatedAs($admin);
    }

    public function test_maintenance_mode_does_not_affect_guests(): void
    {
        // A teljes ág Auth::check() mögött van (:59), tehát a karbantartási
        // mód a kijelentkezett látogatókat egyáltalán nem érinti - a
        // nyilvános oldalak elérhetők maradnak.
        Config::set('settings_maintenance', 1);

        $this->get($this->homeUrl())->assertStatus(200);
    }

    public function test_a_translator_is_logged_out_in_maintenance_mode_too(): void
    {
        // A kivétel KIZÁRÓLAG a mainAdmin (:60) - a translator, aki egyébként
        // az is-translator és is-groupservant gate-eken átmegy, itt nem
        // kivételezett.
        Config::set('settings_maintenance', 1);

        $translator = $this->createUser(['email' => 'maintenance-tr@example.test', 'role' => 'translator']);

        $this->actingAs($translator)
            ->get($this->homeUrl())
            ->assertRedirect('login');

        $this->assertGuest();
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
}
