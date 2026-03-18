<?php

namespace Tests\Feature;

use App\Classes\GroupUserMoves;
use App\Jobs\UserLogoutFromGroupProcess;
use App\Models\Event;
use App\Models\User;
use App\Notifications\FinishRegistrationSuccessNotification;
use App\Notifications\GroupUserAddedNotification;
use App\Notifications\GroupUserLogoutNotification;
use Illuminate\Support\Facades\Bus;
use Illuminate\Support\Facades\Notification;

class CriticalUserFlowsTest extends FeatureTestCase
{
    public function test_login_and_logout_flow(): void
    {
        $user = $this->createUser([
            'email' => 'flow-login@example.test',
            'password' => bcrypt('password'),
        ]);

        $this->post('/login', [
            'email' => $user->email,
            'password' => 'password',
        ])->assertStatus(302);

        $this->assertAuthenticatedAs($user);

        $this->actingAs($user)
            ->post('/logout')
            ->assertStatus(302);

        $this->assertGuest();
    }

    public function test_finish_registration_flow(): void
    {
        Notification::fake();

        $registered = $this->createUser([
            'name' => null,
            'role' => 'registered',
            'email_verified_at' => null,
            'email' => 'finish-registration@example.test',
        ]);

        $signedGet = $this->signedRoute('finish_registration', ['id' => $registered->id]);
        $signedPost = $this->signedRoute('finish_registration_register', ['id' => $registered->id]);

        $this->get($signedGet)->assertStatus(200);

        $this->post($signedPost, [
            'name' => 'Activated User',
            'phone_number' => '36201234567',
            'password' => 'Password1',
            'password_confirmation' => 'Password1',
            'terms' => '1',
        ])->assertRedirect('groups');

        $this->assertSame('activated', $registered->fresh()->role);
        $this->assertNotNull($registered->fresh()->email_verified_at);
        Notification::assertSentTo($registered->fresh(), FinishRegistrationSuccessNotification::class);
    }

    public function test_profile_information_update_flow(): void
    {
        $user = $this->createUser([
            'email' => 'profile-update@example.test',
        ]);

        $this->actingAs($user)
            ->put(route('user-profile-information.update'), [
                'name' => 'Updated Name',
                'email' => $user->email,
                'phone_number' => '36201112222',
                'congregation' => 'Updated Congregation',
            ])
            ->assertStatus(302);

        $fresh = $user->fresh();
        $this->assertSame('Updated Name', $fresh->name);
        $this->assertSame('Updated Congregation', $fresh->congregation);
    }

    public function test_group_membership_attach_and_detach_flow(): void
    {
        Bus::fake();
        Notification::fake();

        $admin = $this->createUser([
            'role' => 'mainAdmin',
            'email' => 'membership-admin@example.test',
        ]);
        $member = $this->createUser([
            'email' => 'membership-member@example.test',
            'role' => 'registered',
        ]);
        $group = $this->createGroup();

        $this->actingAs($admin);

        $moves = new GroupUserMoves($group->id, $member->id);
        $moves->attach();

        Notification::assertSentTo($member, GroupUserAddedNotification::class);

        $moves->detach();

        Bus::assertDispatched(UserLogoutFromGroupProcess::class);
        Notification::assertSentTo($member, GroupUserLogoutNotification::class);
    }

    public function test_event_create_update_delete_lifecycle(): void
    {
        $creator = $this->createUser(['email' => 'event-flow-creator@example.test']);
        $owner = $this->createUser(['email' => 'event-flow-owner@example.test']);
        $group = $this->createGroup();

        $this->attachUserToGroup($creator, $group, 'roler', true);
        $this->attachUserToGroup($owner, $group, 'member', true);

        $this->actingAs($creator);

        $event = Event::factory()->create([
            'group_id' => $group->id,
            'user_id' => $owner->id,
            'status' => 0,
        ]);

        $event->status = 1;
        $event->save();

        $event->delete();

        $this->assertSoftDeleted('events', ['id' => $event->id]);
    }
}
