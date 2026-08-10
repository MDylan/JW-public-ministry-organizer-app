<?php

namespace Tests\Feature\Groups;

use App\Http\Livewire\Groups\UpdateGroupForm;
use App\Models\DayStat;
use App\Models\Event;
use App\Models\Group;
use App\Models\GroupDate;
use App\Models\GroupDay;
use App\Models\LogHistory;
use App\Models\User;
use App\Notifications\EventDeletedNotification;
use App\Notifications\EventUpdatedNotification;
use Illuminate\Support\Facades\Notification;
use Livewire\Livewire;
use Tests\Feature\FeatureTestCase;

/**
 * TODO 10.1: cleanup after narrowing the day template - without an observer.
 *
 * The TODO 10 note claimed that the "delete future events that fall outside
 * the day template" cleanup NEVER runs, because only the unregistered
 * GroupDayObserver would trigger the two jobs, and therefore this is a
 * missing feature. That claim was wrong.
 *
 * The cleanup exists, just on a different chain:
 *
 *   UpdateGroupForm::updateGroup()
 *     -> GroupDateHelper::generateDate()   rewrites the group_dates rows onto
 *                                          the PENDING new template
 *     -> GroupDateHelper::recalculateDates()
 *     -> CalculateDateProcess
 *     -> CalculateDatesEvents::generate()  <- exactly the engine that
 *                                             GroupDayUpdatedProcess also calls
 *
 * This file is the proof and the guard at the same time: if someone later
 * removes the recalculateDates() call or reorganizes the chain, it fails
 * here, not in users' calendars. The other side (the observer still not
 * registered) is guarded by ObserverCauserTest.
 */
class GroupDayTemplateCleanupTest extends FeatureTestCase
{
    private const TEMPLATE_START = '08:00';
    private const TEMPLATE_END   = '16:00';

    private Group $group;
    private User $editor;
    private User $member;
    private string $serviceDate;
    private int $dayNumber;

    protected function setUp(): void
    {
        parent::setUp();

        $this->group = $this->createGroup([
            'name'           => 'Napsablon Csoport',
            'min_time'       => 60,
            'max_time'       => 240,
            'min_publishers' => 1,
            'max_publishers' => 3,
            'need_approval'  => 0,
        ]);

        $this->editor = $this->createUser([
            'email' => 'daytpl-editor@example.test',
            'name'  => 'Szerkesztő Sára',
        ]);
        $this->attachUserToGroup($this->editor, $this->group, 'roler');

        $this->member = $this->createUser([
            'email' => 'daytpl-member@example.test',
            'name'  => 'Tag Tamás',
        ]);
        $this->attachUserToGroup($this->member, $this->group, 'member');

        // One week from now: definitely after the change_date (today), so it will
        // be included in the $refresh_dates set.
        $date = now()->addWeek()->startOfDay();
        $this->serviceDate = $date->toDateString();
        $this->dayNumber   = (int) $date->format('w');

        GroupDay::factory()->create([
            'group_id'   => $this->group->id,
            'day_number' => $this->dayNumber,
            'start_time' => self::TEMPLATE_START,
            'end_time'   => self::TEMPLATE_END,
        ]);

        $this->createServiceDay($this->serviceDate);
    }

    /**
     * A group_dates row matching the day template. GroupDateHelper starts
     * from these: whatever it doesn't find here, it doesn't recalculate either.
     */
    private function createServiceDay(string $date): GroupDate
    {
        return $this->createEventDate($this->group, $date, [
            'date_start' => $date.' '.self::TEMPLATE_START.':00',
            'date_end'   => $date.' '.self::TEMPLATE_END.':00',
        ]);
    }

    private function createEvent(string $date, string $from, string $to, ?User $user = null): Event
    {
        return Event::factory()->create([
            'group_id'    => $this->group->id,
            'user_id'     => ($user ?? $this->member)->id,
            'day'         => $date,
            'start'       => $date.' '.$from.':00',
            'end'         => $date.' '.$to.':00',
            'status'      => 1,
            'accepted_at' => now(),
        ]);
    }

    /**
     * The group's settings unchanged - besides the day template we don't
     * want to modify anything, just satisfy the validator.
     */
    private function formState(): array
    {
        return [
            'name'              => 'Napsablon Csoport',
            'replyTo'           => 'noreply@example.test',
            'max_extend_days'   => 30,
            'need_approval'     => 0,
            'min_publishers'    => 1,
            'max_publishers'    => 3,
            'min_time'          => 60,
            'max_time'          => 240,
            'showPhone'         => 1,
            'messages_on'       => 1,
            'messages_write'    => 1,
            'messages_priority' => 1,
            'weather_enabled'   => 0,
        ];
    }

