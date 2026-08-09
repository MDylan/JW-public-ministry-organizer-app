<?php

namespace App\Actions\Fortify;

use Illuminate\Validation\Rules\Password;

trait PasswordValidationRules
{
    /**
     * Get the validation rules used to validate passwords.
     *
     * TODO 27: a szabály korábban `Laravel\Fortify\Rules\Password`-öt használt,
     * ami a modern Fortify-ból eltűnt. A csere NEM behelyettesítés: a Fortify
     * `requireUppercase()`-e csak azt kérte, hogy a jelszó ne legyen csupa
     * kisbetűs (`Str::lower($value) === $value`), tehát egy csupa NAGYBETŰS
     * jelszó megfelelt neki. A `mixedCase()` nagy- ÉS kisbetűt is megkövetel,
     * vagyis ez szigorítás - a felhasználó döntése. Csak új jelszó megadásakor
     * jelentkezik; a meglévő jelszavakat nem érinti.
     *
     * A hosszt a `Password::min(8)` viszi, ezért a korábbi külön `min:8` nem
     * kell ide - a régi szabály maga is nézte a hosszt, tehát az duplikáció
     * volt. A `confirmed` marad, azt egyik jelszóosztály sem tartalmazza.
     *
     * @return array
     */
    protected function passwordRules()
    {
        return ['required', 'string', Password::min(8)->mixedCase()->numbers(), 'confirmed'];
    }
}
