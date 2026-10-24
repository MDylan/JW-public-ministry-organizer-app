<?php

namespace Tests\Feature\Auth;

use App\Models\User;
use Laravel\Fortify\Contracts\PasskeyUser;
use Laravel\Fortify\Features;
use Laravel\Passkeys\Actions\VerifyPasskey;
use Laravel\Passkeys\Passkey;
use PragmaRX\Google2FA\Google2FA;
use Tests\Feature\FeatureTestCase;
use Webauthn\PublicKeyCredential;
use Webauthn\PublicKeyCredentialRequestOptions;

/**
 * Roadmap TODO 69: passkeys, through Fortify 1.41 and laravel/passkeys.
 *
 * The WebAuthn ceremony itself - attestation, signature, counter - is
 * web-auth/webauthn-lib's, and a test cannot produce a real authenticator's
 * signature. What is the application's, and is measured here: the routes and
 * the middleware they carry, the options the browser is given, what a
 * successful verification does to the session (in particular that a passkey
 * login does not ask for the TOTP code - the developer's decision), who may
 * delete which passkey, the GDPR paths, and the three pages that offer it.
 * The login cases replace VerifyPasskey, the one step that checks the
 * signature, with a stand-in that returns the user's passkey.
 */
class PasskeyTest extends FeatureTestCase
{
    private function passkeyFor(User $user, string $name = 'Anna telefonja'): Passkey
    {
        return $user->passkeys()->create([
            'name' => $name,
            // Base64 without padding, as the package stores and decodes it.
            'credential_id' => rtrim(base64_encode(md5($user->id.'|'.$name, true)), '='),
            'credential' => ['aaguid' => '00000000-0000-0000-0000-000000000000'],
        ]);
    }

    /**
     * A credential that parses as a WebAuthn assertion. It would never verify;
     * VerifyPasskey is replaced wherever it is posted.
     */
    private function assertionPayload(): array
    {
        $b64 = fn (string $bytes) => rtrim(strtr(base64_encode($bytes), '+/', '-_'), '=');

        return [
            'id' => $b64('raw-credential-id'),
            'rawId' => $b64('raw-credential-id'),
            'type' => 'public-key',
            'response' => [
                'clientDataJSON' => $b64(json_encode([
                    'type' => 'webauthn.get',
                    'challenge' => $b64('challenge'),
                    'origin' => config('app.url'),
                ])),
                'authenticatorData' => $b64(str_repeat("\0", 32)."\x05\0\0\0\1"),
                'signature' => $b64('signature'),
            ],
            'clientExtensionResults' => [],
        ];
    }

    private function verificationReturns(Passkey $passkey): void
    {
        $this->app->instance(VerifyPasskey::class, new class($passkey) extends VerifyPasskey
        {
            public function __construct(private Passkey $passkey) {}

            public function __invoke(PublicKeyCredential $credential, PublicKeyCredentialRequestOptions $options, ?\Laravel\Passkeys\Contracts\PasskeyUser $user = null): Passkey
            {
                return $this->passkey;
            }
        });
    }

    private function middlewareOf(string $name): array
    {
        $route = app('router')->getRoutes()->getByName($name);
        $this->assertNotNull($route, "{$name} is not registered.");

        return $route->gatherMiddleware();
    }

    // =========================================================================
    // 1. Wiring
    // =========================================================================

    public function test_the_feature_is_on_and_the_user_model_is_a_passkey_user(): void
    {
        $this->assertTrue(Features::enabled(Features::passkeys()));
        $this->assertInstanceOf(PasskeyUser::class, new User());
    }

    public function test_every_passkey_route_is_throttled_and_guarded(): void
    {
        foreach (['passkey.login-options', 'passkey.login'] as $name) {
            $middleware = $this->middlewareOf($name);
            $this->assertContains('guest:web', $middleware, $name);
            $this->assertContains('throttle:passkeys', $middleware, $name);
        }

        foreach (['passkey.confirm-options', 'passkey.confirm'] as $name) {
            $middleware = $this->middlewareOf($name);
            $this->assertContains('auth:web', $middleware, $name);
            $this->assertContains('throttle:passkeys', $middleware, $name);
        }

        foreach (['passkey.registration-options', 'passkey.store'] as $name) {
            $middleware = $this->middlewareOf($name);
            $this->assertContains('auth:web', $middleware, $name);
            $this->assertContains('password.confirm', $middleware, $name);
            $this->assertContains('throttle:passkeys', $middleware, $name);
        }

        $middleware = $this->middlewareOf('passkey.destroy');
        $this->assertContains('auth:web', $middleware);
        $this->assertContains('password.confirm', $middleware);
    }

