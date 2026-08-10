<?php

namespace Tests\Feature\Jobs;

use App\Jobs\UserLogoutFromGroupProcess;
use App\Models\Event;
use App\Models\Group;
use App\Models\GroupDate;
use App\Models\User;
use App\Notifications\EventDeletedNotification;
use Illuminate\Support\Facades\Notification;
use Tests\Feature\FeatureTestCase;

/**
 * TODO 05: real handle() execution.
 *
 * Dispatched from App\Classes\GroupUserMoves when a user leaves a group. It
 * removes the user's future events in that group, regenerates the affected
 * day statistics and notifies the user about each cancelled accepted event.
 */
class UserLogoutFromGroupProcessTest extends FeatureTestCase
{
    private Group $group;
    private User $member;

    protected function setUp(): void
    {
        parent::setUp();

        $this->group = $this->createGroup();
        $this->member = $this->createUser(['email' => 'logout-member@example.test']);
        $this->attachUserToGroup($this->member, $this->group);
        $this->actingAs($this->member);

        GroupDate::factory()->create([
            'group_id' => $this->group->id,
            'date' => now()->addDay()->toDateString(),
        ]);
    }

    private function createEvent(string $day, string $time = '09:00:00', int $status = 1, ?Group $group = null): Event
    {
        return Event::factory()->create([
            'group_id' => ($group ?? $this->group)->id,
            'user_id' => $this->member->id,
            'day' => $day,
            'start' => $day.' '.$time,
            'end' => $day.' 10:00:00',
            'status' => $status,
            'accepted_at' => $status === 1 ? now() : null,
            'accepted_by' => $status === 1 ? $this->member->id : null,
        ]);
    }

    public function test_handle_deletes_the_users_future_events_in_that_group(): void
    {
        $future = $this->createEvent(now()->addDay()->toDateString());

        (new UserLogoutFromGroupProcess($this->group, $this->member, 'Admin User'))->handle();

        $this->assertNull(Event::find($future->id));
    }

    public function test_handle_keeps_past_events(): void
    {
        // The job only deletes events with start >= now.
        $past = $this->createEvent(now()->subDays(2)->toDateString());

        (new UserLogoutFromGroupProcess($this->group, $this->member, 'Admin User'))->handle();

        $this->assertNotNull(Event::find($past->id));
    }

    public function test_handle_keeps_events_belonging_to_other_groups(): void
    {
        $otherGroup = $this->createGroup();
        $this->attachUserToGroup($this->member, $otherGroup);
        $otherEvent = $this->createEvent(now()->addDay()->toDateString(), '09:00:00', 1, $otherGroup);

        (new UserLogoutFromGroupProcess($this->group, $this->member, 'Admin User'))->handle();

        $this->assertNotNull(Event::find($otherEvent->id));
    }

    public function test_handle_notifies_the_user_for_each_accepted_event(): void
    {
        Notification::fake();

        $this->createEvent(now()->addDay()->toDateString());
        $this->createEvent(now()->addDays(2)->toDateString());

        (new UserLogoutFromGroupProcess($this->group, $this->member, 'Admin User'))->handle();

        Notification::assertSentToTimes($this->member, EventDeletedNotification::class, 2);
    }

    public function test_handle_does_not_notify_for_pending_events_but_still_deletes_them(): void
    {
        Notification::fake();

        $pending = $this->createEvent(now()->addDay()->toDateString(), '09:00:00', 0);

        (new UserLogoutFromGroupProcess($this->group, $this->member, 'Admin User'))->handle();

        // The notification only goes out for accepted (status = 1) events...
        Notification::assertNothingSent();
        // ...whereas deletion applies to every future event regardless of status.
        $this->assertNull(Event::find($pending->id));
    }

    public function test_handle_passes_the_actor_name_into_the_notification(): void
    {
        Notification::fake();

        $this->createEvent(now()->addDay()->toDateString());

        (new UserLogoutFromGroupProcess($this->group, $this->member, 'Kirúgó Adminisztrátor'))->handle();

        Notification::assertSentTo($this->member, EventDeletedNotification::class, function ($notification) {
            $mail = $notification->toMail($this->member);
            $rendered = $mail->render();

            return str_contains($rendered, 'Kirúgó Adminisztrátor');
        });
    }

    public function test_handle_is_a_no_op_when_the_user_has_no_events(): void
    {
        Notification::fake();

        (new UserLogoutFromGroupProcess($this->group, $this->member, 'Admin User'))->handle();

        Notification::assertNothingSent();
        $this->assertSame(0, Event::where('user_id', $this->member->id)->count());
    }
}
