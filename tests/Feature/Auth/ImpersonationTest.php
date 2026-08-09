<?php

namespace Tests\Feature\Auth;

use App\Http\Controllers\Admin\LoginToUserController;
use App\Models\User;
use Illuminate\Support\Facades\Auth;
use Tests\Feature\FeatureTestCase;

/**
 * v1-patch H: az adminisztrátori megszemélyesítés visszaútja.
 *
 * A JAVÍTÁS ELŐTT a `login()` egy 12 órás aláírt URL-t gyártott az admin saját
 * azonosítójával, és a `loginBack()` csak az aláírást ellenőrizte. Az URL-ben
 * álló `{id}` semmihez nem volt kötve: nem a munkamenethez, nem a `mainAdmin`
 * szerephez. Aki megszerezte a linket - a böngészőelőzményből, egy proxy
 * naplójából, egy megosztott képernyőről -, az 12 órán át bármikor
 * visszajátszhatta, és tetszőleges felhasználói azonosítóval léphetett be.
 *
 * Az azonosító innentől kizárólag szerveroldali sessionben él, mindkét váltás
 * POST + CSRF, és a visszaút egyszer használható.
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
        // A KORÁBBI alak mindkét irányban GET volt. Egy GET-et böngészőelőtöltés,
        // egy előolvasó proxy vagy egy idegen oldalról jövő navigáció is
        // elsüthet - a POST + CSRF ezt zárja ki.
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
        // EZ A JAVÍTÁS LÉNYEGE. Korábban egy érvényes aláírás önmagában elég
        // volt; most a visszaút kizárólag abból az azonosítóból dolgozik, amit
        // EBBEN a munkamenetben tettünk el.
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

        // Második visszalépés: a session-kulcs elfogyott, tehát nincs mit
        // visszajátszani. Korábban ugyanaz az aláírt URL 12 órán át működött.
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

        // Az `is-admin` gate CSAK a `mainAdmin`-t engedi (AuthServiceProvider),
        // tehát a kapu már a controller előtt megáll. A controllerben lévő
        // szerepellenőrzés a második réteg: ha a gate valaha tágul, a
        // megszemélyesítés attól még nem nyílik meg.
        $this->actingAs($admin)
            ->withSession($this->passwordConfirmedSession())
            ->post(route('admin.users.login', ['user' => $target->id]))
            ->assertForbidden();

        $this->assertSame($admin->id, auth()->id());
        $this->assertFalse(session()->has(LoginToUserController::SESSION_KEY));
    }

    public function test_impersonation_cannot_be_chained(): void
    {
        // Láncolva a második váltás felülírná az eltárolt eredeti azonosítót, és
        // a visszaút a KÖZTES felhasználóra mutatna: az eredeti admin nem tudna
        // visszalépni, a köztes fiók viszont igen.
        //
        // A gate csak azért nem fogja meg magától, mert két főadmin is lehet -
        // ezt a helyzetet KIZÁRÓLAG a controller őrzi.
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

        // A megszemélyesítés óta az eredeti fiók elveszítette a főadmin jogot.
        $admin->role = 'activated';
        $admin->save();

        $this->post(route('admin.loginback'))->assertRedirect(route('home.home'));

        $this->assertSame($target->id, auth()->id());
    }

    public function test_password_confirmation_does_not_survive_an_identity_switch(): void
    {
        // A `password.confirm` mögötti oldalak különben az ELŐZŐ felhasználó
        // megerősítésével nyílnának meg az újnak.
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
