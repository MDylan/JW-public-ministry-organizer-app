<?php

namespace Tests\Feature\Setup;

use App\Models\Settings;
use App\Notifications\TestNotification;
use Illuminate\Support\Facades\Notification;

/**
 * TODO 12: the installer's mail step.
 *
 * configure() does not just write configuration: it is the ONLY place in the
 * whole application where the 'languages' and 'default_language' Settings
 * rows are created. If this step is skipped or fails, the application starts
 * without a language setting.
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
    // 1. Validation
    // =========================================================================

    public function test_only_three_mailers_are_accepted(): void
    {
        $this->post(route('setup.save-mail'), $this->smtpPayload(['MAIL_MAILER' => 'array']))
            ->assertSessionHasErrors('MAIL_MAILER');
    }

    public function test_the_smtp_fields_are_only_required_for_smtp(): void
    {
        // required_if:MAIL_MAILER,smtp - for sendmail, the host and the rest
        // can be omitted. The from address, however, is always required and validated.
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
    // 2. The success branch
    // =========================================================================

    public function test_a_successful_test_message_advances_the_wizard(): void
    {
        // Notification::fake() here is not a convenience: the controller sends a
        // REAL message with the given settings, and advancing to the next step
        // depends on the send succeeding. The fake lets us measure the steps
        // after the send without actually needing an SMTP server.
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

        // The language comes from the APP_LANG env variable, and is inserted as visible.
        $this->assertCount(1, $languages);
        $this->assertTrue(reset($languages)['visible']);
    }

    // =========================================================================
    // 3. The error branch
    // =========================================================================

    public function test_a_failing_transport_keeps_the_user_on_the_form(): void
    {
        // WITHOUT a fake: there is no SMTP on port 1, so the send throws an
        // exception, which the controller catches and turns into a message. The .env is NOT written.
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
