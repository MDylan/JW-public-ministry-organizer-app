<?php

namespace Tests\Feature;

use App\Models\StaticPage;
use App\Models\User;

/**
 * TODO 11.2: the status matrix of StaticPageController::render().
 *
 * The controller recognizes four statuses and assigns different visibility to
 * each - but the route (routes/web.php:62) is completely public, so all four
 * branches are reachable as a guest too. Coverage so far existed for only one
 * branch: the seeded 'home' page, with its own status.
 *
 * The draft (status 0) branch read Auth::user()->can() without a null check,
 * i.e. it threw a fatal error as a guest instead of a 403. Same defect family
 * as TODO 10's getRole() and TODO 11.1's detachParentGroup().
 */
class StaticPageAccessTest extends FeatureTestCase
{
    private User $owner;

    protected function setUp(): void
    {
        parent::setUp();

        // FeatureTestCase has already created the 'home' page with status 1;
        // its owner is the mainAdmin.
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
    // 1. The draft branch - this is why this file was created
    // =========================================================================

    public function test_a_draft_page_returns_403_for_a_guest_instead_of_a_fatal_error(): void
    {
        // TODO 11.2: line :15 read Auth::user()->can('is-admin') without a
        // null check. As a guest, Auth::user() is null, so this was a
        // "Call to a member function can() on null" fatal - a 500 where a
        // 403 is due. The route (web.php:62) has NO auth middleware at all.
        $this->makePage(0);

        $this->get($this->pageUrl())->assertForbidden();
    }

    public function test_a_draft_home_page_does_not_break_the_site_root(): void
    {
        // The sharpest variant: the '/' route (web.php:57-59) runs with guest
        // middleware, so Auth::user() there is GUARANTEED to be null. If the
        // home page becomes a draft, the site root crashed for every
        // visitor. On the else branch, the 'home' slug does not give a 403,
        // but the home-404 view.
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
        // The non-admin doesn't fail on the can() branch, but falls all the
        // way through the elseif chain to the else: 0 does not match any of
        // the later branches either.
        $this->makePage(0);

        $this->actingAs($this->activatedUser())
            ->get($this->pageUrl())
            ->assertForbidden();
    }

    // =========================================================================
    // 2. The other statuses - the matrix was not recorded anywhere until now
    // =========================================================================

    public function test_a_public_page_is_open_to_everyone(): void
    {
        $this->makePage(1);

        // The guest gets the 'main' layout, the logged-in user gets the inner one.
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
        // Status 2: exclusively for the logged-out visitor. This is the only
        // status where logging in TAKES AWAY access.
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
    // 3. The missing page - the 'home' slug is an exception here too
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
