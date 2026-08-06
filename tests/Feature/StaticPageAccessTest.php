<?php

namespace Tests\Feature;

use App\Models\StaticPage;
use App\Models\User;

/**
 * TODO 11.2: a StaticPageController::render() státuszmátrixa.
 *
 * A controller négy státuszt ismer, és mindegyikhez más láthatóságot rendel -
 * de a route (routes/web.php:62) teljesen nyilvános, tehát mind a négy ág
 * elérhető vendégként is. Fedettség eddig egyetlen ágra volt: a seedelt
 * 'home' oldalra a saját státuszával.
 *
 * A piszkozat (status 0) ága olvasta az Auth::user()->can()-t null-ellenőrzés
 * nélkül, vagyis vendégként fatalt dobott 403 helyett. Ugyanaz a hibacsalád,
 * mint a TODO 10 getRole()-ja és a TODO 11.1 detachParentGroup()-ja.
 */
class StaticPageAccessTest extends FeatureTestCase
{
    private User $owner;

    protected function setUp(): void
    {
        parent::setUp();

        // A FeatureTestCase már létrehozta a 'home' oldalt status 1-gyel,
        // ennek a tulajdonosa a mainAdmin.
        $this->owner = User::where('email', 'owner@example.test')->firstOrFail();
    }

    private function makePage(int $status, string $slug = 'szabalyzat'): StaticPage
    {
        $locale = config('app.locale', 'hu');

        return StaticPage::create([
            'status' => $status,
            'slug' => $slug,
            'position' => 'hidden',
            'user_id' => $this->owner->id,
            $locale => ['title' => 'Oldal', 'content' => 'Tartalom'],
        ]);
    }

    private function pageUrl(string $slug = 'szabalyzat'): string
    {
        return route('static_page', ['slug' => $slug]);
    }

    private function activatedUser(): User
    {
        return $this->createUser(['email' => 'sp-user@example.test', 'role' => 'activated']);
    }

    // =========================================================================
    // 1. A piszkozat ága - ezért készült ez a fájl
    // =========================================================================

    public function test_a_draft_page_returns_403_for_a_guest_instead_of_a_fatal_error(): void
    {
        // TODO 11.2: a :15 sor Auth::user()->can('is-admin')-t olvasott
        // null-ellenőrzés nélkül. Vendégként az Auth::user() null, tehát ez
        // "Call to a member function can() on null" fatal volt - 500 ott, ahol
        // 403 jár. A route-on (web.php:62) SEMMILYEN auth middleware nincs.
        $this->makePage(0);

        $this->get($this->pageUrl())->assertForbidden();
    }

    public function test_a_draft_home_page_does_not_break_the_site_root(): void
    {
        // A legélesebb változat: a '/' route (web.php:57-59) guest
        // middleware-rel fut, tehát ott az Auth::user() GARANTÁLTAN null. Ha a
        // home oldal piszkozatra kerül, a site gyökere szállt el minden
        // látogatónak. A 'home' slug az else ágon nem 403-at ad, hanem a
        // home-404 nézetet.
        StaticPage::where('slug', 'home')->firstOrFail()->update(['status' => 0]);

        $this->get('/')
            ->assertStatus(200)
            ->assertViewIs('home-404');
    }

    public function test_a_draft_page_is_visible_to_the_main_admin(): void
    {
        $page = $this->makePage(0);

        $this->actingAs($this->owner)
            ->get($this->pageUrl())
            ->assertStatus(200)
            ->assertViewIs('layouts.staticpage')
            ->assertViewHas('page', fn ($viewPage) => $viewPage->id === $page->id);
    }

    public function test_a_draft_page_is_forbidden_for_an_authenticated_non_admin(): void
    {
        // A nem-admin nem a can() ágon bukik el, hanem végigesik az
        // elseif-láncon az else-ig: a 0 egyik későbbi ágnak sem felel meg.
        $this->makePage(0);

        $this->actingAs($this->activatedUser())
            ->get($this->pageUrl())
            ->assertForbidden();
    }

    // =========================================================================
    // 2. A többi státusz - a mátrix eddig sehol nem volt rögzítve
    // =========================================================================

    public function test_a_public_page_is_open_to_everyone(): void
    {
        $this->makePage(1);

        // A vendég a 'main' layoutot kapja, a bejelentkezett a belsőt.
        $this->get($this->pageUrl())
            ->assertStatus(200)
            ->assertViewIs('main');

        $this->actingAs($this->activatedUser())
            ->get($this->pageUrl())
            ->assertStatus(200)
            ->assertViewIs('layouts.staticpage');
    }

    public function test_a_guest_only_page_is_hidden_from_logged_in_users(): void
    {
        // Status 2: kizárólag a kijelentkezett látogatóé. Ez az egyetlen
        // státusz, ahol a bejelentkezés ELVESZI a hozzáférést.
        $this->makePage(2);

        $this->get($this->pageUrl())
            ->assertStatus(200)
            ->assertViewIs('main');

        $this->actingAs($this->activatedUser())
            ->get($this->pageUrl())
            ->assertForbidden();
    }

    public function test_a_login_only_page_is_hidden_from_guests(): void
    {
        $this->makePage(3);

        $this->get($this->pageUrl())->assertForbidden();

        $this->actingAs($this->activatedUser())
            ->get($this->pageUrl())
            ->assertStatus(200)
            ->assertViewIs('layouts.staticpage');
    }

    // =========================================================================
    // 3. A hiányzó oldal - a 'home' slug itt is kivétel
    // =========================================================================

    public function test_a_missing_page_is_a_404(): void
    {
        $this->get($this->pageUrl('nincs-ilyen'))->assertNotFound();
    }

    public function test_a_missing_home_page_renders_the_home_404_view(): void
    {
        StaticPage::where('slug', 'home')->firstOrFail()->forceDelete();

        $this->get($this->pageUrl('home'))
            ->assertStatus(200)
            ->assertViewIs('home-404');
    }
}
