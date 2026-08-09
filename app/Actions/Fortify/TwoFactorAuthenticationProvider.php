<?php

namespace App\Actions\Fortify;

use Laravel\Fortify\TwoFactorAuthenticationProvider as FortifyTwoFactorAuthenticationProvider;

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
 * A Fortify 1.x későbbi kiadásai pontosan ezt az egy elágazást szúrták be. Itt
 * nem verziót lépünk (a következő minor, az 1.12.0, egy `two_factor_confirmed_at`
 * oszlopot vezetne be, ami ütközik a projekt saját `two_factor_confirmed`
 * mezőjével és folyamatával), hanem ugyanazt a normalizálást vesszük át.
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
     * Verify the given code.
     *
     * @param  string  $secret
     * @param  string  $code
     * @return bool
     */
    public function verify($secret, $code)
    {
        $key = 'fortify.2fa_codes.'.md5($code);

        $timestamp = $this->engine->verifyKeyNewer(
            $secret, $code, optional($this->cache)->get($key)
        );

        if ($timestamp === false) {
            return false;
        }

        // EZ AZ EGY SOR a különbség: számláló nélkül a következő hívás nem tud
        // mihez képest "újabb" kódot követelni.
        if ($timestamp === true) {
            $timestamp = $this->engine->getTimestamp();
        }

        optional($this->cache)->put($key, $timestamp, ($this->engine->getWindow() ?: 1) * 60);

        return true;
    }
}
