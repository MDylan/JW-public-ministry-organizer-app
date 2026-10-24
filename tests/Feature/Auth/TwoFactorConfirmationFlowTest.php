<?php

namespace Tests\Feature\Auth;

use Illuminate\Support\Facades\Schema;
use Laravel\Fortify\Actions\ConfirmTwoFactorAuthentication;
use Laravel\Fortify\Actions\DisableTwoFactorAuthentication;
use Laravel\Fortify\Contracts\TwoFactorAuthenticationProvider;
use Laravel\Fortify\Fortify;
use PragmaRX\Google2FA\Google2FA;
use Tests\Feature\FeatureTestCase;

/**
 * TODO 39.2: the two-factor confirmation flow, pinned before the Fortify
 * version ceiling is lifted - and kept as the gate when TODO 69 handed the
 * flow to Fortify.
 *
 * WHY THIS FILE EXISTS
 *
 * composer.json pinned laravel/fortify at ~1.11.2, and 1.11.2 declares
 * illuminate/support ^8.82|^9.0 - so it admitted no Laravel 10 release, and the
 * Phase 5 hop could not resolve while the pin stood. Lifting the ceiling
 * reaches 1.19.1, whose own confirmation flow is built around a
 * two_factor_confirmed_at column. This project confirmed a second factor
 * itself, so "bump", "override" and "migrate" were indistinguishable in risk
 * while the flow had no acceptance criteria at all.
 *
 * TODO 69: FORTIFY OWNS THE FLOW NOW
 *
 * config/fortify.php turns `'confirm' => true` on, the confirmation POST is
 * Fortify's ConfirmedTwoFactorAuthenticationController (two-factor.confirm,
 * /user/confirmed-two-factor-authentication), and the application's own
 * controller method, User::confirmTwoFactorAuth(), the custom login pipeline
 * (RedirectIfTwoFactorConfirmed) and the DisableTwoFactorAuthentication
 * override are gone. The behaviour cases below did not change their
 * assertions - which is the proof that the hand-over kept the behaviour. What
 * moved is how a test confirms (Fortify's action), and that the confirmation
 * POST now carries password.confirm and reports into Fortify's error bag.
 *
 * WHAT IS BEHAVIOUR AND WHAT IS STORAGE
 *
 * Every behaviour test asks the model rather than the column. The column
 * test at the end is the directional one: it states where the answer is
 * kept, and its comment carries what it used to say.
 */
class TwoFactorConfirmationFlowTest extends FeatureTestCase
{
    /**
     * Give a user a secret without confirming it - the state Fortify's own
     * enable action leaves behind.
     *
     * @return array{0: \App\Models\User, 1: string}
     */
    private function userWithAnUnconfirmedSecret(string $email): array
    {
        $engine = app(Google2FA::class);
        $secret = $engine->generateSecretKey();

        $user = $this->createUser([
            'email' => $email,
            'role'  => 'activated',
        ]);

        $user->forceFill([
            'two_factor_secret' => encrypt($secret),
            'two_factor_recovery_codes' => encrypt(json_encode(['code-one', 'code-two'])),
        ])->save();

        return [$user->fresh(), $secret];
    }

    private function confirm($user, string $secret): void
    {
        app(ConfirmTwoFactorAuthentication::class)($user, app(Google2FA::class)->getCurrentOtp($secret));
    }

    public function test_fortify_confirms_two_factor_authentication(): void
    {
        $this->assertTrue(Fortify::confirmsTwoFactorAuthentication());
    }

    public function test_enabling_the_second_factor_leaves_it_unconfirmed(): void
    {
        $user = $this->createUser([
            'email' => 'twofactor-enable@example.test',
            'role'  => 'activated',
        ]);

        // two-factor.enable carries auth + password.confirm, because
        // config/fortify.php passes 'confirmPassword' => true.
        $this->actingAs($user)
            ->withSession(['auth.password_confirmed_at' => time()])
            ->post(route('two-factor.enable'))
            ->assertStatus(302);

        $fresh = $user->fresh();

        $this->assertNotNull(
            $fresh->two_factor_secret,
            'Enabling has to store a secret.'
        );
        $this->assertFalse(
            $fresh->hasConfirmedTwoFactorAuth(),
            'Enabling must not confirm on its own: the user has not proved they can read the authenticator yet.'
        );
    }

