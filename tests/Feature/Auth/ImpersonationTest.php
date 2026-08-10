<?php

namespace Tests\Feature\Auth;

use App\Http\Controllers\Admin\LoginToUserController;
use App\Models\User;
use Illuminate\Support\Facades\Auth;
use Tests\Feature\FeatureTestCase;

/**
 * v1-patch H: the return path of admin impersonation.
 *
 * BEFORE THE FIX, `login()` generated a 12-hour signed URL containing the
 * admin's own id, and `loginBack()` only verified the signature. The `{id}`
 * embedded in the URL was bound to nothing: not to the session, not to the
 * `mainAdmin` role. Whoever obtained the link - from browser history, a proxy
 * log, a shared screen - could replay it at any time for 12 hours, and log in
 * as any arbitrary user id.
 *
 * From now on the id lives exclusively in the server-side session, both
 * switches are POST + CSRF, and the return path is single-use.
 */
class ImpersonationTest extends FeatureTestCase
{
    private function mainAdmin(string $email = 'impersonator@example.test'): User
    {
        return $this->createUser([
            'email' => $email,
            'role'  => 'mainAdmin',
            'name'  => 'Main Admin',
        ]);
    }

    private function target(string $email = 'impersonated@example.test'): User
    {
        return $this->createUser([
            'email' => $email,
            'role'  => 'activated',
            'name'  => 'Target User',
        ]);
    }

    public function test_the_admin_can_impersonate_and_step_back(): void
    {
        $admin = $this->mainAdmin();
        $target = $this->target();

        $this->actingAs($admin)
            ->withSession($this->passwordConfirmedSession())
            ->post(route('admin.users.login', ['user' => $target->id]))
            ->assertRedirect(route('home.home'));

        $this->assertSame($target->id, auth()->id());
        $this->assertSame($admin->id, session(LoginToUserController::SESSION_KEY));

        $this->post(route('admin.loginback'))
            ->assertRedirect(route('home.home'));

        $this->assertSame($admin->id, auth()->id());
        $this->assertFalse(session()->has(LoginToUserController::SESSION_KEY));
    }

    public function test_impersonation_requires_a_recent_password_confirmation(): void
    {
        $admin = $this->mainAdmin();
        $target = $this->target();

        $this->actingAs($admin)
            ->post(route('admin.users.login', ['user' => $target->id]))
            ->assertRedirect(route('password.confirm'))
            ->assertSessionHas('url.intended', route('admin.users'));

        $this->assertSame($admin->id, auth()->id());
        $this->assertFalse(session()->has(LoginToUserController::SESSION_KEY));
    }

    public function test_expired_password_confirmation_cannot_start_impersonation(): void
    {
        $admin = $this->mainAdmin();
        $target = $this->target();
        $expiredAt = time() - config('auth.password_timeout') - 1;

        $this->actingAs($admin)
            ->withSession(['auth.password_confirmed_at' => $expiredAt])
            ->post(route('admin.users.login', ['user' => $target->id]))
            ->assertRedirect(route('password.confirm'))
            ->assertSessionHas('url.intended', route('admin.users'));

        $this->assertSame($admin->id, auth()->id());
        $this->assertFalse(session()->has(LoginToUserController::SESSION_KEY));
    }

    public function test_json_impersonation_request_without_confirmation_returns_423(): void
    {
        $admin = $this->mainAdmin();
        $target = $this->target();

        $this->actingAs($admin)
            ->postJson(route('admin.users.login', ['user' => $target->id]))
            ->assertStatus(423)
            ->assertExactJson(['message' => 'Password confirmation required.']);

        $this->assertSame($admin->id, auth()->id());
        $this->assertFalse(session()->has(LoginToUserController::SESSION_KEY));
    }

    public function test_password_confirmation_returns_to_the_list_before_an_explicit_retry(): void
    {
        $admin = $this->mainAdmin();
        $target = $this->target();

        $this->actingAs($admin)
            ->post(route('admin.users.login', ['user' => $target->id]))
            ->assertRedirect(route('password.confirm'));

        $this->post(route('password.confirm.store'), ['password' => 'password'])
            ->assertRedirect(route('admin.users'));

        $this->assertSame($admin->id, auth()->id());

        $this->post(route('admin.users.login', ['user' => $target->id]))
            ->assertRedirect(route('home.home'));

        $this->assertSame($target->id, auth()->id());
    }

    public function test_the_identity_switch_is_not_reachable_with_a_get_request(): void
    {
        // The PREVIOUS shape was GET in both directions. A GET can be fired by
        // browser prefetching, a prefetching proxy, or navigation coming from
        // a foreign page - POST + CSRF rules this out.
        $admin = $this->mainAdmin();
        $target = $this->target();

        $this->actingAs($admin)
            ->get('/admin/users/login/'.$target->id)
            ->assertStatus(405);

        $this->assertSame($admin->id, auth()->id());

        $this->get('/loginback')->assertStatus(405);
    }

