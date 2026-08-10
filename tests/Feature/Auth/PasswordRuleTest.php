<?php

namespace Tests\Feature\Auth;

use App\Actions\Fortify\PasswordValidationRules;
use Illuminate\Support\Facades\Validator;
use Tests\TestCase;

/**
 * TODO 27: the password rule - characterized first, then replaced.
 *
 * WHY THIS WAS NEEDED
 *
 * `PasswordValidationRules::passwordRules()` used the
 * `Laravel\Fortify\Rules\Password` class, which is gone from modern
 * Fortify - so the Phase 4 hop would have failed on it. The swap to
 * `Illuminate\Validation\Rules\Password`, however, is NOT a drop-in
 * replacement: the two classes let different things through, and the suite
 * had so far not asserted a single line about the rules' content. That is
 * why this file was written BEFORE the swap, with the old behavior; the
 * cases below flipped in the swap's diff, and that flip is itself the
 * statement of intent.
 *
 * THE TWO CHANGES, MEASURED
 *
 *  1. TIGHTENING, with the user's approval. Fortify's `requireUppercase()`
 *     failure condition was `Str::lower($value) === $value`, i.e. it only
 *     required that the password not be all lowercase - an all-UPPERCASE
 *     password (`PASSWORD1`) satisfied it. `mixedCase()` requires both
 *     upper- AND lowercase, so such a password is now rejected. This only
 *     comes up when a new password is being set; it does not affect
 *     existing passwords.
 *
 *  2. RELAXATION, which the swap brought along unplanned. Fortify's
 *     `requireNumeric()` matched ASCII `[0-9]`; `Illuminate`'s `numbers()`
 *     matches the `\pN` unicode class - so an Arabic-Indic digit now counts
 *     as a digit too. Not a risk (entropy does not decrease because of it),
 *     but the diff must state it.
 *
 * The length check appeared twice in the old list: Fortify's own rule also
 * checked it (`Str::length($value) >= 8`), alongside a separate `min:8`. In
 * the new list `Password::min(8)` carries it alone; the duplication is gone.
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

        $this->assertSame($expectedToPass, $validator->passes(), $why.' - password: ['.$password.']');
    }

    public static function passwordCases(): array
    {
        return [
            'valid: uppercase, lowercase, digit, 9 characters' => ['Password1', true, "This is the rule's intended case"],
            'all uppercase and digit' => ['PASSWORD1', false, 'TIGHTENING: Fortify only required that it not be all lowercase; mixedCase() also requires a lowercase letter'],
            'all lowercase and digit' => ['password1', false, 'mixedCase: fails without an uppercase letter - this was also true under the old rule'],
            'uppercase, but no digit' => ['Password', false, 'numbers(): fails without a digit'],
            'too short, otherwise fine' => ['Passw1', false, 'min(8)'],
            'exactly 8 characters' => ['Passwor1', true, '8 characters still passes'],
            'special character is not required' => ['Password1', true, 'symbols() is not enabled'],
            'an accented uppercase letter counts as uppercase' => ['Árvíztűrő1', true, 'mixedCase() looks at the \p{Lu}/\p{Ll} unicode classes, so accented letters count too'],
            'a unicode digit counts NOW' => ['Password١', true, 'RELAXATION: numbers() matches the \pN unicode class; Fortify matched ASCII [0-9]'],
        ];
    }

    public function test_a_mismatched_confirmation_is_rejected(): void
    {
        $validator = Validator::make(
            ['password' => 'Password1', 'password_confirmation' => 'Password2'],
            ['password' => $this->currentRules()]
        );

        $this->assertFalse($validator->passes(), 'The `confirmed` rule is part of the rule list.');
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
     * The error message is not incidental, and this was the swap's third
     * measurable consequence. Both password classes use the full English
     * sentence as the translation key. Fortify's sentence IS translated into
     * Hungarian; the `Illuminate` rule's three sentences were present as keys
     * in `hu.json`, but untranslated - so the swap would have switched
     * Hungarian users' error message to English. The translation is
     * therefore part of this same change set.
     *
     * Left open: `ro.json` and `sk.json` are likewise untranslated (`de` and
     * `fr` already contain all three). There, the English sentence appears today.
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
        $this->assertStringNotContainsString('must contain', $message, 'The error message must appear in Hungarian, not as the raw English key.');
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
