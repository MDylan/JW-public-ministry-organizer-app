<?php

namespace App\Actions\Fortify;

use Illuminate\Validation\Rules\Password;

trait PasswordValidationRules
{
    /**
     * Get the validation rules used to validate passwords.
     *
     * TODO 27: the rule previously used `Laravel\Fortify\Rules\Password`,
     * which is gone from modern Fortify. The replacement is NOT a drop-in swap: Fortify's
     * `requireUppercase()` only required the password not to be all
     * lowercase (`Str::lower($value) === $value`), so an all-UPPERCASE
     * password satisfied it. `mixedCase()` requires both upper AND lower case,
     * i.e. this is a tightening - the maintainer's decision. It only kicks in when a new
     * password is set; existing passwords are unaffected.
     *
     * The length is carried by `Password::min(8)`, so the previous separate `min:8`
     * is not needed here - the old rule checked the length itself too, so that was
     * duplication. `confirmed` stays, as neither password class contains it.
     *
     * @return array
     */
    protected function passwordRules()
    {
        return ['required', 'string', Password::min(8)->mixedCase()->numbers(), 'confirmed'];
    }
}
