<?php

namespace Tests\Feature\Setup;

use App\Models\StaticPage;
use App\Models\User;
use App\Notifications\UserRegisteredNotification;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Notification;

/**
 * TODO 12: creating the first user.
 *
 * AccountController::register() uses Fortify's CreateNewUser action, then
 * promotes the resulting account to mainAdmin. This is the only path through
 * which a mainAdmin can come into being without human intervention - and it
 * is completely public while the installer is open.
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
    // 1. Successful registration
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
        // Per the TODO 09 finding, SetLocale caches the menu with rememberForever,
        // and only two places clear it. This is one of them: without it, the
        // pages created during setup would not show up in the menu.
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
    // 2. Validation
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
        // CHARACTERIZATION: the step is not one-shot. As long as the installer
        // is open (no installed.txt), save-account can be called over and
        // over, and every call creates a new mainAdmin.
        Notification::fake();

        $this->post(route('setup.save-account'), $this->payload());
        $this->post(route('setup.save-account'), $this->payload([
            'email' => 'second-admin@example.test',
        ]));

        $this->assertSame(2, User::where('role', 'mainAdmin')->count());
    }
}
