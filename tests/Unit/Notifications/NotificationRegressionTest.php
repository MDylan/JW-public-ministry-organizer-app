<?php

namespace Tests\Unit\Notifications;

use App\Notifications\EventCreatedNotification;
use App\Notifications\EventDeletedAdminsNotification;
use App\Notifications\EventDeletedNotification;
use App\Notifications\EventStatusChangedNotification;
use App\Notifications\EventUpdatedNotification;
use App\Notifications\FinishRegistration;
use App\Notifications\FinishRegistrationSuccessNotification;
use App\Notifications\GroupParentGroupAttachedNotification;
use App\Notifications\GroupParentGroupDetachedNotification;
use App\Notifications\GroupPriorityMessageNotification;
use App\Notifications\GroupUserAddedNotification;
use App\Notifications\GroupUserLogoutNotification;
use App\Notifications\LoginData;
use App\Notifications\NewAdminNotification;
use App\Notifications\Newsletter;
use App\Notifications\TestNotification;
use App\Notifications\UserEmailChangedNotification;
use App\Notifications\UserProfileChangedNotification;
use App\Notifications\UserProfileRenewalAdminNotification;
use App\Notifications\UserProfileRenewalNotification;
use App\Notifications\UserRegisteredNotification;
use App\Notifications\UserRoleIsGroupCreatorNotification;
use App\Notifications\UserWillBeAnonymizeNotification;
use App\Notifications\UserWillBeAnyonimizeAdminNotification;
use App\Notifications\deletePersonalDataNotification;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\MailMessage;
use Tests\TestCase;

class NotificationRegressionTest extends TestCase
{
    /**
     * @dataProvider notificationProvider
     */
    public function test_notification_mail_contracts(string $class, array $constructorArgs, bool $shouldQueue): void
    {
        $notifiable = $this->makeNotifiable();

        $notification = new $class(...$constructorArgs);

        if ($shouldQueue) {
            $this->assertInstanceOf(ShouldQueue::class, $notification);
        } else {
            $this->assertNotInstanceOf(ShouldQueue::class, $notification);
        }

        $channels = $notification->via($notifiable);
        $this->assertContains('mail', $channels);

        $mail = $notification->toMail($notifiable);
        $this->assertInstanceOf(MailMessage::class, $mail);
    }

    public function test_opt_out_notifications_can_disable_delivery_channel(): void
    {
        $notifiable = $this->makeNotifiable([
            'email' => 'optout@example.test',
            'opted_out_of_notifications' => [
                'EventDeletedNotification' => true,
                'EventDeletedAdminsNotification' => true,
                'GroupPriorityMessageNotification' => true,
                'UserProfileChangedNotification' => true,
            ],
        ]);

        $payload = $this->sharedPayload();

        $this->assertSame([], (new EventDeletedNotification($payload))->via($notifiable));
        $this->assertSame([], (new EventDeletedAdminsNotification($payload))->via($notifiable));
        $this->assertSame([], (new GroupPriorityMessageNotification($payload))->via($notifiable));
        $this->assertSame([], (new UserProfileChangedNotification($payload))->via($notifiable));
    }

    /**
     * TODO 15: the provider list is itself an assertion.
     *
     * The same pattern as TODO 04's factory list and TODO 13's cast list: if
     * someone adds a new notification class without a mail contract, this
     * test fails. The provider's KEYS are exactly the short class names -
     * including the lowercase deletePersonalDataNotification.
     */
    public function test_every_notification_class_has_a_mail_contract(): void
    {
        $classes = array_map(
            fn (string $path): string => basename($path, '.php'),
            glob(app_path('Notifications/*.php'))
        );
        sort($classes);

        $covered = array_keys($this->notificationProvider());
        sort($covered);

        $this->assertSame($classes, $covered);
    }