    public function test_an_unconfirmed_second_factor_does_not_challenge_at_login(): void
    {
        [$user] = $this->userWithAnUnconfirmedSecret('twofactor-unconfirmed@example.test');

        $this->post('/login', [
            'email' => $user->email,
            'password' => 'password',
        ])->assertStatus(302);

        // This is the lockout guard: a user who enabled the second factor but
        // never proved it works still gets in with their password.
        $this->assertAuthenticatedAs($user->fresh());
    }

    public function test_a_confirmed_second_factor_challenges_at_login(): void
    {
        [$user, $secret] = $this->userWithAnUnconfirmedSecret('twofactor-confirmed@example.test');

        $this->confirm($user, $secret);

        $this->post('/login', [
            'email' => $user->email,
            'password' => 'password',
        ])->assertRedirect(route('two-factor.login'));

        $this->assertGuest();
    }

    public function test_confirming_with_a_valid_code_confirms_the_second_factor(): void
    {
        [$user, $secret] = $this->userWithAnUnconfirmedSecret('twofactor-confirm-ok@example.test');

        $this->actingAs($user)
            ->withSession(['auth.password_confirmed_at' => time()])
            ->post(route('two-factor.confirm'), [
                'code' => app(Google2FA::class)->getCurrentOtp($secret),
            ])
            ->assertSessionHasNoErrors()
            ->assertSessionHas('status', 'two-factor-authentication-confirmed');

        $this->assertTrue($user->fresh()->hasConfirmedTwoFactorAuth());
    }

    public function test_confirming_with_an_invalid_code_leaves_it_unconfirmed(): void
    {
        [$user] = $this->userWithAnUnconfirmedSecret('twofactor-confirm-bad@example.test');

        $this->actingAs($user)
            ->withSession(['auth.password_confirmed_at' => time()])
            ->post(route('two-factor.confirm'), ['code' => '000000'])
            ->assertSessionHasErrorsIn('confirmTwoFactorAuthentication', 'code');

        $this->assertFalse($user->fresh()->hasConfirmedTwoFactorAuth());
    }

    public function test_confirming_without_a_secret_is_an_error_not_a_crash(): void
    {
        // The application's own confirmation decrypted a null secret and
        // answered 500 for a user who had never pressed "enable". Fortify's
        // action checks first.
        $user = $this->createUser([
            'email' => 'twofactor-no-secret@example.test',
            'role'  => 'activated',
        ]);

        $this->actingAs($user)
            ->withSession(['auth.password_confirmed_at' => time()])
            ->post(route('two-factor.confirm'), ['code' => '123456'])
            ->assertRedirect()
            ->assertSessionHasErrorsIn('confirmTwoFactorAuthentication', 'code');
    }

    public function test_confirming_requires_a_confirmed_password(): void
    {
        // The application's own POST carried no password.confirm; Fortify's
        // carries the same middleware as enabling and disabling.
        [$user, $secret] = $this->userWithAnUnconfirmedSecret('twofactor-confirm-password@example.test');

        $this->actingAs($user)
            ->post(route('two-factor.confirm'), [
                'code' => app(Google2FA::class)->getCurrentOtp($secret),
            ])
            ->assertRedirect(route('password.confirm'));

        $this->assertFalse($user->fresh()->hasConfirmedTwoFactorAuth());
    }

    public function test_a_wrong_code_is_reported_on_the_settings_page(): void
    {
        // Until TODO 69 the page rendered no error at all: a wrong code sent
        // the user back to the same form without a word.
        [$user] = $this->userWithAnUnconfirmedSecret('twofactor-confirm-message@example.test');

        $this->actingAs($user)
            ->withSession(['auth.password_confirmed_at' => time()])
            ->from(route('user.twofactorsettings'))
            ->followingRedirects()
            ->post(route('two-factor.confirm'), ['code' => '000000'])
            ->assertOk()
            ->assertSee(__('user.two_factor.error'));
    }