    public function test_the_relying_party_is_the_host_of_app_url(): void
    {
        $this->assertSame(parse_url(config('app.url'), PHP_URL_HOST), config('passkeys.relying_party_id'));
        $this->assertSame([rtrim(config('app.url'), '/')], config('passkeys.allowed_origins'));
    }

    // =========================================================================
    // 2. Options
    // =========================================================================

    public function test_a_guest_gets_login_options(): void
    {
        $this->getJson(route('passkey.login-options'))
            ->assertOk()
            ->assertJsonPath('options.rpId', parse_url(config('app.url'), PHP_URL_HOST))
            ->assertJsonStructure(['options' => ['challenge']]);
    }

    public function test_registration_options_need_a_confirmed_password(): void
    {
        $user = $this->createUser(['email' => 'passkey-options@example.test', 'role' => 'activated']);

        $this->actingAs($user)
            ->getJson(route('passkey.registration-options'))
            ->assertStatus(423);

        $this->actingAs($user)
            ->withSession(['auth.password_confirmed_at' => time()])
            ->getJson(route('passkey.registration-options'))
            ->assertOk()
            ->assertJsonPath('options.user.name', 'passkey-options@example.test');
    }

    // =========================================================================
    // 3. Signing in and confirming
    // =========================================================================

    public function test_a_verified_passkey_signs_the_user_in(): void
    {
        $user = $this->createUser(['email' => 'passkey-login@example.test', 'role' => 'activated']);
        $this->verificationReturns($this->passkeyFor($user));

        $this->getJson(route('passkey.login-options'))->assertOk();

        $this->postJson(route('passkey.login'), ['credential' => $this->assertionPayload()])
            ->assertOk()
            ->assertJsonStructure(['redirect']);

        $this->assertAuthenticatedAs($user);
    }

    public function test_a_passkey_login_does_not_ask_for_the_totp_code(): void
    {
        // The developer's decision at TODO 69: a passkey is already two
        // factors, so a user with a confirmed TOTP second factor is signed in
        // without the challenge.
        $engine = app(Google2FA::class);
        $user = $this->createUser(['email' => 'passkey-2fa@example.test', 'role' => 'activated']);
        $user->forceFill([
            'two_factor_secret' => encrypt($engine->generateSecretKey()),
            'two_factor_recovery_codes' => encrypt(json_encode(['one'])),
            'two_factor_confirmed_at' => now(),
        ])->save();
        $this->verificationReturns($this->passkeyFor($user));

        $this->getJson(route('passkey.login-options'))->assertOk();

        $this->postJson(route('passkey.login'), ['credential' => $this->assertionPayload()])
            ->assertOk()
            ->assertJsonMissingPath('two_factor');

        $this->assertAuthenticatedAs($user);
    }

    public function test_a_login_without_options_in_the_session_is_rejected(): void
    {
        $user = $this->createUser(['email' => 'passkey-no-options@example.test', 'role' => 'activated']);
        $this->verificationReturns($this->passkeyFor($user));

        $this->postJson(route('passkey.login'), ['credential' => $this->assertionPayload()])
            ->assertStatus(422)
            ->assertJsonValidationErrors('credential');

        $this->assertGuest();
    }

    public function test_a_passkey_confirms_the_password(): void
    {
        $user = $this->createUser(['email' => 'passkey-confirm@example.test', 'role' => 'activated']);
        $this->verificationReturns($this->passkeyFor($user));

        $this->actingAs($user)->getJson(route('passkey.confirm-options'))->assertOk();

        $this->actingAs($user)
            ->postJson(route('passkey.confirm'), ['credential' => $this->assertionPayload()])
            ->assertOk();

        $this->assertNotNull(session('auth.password_confirmed_at'));
    }

    // =========================================================================
    // 4. Deleting
    // =========================================================================

