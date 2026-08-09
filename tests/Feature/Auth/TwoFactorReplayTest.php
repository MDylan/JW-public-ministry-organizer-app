<?php

namespace Tests\Feature\Auth;

use Laravel\Fortify\Contracts\TwoFactorAuthenticationProvider;
use PragmaRX\Google2FA\Google2FA;
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
 * A v1.11.2 - az az első verzió, amit az advisory javítottnak jelöl -
 * gyorsítótárba teszi a felhasznált kód időbélyegét, és `verifyKeyNewer()`-rel
 * csak szigorúan újabb kódot fogadna el. MÉRVE: ez a javítás önmagában nem ér
 * célba, mert a google2fa a legelső hívásnál `true`-t ad számláló helyett, és
 * abból a következő hívás nem tud "újabbat" követelni. A tényleges védelmet az
 * App\Actions\Fortify\TwoFactorAuthenticationProvider adja, amit a
 * FortifyServiceProvider köt be; a részletek ott vannak leírva.
 *
 * A `CACHE_DRIVER` az éles rendszeren `file`, tehát a védelem valóban működik;
 * a tesztkörnyezet `array` store-ja a teszt-metóduson belül perzisztens, ezért
 * a visszajátszás itt bizonyítható.
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
