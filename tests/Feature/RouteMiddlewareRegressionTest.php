<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Support\Facades\URL;

class RouteMiddlewareRegressionTest extends FeatureTestCase
{
    public function test_guest_access_matrix_for_core_routes(): void
    {
        $this->get('/')->assertStatus(200);
        $this->get(route('static_page', ['slug' => 'home']))->assertStatus(200);

        $this->get(route('home.home'))->assertRedirect(route('login'));
        $this->get(route('groups'))->assertRedirect(route('login'));
        $this->get(route('admin.settings'))->assertRedirect(route('login'));
    }

    public function test_finish_registration_signed_route_requires_valid_signature(): void
    {
        $user = $this->createUser([
            'email_verified_at' => null,
            'role' => 'registered',
            'name' => null,
        ]);

        $this->get(route('finish_registration', ['id' => $user->id]))->assertForbidden();

        $signed = $this->signedRoute('finish_registration', ['id' => $user->id]);
        $this->get($signed)->assertStatus(200);
    }

    public function test_verified_and_profile_full_middleware_flow(): void
    {
        $user = $this->createUser([
            'email_verified_at' => null,
            'email' => 'unverified@example.test',
        ]);

        $this->actingAs($user)
            ->get(route('groups'))
            ->assertRedirect(route('verification.notice'));

        $user->email_verified_at = now();
        $user->name = null;
        $user->save();

        $profileRedirect = $this->actingAs($user)
            ->get(route('groups'));
        $profileRedirect->assertStatus(302);
        $this->assertStringContainsString(
            route('user.profile', [], false),
            $profileRedirect->headers->get('Location', '')
        );

        $user->name = 'Verified User';
        $user->save();

        $this->actingAs($user)
            ->get(route('groups'))
            ->assertStatus(200);
    }

    public function test_group_member_and_group_admin_middleware_flow(): void
    {
        $user = $this->createUser(['email' => 'member@example.test']);
        $group = $this->createGroup();

        $this->actingAs($user->fresh())
            ->get(route('groups.users', ['group' => $group->id]))
            ->assertForbidden();

        $this->attachUserToGroup($user, $group, 'member', true);
        $user = $user->fresh();

        $this->actingAs($user)
            ->get(route('groups.users', ['group' => $group->id]))
            ->assertStatus(200);

        $this->actingAs($user)
            ->withSession($this->passwordConfirmedSession())
            ->get(route('groups.edit', ['group' => $group->id]))
            ->assertForbidden();

        $this->attachUserToGroup($user, $group, 'roler', true);
        $user = $user->fresh();

        $this->actingAs($user)
            ->withSession($this->passwordConfirmedSession())
            ->get(route('groups.edit', ['group' => $group->id]))
            ->assertStatus(200);
    }

    public function test_admin_and_translator_gate_routes(): void
    {
        $user = $this->createUser(['email' => 'user@example.test']);

        $this->actingAs($user)
            ->withSession($this->passwordConfirmedSession())
            ->get(route('admin.settings'))
            ->assertForbidden();

        $user->role = 'mainAdmin';
        $user->save();
        $user = $user->fresh();

        $this->actingAs($user)
            ->withSession($this->passwordConfirmedSession())
            ->get(route('admin.settings'))
            ->assertStatus(200);

        $user->role = 'registered';
        $user->save();
        $user = $user->fresh();

        $this->actingAs($user)
            ->withSession($this->passwordConfirmedSession())
            ->get(route('admin.translate'))
            ->assertForbidden();

        $user->role = 'translator';
        $user->save();
        $user = $user->fresh();

        $this->actingAs($user)
            ->withSession($this->passwordConfirmedSession())
            ->get(route('admin.translate'))
            ->assertStatus(200);
    }

    public function test_group_servant_gate_route_accepts_membership_instead_of_a_global_role(): void
    {
        // TODO 07.2: can:is-groupservant is the only route-level use of the
        // three group gates (routes/web.php:160). Unlike the is-admin and
        // is-translator gates, this one does NOT work off the users.role
        // column, but off the count of accepted admin/roler memberships -
        // meaning a plain 'activated' user can also pass through it.
        // The full matrix of the closures: tests/Feature/Auth/AuthorizationGateTest.
        $group = $this->createGroup();
        $user = $this->createUser([
            'email' => 'newsletter-gate@example.test',
            'role'  => 'activated',
        ]);

        $this->actingAs($user)
            ->get(route('newsletters'))
            ->assertForbidden();

        $this->attachUserToGroup($user, $group, 'roler');

        $this->actingAs($user->fresh())
            ->get(route('newsletters'))
            ->assertStatus(200);
    }

    public function test_group_servant_gate_route_rejects_a_plain_member(): void
    {
        $group = $this->createGroup();
        $member = $this->createUser([
            'email' => 'newsletter-member@example.test',
            'role'  => 'activated',
        ]);
        $this->attachUserToGroup($member, $group, 'member');

        $this->actingAs($member->fresh())
            ->get(route('newsletters'))
            ->assertForbidden();
    }

    public function test_password_confirmed_middleware_is_enforced(): void
    {
        $admin = User::factory()->asAdmin()->create(['email' => 'admin-confirm@example.test']);

        $this->actingAs($admin)
            ->get(route('admin.settings'))
            ->assertRedirect(route('password.confirm'));

        $this->actingAs($admin)
            ->withSession($this->passwordConfirmedSession())
            ->get(route('admin.settings'))
            ->assertStatus(200);
    }

    public function test_fortify_email_verification_route_accepts_signed_request(): void
    {
        $user = $this->createUser([
            'email_verified_at' => null,
            'email' => 'verifyme@example.test',
        ]);

        $url = URL::temporarySignedRoute('verification.verify', now()->addMinutes(30), [
            'id' => $user->id,
            'hash' => sha1($user->getEmailForVerification()),
        ]);

        $this->actingAs($user)
            ->get($url)
            ->assertStatus(302);

        $this->assertNotNull($user->fresh()->email_verified_at);
    }
}
