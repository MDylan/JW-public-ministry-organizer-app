<?php

namespace Tests\Feature\Setup;

use App\Models\StaticPage;
use App\Models\User;
use App\Notifications\UserRegisteredNotification;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Notification;

/**
 * TODO 12: az első felhasználó létrehozása.
 *
 * Az AccountController::register() a Fortify CreateNewUser akcióját használja,
 * majd mainAdmin-ra emeli az így születő fiókot. Ez az egyetlen út, amin
 * mainAdmin keletkezhet emberi beavatkozás nélkül - és teljesen nyilvános,
 * amíg a telepítő nyitva van.
 */
class SetupAccountTest extends SetupTestCase
{
    private function payload(array $overrides = []): array
    {
        return array_merge([
            'name' => 'Fő Adminisztrátor',
            'email' => 'first-admin@example.test',
            'phone_number' => '36301234567',
            'password' => 'Titkos-Jelszo-12',
            'password_confirmation' => 'Titkos-Jelszo-12',
            'terms' => '1',
        ], $overrides);
    }

    // =========================================================================
    // 1. A sikeres regisztráció
    // =========================================================================

    public function test_the_first_user_becomes_a_verified_main_admin(): void
    {
        Notification::fake();

        $this->post(route('setup.save-account'), $this->payload())
            ->assertRedirect(route('setup.complete'));

        $user = User::where('email', 'first-admin@example.test')->firstOrFail();

        $this->assertSame('mainAdmin', $user->role);
        $this->assertNotNull($user->email_verified_at, 'A telepítő fiókja nem igényel megerősítést.');
        $this->assertSame('Fő Adminisztrátor', $user->name);
    }

    public function test_the_new_admin_is_logged_in_immediately(): void
    {
        Notification::fake();

        $this->post(route('setup.save-account'), $this->payload());

        $this->assertAuthenticated();
        $this->assertSame(
            'first-admin@example.test',
            auth()->user()->email
        );
    }

    public function test_the_default_static_pages_are_seeded(): void
    {
        Notification::fake();

        $this->assertSame(0, StaticPage::count());

        $this->post(route('setup.save-account'), $this->payload());

        $owner = User::where('email', 'first-admin@example.test')->firstOrFail();

        foreach (['home', 'contact', 'terms', 'help'] as $slug) {
            $this->assertDatabaseHas('static_pages', [
                'slug' => $slug,
                'user_id' => $owner->id,
            ]);
        }
    }

    public function test_the_side_menu_cache_is_cleared_for_the_new_pages(): void
    {
        // A TODO 09 lelete szerint a SetLocale rememberForever-rel gyorsítótárazza
        // a menüt, és mindössze két hely üríti. Ez az egyik: enélkül a
        // telepítéskor létrehozott oldalak nem jelennének meg a menüben.
        Notification::fake();

        Cache::forever('sidemenu_guest', ['elavult']);
        Cache::forever('sidemenu_auth', ['elavult']);

        $this->post(route('setup.save-account'), $this->payload());

        $this->assertNull(Cache::get('sidemenu_guest'));
        $this->assertNull(Cache::get('sidemenu_auth'));
    }

    public function test_the_registration_notification_goes_out(): void
    {
        Notification::fake();

        $this->post(route('setup.save-account'), $this->payload());

        Notification::assertSentTo(
            User::where('email', 'first-admin@example.test')->firstOrFail(),
            UserRegisteredNotification::class
        );
    }

    // =========================================================================
    // 2. Validáció
    // =========================================================================

    public function test_the_password_rules_apply_to_the_first_account_too(): void
    {
        Notification::fake();

        $this->post(route('setup.save-account'), $this->payload([
            'password' => 'rovid',
            'password_confirmation' => 'rovid',
        ]))->assertSessionHasErrors('password');

        $this->assertDatabaseMissing('users', ['email' => 'first-admin@example.test']);
    }

    public function test_the_email_must_be_unique(): void
    {
        Notification::fake();

        $this->post(route('setup.save-account'), $this->payload());
        $this->post(route('setup.save-account'), $this->payload([
            'name' => 'Második',
        ]))->assertSessionHasErrors('email');

        $this->assertSame(1, User::where('email', 'first-admin@example.test')->count());
    }

    public function test_a_second_run_creates_a_second_main_admin(): void
    {
        // KARAKTERIZÁLÁS: a lépés nem egyszer futtatható. Amíg a telepítő
        // nyitva van (nincs installed.txt), a save-account újra és újra
        // meghívható, és minden hívás új mainAdmin-t hoz létre.
        Notification::fake();

        $this->post(route('setup.save-account'), $this->payload());
        $this->post(route('setup.save-account'), $this->payload([
            'email' => 'second-admin@example.test',
        ]));

        $this->assertSame(2, User::where('role', 'mainAdmin')->count());
    }
}
