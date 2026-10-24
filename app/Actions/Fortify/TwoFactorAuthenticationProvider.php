<?php

namespace App\Actions\Fortify;

use Illuminate\Contracts\Cache\Repository;
use Laravel\Fortify\TwoFactorAuthenticationProvider as FortifyTwoFactorAuthenticationProvider;
use PragmaRX\Google2FA\Google2FA;
use Throwable;

/**
 * Actually preventing TOTP replay (CVE-2022-25838).
 *
 * Fortify v1.11.2 - the first version the advisory marks as fixed -
 * caches the used code's timestamp, and from then on only accepts a
 * STRICTLY newer code via the `verifyKeyNewer()` call. The fix, however, doesn't
 * land, because `PragmaRX\Google2FA`'s `findValidOTP()` on the *very first* call -
 * when there's no stored timestamp yet, i.e. `$oldTimestamp === null` - returns
 * not a counter but the value `true`. Fortify stores that away, and the
 * second call then passes `true` along as `$oldTimestamp`, from which the
 * expression `max($timestamp - $window, true + 1)` computes `2` - meaning
 * the starting timestamp doesn't shift at all, and the same code passes a second time too.
 *
 * The provider doesn't just normalize the result into a counter: it separates users by
 * hashing the credential and the counter together, then uses an atomic cache-add to
 * ensure that even among concurrent requests only one can redeem the code.
 *
 * The binding lives in App\Providers\FortifyServiceProvider::boot(). Both
 * verification paths - the login challenge (Fortify TwoFactorLoginRequest) and
 * the confirmation (Fortify ConfirmTwoFactorAuthentication, since roadmap
 * TODO 69) - resolve the provider through the contract, so it affects both.
 *
 * STILL NEEDED ON FORTIFY 1.36.2 (checked at TODO 69). The package now turns
 * Google2FA's `true` into a timestamp itself, so the replay this class was
 * written for is closed upstream too. Two differences remain: the package
 * keys its cache entry by the code alone (`md5($code)`), so two users who
 * happen to type the same six digits in the same window collide, and it
 * reads then writes the entry, so two concurrent requests can both redeem
 * one code. This class keys by secret and counter and claims with add().
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
        // Because of the non-null starting value, Google2FA always returns the
        // actually matching time counter, not the value `true`.
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
            // Repository::add() is atomic: exactly one concurrent request can
            // claim the same credential + time counter pair.
            return $this->cache->add($key, true, $ttl);
        } catch (Throwable $exception) {
            // Without the cache we can't prove single use.
            // The exception report contains neither the secret nor the TOTP code.
            report($exception);

            return false;
        }
    }
}
