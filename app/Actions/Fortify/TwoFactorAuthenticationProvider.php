<?php

namespace App\Actions\Fortify;

use Illuminate\Contracts\Cache\Repository;
use Laravel\Fortify\TwoFactorAuthenticationProvider as FortifyTwoFactorAuthenticationProvider;
use PragmaRX\Google2FA\Google2FA;
use Throwable;

/**
 * A TOTP-visszajátszás tényleges megakadályozása (CVE-2022-25838).
 *
 * A Fortify v1.11.2 - az az első verzió, amit az advisory javítottnak jelöl -
 * gyorsítótárazza a felhasznált kód időbélyegét, és onnantól `verifyKeyNewer()`
 * hívással csak SZIGORÚAN újabb kódot fogadna el. A javítás azonban nem ér
 * célba, mert a `PragmaRX\Google2FA` `findValidOTP()`-ja a *legelső* híváskor -
 * amikor még nincs eltárolt időbélyeg, tehát `$oldTimestamp === null` - nem
 * számlálót, hanem a `true` értéket adja vissza. A Fortify azt teszi el, a
 * második hívás pedig `true`-t ad tovább `$oldTimestamp`-ként, amiből a
 * `max($timestamp - $window, true + 1)` kifejezés `2`-t számol - vagyis a
 * kezdőidőbélyeg semmivel nem tolódik el, és ugyanaz a kód másodszor is átmegy.
 *
 * A provider nemcsak számlálóvá normalizálja az eredményt: a credential és a
 * számláló hashével elkülöníti a felhasználókat, majd atomikus cache-adddal
 * biztosítja, hogy párhuzamos kérések közül is csak egy válthassa be a kódot.
 *
 * A kötés az App\Providers\FortifyServiceProvider::boot()-ban él, ugyanúgy,
 * ahogy a DisableTwoFactorAuthentication felüldefiniálása. Mindkét ellenőrzési
 * útvonal - a bejelentkezési kihívás (Fortify TwoFactorLoginRequest) és az
 * alkalmazás saját megerősítése (App\Models\User::confirmTwoFactorAuth) - a
 * contracton keresztül oldja fel a providert, tehát mindkettőre hat.
 */
class TwoFactorAuthenticationProvider extends FortifyTwoFactorAuthenticationProvider
{
    /**
     * Create a replay-safe two factor authentication provider.
     */
    public function __construct(Google2FA $engine, Repository $cache)
    {
        parent::__construct($engine, $cache);
    }

    /**
     * Verify the given code.
     *
     * @param  string  $secret
     * @param  string  $code
     * @return bool
     */
    public function verify($secret, $code)
    {
        // A nem null kezdőérték miatt a Google2FA mindig a ténylegesen
        // illeszkedő időszámlálót adja vissza, nem a `true` értéket.
        $timestamp = $this->engine->verifyKeyNewer(
            $secret, $code, 0
        );

        if ($timestamp === false) {
            return false;
        }

        $key = 'fortify.2fa_codes.'.hash('sha256', $secret.'|'.$timestamp);
        $regeneration = max(1, (int) $this->engine->getKeyRegeneration());
        $window = max(0, (int) $this->engine->getWindow());
        $ttl = max($regeneration, (2 * $window + 1) * $regeneration);

        try {
            // Repository::add() atomikus: pontosan egy párhuzamos kérés tudja
            // lefoglalni ugyanazt a credential + időszámláló párost.
            return $this->cache->add($key, true, $ttl);
        } catch (Throwable $exception) {
            // Cache nélkül nem tudjuk bizonyítani az egyszeri felhasználást.
            // A kivételjelentés nem tartalmazza sem a secretet, sem a TOTP-kódot.
            report($exception);

            return false;
        }
    }
}