    /**
     * Submitting the edit form with today's change_date - this is the branch
     * where UpdateGroupForm immediately applies the change too (initChanges),
     * rather than leaving it to the scheduler.
     */
    private function submitTemplate(array $days): void
    {
        $component = Livewire::actingAs($this->editor)
            ->test(UpdateGroupForm::class, ['group' => $this->group])
            ->set('change_date', today()->toDateString());

        // mount() fills $state from $group->toArray(), but that does not
        // contain every validated field (e.g. weather_enabled), so we fill
        // the form fields - the way the browser would too.
        foreach ($this->formState() as $field => $value) {
            $component->set('state.'.$field, $value);
        }

        foreach ($days as $dayNumber => $values) {
            foreach ($values as $field => $value) {
                $component->set('days.'.$dayNumber.'.'.$field, $value);
            }
        }

        $component->call('updateGroup')->assertHasNoErrors();
    }

    /**
     * Event::getStartAttribute() overrides the datetime cast, and
     * returns a unixtime - strtotime() on it would silently return false.
     */
    private function startTimeOf(Event $event): string
    {
        return date('H:i', Event::findOrFail($event->id)->start);
    }

    /**
     * Not assertNothingSent(), because creating the event on its own already
     * sends one (EventObserver::created) - here we only forbid the cleanup's
     * notifications.
     */
    private function assertNoCleanupNotifications(): void
    {
        Notification::assertNotSentTo($this->member, EventDeletedNotification::class);
        Notification::assertNotSentTo($this->member, EventUpdatedNotification::class);
    }

    // =========================================================================
    // 1. Narrowing the day
    // =========================================================================

    public function test_narrowing_a_service_day_deletes_the_events_that_fall_outside(): void
    {
        Notification::fake();

        $before  = $this->createEvent($this->serviceDate, '08:00', '09:00');
        $inside  = $this->createEvent($this->serviceDate, '10:00', '12:00');
        $after   = $this->createEvent($this->serviceDate, '14:00', '15:00');

        // 08:00-16:00 -> 10:00-12:00
        $this->submitTemplate([
            $this->dayNumber => ['start_time' => '10:00', 'end_time' => '12:00'],
        ]);

        $this->assertNull(Event::find($before->id), 'Az új ablak elé eső eseményt törölni kell.');
        $this->assertNull(Event::find($after->id), 'Az új ablak mögé eső eseményt törölni kell.');
        $this->assertNotNull(Event::find($inside->id), 'Az ablakon belüli esemény maradjon.');
    }

    public function test_narrowing_a_service_day_pulls_a_partially_overlapping_event_inside(): void
    {
        Notification::fake();

        $overlapping = $this->createEvent($this->serviceDate, '09:00', '11:00');

        $this->submitTemplate([
            $this->dayNumber => ['start_time' => '10:00', 'end_time' => '12:00'],
        ]);

        // Only its start sticks out, so it's not deletion but adjustment.
        $this->assertNotNull(Event::find($overlapping->id));
        $this->assertSame('10:00', $this->startTimeOf($overlapping));
    }

    public function test_narrowing_a_service_day_notifies_the_affected_user(): void
    {
        Notification::fake();

        $this->createEvent($this->serviceDate, '08:00', '09:00');
        $this->createEvent($this->serviceDate, '09:00', '11:00');

        $this->submitTemplate([
            $this->dayNumber => ['start_time' => '10:00', 'end_time' => '12:00'],
        ]);

        Notification::assertSentTo($this->member, EventDeletedNotification::class);
        Notification::assertSentTo($this->member, EventUpdatedNotification::class);
    }

    public function test_the_group_date_row_follows_the_narrowed_template(): void
    {
        Notification::fake();

        $this->submitTemplate([
            $this->dayNumber => ['start_time' => '10:00', 'end_time' => '12:00'],
        ]);

        $groupDate = GroupDate::where('group_id', $this->group->id)
            ->where('date', $this->serviceDate)
            ->first();

        $this->assertNotNull($groupDate);
        $this->assertSame('10:00', date('H:i', strtotime($groupDate->date_start)));
        $this->assertSame('12:00', date('H:i', strtotime($groupDate->date_end)));
    }

    // =========================================================================
    // 2. Complete deletion of the day
    // =========================================================================