    public function notificationProvider(): array
    {
        $payload = $this->sharedPayload();

        return [
            'EventCreatedNotification' => [EventCreatedNotification::class, [$payload], true],
            'EventDeletedAdminsNotification' => [EventDeletedAdminsNotification::class, [$payload], true],
            'EventDeletedNotification' => [EventDeletedNotification::class, [$payload], true],
            'EventStatusChangedNotification' => [EventStatusChangedNotification::class, [$payload], true],
            'EventUpdatedNotification' => [EventUpdatedNotification::class, [$payload], true],
            'FinishRegistration' => [FinishRegistration::class, [$payload], true],
            'FinishRegistrationSuccessNotification' => [FinishRegistrationSuccessNotification::class, [], true],
            'GroupParentGroupAttachedNotification' => [GroupParentGroupAttachedNotification::class, [$payload], true],
            'GroupParentGroupDetachedNotification' => [GroupParentGroupDetachedNotification::class, [$payload], true],
            'GroupPriorityMessageNotification' => [GroupPriorityMessageNotification::class, [$payload], true],
            'GroupUserAddedNotification' => [GroupUserAddedNotification::class, [$payload], true],
            'GroupUserLogoutNotification' => [GroupUserLogoutNotification::class, [$payload], true],
            'LoginData' => [LoginData::class, [$payload], true],
            'NewAdminNotification' => [NewAdminNotification::class, [$payload], true],
            'Newsletter' => [Newsletter::class, [$payload], true],
            'TestNotification' => [TestNotification::class, [], false],
            'UserEmailChangedNotification' => [UserEmailChangedNotification::class, ['updated@example.test'], true],
            'UserProfileChangedNotification' => [UserProfileChangedNotification::class, [$payload], true],
            'UserProfileRenewalAdminNotification' => [UserProfileRenewalAdminNotification::class, [$payload], true],
            'UserProfileRenewalNotification' => [UserProfileRenewalNotification::class, [$payload], true],
            'UserRegisteredNotification' => [UserRegisteredNotification::class, [], true],
            'UserRoleIsGroupCreatorNotification' => [UserRoleIsGroupCreatorNotification::class, [], true],
            'UserWillBeAnonymizeNotification' => [UserWillBeAnonymizeNotification::class, [$payload], true],
            'UserWillBeAnyonimizeAdminNotification' => [UserWillBeAnyonimizeAdminNotification::class, [$payload], true],
            'deletePersonalDataNotification' => [deletePersonalDataNotification::class, [$payload], true],
        ];
    }

    private function sharedPayload(): array
    {
        return [
            'userName' => 'Tester',
            'groupName' => 'Group A',
            'groupAdmin' => 'Admin User',
            'userMail' => 'member@example.test',
            'userPassword' => 'password',
            'url' => 'https://example.test/finish-registration',
            'date' => now()->toDateString(),
            'newService' => [
                'start' => now()->addDay()->setTime(9, 0)->format('Y-m-d H:i:s'),
                'end' => now()->addDay()->setTime(10, 0)->format('Y-m-d H:i:s'),
            ],
            'oldService' => [
                'start' => now()->addDay()->setTime(8, 0)->format('Y-m-d H:i:s'),
                'end' => now()->addDay()->setTime(9, 0)->format('Y-m-d H:i:s'),
            ],
            'replyTo' => 'reply@example.test',
            'status' => 1,
            'reason' => false,
            'event_user' => 'Tester',
            'childGroupName' => 'Child Group',
            'adminBy' => 'Admin User',
            'newAdmin' => 'New Admin',
            'adminName' => 'Admin User',
            'subject' => 'Newsletter Subject',
            'content' => 'Newsletter Content',
            'recipients' => 'groupCreators',
            'name' => 'Group A',
            'lastDate' => now()->addDays(15)->toDateString(),
            'users' => [
                ['name' => 'User One', 'lastDate' => now()->addDays(15)->toDateString()],
            ],
            'message' => 'Priority group message',
            'old' => [
                'name' => 'Old Name',
                'phone_number' => '123',
                'congregation' => 'Old Congregation',
            ],
            'new' => [
                'name' => 'New Name',
                'phone_number' => '456',
                'congregation' => 'New Congregation',
            ],
        ];
    }

    private function makeNotifiable(array $attributes = []): object
    {
        return new class($attributes) {
            public int $id;
            public string $email;
            public array $opted_out_of_notifications;

            public function __construct(array $attributes)
            {
                $this->id = $attributes['id'] ?? 1;
                $this->email = $attributes['email'] ?? 'notify-target@example.test';
                $this->opted_out_of_notifications = $attributes['opted_out_of_notifications'] ?? [];
            }

            public function getKey(): int
            {
                return $this->id;
            }

            public function getEmailForVerification(): string
            {
                return $this->email;
            }
        };
    }
}