    public function test_login_back_does_nothing_without_the_session_binding(): void
    {
        // THIS IS THE ESSENCE OF THE FIX. Previously a valid signature alone
        // was enough; now the return path works exclusively from the id we
        // stored IN THIS session.
        $user = $this->target('no-binding@example.test');

        $this->actingAs($user)
            ->post(route('admin.loginback'))
            ->assertRedirect(route('home.home'));

        $this->assertSame($user->id, auth()->id());
    }

    public function test_the_return_path_is_single_use(): void
    {
        $admin = $this->mainAdmin();
        $target = $this->target();

        $this->actingAs($admin)
            ->withSession($this->passwordConfirmedSession())
            ->post(route('admin.users.login', ['user' => $target->id]));
        $this->post(route('admin.loginback'));
        $this->assertSame($admin->id, auth()->id());

        // Second step-back: the session key is used up, so there's nothing to
        // replay. Previously the same signed URL worked for 12 hours.
        $this->post(route('admin.loginback'))->assertRedirect(route('home.home'));
        $this->assertSame($admin->id, auth()->id());
    }

    public function test_a_non_main_admin_cannot_impersonate(): void
    {
        $admin = $this->createUser([
            'email' => 'plain-admin@example.test',
            'role'  => 'admin',
            'name'  => 'Plain Admin',
        ]);
        $target = $this->target();

        // The `is-admin` gate allows ONLY `mainAdmin` (AuthServiceProvider),
        // so the gate already stops it before the controller. The role check
        // in the controller is the second layer: if the gate ever widens,
        // impersonation still won't open up because of it.
        $this->actingAs($admin)
            ->withSession($this->passwordConfirmedSession())
            ->post(route('admin.users.login', ['user' => $target->id]))
            ->assertForbidden();

        $this->assertSame($admin->id, auth()->id());
        $this->assertFalse(session()->has(LoginToUserController::SESSION_KEY));
    }

    public function test_impersonation_cannot_be_chained(): void
    {
        // If chained, the second switch would overwrite the stored original
        // id, and the return path would point to the INTERMEDIATE user: the
        // original admin couldn't step back, but the intermediate account
        // could.
        //
        // The gate doesn't catch this on its own only because there can be
        // two main admins - this situation is guarded EXCLUSIVELY by the
        // controller.
        $admin = $this->mainAdmin();
        $secondAdmin = $this->mainAdmin('second-admin@example.test');
        $target = $this->target();

        $this->actingAs($admin)
            ->withSession($this->passwordConfirmedSession())
            ->post(route('admin.users.login', ['user' => $secondAdmin->id]));
        $this->assertSame($secondAdmin->id, auth()->id());

        $this->withSession($this->passwordConfirmedSession())
            ->post(route('admin.users.login', ['user' => $target->id]))
            ->assertRedirect(route('home.home'));

        $this->assertSame($secondAdmin->id, auth()->id());
        $this->assertSame($admin->id, session(LoginToUserController::SESSION_KEY));
    }

    public function test_login_back_re_checks_the_main_admin_role(): void
    {
        $admin = $this->mainAdmin();
        $target = $this->target();

        $this->actingAs($admin)
            ->withSession($this->passwordConfirmedSession())
            ->post(route('admin.users.login', ['user' => $target->id]));

        // Since the impersonation started, the original account has lost the main-admin role.
        $admin->role = 'activated';
        $admin->save();

        $this->post(route('admin.loginback'))->assertRedirect(route('home.home'));

        $this->assertSame($target->id, auth()->id());
    }

    public function test_password_confirmation_does_not_survive_an_identity_switch(): void
    {
        // Otherwise the pages behind `password.confirm` would open for the new
        // user using the PREVIOUS user's confirmation.
        $admin = $this->mainAdmin();
        $target = $this->target();

        $this->actingAs($admin)
            ->withSession($this->passwordConfirmedSession())
            ->post(route('admin.users.login', ['user' => $target->id]));

        $this->assertFalse(session()->has('auth.password_confirmed_at'));
    }

    public function test_impersonation_never_creates_or_restores_a_remember_cookie(): void
    {
        $admin = $this->mainAdmin();
        $target = $this->target();
        $recallerName = Auth::guard()->getRecallerName();

        $this->assertNotNull($admin->getRememberToken(), 'A tartós token megléte nem jelent aktív remember sessiont.');

        $this->actingAs($admin)
            ->withSession($this->passwordConfirmedSession())
            ->post(route('admin.users.login', ['user' => $target->id]))
            ->assertCookieMissing($recallerName)
            ->assertSessionMissing('impersonator_remember');

        $this->post(route('admin.loginback'))
            ->assertCookieMissing($recallerName)
            ->assertSessionMissing('impersonator_remember');

        $this->assertSame($admin->id, auth()->id());
    }

    public function test_the_session_id_is_regenerated_on_both_switches(): void
    {
        $admin = $this->mainAdmin();
        $target = $this->target();

        $this->actingAs($admin)->get(route('home.home'));
        $beforeImpersonation = session()->getId();

        $this->withSession($this->passwordConfirmedSession())
            ->post(route('admin.users.login', ['user' => $target->id]));
        $afterImpersonation = session()->getId();
        $this->assertNotSame($beforeImpersonation, $afterImpersonation);

        $this->post(route('admin.loginback'));
        $this->assertNotSame($afterImpersonation, session()->getId());
    }
}