    public function test_removing_a_service_day_deletes_every_event_on_it(): void
    {
        Notification::fake();

        $morning = $this->createEvent($this->serviceDate, '08:00', '09:00');
        $noon    = $this->createEvent($this->serviceDate, '12:00', '13:00');

        // Unchecking the checkbox writes false in place of day_number -
        // updateGroupFutureChanges reads this as a deletion.
        $this->submitTemplate([
            $this->dayNumber => ['day_number' => false],
        ]);

        $this->assertNull(Event::find($morning->id));
        $this->assertNull(Event::find($noon->id));
        Notification::assertSentToTimes($this->member, EventDeletedNotification::class, 2);
    }

    public function test_removing_a_service_day_purges_the_group_date_and_its_stats(): void
    {
        Notification::fake();

        $this->createEvent($this->serviceDate, '08:00', '09:00');

        DayStat::factory()->create([
            'group_id'  => $this->group->id,
            'day'       => $this->serviceDate,
            'time_slot' => $this->serviceDate.' 08:00',
            'events'    => 1,
        ]);

        $this->submitTemplate([
            $this->dayNumber => ['day_number' => false],
        ]);

        $this->assertSame(
            0,
            GroupDate::where('group_id', $this->group->id)->where('date', $this->serviceDate)->count(),
            'A már nem szolgálati nap group_dates sorát törölni kell.'
        );
        $this->assertSame(
            0,
            DayStat::where('group_id', $this->group->id)->where('day', $this->serviceDate)->count(),
            'A hozzá tartozó statisztikát is.'
        );
    }

    public function test_removing_a_service_day_removes_the_template_row_itself(): void
    {
        Notification::fake();

        $this->submitTemplate([
            $this->dayNumber => ['day_number' => false],
        ]);

        $this->assertSame(
            0,
            GroupDay::where('group_id', $this->group->id)->where('day_number', $this->dayNumber)->count(),
            'A group_days sort az updateGroupFutureChanges::initChanges() törli.'
        );
    }

    // =========================================================================
    // 3. What it must NOT touch
    // =========================================================================

    public function test_events_before_the_change_date_are_left_alone(): void
    {
        Notification::fake();

        // A day two weeks earlier, falling on the same day of the week. The
        // $refresh_dates filter is date >= change_date, so this never even
        // makes it into the set.
        $pastDate = now()->subWeeks(2)->startOfDay()->toDateString();
        $this->createServiceDay($pastDate);
        $pastEvent = $this->createEvent($pastDate, '08:00', '09:00');

        $this->submitTemplate([
            $this->dayNumber => ['start_time' => '10:00', 'end_time' => '12:00'],
        ]);

        $this->assertNotNull(Event::find($pastEvent->id), 'A múltbeli eseményt nem szabad hozzáigazítani.');
        $this->assertNoCleanupNotifications();
    }

    public function test_widening_a_service_day_deletes_nothing(): void
    {
        Notification::fake();

        $event = $this->createEvent($this->serviceDate, '09:00', '11:00');

        // 08:00-16:00 -> 07:00-17:00
        $this->submitTemplate([
            $this->dayNumber => ['start_time' => '07:00', 'end_time' => '17:00'],
        ]);

        // The chain runs - the group_dates row follows the widening - but
        // there's nothing for it to clean up.
        $groupDate = GroupDate::where('group_id', $this->group->id)
            ->where('date', $this->serviceDate)
            ->first();
        $this->assertSame('07:00', date('H:i', strtotime($groupDate->date_start)));

        $this->assertNotNull(Event::find($event->id));
        $this->assertSame('09:00', $this->startTimeOf($event));
        $this->assertNoCleanupNotifications();
    }

    public function test_the_cleanup_runs_without_the_group_day_observer(): void
    {
        // The closing assertion: everything that happens above happens
        // WITHOUT registering the GroupDayObserver. The observer and its two
        // jobs are therefore a superseded implementation, not a missing
        // feature - turning them on would not add a new capability, it would
        // run the same thing a second time.
        Notification::fake();

        $event = $this->createEvent($this->serviceDate, '08:00', '09:00');

        $this->submitTemplate([
            $this->dayNumber => ['day_number' => false],
        ]);

        $this->assertNull(Event::find($event->id), 'A takarítás observer nélkül is megtörtént.');

        // The GroupDay row was deleted (initChanges), but no log entry
        // was created - that would be the observer's signature.
        $this->assertSame(
            0,
            LogHistory::where('model_type', GroupDay::class)->count(),
            'Ha ez elbukik, a GroupDayObserver regisztrációja került vissza.'
        );
    }
}
