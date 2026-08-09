<?php

namespace Tests\Feature;

use App\Models\User;

class BladePageInteractionTest extends FeatureTestCase
{
    public function test_login_blade_renders_expected_form_and_links_and_allows_submit_flow(): void
    {
        $response = $this->get(route('login'));

        $response->assertStatus(200);
        $response->assertSee('id="loginForm"', false);
        $response->assertSee('name="email"', false);
        $response->assertSee('name="password"', false);
        $response->assertSee(route('password.request'), false);

        $user = $this->createUser([
            'email' => 'blade-login@example.test',
            'password' => bcrypt('password'),
        ]);

        $this->post(route('login'), [
            'email' => $user->email,
            'password' => 'password',
        ])->assertStatus(302);

        $this->assertAuthenticatedAs($user);

        $this->post(route('logout'))->assertStatus(302);
        $this->assertGuest();
    }

    public function test_register_blade_renders_expected_fields_and_creates_user_on_submit(): void
    {
        $response = $this->get(route('register'));

        $response->assertStatus(200);
        $response->assertSee('id="registerForm"', false);
        $response->assertSee('name="name"', false);
        $response->assertSee('name="email"', false);
        $response->assertSee('name="password"', false);
        $response->assertSee('name="password_confirmation"', false);

        $email = 'blade-register@example.test';

        $this->post(route('register'), [
            'name' => 'Blade Register User',
            'email' => $email,
            'phone_number' => '36201230000',
            'password' => 'Password1',
            'password_confirmation' => 'Password1',
            'terms' => '1',
        ])->assertStatus(302);

        $this->assertDatabaseHas('users', ['email' => $email]);
    }

    public function test_forgot_password_blade_renders_form_and_accepts_submit(): void
    {
        $response = $this->get(route('password.request'));

        $response->assertStatus(200);
        $response->assertSee('id="lostpasswordForm"', false);
        $response->assertSee('name="email"', false);
        $response->assertSee(route('password.email'), false);

        $user = $this->createUser([
            'email' => 'blade-forgot@example.test',
            'password' => bcrypt('password'),
        ]);

        $this->post(route('password.email'), [
            'email' => $user->email,
        ])->assertStatus(302);
    }

    public function test_finish_registration_blade_renders_form_and_signed_cancel_link_works(): void
    {
        $registered = User::factory()->create([
            'name' => null,
            'role' => 'registered',
            'email_verified_at' => null,
            'email' => 'blade-finish@example.test',
            'password' => bcrypt('Password1'),
            'language' => 'hu',
        ]);

        $signedGet = $this->signedRoute('finish_registration', ['id' => $registered->id]);

        $response = $this->get($signedGet);
        $response->assertStatus(200);
        $response->assertSee('name="name"', false);
        $response->assertSee('name="phone_number"', false);
        $response->assertSee('name="password"', false);
        $response->assertSee('name="password_confirmation"', false);

        // A nézet a Mégsem gombot űrlapként rajzolja ki (v1-patch H): a törlés
        // POST + CSRF, mert egy aláírt GET-et böngészőelőtöltés vagy egy
        // levelezőrendszer linkellenőrzője is elsüthetett.
        $response->assertSee('method="POST"', false);

        $signedCancel = $this->signedRoute('finish_registration_cancel', ['id' => $registered->id]);
        $this->post($signedCancel)->assertRedirect('/');

        $this->assertDatabaseMissing('users', ['id' => $registered->id]);
    }

    public function test_confirm_password_blade_renders_and_sets_password_confirmed_session_on_valid_submit(): void
    {
        $user = $this->createUser([
            'email' => 'blade-confirm-password@example.test',
            'password' => bcrypt('password'),
        ]);

        $page = $this->actingAs($user)->get(route('password.confirm'));
        $page->assertStatus(200);
        $page->assertSee('name="password"', false);
        $page->assertSee(route('password.confirm'), false);

        $this->actingAs($user)
            ->post(route('password.confirm'), ['password' => 'password'])
            ->assertStatus(302)
            ->assertSessionHas('auth.password_confirmed_at');
    }
}
