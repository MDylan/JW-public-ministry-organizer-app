<?php

namespace Tests\Unit\Notifications;

use App\Notifications\EventCreatedNotification;
use App\Notifications\EventDeletedNotification;
use App\Notifications\EventStatusChangedNotification;
use App\Notifications\EventUpdatedNotification;
use App\Notifications\UserRoleIsGroupCreatorNotification;
use App\Notifications\UserWillBeAnonymizeNotification;
use Illuminate\Support\Facades\Notification as NotificationFacade;
use Tests\TestCase;

/**
 * TODO 11 -> TODO 28: from a tripwire to proof.
 *
 * Six notifications read env() AT RUNTIME. Laravel only loads the .env file
 * when there is no cached configuration - so after config:cache, env()
 * returns null outside the config files. This file originally recorded what
 * happens in that case; since v1-patch TODO 28 it records that NOTHING
 * HAPPENS ANY MORE.
 *
 * There were two severity classes, and the tests' names split them apart too:
 *  - the four Event*Notification classes' ->replyTo() and
 *    UserRoleIsGroupCreatorNotification's ->bcc() read the ADDRESS itself
 *    from env(), so sending FAILED (Swift_RfcComplianceException),
 *  - UserWillBeAnonymizeNotification only put APP_NAME into the mail's BODY,
 *    so the mail went out, with an empty name.
 *
 * The fix was a one-liner in both cases - config('mail.from.address') and
 * config('app.name') respectively - but it was only visible because there
 * was a test for it. The cases here now prove the opposite: removing the
 * environment variable no longer changes anything about the mail.
 */
class NotificationEnvFallbackTest extends TestCase
{
    private function payload(array $overrides = []): array
    {
        return array_merge([
            'userName'   => 'Tester',
            'groupName'  => 'Group A',
            'event_user' => 'Tester',
            'date'       => now()->toDateString(),
            'newService' => [
                'start' => now()->addDay()->setTime(9, 0)->format('Y-m-d H:i:s'),
                'end'   => now()->addDay()->setTime(10, 0)->format('Y-m-d H:i:s'),
            ],
            'oldService' => [
                'start' => now()->addDay()->setTime(8, 0)->format('Y-m-d H:i:s'),
                'end'   => now()->addDay()->setTime(9, 0)->format('Y-m-d H:i:s'),
            ],
            'status'     => 1,
            'reason'     => false,
            'replyTo'    => '',
            'lastDate'   => now()->addDays(15)->toDateString(),
        ], $overrides);
    }

    private function notifiable(): object
    {
        return new class {
            public int $id = 1;
            public string $email = 'env-target@example.test';
            public array $opted_out_of_notifications = [];

            public function getKey(): int
            {
                return $this->id;
            }
        };
    }

    /**
     * SIMULATION of the config:cache state, for a single key.
     *
     * The existing BuildsDomainFixtures::withEnvValue() only writes the
     * $_SERVER array, which is enough for the phpunit.xml <server> entries
     * (USE_RECAPTCHA), but not enough for the ones that come from the .env
     * file - Dotenv writes those into all three places. Env::getRepository()
     * ->clear() cannot be used: the repository is immutable, and clear() is
     * a no-op for already-loaded keys.
     *
     * IMPORTANT: this is a simulation. Under a real config:cache, EVERY key
     * comes back null, not just this one. It does not, however, touch the
     * configuration - and that is exactly what turns section 2's cases into proof.
     */
    private function withoutEnv(string $key, callable $callback)
    {
        $hadServer = array_key_exists($key, $_SERVER);
        $hadEnv    = array_key_exists($key, $_ENV);
        $server    = $_SERVER[$key] ?? null;
        $env       = $_ENV[$key] ?? null;
        $putenv    = getenv($key);

        unset($_SERVER[$key], $_ENV[$key]);
        putenv($key);

        try {
            return $callback();
        } finally {
            if ($hadServer) {
                $_SERVER[$key] = $server;
            }
            if ($hadEnv) {
                $_ENV[$key] = $env;
            }
            if ($putenv !== false) {
                putenv($key.'='.$putenv);
            }
        }
    }

    // =========================================================================
    // 1. Today's behavior
    // =========================================================================

    public function test_an_explicit_reply_to_address_wins_over_the_environment(): void
    {
        $mail = (new EventDeletedNotification($this->payload(['replyTo' => 'group@example.test'])))
            ->toMail($this->notifiable());

        $this->assertSame('group@example.test', $mail->replyTo[0][0]);
    }

    public function test_a_whitespace_only_reply_to_falls_back_to_the_configured_address(): void
    {
        // Because of the strlen(trim(...)) > 0 check, whitespace-only also
        // counts as "empty" - this is exactly how the group's replyTo field behaves.
        $mail = (new EventDeletedNotification($this->payload(['replyTo' => '   '])))
            ->toMail($this->notifiable());

        $this->assertSame(config('mail.from.address'), $mail->replyTo[0][0]);
    }

