<?php

namespace Tests\Feature\Jobs;

use App\Classes\CalculateDatesEvents;
use App\Models\Event;
use App\Models\Group;
use App\Models\GroupDate;
use App\Models\User;
use App\Notifications\EventDeletedNotification;
use Illuminate\Support\Facades\Notification;
use Tests\Feature\FeatureTestCase;

/**
 * TODO 10: the system-causer (causer_id = 0) on the RECEIVING side.
 *
 * TODO 10 standardized that, absent authentication, 0 goes into the causer's
 * place - but the 0 must also be handled on the receiving side. Two places read the
 * causer's NAME directly from the model, and both would have crashed:
 *
 *   - GroupDayDeletedProcess::handle(): User::find(0) is null, then ->name
 *   - CalculateDatesEvents::generate(): if($user_id) is false for 0, so it
 *     fell back to auth()->user() - which in a queue worker is ALWAYS null
 *
 * The name goes directly into the notification text, and Laravel turns PHP
 * warnings into ErrorException, so this would have been a real error,
 * not a silent null.
 *
 * TODO 10.2 deleted GroupDayDeletedProcess, so the first path
 * disappeared. The three branches of causerNameFor() - the 0, the real ID, and the
 * user DELETED IN THE MEANTIME - are now all covered here via
 * CalculateDatesEvents; this is the path that actually runs in production.
 *
 * Why this wasn't caught earlier: the existing job tests always ran with a real,
 * existing causer, and the test environment has QUEUE_CONNECTION=sync - meaning
 * jobs run INSIDE the authenticated request, where auth() still provides a
 * user. In production QUEUE_CONNECTION=database, so they would run in a
 * separate queue-worker process, without auth.
 */
class SystemCauserJobsTest extends FeatureTestCase
{
    private Group $group;
    private User $member;

    protected function setUp(): void
    {
        parent::setUp();

        $this->group = $this->createGroup();
        $this->member = $this->createUser(['email' => 'sysc-member@example.test']);
        $this->attachUserToGroup($this->member, $this->group);
    }

    private function createEventOn(string $day, string $from = '09:00', string $to = '10:00'): Event
    {
        return Event::factory()->create([
            'group_id'    => $this->group->id,
            'user_id'     => $this->member->id,
            'day'         => $day,
            'start'       => $day.' '.$from.':00',
            'end'         => $day.' '.$to.':00',
            'status'      => 1,
            'accepted_at' => now(),
        ]);
    }

    private function assertNotifiedBySystem(): void
    {
        Notification::assertSentTo(
            $this->member,
            EventDeletedNotification::class,
            function ($notification) {
                $property = new \ReflectionProperty($notification, 'data');
                $property->setAccessible(true);

                return $property->getValue($notification)['userName'] === 'SYSTEM';
            }
        );
    }

    /** Disabled day: generate() deletes every event on it. */
    private function disabledDate(string $day): void
    {
        GroupDate::factory()->create([
            'group_id'    => $this->group->id,
            'date'        => $day,
            'date_start'  => $day.' 08:00:00',
            'date_end'    => $day.' 12:00:00',
            'date_status' => 0,
        ]);
    }

    // =========================================================================
    // CalculateDatesEvents - the only remaining receiving side
    // =========================================================================

    public function test_calculate_dates_events_runs_with_a_system_causer(): void
    {
        // This path is reached through GroupDayUpdatedProcess, which passes
        // along the causer's ID. generate() previously checked
        // if($user_id) - which is FALSE for 0 - and fell back to
        // auth()->user(). In a queue worker that is always null.
        Notification::fake();
        $this->assertGuest();

        $day = now()->addDays(3)->toDateString();
        $this->disabledDate($day);
        $event = $this->createEventOn($day);

        CalculateDatesEvents::generate($this->group->id, $day, 0);

        $this->assertNull(Event::find($event->id), 'A letiltott nap eseményét törölnie kell.');
        $this->assertNotifiedBySystem();
    }

    public function test_calculate_dates_events_still_names_a_real_causer(): void
    {
        Notification::fake();

        $causer = $this->createUser(['email' => 'sysc-calc@example.test', 'name' => 'Naptár Nóra']);

        $day = now()->addDays(4)->toDateString();
        $this->disabledDate($day);
        $this->createEventOn($day);

        CalculateDatesEvents::generate($this->group->id, $day, $causer->id);

        Notification::assertSentTo(
            $this->member,
            EventDeletedNotification::class,
            function ($notification) {
                $property = new \ReflectionProperty($notification, 'data');
                $property->setAccessible(true);

                return $property->getValue($notification)['userName'] === 'Naptár Nóra';
            }
        );
    }

    public function test_calculate_dates_events_uses_the_authenticated_user_when_no_id_is_given(): void
    {
        // Livewire components call it without a user_id (with a default of false),
        // and there the logged-in user is the correct causer - this
        // fallback had to be kept.
        Notification::fake();

        $actor = $this->createUser(['email' => 'sysc-actor@example.test', 'name' => 'Belépett Béla']);
        $this->actingAs($actor);

        $day = now()->addDays(5)->toDateString();
        $this->disabledDate($day);
        $this->createEventOn($day);

        CalculateDatesEvents::generate($this->group->id, $day);

        Notification::assertSentTo(
            $this->member,
            EventDeletedNotification::class,
            function ($notification) {
                $property = new \ReflectionProperty($notification, 'data');
                $property->setAccessible(true);

                return $property->getValue($notification)['userName'] === 'Belépett Béla';
            }
        );
    }

    public function test_a_deleted_causer_falls_back_to_the_system_name(): void
    {
        // It's not just 0 that's problematic: User::find() also returns null
        // for the ID of a user DELETED in the meantime. The ID is fixed at the
        // moment of the call, but processing runs later - the user can disappear
        // in between.
        //
        // This branch used to be covered by GroupDayDeletedProcess; with that job's
        // deletion (TODO 10.2) it moved here, to the path that
        // actually runs in production.
        Notification::fake();

        $causer = $this->createUser(['email' => 'sysc-gone@example.test']);
        $causerId = $causer->id;
        $causer->delete();

        $day = now()->addDays(6)->toDateString();
        $this->disabledDate($day);
        $event = $this->createEventOn($day);

        CalculateDatesEvents::generate($this->group->id, $day, $causerId);

        $this->assertNull(Event::find($event->id), 'Az eseményt így is törölnie kell.');
        $this->assertNotifiedBySystem();
    }
}
