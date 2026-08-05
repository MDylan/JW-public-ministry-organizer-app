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
 * TODO 11: a Phase 4 riasztóhuzalja.
 *
 * Hat értesítés olvas FUTÁSIDŐBEN env()-et. A Laravel a .env fájlt csak
 * akkor tölti be, ha nincs gyorsítótárazott konfiguráció - config:cache
 * után tehát az env() a config-fájlokon KÍVÜL null-t ad. Ez a Phase 4
 * tárgya, és ma semmi nem jelezné, ha a viselkedés némán megváltozik.
 *
 * Két súlyossági osztály van, és a tesztek nevei is ezt választják szét:
 *  - a négy Event*Notification ->replyTo()-ja és az
 *    UserRoleIsGroupCreatorNotification ->bcc()-je magát a CÍMET olvassa
 *    env()-ből,
 *  - az UserWillBeAnonymizeNotification csak a levél SZÖVEGÉBE teszi az
 *    APP_NAME-et.
 *
 * A javítás mindkét esetben egysoros lesz (config('mail.from.address'),
 * illetve config('app.name')) - de csak akkor látszik a különbség, ha ma
 * van rá teszt.
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
     * A config:cache állapot SZIMULÁLÁSA egyetlen kulcsra.
     *
     * A meglévő BuildsDomainFixtures::withEnvValue() csak a $_SERVER tömböt
     * írja, ami elég a phpunit.xml <server> bejegyzéseihez (USE_RECAPTCHA),
     * de nem elég azokhoz, amik a .env fájlból jönnek - a Dotenv azokat
     * mindhárom helyre kiírja. Az Env::getRepository()->clear() nem
     * használható: a repository immutable, és a már betöltött kulcsokra
     * a clear() no-op.
     *
     * FONTOS, hogy ez szimuláció: valódi config:cache mellett MINDEN kulcsra
     * null jön vissza, nem csak erre az egyre.
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
    // 1. A mai viselkedés
    // =========================================================================

    public function test_an_explicit_reply_to_address_wins_over_the_environment(): void
    {
        $mail = (new EventDeletedNotification($this->payload(['replyTo' => 'group@example.test'])))
            ->toMail($this->notifiable());

        $this->assertSame('group@example.test', $mail->replyTo[0][0]);
    }

    public function test_a_whitespace_only_reply_to_falls_back_to_the_environment(): void
    {
        // A strlen(trim(...)) > 0 vizsgálat miatt a csupa szóköz is
        // "üresnek" számít - a csoport replyTo mezője pontosan így viselkedik.
        $mail = (new EventDeletedNotification($this->payload(['replyTo' => '   '])))
            ->toMail($this->notifiable());

        $this->assertSame(env('MAIL_FROM_ADDRESS'), $mail->replyTo[0][0]);
    }

    /**
     * @dataProvider replyToNotificationProvider
     */
    public function test_every_event_notification_falls_back_to_the_same_environment_value(string $class): void
    {
        $mail = (new $class($this->payload()))->toMail($this->notifiable());

        $this->assertSame(env('MAIL_FROM_ADDRESS'), $mail->replyTo[0][0]);
        $this->assertSame(
            config('mail.from.address'),
            $mail->replyTo[0][0],
            'Ma az env() és a config ugyanazt adja - ezért lesz a Phase 4 javítása egysoros.'
        );
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

    public function test_the_group_creator_notification_bccs_the_environment_address(): void
    {
        $mail = (new UserRoleIsGroupCreatorNotification())->toMail($this->notifiable());

        $this->assertSame(env('MAIL_FROM_ADDRESS'), $mail->bcc[0][0]);
        $this->assertSame(config('mail.from.address'), $mail->bcc[0][0]);
    }

    // =========================================================================
    // 2. Amit a config:cache okoz
    // =========================================================================

    public function test_without_the_environment_variable_the_reply_to_address_becomes_null(): void
    {
        $mail = $this->withoutEnv('MAIL_FROM_ADDRESS', function () {
            $this->assertNull(env('MAIL_FROM_ADDRESS'), 'A szimuláció valóban kiüríti a kulcsot.');

            return (new EventDeletedNotification($this->payload()))->toMail($this->notifiable());
        });

        // A MailMessage maga még felépül - a hiba csak a küldésnél derül ki.
        $this->assertNull($mail->replyTo[0][0]);
    }

    public function test_without_the_environment_variable_the_bcc_address_becomes_null(): void
    {
        $mail = $this->withoutEnv('MAIL_FROM_ADDRESS', function () {
            return (new UserRoleIsGroupCreatorNotification())->toMail($this->notifiable());
        });

        $this->assertNull($mail->bcc[0][0]);
    }

    /**
     * A "kemény hiba" nem feltételezés: itt tényleg elküldjük a levelet.
     *
     * A MAIL_MAILER a phpunit.xml-ben 'array', de a levél borítékja akkor is
     * felépül, és a címzett-ellenőrzés ott csap le. A KIMÉRT hiba ma:
     *
     *   Swift_RfcComplianceException
     *   "Address in mailbox given [] does not comply with RFC 2822, 3.6.2."
     *
     * Az osztálynevet szándékosan csak a végződésére vizsgáljuk: a Phase 5
     * (Laravel 9) a SwiftMailert Symfony Mailerre cseréli, ahol ugyanez a
     * hiba Symfony\Component\Mime\Exception\RfcComplianceException néven jön.
     * A lényeg - hogy a küldés MEGHIÚSUL, nem csak csúnya lesz - ugyanaz.
     */
    private function sendFailure(callable $send): \Throwable
    {
        try {
            $send();
        } catch (\Throwable $e) {
            return $e;
        }

        $this->fail('A küldésnek el kellett volna hasalnia az üres címen.');
    }

    public function test_sending_without_the_environment_variable_is_a_hard_failure(): void
    {
        $exception = $this->withoutEnv('MAIL_FROM_ADDRESS', function () {
            return $this->sendFailure(function () {
                NotificationFacade::route('mail', 'probe@example.test')
                    ->notify(new EventDeletedNotification($this->payload()));
            });
        });

        $this->assertStringEndsWith('RfcComplianceException', get_class($exception));
    }

    public function test_the_same_send_succeeds_while_the_environment_variable_is_present(): void
    {
        // Kontroll: a fenti hibát az env() hiánya okozza, nem a teszt
        // felállása.
        NotificationFacade::route('mail', 'probe@example.test')
            ->notify(new EventDeletedNotification($this->payload()));

        $this->assertNotNull(env('MAIL_FROM_ADDRESS'));
    }

    public function test_the_group_creator_notification_fails_the_same_way(): void
    {
        $exception = $this->withoutEnv('MAIL_FROM_ADDRESS', function () {
            return $this->sendFailure(function () {
                NotificationFacade::route('mail', 'probe@example.test')
                    ->notify(new UserRoleIsGroupCreatorNotification());
            });
        });

        $this->assertStringEndsWith('RfcComplianceException', get_class($exception));
    }

    public function test_the_missing_app_name_still_sends(): void
    {
        // A súlyossági határ másik oldala: az APP_NAME hiánya nem akadályozza
        // meg a küldést.
        $this->withoutEnv('APP_NAME', function () {
            NotificationFacade::route('mail', 'probe@example.test')
                ->notify(new UserWillBeAnonymizeNotification($this->payload()));
        });

        $this->assertFalse(false, 'A küldés kivétel nélkül lefutott.');
    }

    public function test_the_missing_app_name_only_degrades_the_message_body(): void
    {
        // KÜLÖN SÚLYOSSÁG: itt az env() csak egy fordítási placeholderbe
        // kerül, tehát a levél kimegy, csak hiányos névvel.
        $withName = (new UserWillBeAnonymizeNotification($this->payload()))
            ->toMail($this->notifiable());

        $withoutName = $this->withoutEnv('APP_NAME', function () {
            return (new UserWillBeAnonymizeNotification($this->payload()))
                ->toMail($this->notifiable());
        });

        $this->assertNotSame(
            $withName->introLines[0],
            $withoutName->introLines[0],
            'A levél szövege megváltozik, de a küldés nem hiúsul meg.'
        );
        $this->assertNotEmpty($withoutName->introLines[0]);
    }
}
