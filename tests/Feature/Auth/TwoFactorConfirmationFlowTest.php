<?php

namespace Tests\Feature\Auth;

use App\Actions\Fortify\DisableTwoFactorAuthentication;
use Illuminate\Support\Facades\Schema;
use Laravel\Fortify\Contracts\TwoFactorAuthenticationProvider;
use PragmaRX\Google2FA\Google2FA;
use Tests\Feature\FeatureTestCase;

/**
 * TODO 39.2: the two-factor confirmation flow, pinned before the Fortify
 * version ceiling is lifted.
 *
 * WHY THIS FILE EXISTS
 *
 * composer.json pins laravel/fortify at ~1.11.2, and 1.11.2 declares
 * illuminate/support ^8.82|^9.0 - so it admits no Laravel 10 release, and the
 * Phase 5 hop cannot resolve while the pin stands. Lifting the ceiling crosses
 * 1.12.0, which introduces Fortify's own two_factor_confirmed_at column and its
 * own confirmation flow. This project confirms a second factor itself, through
 * a boolean the vendor knows nothing about, so "bump", "override" and "migrate"
 * were indistinguishable in risk while the flow had no acceptance criteria at
 * all - the same reason TODO 19.1, 20.1, 21.1 and 22.1 were written.
 *
 * Before this file the only 2FA coverage was TwoFactorReplayTest, which
 * measures the TOTP provider. Enabling, confirming, disabling and the login
 * challenge that depends on all three had zero tests.
 *
 * WHAT IS BEHAVIOUR AND WHAT IS STORAGE
 *
 * Every test but the last asserts BEHAVIOUR, and asks the model rather than the
 * column. They were written against the boolean and they did not move by a
 * single character when the storage migrated - which is the actual proof that
 * the migration changed where the answer is kept and nothing else.
 *
 * The last test is the directional one, and it is the only one that moved. It
 * asserted the old storage on purpose so that it would fail the moment the
 * confirmation moved; that failure was the review. It now states the new
 * storage, and its comment carries what it used to say.
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

        $user->confirmTwoFactorAuth(app(Google2FA::class)->getCurrentOtp($secret));

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
            ->post(route('two-factor.confirm'), [
                'code' => app(Google2FA::class)->getCurrentOtp($secret),
            ])
            ->assertSessionHasNoErrors();

        $this->assertTrue($user->fresh()->hasConfirmedTwoFactorAuth());
    }

    public function test_confirming_with_an_invalid_code_leaves_it_unconfirmed(): void
    {
        [$user] = $this->userWithAnUnconfirmedSecret('twofactor-confirm-bad@example.test');

        $this->actingAs($user)
            ->post(route('two-factor.confirm'), ['code' => '000000'])
            ->assertSessionHasErrors();

        $this->assertFalse($user->fresh()->hasConfirmedTwoFactorAuth());
    }

    public function test_disabling_clears_the_secret_the_recovery_codes_and_the_confirmation(): void
    {
        [$user, $secret] = $this->userWithAnUnconfirmedSecret('twofactor-disable@example.test');

        $user->confirmTwoFactorAuth(app(Google2FA::class)->getCurrentOtp($secret));

        // Found by the control experiment: without this line the test stays
        // green even when the confirmation is never written, because "not
        // confirmed afterwards" is also true of a user who never got there.
        $this->assertTrue($user->fresh()->hasConfirmedTwoFactorAuth());

        // Resolving Fortify's contract is what proves the project's override is
        // still the action that runs.
        app(\Laravel\Fortify\Actions\DisableTwoFactorAuthentication::class)($user);

        $fresh = $user->fresh();

        $this->assertNull($fresh->two_factor_secret);
        $this->assertNull($fresh->two_factor_recovery_codes);
        $this->assertFalse($fresh->hasConfirmedTwoFactorAuth());
    }

    public function test_the_override_is_the_action_the_container_hands_out(): void
    {
        $this->assertInstanceOf(
            DisableTwoFactorAuthentication::class,
            app(\Laravel\Fortify\Actions\DisableTwoFactorAuthentication::class),
            'FortifyServiceProvider::boot() has to keep winning over the package register() binding.'
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
        // This is the directional assertion, REVERSED by the migration commit -
        // and the reversal is the whole review. Before it, this test read
        // "two_factor_confirmed exists and two_factor_confirmed_at does not".
        // The boolean is gone, and the column Fortify itself writes from 1.12.0
        // onwards is now the only one that carries the answer.
        $this->assertTrue(Schema::hasColumn('users', 'two_factor_confirmed_at'));
        $this->assertFalse(Schema::hasColumn('users', 'two_factor_confirmed'));

        [$user, $secret] = $this->userWithAnUnconfirmedSecret('twofactor-column@example.test');

        $this->assertNull($user->two_factor_confirmed_at);

        $user->confirmTwoFactorAuth(app(Google2FA::class)->getCurrentOtp($secret));

        $this->assertNotNull($user->fresh()->two_factor_confirmed_at);
    }
}
