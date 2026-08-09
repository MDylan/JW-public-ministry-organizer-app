<?php

namespace Tests\Feature\Auth;

use App\Actions\Fortify\TwoFactorAuthenticationProvider as ReplaySafeTwoFactorAuthenticationProvider;
use Illuminate\Contracts\Cache\Repository as CacheRepository;
use Laravel\Fortify\Contracts\TwoFactorAuthenticationProvider;
use PragmaRX\Google2FA\Google2FA;
use RuntimeException;
use Tests\Feature\FeatureTestCase;

/**
 * v1-patch H: CVE-2022-25838 (GHSA-6w4v-qr4m-97gg) regresszió.
 *
 * A telepített Fortify v1.10.2 `TwoFactorAuthenticationProvider::verify()`-ja
 * egyszerűen `verifyKey()`-t hívott, ami minden érvényes időablakban IGAZ-at ad
 * - akárhányszor. Egy egyszer megszerzett TOTP-kód (vállfelettről leolvasva,
 * egy phishing oldalról továbbjátszva, egy naplóból kibányászva) az ablak
 * végéig újra és újra beváltható volt. Ettől a "one-time" jelző elveszett.
 *
 * A tényleges védelem az App\Actions\Fortify\TwoFactorAuthenticationProvider:
 * valódi időszámlálót kér, credentialenként elkülönített cache-kulcsot képez,
 * és atomikus Cache::add() művelettel csak egy beváltást enged.
 *
 * A `CACHE_DRIVER` az éles rendszeren `file`; a Laravel FileStore add() művelete
 * fájlzárral atomikus. A tesztkörnyezet közös `array` store-ja a szerződés
 * funkcionális oldalát bizonyítja, külön mock pedig rögzíti az atomi API-t.
 */
class TwoFactorReplayTest extends FeatureTestCase
{
    public function test_the_same_totp_code_cannot_be_verified_twice(): void
    {
        $engine = app(Google2FA::class);
        $secret = $engine->generateSecretKey();
        $code = $engine->getCurrentOtp($secret);

        $provider = app(TwoFactorAuthenticationProvider::class);

        $this->assertTrue($provider->verify($secret, $code), 'Az első beváltásnak sikerülnie kell.');
        $this->assertFalse($provider->verify($secret, $code), 'Ugyanaz a kód másodszor nem fogadható el.');
    }

    public function test_the_provider_is_wired_with_a_cache_repository(): void
    {
        // A visszajátszás-védelem KIZÁRÓLAG akkor működik, ha a providerbe
        // tényleg bekerül a cache. A projekt a User modellben a contractot
        // oldja fel (App\Models\User::confirmTwoFactorAuth), tehát a Fortify
        // saját kötésén megy keresztül - ezt rögzítjük.
        $provider = app(TwoFactorAuthenticationProvider::class);

        $this->assertInstanceOf(
            \App\Actions\Fortify\TwoFactorAuthenticationProvider::class,
            $provider,
            'A Fortify saját providerének kötése nem maradhat érvényben.'
        );

        $cache = (new \ReflectionClass(\Laravel\Fortify\TwoFactorAuthenticationProvider::class))
            ->getProperty('cache');
        $cache->setAccessible(true);

        $this->assertInstanceOf(
            \Illuminate\Contracts\Cache\Repository::class,
            $cache->getValue($provider),
            'Cache nélkül a verify() minden kódot korlátlanul elfogadna.'
        );
    }

    public function test_the_cache_repository_is_a_required_constructor_dependency(): void
    {
        $constructor = (new \ReflectionClass(ReplaySafeTwoFactorAuthenticationProvider::class))
            ->getConstructor();
        $cache = $constructor->getParameters()[1];

        $this->assertSame(CacheRepository::class, $cache->getType()->getName());
        $this->assertFalse($cache->allowsNull());
        $this->assertFalse($cache->isOptional());
    }

    public function test_equal_numeric_codes_from_different_secrets_do_not_collide(): void
    {
        $engine = \Mockery::mock(Google2FA::class);
        $engine->shouldReceive('verifyKeyNewer')->twice()->andReturn(123456);
        $engine->shouldReceive('getKeyRegeneration')->twice()->andReturn(30);
        $engine->shouldReceive('getWindow')->twice()->andReturn(1);

        $provider = new ReplaySafeTwoFactorAuthenticationProvider(
            $engine,
            app(CacheRepository::class)
        );

        $this->assertTrue($provider->verify('first-secret', '123456'));
        $this->assertTrue($provider->verify('second-secret', '123456'));
    }

    public function test_redemption_uses_atomic_add_with_a_full_window_ttl(): void
    {
        $engine = \Mockery::mock(Google2FA::class);
        $engine->shouldReceive('verifyKeyNewer')
            ->once()
            ->with('credential-secret', '654321', 0)
            ->andReturn(123456);
        $engine->shouldReceive('getKeyRegeneration')->once()->andReturn(30);
        $engine->shouldReceive('getWindow')->once()->andReturn(2);

        $cache = \Mockery::mock(CacheRepository::class);
        $cache->shouldReceive('add')
            ->once()
            ->withArgs(function ($key, $value, $ttl): bool {
                $this->assertStringStartsWith('fortify.2fa_codes.', $key);
                $this->assertStringNotContainsString('credential-secret', $key);
                $this->assertStringNotContainsString('654321', $key);
                $this->assertTrue($value);
                $this->assertSame(150, $ttl);

                return true;
            })
            ->andReturn(true);

        $provider = new ReplaySafeTwoFactorAuthenticationProvider($engine, $cache);

        $this->assertTrue($provider->verify('credential-secret', '654321'));
    }

    public function test_cache_failure_rejects_the_code(): void
    {
        $engine = \Mockery::mock(Google2FA::class);
        $engine->shouldReceive('verifyKeyNewer')->once()->andReturn(123456);
        $engine->shouldReceive('getKeyRegeneration')->once()->andReturn(30);
        $engine->shouldReceive('getWindow')->once()->andReturn(1);

        $cache = \Mockery::mock(CacheRepository::class);
        $cache->shouldReceive('add')->once()->andThrow(new RuntimeException('Cache unavailable.'));

        $provider = new ReplaySafeTwoFactorAuthenticationProvider($engine, $cache);

        $this->assertFalse($provider->verify('credential-secret', '123456'));
    }

    public function test_the_user_model_confirmation_path_also_rejects_a_replayed_code(): void
    {
        // Az alkalmazás SAJÁT 2FA-megerősítése (POST /user/2fa-confirm ->
        // User::confirmTwoFactorAuth) ugyanezt a providert használja, tehát a
        // javítás ezen az ágon is hat.
        $engine = app(Google2FA::class);
        $secret = $engine->generateSecretKey();

        $user = $this->createUser([
            'email' => 'twofactor-replay@example.test',
            'role'  => 'activated',
        ]);
        $user->forceFill(['two_factor_secret' => encrypt($secret)])->save();

        $code = $engine->getCurrentOtp($secret);

        $this->assertTrue($user->confirmTwoFactorAuth($code));

        $user->forceFill(['two_factor_confirmed' => 0])->save();

        $this->assertFalse($user->fresh()->confirmTwoFactorAuth($code));
    }
}
