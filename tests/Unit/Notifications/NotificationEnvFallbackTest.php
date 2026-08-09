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
 * TODO 11 -> TODO 28: a riasztóhuzalból bizonyíték lett.
 *
 * Hat értesítés olvasott FUTÁSIDŐBEN env()-et. A Laravel a .env fájlt csak
 * akkor tölti be, ha nincs gyorsítótárazott konfiguráció - config:cache után
 * tehát az env() a config-fájlokon KÍVÜL null-t ad. Ez a fájl eredetileg azt
 * rögzítette, mi történik olyankor; a v1-patch TODO 28 óta azt rögzíti, hogy
 * MÁR NEM TÖRTÉNIK SEMMI.
 *
 * Két súlyossági osztály volt, és a tesztek nevei is ezt választják szét:
 *  - a négy Event*Notification ->replyTo()-ja és az
 *    UserRoleIsGroupCreatorNotification ->bcc()-je magát a CÍMET olvasta
 *    env()-ből, tehát a küldés MEGHIÚSULT (Swift_RfcComplianceException),
 *  - az UserWillBeAnonymizeNotification csak a levél SZÖVEGÉBE tette az
 *    APP_NAME-et, tehát a levél kiment, üres névvel.
 *
 * A javítás mindkét esetben egysoros volt - config('mail.from.address'),
 * illetve config('app.name') -, de csak azért volt látható, mert volt rá
 * teszt. Az itteni esetek most a fordítottját bizonyítják: a környezeti
 * változó eltüntetése a levélen többé nem változtat semmit.
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
     * null jön vissza, nem csak erre az egyre. A konfigurációhoz viszont nem
     * nyúl - és pontosan ez teszi a 2. szakasz eseteit bizonyítékká.
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

    public function test_a_whitespace_only_reply_to_falls_back_to_the_configured_address(): void
    {
        // A strlen(trim(...)) > 0 vizsgálat miatt a csupa szóköz is
        // "üresnek" számít - a csoport replyTo mezője pontosan így viselkedik.
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
    // 2. Amit a config:cache OKOZOTT - és amit ma már nem
    // =========================================================================

    public function test_the_reply_to_address_survives_a_missing_environment_variable(): void
    {
        // MEGFORDÍTVA a v1-patch TODO 28 javításával.
        //
        // Korábban ez a cím null lett, mert az értesítés env()-ből olvasta.
        // A konfiguráció a betöltéskor rögzítette az értéket, tehát a
        // környezeti változó eltüntetése már nem ér el hozzá - és pont ez az,
        // amit egy config:cache csinál.
        $mail = $this->withoutEnv('MAIL_FROM_ADDRESS', function () {
            $this->assertNull(env('MAIL_FROM_ADDRESS'), 'A szimuláció valóban kiüríti a kulcsot.');

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
     * A "kemény hiba" nem volt feltételezés, és a megszűnése sem az: itt
     * tényleg elküldjük a levelet.
     *
     * A MAIL_MAILER a phpunit.xml-ben 'array', de a levél borítékja akkor is
     * felépül, és a címzett-ellenőrzés ott csap le. A KIMÉRT hiba korábban:
     *
     *   Swift_RfcComplianceException
     *   "Address in mailbox given [] does not comply with RFC 2822, 3.6.2."
     *
     * A Phase 5 (Laravel 9) a SwiftMailert Symfony Mailerre cseréli, ahol
     * ugyanez a hiba Symfony\Component\Mime\Exception\RfcComplianceException
     * néven jönne - vagyis a javítás nélkül a Phase 5 után is ugyanígy
     * meghiúsulna a küldés, csak más kivételnévvel.
     */
    public function test_sending_without_the_environment_variable_no_longer_fails(): void
    {
        // MEGFORDÍTVA a v1-patch TODO 28 javításával: ez az eset korábban
        // KIVÉTELT VÁRT, és azt is kapott.
        $this->withoutEnv('MAIL_FROM_ADDRESS', function () {
            NotificationFacade::route('mail', 'probe@example.test')
                ->notify(new EventDeletedNotification($this->payload()));
        });

        $this->assertTrue(true, 'A küldés kivétel nélkül lefutott.');
    }

    public function test_the_group_creator_notification_sends_the_same_way(): void
    {
        $this->withoutEnv('MAIL_FROM_ADDRESS', function () {
            NotificationFacade::route('mail', 'probe@example.test')
                ->notify(new UserRoleIsGroupCreatorNotification());
        });

        $this->assertTrue(true, 'A küldés kivétel nélkül lefutott.');
    }

    public function test_the_missing_app_name_still_sends(): void
    {
        // A súlyossági határ másik oldala: az APP_NAME hiánya korábban sem
        // akadályozta meg a küldést, csak a levél szövegét rontotta el.
        $this->withoutEnv('APP_NAME', function () {
            NotificationFacade::route('mail', 'probe@example.test')
                ->notify(new UserWillBeAnonymizeNotification($this->payload()));
        });

        $this->assertTrue(true, 'A küldés kivétel nélkül lefutott.');
    }

    public function test_the_message_body_keeps_the_application_name(): void
    {
        // MEGFORDÍTVA a v1-patch TODO 28 javításával. Korábban a két szöveg
        // ELTÉRT: a környezeti változó nélkül a levélből kiesett az
        // alkalmazás neve. Most a konfigurációból jön, tehát ugyanaz.
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