    public function test_the_settings_page_shows_the_setup_key_of_an_unconfirmed_secret(): void
    {
        [$user, $secret] = $this->userWithAnUnconfirmedSecret('twofactor-setup-key@example.test');

        $this->actingAs($user)
            ->withSession(['auth.password_confirmed_at' => time()])
            ->get(route('user.twofactorsettings'))
            ->assertOk()
            ->assertSee(trim(chunk_split($secret, 4, ' ')));
    }

    public function test_the_secret_key_route_returns_the_secret(): void
    {
        [$user, $secret] = $this->userWithAnUnconfirmedSecret('twofactor-secret-route@example.test');

        $this->actingAs($user)
            ->withSession(['auth.password_confirmed_at' => time()])
            ->getJson(route('two-factor.secret-key'))
            ->assertOk()
            ->assertExactJson(['secretKey' => $secret]);
    }

    public function test_the_secret_key_route_requires_a_confirmed_password(): void
    {
        [$user] = $this->userWithAnUnconfirmedSecret('twofactor-secret-route-guard@example.test');

        $this->actingAs($user)
            ->getJson(route('two-factor.secret-key'))
            ->assertStatus(423);
    }

    public function test_disabling_clears_the_secret_the_recovery_codes_and_the_confirmation(): void
    {
        [$user, $secret] = $this->userWithAnUnconfirmedSecret('twofactor-disable@example.test');

        $this->confirm($user, $secret);

        // Found by the control experiment: without this line the test stays
        // green even when the confirmation is never written, because "not
        // confirmed afterwards" is also true of a user who never got there.
        $this->assertTrue($user->fresh()->hasConfirmedTwoFactorAuth());

        app(DisableTwoFactorAuthentication::class)($user);

        $fresh = $user->fresh();

        $this->assertNull($fresh->two_factor_secret);
        $this->assertNull($fresh->two_factor_recovery_codes);
        $this->assertFalse($fresh->hasConfirmedTwoFactorAuth());
    }

    public function test_the_container_hands_out_fortifys_own_disable_action(): void
    {
        // REVERSED by TODO 69. This case asserted that the application's
        // override won over the package binding. Fortify 1.36.2's own action
        // clears two_factor_confirmed_at whenever it is set - what the
        // override existed for - and dispatches TwoFactorAuthenticationDisabled,
        // which the override did not; so the override went.
        $this->assertSame(
            DisableTwoFactorAuthentication::class,
            get_class(app(DisableTwoFactorAuthentication::class))
        );
    }

    public function test_the_confirmation_path_resolves_the_replay_safe_provider(): void
    {
        // Guards the seam between this file and TwoFactorReplayTest: the
        // confirmation must not fall back to the package provider when the
        // version moves.
        $this->assertInstanceOf(
            \App\Actions\Fortify\TwoFactorAuthenticationProvider::class,
            app(TwoFactorAuthenticationProvider::class)
        );
    }

    public function test_the_confirmation_is_stored_in_the_two_factor_confirmed_at_column(): void
    {
        // This is the directional assertion, REVERSED by the TODO 39.2
        // migration commit - and the reversal was the whole review. Before it,
        // this test read "two_factor_confirmed exists and
        // two_factor_confirmed_at does not". The boolean is gone, and the
        // column Fortify itself writes is the only one that carries the answer.
        $this->assertTrue(Schema::hasColumn('users', 'two_factor_confirmed_at'));
        $this->assertFalse(Schema::hasColumn('users', 'two_factor_confirmed'));

        [$user, $secret] = $this->userWithAnUnconfirmedSecret('twofactor-column@example.test');

        $this->assertNull($user->two_factor_confirmed_at);

        $this->confirm($user, $secret);

        $this->assertNotNull($user->fresh()->two_factor_confirmed_at);
    }
}
