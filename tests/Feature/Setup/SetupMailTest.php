<?php

namespace Tests\Feature\Setup;

use App\Models\Settings;
use App\Notifications\TestNotification;
use Illuminate\Support\Facades\Notification;

/**
 * TODO 12: a telepítő levelezés-lépése.
 *
 * A configure() nem csak konfigurációt ír: ez az EGYETLEN hely az egész
 * alkalmazásban, ahol a 'languages' és a 'default_language' Settings sorok
 * létrejönnek. Ha ez a lépés kimarad vagy elhasal, az alkalmazás
 * nyelvbeállítás nélkül indul.
 */
class SetupMailTest extends SetupTestCase
{
    private function smtpPayload(array $overrides = []): array
    {
        return array_merge([
            'MAIL_MAILER' => 'smtp',
            'MAIL_HOST' => 'smtp.example.test',
            'MAIL_PORT' => '2525',
            'MAIL_ENCRYPTION' => 'tls',
            'MAIL_USERNAME' => 'telepito',
            'MAIL_PASSWORD' => 'titok',
            'MAIL_FROM_ADDRESS' => 'admin@example.test',
        ], $overrides);
    }

    // =========================================================================
    // 1. Validáció
    // =========================================================================

    public function test_only_three_mailers_are_accepted(): void
    {
        $this->post(route('setup.save-mail'), $this->smtpPayload(['MAIL_MAILER' => 'array']))
            ->assertSessionHasErrors('MAIL_MAILER');
    }

    public function test_the_smtp_fields_are_only_required_for_smtp(): void
    {
        // required_if:MAIL_MAILER,smtp - sendmail esetén a host és a többi
        // elhagyható. A feladó címe viszont mindig kötelező és validált.
        $this->post(route('setup.save-mail'), [
            'MAIL_MAILER' => 'sendmail',
            'MAIL_FROM_ADDRESS' => 'admin@example.test',
        ])->assertSessionDoesntHaveErrors(['MAIL_HOST', 'MAIL_PORT', 'MAIL_USERNAME']);
    }

    public function test_the_from_address_must_be_an_email(): void
    {
        $this->post(route('setup.save-mail'), $this->smtpPayload(['MAIL_FROM_ADDRESS' => 'nem-cim']))
            ->assertSessionHasErrors('MAIL_FROM_ADDRESS');
    }

    // =========================================================================
    // 2. A sikeres ág
    // =========================================================================

    public function test_a_successful_test_message_advances_the_wizard(): void
    {
        // A Notification::fake() itt nem kényelmi elem: a kontroller VALÓDI
        // levelet küld a megadott beállításokkal, és a küldés sikerén múlik a
        // továbblépés. A fake teszi lehetővé, hogy a küldés utáni lépéseket
        // mérjük anélkül, hogy tényleg SMTP-szervert keresnénk.
        Notification::fake();

        $this->withTemporaryEnvFile(function () {
            $this->post(route('setup.save-mail'), $this->smtpPayload())
                ->assertRedirect(route('setup.account'));
        });

        Notification::assertSentOnDemand(TestNotification::class);
    }

    public function test_the_credentials_are_written_into_the_env_file(): void
    {
        Notification::fake();

        $contents = $this->withTemporaryEnvFile(function () {
            $this->post(route('setup.save-mail'), $this->smtpPayload());
        });

        $this->assertStringContainsString('MAIL_HOST="smtp.example.test"', $contents);
        $this->assertStringContainsString('MAIL_PORT="2525"', $contents);
        $this->assertStringContainsString('MAIL_FROM_ADDRESS="admin@example.test"', $contents);
    }

    public function test_the_language_settings_rows_are_created_here(): void
    {
        Notification::fake();

        $this->assertDatabaseMissing('settings', ['name' => 'languages']);

        $this->withTemporaryEnvFile(function () {
            $this->post(route('setup.save-mail'), $this->smtpPayload());
        });

        $this->assertDatabaseHas('settings', ['name' => 'languages']);
        $this->assertDatabaseHas('settings', ['name' => 'default_language']);

        $languages = json_decode(
            Settings::where('name', 'languages')->value('value'),
            true
        );

        // A nyelv az APP_LANG env-változóból jön, és láthatóként kerül be.
        $this->assertCount(1, $languages);
        $this->assertTrue(reset($languages)['visible']);
    }

    // =========================================================================
    // 3. A hibaág
    // =========================================================================

    public function test_a_failing_transport_keeps_the_user_on_the_form(): void
    {
        // Fake NÉLKÜL: az 1-es porton nincs SMTP, tehát a küldés kivételt dob,
        // amit a kontroller elkap és üzenetté alakít. A .env NEM íródik.
        $contents = $this->withTemporaryEnvFile(function () {
            $response = $this->post(route('setup.save-mail'), $this->smtpPayload([
                'MAIL_HOST' => '127.0.0.1',
                'MAIL_PORT' => '1',
            ]));

            $response->assertRedirect();
            $response->assertSessionHas('error_message');
            $this->assertNotSame(route('setup.account'), $response->headers->get('Location'));
            $this->assertStringStartsWith(
                trans('settings.mail_test_error'),
                session('error_message')
            );
        });

        $this->assertStringNotContainsString('MAIL_HOST="127.0.0.1"', $contents);
    }
}
