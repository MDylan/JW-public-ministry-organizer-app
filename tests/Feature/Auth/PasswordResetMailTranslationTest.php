<?php

namespace Tests\Feature\Auth;

use App\Models\User;
use Illuminate\Auth\Notifications\ResetPassword;
use Tests\TestCase;

/**
 * Roadmap TODO 67: the password reset mail is the framework's, and so are
 * its translation keys.
 *
 * Fortify sends Illuminate\Auth\Notifications\ResetPassword unchanged, and
 * every string in it goes through Lang::get() with the English text as the
 * key - which this application translates in lang/*.json. Laravel 13 renamed
 * the subject from "Reset Password Notification" to "Reset your password",
 * and with the old key in the files every Hungarian, German, French,
 * Romanian and Slovak user got an English subject on an otherwise translated
 * mail. Nothing failed; the fallback is the key itself.
 *
 * So the check reads the keys off the rendered mail instead of listing them:
 * the next renamed string fails here, naming itself.
 */
class PasswordResetMailTranslationTest extends TestCase
{
    public function test_every_string_of_the_reset_mail_is_a_hungarian_key(): void
    {
        $hungarian = json_decode(file_get_contents(lang_path('hu.json')), true);

        app()->setLocale('en');
        $english = (new ResetPassword('token'))->toMail(User::factory()->make());

        $strings = array_merge(
            [$english->subject, $english->actionText],
            $english->introLines,
            $english->outroLines
        );

        // The expiry line carries its :count already replaced in the
        // rendered text, so it is compared through its key instead.
        $strings = array_map(
            fn (string $line): string => preg_replace('/\d+ minutes\./', ':count minutes.', $line),
            $strings
        );

        $this->assertCount(5, $strings);
        $this->assertSame([], array_values(array_diff($strings, array_keys($hungarian))));
    }

    public function test_the_hungarian_reset_mail_has_a_hungarian_subject(): void
    {
        app()->setLocale('hu');

        $mail = (new ResetPassword('token'))->toMail(User::factory()->make());

        $this->assertSame(
            json_decode(file_get_contents(lang_path('hu.json')), true)['Reset your password'],
            $mail->subject
        );
    }
}
