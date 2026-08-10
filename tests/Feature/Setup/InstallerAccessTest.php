<?php

namespace Tests\Feature\Setup;

use App\Http\Middleware\EnsureInstallerToken;
use App\Models\User;
use Illuminate\Support\Facades\Storage;

/**
 * v1-patch D2: the installer's access protection.
 *
 * THE MEASURED DEFECT
 *
 * The `setup/*` route group carried not a single authorization check -
 * neither `auth`, nor a gate, nor a signature. So during the installation
 * window, ANYONE who knew the address could create a `mainAdmin` account,
 * repeatedly even, and anyone could close the installer, because
 * `setup.complete` wrote the sentinel file on a plain GET - after which the
 * group no longer registers, meaning the real installation became
 * UNCOMPLETABLE.
 *
 * THE CHOSEN PROTECTION
 *
 * It cannot be tied to login: the installation is exactly the phase where
 * there is not yet a user. So the middleware proves filesystem access
 * instead - the token must be read on the server, in the
 * `storage/app/installer-token.txt` file.
 *
 * This is the only file under `tests/Feature/Setup/` that does NOT unlock the
 * gate in setUp() - the other subclasses inherit the unlock from
 * SetupTestCase, so they keep measuring what they were written for.
 */
class InstallerAccessTest extends SetupTestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        // SetupTestCase unlocks by default; here the locked state is exactly the subject.
        $this->flushSession();
    }

    // =========================================================================
    // 1. The gate
    // =========================================================================

    /**
     * @dataProvider guardedRouteProvider
     */
    public function test_a_visitor_without_the_token_is_turned_away(string $route): void
    {
        $this->get(route($route))->assertRedirect(route('setup.welcome'));
    }

    public static function guardedRouteProvider(): array
    {
        return [
            'requirements' => ['setup.requirements'],
            'basics'       => ['setup.basics'],
            'database'     => ['setup.database'],
            'mail'         => ['setup.mail'],
            'account'      => ['setup.account'],
            'complete'     => ['setup.complete'],
        ];
    }

    public function test_a_visitor_without_the_token_cannot_create_an_administrator(): void
    {
        // THIS IS THE POINT. During the installation window, anyone who knew
        // the address could create themselves a mainAdmin account.
        $this->post(route('setup.save-account'), [
            'name'                  => 'Betolakodó',
            'email'                 => 'intruder@example.test',
            'password'              => 'Titkos-Jelszo1',
            'password_confirmation' => 'Titkos-Jelszo1',
        ])->assertRedirect(route('setup.welcome'));

        $this->assertSame(0, User::count(), 'Egyetlen fiók sem jöhet létre.');
    }

    public function test_a_visitor_without_the_token_cannot_close_the_installer(): void
    {
        // Writing the sentinel closes the installer: the route group no
        // longer registers from that point on. If a stranger can trigger
        // this, the real installation becomes uncompletable.
        $this->get(route('setup.complete'))->assertRedirect(route('setup.welcome'));

        $this->assertFileDoesNotExist($this->sentinelPath());
    }

    public function test_the_welcome_screen_stays_open_because_the_token_goes_in_there(): void
    {
        $this->get(route('setup.welcome'))->assertStatus(200);
    }

    // =========================================================================
    // 2. The unlock
    // =========================================================================

    public function test_the_token_file_is_created_on_demand_and_is_not_guessable(): void
    {
        // SetupTestCase::setUp() already forced the file into existence when
        // unlocking; here its creation is exactly the subject, so we discard it.
        EnsureInstallerToken::forget();

        $this->assertFalse(Storage::exists(EnsureInstallerToken::TOKEN_FILE));

        $token = EnsureInstallerToken::currentToken();

        $this->assertTrue(Storage::exists(EnsureInstallerToken::TOKEN_FILE));
        $this->assertSame(32, strlen($token));

        // Repeated reads give the same value - we do not generate a new one on every request.
        $this->assertSame($token, EnsureInstallerToken::currentToken());
    }

    public function test_a_wrong_token_is_rejected(): void
    {
        EnsureInstallerToken::currentToken();

        $this->post(route('setup.unlock'), ['token' => 'nem-ez-az'])
            ->assertSessionHasErrors('token');

        $this->get(route('setup.requirements'))->assertRedirect(route('setup.welcome'));
    }

    public function test_the_right_token_unlocks_the_rest_of_the_installer(): void
    {
        $token = EnsureInstallerToken::currentToken();

        $this->post(route('setup.unlock'), ['token' => $token])
            ->assertRedirect(route('setup.requirements'));

        $this->get(route('setup.requirements'))->assertStatus(200);
    }

    public function test_the_token_is_accepted_with_surrounding_whitespace(): void
    {
        // When copied from a file, a line break or space easily comes along
        // with it; an installation should not fail because of that.
        $token = EnsureInstallerToken::currentToken();

        $this->post(route('setup.unlock'), ['token' => "  \n".$token."  \n"])
            ->assertRedirect(route('setup.requirements'));
    }

    // =========================================================================
    // 3. The precondition for closing
    // =========================================================================

    public function test_the_installer_cannot_be_closed_before_an_administrator_exists(): void
    {
        // The sentinel's meaning is "the installation has completed", and
        // that means precisely that an administrator account exists.
        // Previously the GET wrote it unconditionally.
        $this->unlockInstaller();

        $this->get(route('setup.complete'))->assertRedirect(route('setup.account'));

        $this->assertFileDoesNotExist($this->sentinelPath());
    }

    public function test_the_installer_closes_once_an_administrator_exists(): void
    {
        $this->unlockInstaller();

        User::factory()->create(['role' => 'mainAdmin', 'email' => 'admin@example.test']);

        $this->get(route('setup.complete'))->assertStatus(200);

        $this->assertFileExists($this->sentinelPath());
    }

    public function test_closing_the_installer_removes_the_token_file(): void
    {
        // The token loses its meaning; a file that once granted access
        // should not stay behind on disk.
        $this->unlockInstaller();
        EnsureInstallerToken::currentToken();

        User::factory()->create(['role' => 'mainAdmin', 'email' => 'admin2@example.test']);

        $this->get(route('setup.complete'))->assertStatus(200);

        $this->assertFalse(Storage::exists(EnsureInstallerToken::TOKEN_FILE));
    }
}
