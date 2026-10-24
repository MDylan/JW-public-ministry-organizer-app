<?php

namespace Tests\Feature\Auth;

use Illuminate\Support\Str;
use PragmaRX\Google2FA\Google2FA;
use Tests\Feature\FeatureTestCase;

/**
 * The recovery codes as a downloadable .txt file (user.recovery-codes.download).
 *
 * The file carries the same codes the settings page shows, behind the same
 * gate, and is never cached: it is a credential.
 */
class RecoveryCodesDownloadTest extends FeatureTestCase
{
    private const CODES = ['AbCdEfGhIj-KlMnOpQrSt', 'UvWxYzAbCd-EfGhIjKlMn'];

    private function userWithTwoFactor(string $email, bool $confirmed = true)
    {
        $user = $this->createUser([
            'email' => $email,
            'role'  => 'activated',
        ]);

        $user->forceFill([
            'two_factor_secret' => encrypt(app(Google2FA::class)->generateSecretKey()),
            'two_factor_recovery_codes' => encrypt(json_encode(self::CODES)),
            'two_factor_confirmed_at' => $confirmed ? now() : null,
        ])->save();

        return $user->fresh();
    }

    public function test_the_codes_download_as_a_plain_text_attachment(): void
    {
        $user = $this->userWithTwoFactor('recovery-download@example.test');

        $response = $this->actingAs($user)
            ->withSession(['auth.password_confirmed_at' => time()])
            ->get(route('user.recovery-codes.download'))
            ->assertOk()
            ->assertHeader('Content-Type', 'text/plain; charset=UTF-8')
            ->assertDownload(Str::slug(config('app.name')).'-recovery-codes.txt');

        $this->assertStringContainsString('no-store', $response->headers->get('Cache-Control'));

        $lines = explode("\r\n", $response->getContent());
        foreach (self::CODES as $code) {
            $this->assertContains($code, $lines, 'Each code must stand on a line of its own.');
        }
        $this->assertStringContainsString('recovery-download@example.test', $response->getContent());
    }

    public function test_the_download_requires_a_confirmed_password(): void
    {
        $user = $this->userWithTwoFactor('recovery-download-password@example.test');

        $this->actingAs($user)
            ->get(route('user.recovery-codes.download'))
            ->assertRedirect(route('password.confirm'));
    }

    public function test_a_guest_is_sent_to_the_login(): void
    {
        $this->get(route('user.recovery-codes.download'))
            ->assertRedirect(route('login'));
    }

    public function test_an_unconfirmed_second_factor_has_nothing_to_download(): void
    {
        $user = $this->userWithTwoFactor('recovery-download-unconfirmed@example.test', confirmed: false);

        $this->actingAs($user)
            ->withSession(['auth.password_confirmed_at' => time()])
            ->get(route('user.recovery-codes.download'))
            ->assertNotFound();
    }

    public function test_the_settings_page_links_the_download(): void
    {
        $user = $this->userWithTwoFactor('recovery-download-link@example.test');

        $this->actingAs($user)
            ->withSession(['auth.password_confirmed_at' => time()])
            ->get(route('user.twofactorsettings'))
            ->assertOk()
            ->assertSee(route('user.recovery-codes.download'), false);
    }
}
