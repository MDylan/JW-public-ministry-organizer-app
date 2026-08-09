<?php

namespace Tests\Feature\Auth;

use App\Actions\Fortify\PasswordValidationRules;
use Illuminate\Support\Facades\Validator;
use Tests\TestCase;

/**
 * TODO 27: a jelszószabály - előbb karakterizálva, aztán lecserélve.
 *
 * MIÉRT KELLETT EZ
 *
 * `PasswordValidationRules::passwordRules()` a `Laravel\Fortify\Rules\Password`
 * osztályt használta, ami a modern Fortify-ból eltűnt - a Phase 4 hop tehát
 * megbukott volna rajta. A csere `Illuminate\Validation\Rules\Password`-re
 * viszont NEM behelyettesítés: a két osztály mást enged át, és a suite eddig
 * egyetlen sort sem állított a szabályok tartalmáról. Ezért készült ez a fájl
 * a csere ELŐTT, a régi viselkedéssel; az alábbi esetek a csere diffjében
 * fordultak át, és ez a fordulás maga a szándék kimondása.
 *
 * A KÉT VÁLTOZÁS, MÉRVE
 *
 *  1. SZIGORÍTÁS, a felhasználó jóváhagyásával. A Fortify `requireUppercase()`
 *     bukási feltétele `Str::lower($value) === $value` volt, vagyis csak azt
 *     kérte, hogy a jelszó ne legyen csupa kisbetűs - egy csupa NAGYBETŰS
 *     jelszó (`PASSWORD1`) megfelelt neki. A `mixedCase()` nagy- ÉS kisbetűt
 *     is megkövetel, tehát az ilyen jelszó ezentúl elutasított. Csak új jelszó
 *     megadásakor jelentkezik; a meglévő jelszavakat nem érinti.
 *
 *  2. LAZÍTÁS, amit a csere hozott magával és nem volt tervezve. A Fortify
 *     `requireNumeric()`-je ASCII `[0-9]`-re illesztett, az `Illuminate`
 *     `numbers()`-e a `\pN` unicode osztályra - így egy arab-indiai számjegy
 *     ma már számjegynek számít. Nem kockázat (az entrópia nem lesz kisebb
 *     tőle), de a diffnek ki kell mondania.
 *
 * A hossz ellenőrzése a régi listában kétszer szerepelt: a Fortify szabálya
 * maga is nézte (`Str::length($value) >= 8`), és mellette ott volt a `min:8`.
 * Az új listában a `Password::min(8)` viszi, a duplikáció megszűnt.
 */
class PasswordRuleTest extends TestCase
{
    /**
     * @dataProvider passwordCases
     */
    public function test_the_current_password_rules_accept_exactly_these_passwords(string $password, bool $expectedToPass, string $why): void
    {
        $validator = Validator::make(
            ['password' => $password, 'password_confirmation' => $password],
            ['password' => $this->currentRules()]
        );

        $this->assertSame($expectedToPass, $validator->passes(), $why.' - jelszó: ['.$password.']');
    }

    public static function passwordCases(): array
    {
        return [
            'érvényes: nagybetű, kisbetű, szám, 9 karakter' => ['Password1', true, 'Ez a szabály szándékolt esete'],
            'csupa nagybetű és szám' => ['PASSWORD1', false, 'SZIGORÍTÁS: a Fortify csak azt kérte, hogy ne legyen csupa kisbetűs, a mixedCase() kisbetűt is megkövetel'],
            'csupa kisbetű és szám' => ['password1', false, 'mixedCase: nagybetű nélkül bukik - ez a régi szabállyal is így volt'],
            'nagybetű, de nincs szám' => ['Password', false, 'numbers(): számjegy nélkül bukik'],
            'túl rövid, egyébként jó' => ['Passw1', false, 'min(8)'],
            'pontosan 8 karakter' => ['Passwor1', true, 'A 8 karakter még megfelel'],
            'speciális karakter nem kötelező' => ['Password1', true, 'symbols() nincs bekapcsolva'],
            'ékezetes nagybetű számít nagybetűnek' => ['Árvíztűrő1', true, 'A mixedCase() a \p{Lu}/\p{Ll} unicode osztályokat nézi, tehát az ékezetes betűk is számítanak'],
            'unicode számjegy MOST MÁR számít' => ['Password١', true, 'LAZÍTÁS: numbers() a \pN unicode osztályra illeszt, a Fortify ASCII [0-9]-re illesztett'],
        ];
    }

    public function test_a_mismatched_confirmation_is_rejected(): void
    {
        $validator = Validator::make(
            ['password' => 'Password1', 'password_confirmation' => 'Password2'],
            ['password' => $this->currentRules()]
        );

        $this->assertFalse($validator->passes(), 'A `confirmed` szabály a szabálylista része.');
    }

    public function test_an_empty_password_is_rejected(): void
    {
        $validator = Validator::make(
            ['password' => '', 'password_confirmation' => ''],
            ['password' => $this->currentRules()]
        );

        $this->assertFalse($validator->passes());
    }

    /**
     * A hibaüzenet nem mellékes, és ez volt a csere harmadik mérhető
     * következménye. Mindkét jelszóosztály a teljes angol mondatot használja
     * fordítási kulcsként. A Fortify mondata le VAN fordítva magyarra; az
     * `Illuminate` szabály három mondata a `hu.json`-ban kulcsként ott volt,
     * de fordítatlanul - a csere tehát angolra váltotta volna a magyar
     * felhasználók hibaüzenetét. A fordítás ezért ugyanennek a change setnek
     * a része.
     *
     * Nyitva marad: `ro.json` és `sk.json` ugyanígy fordítatlan (a `de` és az
     * `fr` már tartalmazza mindhármat). Ott ma az angol mondat jelenik meg.
     */
    public function test_the_failure_message_is_translated_to_hungarian(): void
    {
        $this->app->setLocale('hu');

        $validator = Validator::make(
            ['password' => 'password1', 'password_confirmation' => 'password1'],
            ['password' => $this->currentRules()]
        );

        $this->assertFalse($validator->passes());

        $message = $validator->errors()->first('password');
        $this->assertStringNotContainsString('must contain', $message, 'A hibaüzenet magyarul kell megjelenjen, nem az angol kulcs nyersen.');
        $this->assertStringContainsString('nagybetűt', $message);
    }

    private function currentRules(): array
    {
        return (new class
        {
            use PasswordValidationRules;

            public function expose(): array
            {
                return $this->passwordRules();
            }
        })->expose();
    }
}
