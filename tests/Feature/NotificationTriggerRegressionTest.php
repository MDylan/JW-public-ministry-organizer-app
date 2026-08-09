<?php

namespace Tests\Feature;

use App\Models\Event;
use App\Models\User;
use App\Notifications\EventDeletedAdminsNotification;
use App\Notifications\EventDeletedNotification;
use App\Notifications\EventUpdatedNotification;
use App\Notifications\NewAdminNotification;
use App\Notifications\UserEmailChangedNotification;
use App\Notifications\UserRegisteredNotification;
use App\Notifications\deletePersonalDataNotification;
use Illuminate\Support\Facades\Notification;

class NotificationTriggerRegressionTest extends FeatureTestCase
{
    public function test_registration_dispatches_user_registered_notification(): void
    {
        Notification::fake();

        $email = 'register-trigger@example.test';

        $this->post('/register', [
            'name' => 'Registration Trigger',
            'email' => $email,
            'phone_number' => '36201111111',
            'password' => 'Password1',
            'password_confirmation' => 'Password1',
            'terms' => '1',
        ])->assertStatus(302);

        $registered = User::where('email', $email)->firstOrFail();

        Notification::assertSentTo($registered, UserRegisteredNotification::class);
    }

    public function test_profile_email_change_dispatches_user_email_changed_notification(): void
    {
        Notification::fake();

        $user = $this->createUser([
            'email' => 'old-email@example.test',
        ]);

        $this->actingAs($user)
            ->put(route('user-profile-information.update'), [
                'name' => 'Updated Name',
                'email' => 'new-email@example.test',
                'phone_number' => '36202223333',
                'congregation' => 'Updated Congregation',
            ])
            ->assertStatus(302);

        Notification::assertSentTo($user->fresh(), UserEmailChangedNotification::class);
    }

    public function test_ask_to_delete_dispatches_delete_personal_data_notification(): void
    {
        Notification::fake();

        $user = $this->createUser([
            'email' => 'delete-request@example.test',
        ]);

        $this->actingAs($user)
            ->withSession($this->passwordConfirmedSession())
            ->get(route('user.askToDelete'))
            ->assertRedirect(route('user.profile'));

        Notification::assertSentTo($user, deletePersonalDataNotification::class);
    }

    public function test_user_observer_dispatches_new_admin_notification_to_other_admins(): void
    {
        Notification::fake();

        $actor = $this->createUser([
            'role' => 'mainAdmin',
            'email' => 'actor-admin@example.test',
        ]);
        $otherAdmin = $this->createUser([
            'role' => 'mainAdmin',
            'email' => 'other-admin@example.test',
        ]);
        $candidate = $this->createUser([
            'role' => 'registered',
            'email' => 'candidate-admin@example.test',
        ]);

        $this->actingAs($actor);

        $candidate->role = 'mainAdmin';
        $candidate->save();

        Notification::assertSentTo($otherAdmin, NewAdminNotification::class);
    }

    public function test_event_update_and_delete_dispatch_related_notifications(): void
    {
        Notification::fake();

        $creator = $this->createUser(['email' => 'event-notify-creator@example.test']);
        $assignee = $this->createUser(['email' => 'event-notify-assignee@example.test']);
        $otherEditor = $this->createUser(['email' => 'event-notify-editor@example.test']);
        $group = $this->createGroup();

        $this->attachUserToGroup($creator, $group, 'roler', true);
        $this->attachUserToGroup($assignee, $group, 'member', true);
        $this->attachUserToGroup($otherEditor, $group, 'admin', true);

        $this->actingAs($creator);

        $event = Event::factory()->create([
            'group_id' => $group->id,
            'user_id' => $assignee->id,
            'status' => 1,
        ]);

        $event->start = now()->addDays(2)->setTime(10, 0)->format('Y-m-d H:i:s');
        $event->end = now()->addDays(2)->setTime(11, 0)->format('Y-m-d H:i:s');
        $event->save();

        Notification::assertSentTo($assignee, EventUpdatedNotification::class);

        $event->delete();

        Notification::assertSentTo($assignee, EventDeletedNotification::class);
        Notification::assertSentTo($otherEditor, EventDeletedAdminsNotification::class);
    }
}