    public function test_a_user_deletes_their_own_passkey(): void
    {
        $user = $this->createUser(['email' => 'passkey-delete@example.test', 'role' => 'activated']);
        $passkey = $this->passkeyFor($user);

        $this->actingAs($user)
            ->withSession(['auth.password_confirmed_at' => time()])
            ->from(route('user.twofactorsettings'))
            ->delete(route('passkey.destroy', $passkey))
            ->assertRedirect(route('user.twofactorsettings'))
            ->assertSessionHas('status', 'passkey-deleted');

        $this->assertFalse($user->hasPasskeysEnabled());
    }

    public function test_a_user_cannot_delete_someone_elses_passkey(): void
    {
        $owner = $this->createUser(['email' => 'passkey-owner@example.test', 'role' => 'activated']);
        $other = $this->createUser(['email' => 'passkey-other@example.test', 'role' => 'activated']);
        $passkey = $this->passkeyFor($owner);

        $this->actingAs($other)
            ->withSession(['auth.password_confirmed_at' => time()])
            ->delete(route('passkey.destroy', $passkey))
            ->assertForbidden();

        $this->assertTrue($owner->hasPasskeysEnabled());
    }

    // =========================================================================
    // 5. GDPR
    // =========================================================================

    public function test_anonymizing_a_user_deletes_their_passkeys(): void
    {
        $user = $this->createUser(['email' => 'passkey-anonymize@example.test', 'role' => 'activated']);
        $this->passkeyFor($user);

        $this->assertTrue($user->anonymize());

        $this->assertSame(0, Passkey::where('user_id', $user->id)->count());
    }

    public function test_the_export_lists_passkeys_without_the_credential(): void
    {
        $user = $this->createUser(['email' => 'passkey-export@example.test', 'role' => 'activated']);
        $this->passkeyFor($user, 'Laptop');

        $export = $user->fresh()->portable();

        $this->assertSame(['Laptop'], array_column($export['passkeys'], 'name'));
        $this->assertSame(['name', 'created_at', 'last_used_at'], array_keys($export['passkeys'][0]));
    }

    public function test_deleting_a_user_deletes_their_passkeys(): void
    {
        $user = $this->createUser(['email' => 'passkey-cascade@example.test', 'role' => 'activated']);
        $this->passkeyFor($user);

        $user->forceDelete();

        $this->assertSame(0, Passkey::where('user_id', $user->id)->count());
    }

    // =========================================================================
    // 6. The pages
    // =========================================================================

    public function test_the_login_page_offers_a_passkey_and_loads_the_client(): void
    {
        $this->get(route('login'))
            ->assertOk()
            ->assertSee('data-passkey="login"', false)
            ->assertSee(route('passkey.login-options'), false)
            ->assertSee('<script type="importmap">', false)
            ->assertSee('vendor/passkeys/laravel-passkeys/index.js', false);
    }

    public function test_the_settings_page_lists_the_passkeys(): void
    {
        $user = $this->createUser(['email' => 'passkey-page@example.test', 'role' => 'activated']);
        $passkey = $this->passkeyFor($user, 'Irodai gép');

        $this->actingAs($user)
            ->withSession(['auth.password_confirmed_at' => time()])
            ->get(route('user.twofactorsettings'))
            ->assertOk()
            ->assertSee('Irodai gép')
            ->assertSee(route('passkey.destroy', $passkey), false)
            ->assertSee('data-passkey="register"', false);
    }

    // Two cases, not one: switching users inside one test session trips the
    // web group's AuthenticateSession, which logs the second user out.
    public function test_the_confirm_page_offers_a_passkey_to_a_user_who_has_one(): void
    {
        $user = $this->createUser(['email' => 'passkey-confirm-some@example.test', 'role' => 'activated']);
        $this->passkeyFor($user);

        $this->actingAs($user)->get(route('password.confirm'))
            ->assertOk()
            ->assertSee('data-passkey="confirm"', false)
            ->assertSee(route('passkey.confirm-options'), false);
    }

    public function test_the_confirm_page_offers_no_passkey_to_a_user_without_one(): void
    {
        $user = $this->createUser(['email' => 'passkey-confirm-none@example.test', 'role' => 'activated']);

        $this->actingAs($user)->get(route('password.confirm'))
            ->assertOk()
            ->assertDontSee('data-passkey="confirm"', false)
            ->assertDontSee('<script type="importmap">', false);
    }
}
