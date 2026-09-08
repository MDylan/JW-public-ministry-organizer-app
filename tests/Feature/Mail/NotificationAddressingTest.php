<?php

namespace Tests\Feature\Mail;

use App\Notifications\EventCreatedNotification;
use App\Notifications\EventDeletedNotification;
use App\Notifications\EventStatusChangedNotification;
use App\Notifications\EventUpdatedNotification;
use App\Notifications\UserRoleIsGroupCreatorNotification;
use Illuminate\Notifications\Notification as BaseNotification;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Notification;
use Symfony\Component\Mime\Email;
use Tests\TestCase;
use PHPUnit\Framework\Attributes\DataProvider;

/**
 * TODO 36: the notification addresses, measured on a RENDERED message.
 *
 * tests/Unit/Notifications/NotificationRegressionTest.php walks all 25
 * notifications, but it stops at the MailMessage object - it never renders one
 * into a Symfony\Component\Mime\Email. Rendering is the step where Symfony
 * Mailer's address handling applies, so the half of the SwiftMailer switch this
 * item exists to verify was the untested half.
 *
 * Five notifications address anything beyond the recipient: four set replyTo
 * from group data, one blind-copies the from address. All five fall back to
 * config('mail.from.address'), which is what makes an empty or null value
 * interesting rather than academic.
 *
 * QUEUE_CONNECTION=sync in phpunit.xml, so the ShouldQueue notifications here
 * run inline; MAIL_MAILER=array, so the finished message stays in memory. The
 * reader that gets it back is the same one GroupCreationTest:276 uses.
 */
class NotificationAddressingTest extends TestCase
{
    private const RECIPIENT = 'target@example.test';
    private const GROUP_REPLY_TO = 'csoport@example.test';

    /**
     * @return array<string, array{0: class-string}>
     */
    public static function replyToNotificationProvider(): array
    {
        return [
            'EventCreatedNotification' => [EventCreatedNotification::class],
            'EventDeletedNotification' => [EventDeletedNotification::class],
            'EventStatusChangedNotification' => [EventStatusChangedNotification::class],
            'EventUpdatedNotification' => [EventUpdatedNotification::class],
        ];
    }

    // =========================================================================
    // 1. The address the group supplies
    // =========================================================================

    #[DataProvider('replyToNotificationProvider')]
    public function test_the_group_reply_to_address_survives_rendering(string $class): void
    {
        $message = $this->send(new $class($this->payload()));

        $this->assertSame([self::RECIPIENT], $this->addresses($message->getTo()));
        $this->assertSame(
            [self::GROUP_REPLY_TO],
            $this->addresses($message->getReplyTo()),
            'A csoport replyTo címe nem élte túl a renderelést.'
        );
    }

    // =========================================================================
    // 2. The fallback, and the two ways it is reached
    // =========================================================================

    #[DataProvider('replyToNotificationProvider')]
    public function test_an_empty_reply_to_falls_back_to_the_configured_from_address(string $class): void
    {
        $message = $this->send(new $class($this->payload(['replyTo' => '   '])));

        $this->assertSame(
            [config('mail.from.address')],
            $this->addresses($message->getReplyTo()),
            'Az üres replyTo nem esett vissza a mail.from.address-re.'
        );
    }

    #[DataProvider('replyToNotificationProvider')]
    public function test_a_null_reply_to_falls_back_too(string $class): void
    {
        // groups.replyTo is a NULLABLE text column and every producer passes it
        // straight through (EventObserver:56, CalculateDatesEvents:237), so null
        // is a real value rather than a contrived one. Only
        // EventDeletedNotification guards it with ?? ''; the other three call
        // trim() on the null directly. That difference is measured here rather
        // than assumed - all four are expected to reach the same address.
        $message = $this->send(new $class($this->payload(['replyTo' => null])));

        $this->assertSame(
            [config('mail.from.address')],
            $this->addresses($message->getReplyTo()),
            'A null replyTo nem esett vissza a mail.from.address-re.'
        );
    }

    // =========================================================================
    // 3. The one notification that blind-copies
    // =========================================================================

    public function test_the_group_creator_notification_blind_copies_the_from_address(): void
    {
        $message = $this->send(new UserRoleIsGroupCreatorNotification());

        $this->assertSame([self::RECIPIENT], $this->addresses($message->getTo()));
        $this->assertSame(
            [config('mail.from.address')],
            $this->addresses($message->getBcc()),
            'A bcc cím nem élte túl a renderelést.'
        );
    }

    // =========================================================================
    // 4. That this file measures a real message at all
    // =========================================================================

    public function test_the_measured_object_is_a_rendered_symfony_email(): void
    {
        // Without this the assertions above could be passing on some other
        // shape entirely. The from address is set by the global mail.from
        // config rather than by any notification, so it also pins that the
        // message went through the mailer instead of being built by hand.
        $message = $this->send(new UserRoleIsGroupCreatorNotification());

        $this->assertInstanceOf(Email::class, $message);
        $this->assertSame([config('mail.from.address')], $this->addresses($message->getFrom()));
        $this->assertNotSame('', $message->getHtmlBody());
    }

    // =========================================================================
    // Helpers
    // =========================================================================

    /**
     * Send to an on-demand notifiable and return the single rendered message.
     */
    private function send(BaseNotification $notification): Email
    {
        Notification::route('mail', self::RECIPIENT)->notify($notification);

        $messages = collect(Mail::getSymfonyTransport()->messages())
            ->map(fn ($sent) => $sent->getOriginalMessage());

        $this->assertCount(1, $messages, 'Pontosan egy üzenetet vártunk az array transportban.');

        return $messages->first();
    }

    /**
     * The bare addresses of a Symfony address list, display names dropped.
     *
     * @param  \Symfony\Component\Mime\Address[]  $addresses
     * @return string[]
     */
    private function addresses(array $addresses): array
    {
        return array_map(fn ($address) => $address->getAddress(), $addresses);
    }

    /**
     * The subset of the event payload the four toMail() implementations read.
     */
    private function payload(array $overrides = []): array
    {
        return array_merge([
            'replyTo' => self::GROUP_REPLY_TO,
            'groupName' => 'Példa Csoport',
            'userName' => 'Teszt Elek',
            'date' => now()->toDateString(),
            'status' => 1,
            'reason' => false,
            'newService' => [
                'start' => now()->addDay()->setTime(9, 0)->format('Y-m-d H:i:s'),
                'end' => now()->addDay()->setTime(10, 0)->format('Y-m-d H:i:s'),
            ],
            'oldService' => [
                'start' => now()->addDay()->setTime(8, 0)->format('Y-m-d H:i:s'),
                'end' => now()->addDay()->setTime(9, 0)->format('Y-m-d H:i:s'),
            ],
        ], $overrides);
    }
}