    /**
     * @dataProvider replyToNotificationProvider
     */
    public function test_every_event_notification_falls_back_to_the_same_configured_value(string $class): void
    {
        $mail = (new $class($this->payload()))->toMail($this->notifiable());

        $this->assertSame(config('mail.from.address'), $mail->replyTo[0][0]);
    }

    public function replyToNotificationProvider(): array
    {
        return [
            'EventCreatedNotification'       => [EventCreatedNotification::class],
            'EventDeletedNotification'       => [EventDeletedNotification::class],
            'EventStatusChangedNotification' => [EventStatusChangedNotification::class],
            'EventUpdatedNotification'       => [EventUpdatedNotification::class],
        ];
    }

    public function test_the_group_creator_notification_bccs_the_configured_address(): void
    {
        $mail = (new UserRoleIsGroupCreatorNotification())->toMail($this->notifiable());

        $this->assertSame(config('mail.from.address'), $mail->bcc[0][0]);
    }

    // =========================================================================
    // 2. What config:cache USED TO CAUSE - and no longer does
    // =========================================================================

    public function test_the_reply_to_address_survives_a_missing_environment_variable(): void
    {
        // FLIPPED by the v1-patch TODO 28 fix.
        //
        // Previously this address became null, because the notification read
        // it from env(). The configuration captured the value at load time,
        // so removing the environment variable no longer reaches it - and
        // that is exactly what a config:cache does.
        $mail = $this->withoutEnv('MAIL_FROM_ADDRESS', function () {
            $this->assertNull(env('MAIL_FROM_ADDRESS'), 'The simulation really does clear the key.');

            return (new EventDeletedNotification($this->payload()))->toMail($this->notifiable());
        });

        $this->assertSame(config('mail.from.address'), $mail->replyTo[0][0]);
        $this->assertNotNull($mail->replyTo[0][0]);
    }

    public function test_the_bcc_address_survives_a_missing_environment_variable(): void
    {
        $mail = $this->withoutEnv('MAIL_FROM_ADDRESS', function () {
            return (new UserRoleIsGroupCreatorNotification())->toMail($this->notifiable());
        });

        $this->assertSame(config('mail.from.address'), $mail->bcc[0][0]);
    }

    /**
     * The "hard failure" was not an assumption, and neither is its
     * disappearance: here we actually send the mail.
     *
     * MAIL_MAILER is 'array' in phpunit.xml, but the mail's envelope is
     * still built, and the recipient check strikes there. The MEASURED
     * failure previously was:
     *
     *   Swift_RfcComplianceException
     *   "Address in mailbox given [] does not comply with RFC 2822, 3.6.2."
     *
     * Phase 5 (Laravel 9) replaces SwiftMailer with Symfony Mailer, where the
     * same error would come as
     * Symfony\Component\Mime\Exception\RfcComplianceException - meaning
     * that without the fix, sending would fail the same way after Phase 5
     * too, just under a different exception name.
     */
    public function test_sending_without_the_environment_variable_no_longer_fails(): void
    {
        // FLIPPED by the v1-patch TODO 28 fix: this case previously EXPECTED
        // AN EXCEPTION, and got one.
        $this->withoutEnv('MAIL_FROM_ADDRESS', function () {
            NotificationFacade::route('mail', 'probe@example.test')
                ->notify(new EventDeletedNotification($this->payload()));
        });

        $this->assertTrue(true, 'Sending completed without an exception.');
    }

    public function test_the_group_creator_notification_sends_the_same_way(): void
    {
        $this->withoutEnv('MAIL_FROM_ADDRESS', function () {
            NotificationFacade::route('mail', 'probe@example.test')
                ->notify(new UserRoleIsGroupCreatorNotification());
        });

        $this->assertTrue(true, 'Sending completed without an exception.');
    }

    public function test_the_missing_app_name_still_sends(): void
    {
        // The other side of the severity boundary: the absence of APP_NAME
        // never prevented sending either, it only spoiled the mail's text.
        $this->withoutEnv('APP_NAME', function () {
            NotificationFacade::route('mail', 'probe@example.test')
                ->notify(new UserWillBeAnonymizeNotification($this->payload()));
        });

        $this->assertTrue(true, 'Sending completed without an exception.');
    }

    public function test_the_message_body_keeps_the_application_name(): void
    {
        // FLIPPED by the v1-patch TODO 28 fix. Previously the two texts
        // DIFFERED: without the environment variable, the application name
        // dropped out of the mail. Now it comes from the configuration, so it's the same.
        $withName = (new UserWillBeAnonymizeNotification($this->payload()))
            ->toMail($this->notifiable());

        $withoutEnv = $this->withoutEnv('APP_NAME', function () {
            return (new UserWillBeAnonymizeNotification($this->payload()))
                ->toMail($this->notifiable());
        });

        $this->assertSame($withName->introLines[0], $withoutEnv->introLines[0]);
        $this->assertStringContainsString(config('app.name'), $withoutEnv->introLines[0]);
    }
}
