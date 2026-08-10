<?php

namespace Tests\Feature\Events;

use App\Http\Livewire\Events\EventEdit;
use App\Models\Event;
use App\Models\Group;
use App\Models\User;
use Livewire\Livewire;
use Tests\Feature\FeatureTestCase;

/**
 * TODO 07.1: the capacity gate of Events\EventEdit::saveEvent().
 *
 * This is the application's central business rule, and until now it had
 * ZERO coverage: CalendarEventEditTest sets date_max_publishers => 3, but
 * not a single one of its tests fills up a slot, so the capacity branch
 * never ran.
 *
 * Capacity applies PER SLOT, not per event. getInfo() (EventEdit.php:228-326)
 * builds the day's table, walks the existing events, and increments a
 * counter on every slot they touch; saveEvent() (:475-485) walks the
 * requested range with the same step size.
 *
 * IMPORTANT - the approval-based over-application in saveEvent() does NOT
 * work the way reading the code suggests. Details in the
 * test_pending_applications_do_not_consume_publisher_capacity test.
 */
class EventCapacityTest extends FeatureTestCase
{
    private string $date;

    protected function setUp(): void
    {
        parent::setUp();

        $this->date = now()->addDay()->toDateString();
    }

    private function ts(string $time): int
    {
        return $this->timestampFor($this->date, $time);
    }

    /**
     * A new member trying to apply through the component.
     */
    private function newMember(Group $group, string $email): User
    {
        $user = $this->createUser(['email' => $email]);
        $this->attachUserToGroup($user, $group, 'member');

        return $user;
    }

    /**
     * The actual application through the component - the same path the
     * browser also takes.
     */
    private function book(Group $group, User $user, string $from, string $to)
    {
        return Livewire::actingAs($user)
            ->test(EventEdit::class, ['groupId' => $group->id, 'date' => $this->date])
            ->call('setStart', $this->ts($from))
            ->set('state.end', $this->ts($to))
            ->call('saveEvent');
    }

    private function eventCount(Group $group): int
    {
        return Event::where('group_id', $group->id)->count();
    }

    // =========================================================================
    // 1. Per-slot maximum in a group without approval
    // =========================================================================

    public function test_bookings_are_accepted_up_to_the_per_slot_maximum(): void
    {
        $group = $this->createGroup(['need_approval' => 0]);
        $this->createEventDate($group, $this->date, ['date_max_publishers' => 3]);

        $actor = $this->newMember($group, 'cap-third@example.test');
        $this->actingAs($actor);

        // Two spots are already taken, the third is still free.
        $this->fillSlotRange($group, $this->date, '09:00', '10:00', 2, true, 'cap-taken');

        $this->book($group, $actor, '09:00', '10:00')
            ->assertHasNoErrors()
            ->assertDispatchedBrowserEvent('success');

        $this->assertSame(3, $this->eventCount($group));
    }

    public function test_the_booking_after_the_per_slot_maximum_is_rejected(): void
    {
        $group = $this->createGroup(['need_approval' => 0]);
        $this->createEventDate($group, $this->date, ['date_max_publishers' => 3]);

        $actor = $this->newMember($group, 'cap-fourth@example.test');
        $this->actingAs($actor);

        $this->fillSlotRange($group, $this->date, '09:00', '10:00', 3, true, 'cap-full');

        $component = $this->book($group, $actor, '09:00', '10:00')
            ->assertHasErrors(['start', 'end']);

        $this->assertContains(
            __('event.reach_max_publisher'),
            $component->lastErrorBag->get('start'),
            'A negyedik jelentkezésnek a maximális hírnökszámra kell hivatkoznia.'
        );

        // The table did not change.
        $this->assertSame(3, $this->eventCount($group));
    }

    public function test_capacity_is_counted_per_slot_not_per_day(): void
    {
        $group = $this->createGroup(['need_approval' => 0]);
        $this->createEventDate($group, $this->date, ['date_max_publishers' => 1]);

        $actor = $this->newMember($group, 'cap-next-slot@example.test');
        $this->actingAs($actor);

        // The 09:00 slot is full, but the 10:00 one is untouched.
        $this->fillSlotRange($group, $this->date, '09:00', '10:00', 1, true, 'cap-slot');

        $this->book($group, $actor, '10:00', '11:00')->assertHasNoErrors();

        $this->assertSame(2, $this->eventCount($group));
    }

    // =========================================================================
    // 2. Approval group: over-application
    // =========================================================================

    public function test_approval_groups_accept_more_applications_than_the_maximum(): void
    {
        // The user's second rule: if approval is required, there can be more
        // applicants than the maximum - the excess is later filtered by the
        // approval process.
        config(['events.max_columns' => 4]);

        $group = $this->createGroup(['need_approval' => 1]);
        $this->createEventDate($group, $this->date, ['date_max_publishers' => 3]);

        $actor = $this->newMember($group, 'cap-over-4th@example.test');
        $this->actingAs($actor);

        // Three PENDING applications - exactly the maximum, none accepted yet.
        $this->fillSlotRange($group, $this->date, '09:00', '10:00', 3, false, 'cap-over');

        $this->book($group, $actor, '09:00', '10:00')->assertHasNoErrors();

        $this->assertSame(4, $this->eventCount($group));
        $this->assertDatabaseHas('events', [
            'group_id' => $group->id,
            'user_id'  => $actor->id,
            'status'   => 0,
        ]);
    }

    public function test_approval_groups_stop_accepting_at_the_raised_ceiling(): void
    {
        // The raised ceiling is max_publishers + events.max_columns = 3 + 4 = 7.
        config(['events.max_columns' => 4]);

        $group = $this->createGroup(['need_approval' => 1]);
        $this->createEventDate($group, $this->date, ['date_max_publishers' => 3]);

        $actor = $this->newMember($group, 'cap-ceiling@example.test');
        $this->actingAs($actor);

        $this->fillSlotRange($group, $this->date, '09:00', '10:00', 7, false, 'cap-ceil');

        $component = $this->book($group, $actor, '09:00', '10:00')
            ->assertHasErrors(['start', 'end']);

        // CHARACTERIZATION: the ceiling is held NOT by the publishers check,
        // but by the filtering of valid time points - hence the error
        // message is invalid_value, not reach_max_publisher. See the
        // publishers test below.
        $this->assertContains(__('event.invalid_value'), $component->lastErrorBag->get('start'));
        $this->assertNotContains(__('event.reach_max_publisher'), $component->lastErrorBag->get('start'));

        $this->assertSame(7, $this->eventCount($group));
    }

    public function test_the_raised_ceiling_collapses_once_the_accepted_count_reaches_the_maximum(): void
    {
        // Same group and slot as in the over-application test - the only
        // difference is that the three events are ACCEPTED. This drops the
        // limit back from 7 to 3, and the fourth application fails.
        config(['events.max_columns' => 4]);

        $group = $this->createGroup(['need_approval' => 1]);
        $this->createEventDate($group, $this->date, ['date_max_publishers' => 3]);

        $actor = $this->newMember($group, 'cap-collapse@example.test');
        $this->actingAs($actor);

        $this->fillSlotRange($group, $this->date, '09:00', '10:00', 3, true, 'cap-coll');

        $component = $this->book($group, $actor, '09:00', '10:00')
            ->assertHasErrors(['start', 'end']);

        $this->assertContains(__('event.reach_max_publisher'), $component->lastErrorBag->get('start'));

        $this->assertSame(3, $this->eventCount($group));
    }

    public function test_a_group_without_approval_gets_no_extra_headroom(): void
    {
        // Contrast: same maximum, same number of pending events - but
        // without need_approval there is no raised ceiling. (In a group
        // without approval, the status=0 state can only arise from data
        // import, the component accepts everything immediately - which is
        // why we produce it with a factory.)
        config(['events.max_columns' => 4]);

        $group = $this->createGroup(['need_approval' => 0]);
        $this->createEventDate($group, $this->date, ['date_max_publishers' => 3]);

        $actor = $this->newMember($group, 'cap-noapproval@example.test');
        $this->actingAs($actor);

        $this->fillSlotRange($group, $this->date, '09:00', '10:00', 3, true, 'cap-noap');

        $this->book($group, $actor, '09:00', '10:00')->assertHasErrors(['start', 'end']);

        $this->assertSame(3, $this->eventCount($group));
    }

    public function test_the_extra_headroom_follows_the_events_max_columns_config(): void
    {
        // The number 4 must not be hardwired into the rule: with
        // max_columns = 1 the ceiling is 1 + 1 = 2.
        config(['events.max_columns' => 1]);

        $group = $this->createGroup(['need_approval' => 1]);
        $this->createEventDate($group, $this->date, ['date_max_publishers' => 1]);

        $actor = $this->newMember($group, 'cap-cols@example.test');
        $this->actingAs($actor);

        // One pending application: we are below the ceiling, the second one goes through.
        $this->fillSlotRange($group, $this->date, '09:00', '10:00', 1, false, 'cap-col1');
        $this->book($group, $actor, '09:00', '10:00')->assertHasNoErrors();

        // Now there are two of them - the third one fails.
        $third = $this->newMember($group, 'cap-cols-third@example.test');
        $this->book($group, $third, '09:00', '10:00')->assertHasErrors(['start', 'end']);

        $this->assertSame(2, $this->eventCount($group));
    }

    // =========================================================================
    // 3. The publishers/accepted counting - recording the discovered discrepancy
    // =========================================================================

    public function test_pending_applications_do_not_consume_publisher_capacity(): void
    {
        // CHARACTERIZATION TEST for a latent bug.
        //
        // EventEdit::getInfo() (:288-291) increments both the publishers AND
        // the accepted counter ONLY when status == 1, always together. So
        // the two values are always identical in this component.
        //
        // This causes the approval branch of saveEvent() (:477-482) to
        // algebraically collapse: if accepted >= max, the limit is max, and
        // publishers(= accepted) >= max is true; if accepted < max, the
        // limit is max + max_columns, but publishers(= accepted) < max <
        // max + max_columns, so it is false. The "+ events.max_columns"
        // branch NEVER takes effect in the save-time check.
        //
        // The over-application ceiling is in fact held by the $slots array
        // (:286), which counts EVERY event - that is why the user gets an
        // invalid_value error instead of reach_max_publisher when they hit
        // the ceiling.
        //
        // The fix belongs to TODO 77 (Events\Modal deduplication), where it
        // will be decided which count is correct - the Modal, after all,
        // increments publishers for EVERY event (Modal.php:352).
        $group = $this->createGroup(['need_approval' => 1]);
        $this->createEventDate($group, $this->date, ['date_max_publishers' => 3]);

        $actor = $this->newMember($group, 'cap-counters@example.test');
        $this->actingAs($actor);

        $this->fillSlotRange($group, $this->date, '09:00', '10:00', 3, false, 'cap-cnt');

        $component = Livewire::actingAs($actor)
            ->test(EventEdit::class, ['groupId' => $group->id, 'date' => $this->date]);

        $slot = $component->get('day_data')['table'][$this->slotKey('09:00')];

        $this->assertSame(0, $slot['publishers'], 'A függő jelentkezéseket a publishers nem számolja.');
        $this->assertSame(0, $slot['accepted']);
        $this->assertSame(3, $this->eventCount($group), 'Az események viszont léteznek.');
    }

    public function test_accepted_events_increment_both_counters_together(): void
    {
        $group = $this->createGroup(['need_approval' => 1]);
        $this->createEventDate($group, $this->date, ['date_max_publishers' => 3]);

        $actor = $this->newMember($group, 'cap-counters2@example.test');
        $this->actingAs($actor);

        $this->fillSlotRange($group, $this->date, '09:00', '10:00', 2, true, 'cap-cnt2');

        $component = Livewire::actingAs($actor)
            ->test(EventEdit::class, ['groupId' => $group->id, 'date' => $this->date]);

        $slot = $component->get('day_data')['table'][$this->slotKey('09:00')];

        $this->assertSame(2, $slot['publishers']);
        $this->assertSame(2, $slot['accepted']);
    }

    // =========================================================================
    // 4. Own event and overflow
    // =========================================================================

    public function test_a_member_cannot_book_the_same_slot_twice_in_the_same_group(): void
    {
        // getInfo() (:292-294) puts the applicant's OWN events' slots into
        // disabled_slots, regardless of capacity. This is the protection
        // against double booking within a group - the cross-group busy
        // check (EventOverlapTest) does the same for other groups.
        $group = $this->createGroup(['need_approval' => 0]);
        $this->createEventDate($group, $this->date, ['date_max_publishers' => 3]);

        $actor = $this->newMember($group, 'cap-self@example.test');
        $this->actingAs($actor);

        $this->createEventInRange($group, $actor, $this->date, '09:00', '10:00');

        $this->book($group, $actor, '09:00', '10:00')->assertHasErrors(['start', 'end']);

        $this->assertSame(1, $this->eventCount($group));
    }

    public function test_lowering_the_maximum_below_the_existing_event_count_still_opens_the_day(): void
    {
        // REVERSED by the v1-patch B11 fix.
        //
        // getInfo() reserves (max_publishers + events.max_columns) cells for
        // every slot, and uses up one per event. If a slot has more events
        // than cells, the cell list empties out, and the previous
        // unconditional min(array_keys([])) threw a ValueError under PHP 8 -
        // which made the day UNOPENABLE, not just incorrectly rendered.
        //
        // This is a reachable state, not theoretical: the events are
        // created under the old, higher maximum, then an admin lowers
        // date_max_publishers, or a future group edit overwrites it. Nobody
        // deletes the existing events in that case.
        //
        // The overflowing event now gets a separate column; the day opens.
        config(['events.max_columns' => 4]);

        $group = $this->createGroup(['need_approval' => 1]);
        $date = $this->createEventDate($group, $this->date, ['date_max_publishers' => 6]);

        $actor = $this->newMember($group, 'cap-overflow@example.test');
        $this->actingAs($actor);

        $this->fillSlotRange($group, $this->date, '09:00', '10:00', 6, false, 'cap-ovf');

        // The admin lowers the maximum: 1 + 4 = 5 cells remain for 6 events.
        $date->update(['date_max_publishers' => 1]);

        Livewire::actingAs($actor)
            ->test(EventEdit::class, ['groupId' => $group->id, 'date' => $this->date])
            ->assertOk();
    }

    public function test_the_overflowing_slot_is_still_described_correctly(): void
    {
        // The substantive half of B11: it is not enough that it does not
        // fail - the slot must also be correctly described afterwards. Six
        // events for five cells, so all the pre-reserved cells are used up
        // and the slot becomes full.
        //
        // (The `publishers` counter is deliberately not covered here:
        // getInfo() only increments it for ACCEPTED events, while the six
        // applications are pending.)
        config(['events.max_columns' => 4]);

        $group = $this->createGroup(['need_approval' => 1]);
        $date = $this->createEventDate($group, $this->date, ['date_max_publishers' => 6]);

        $actor = $this->newMember($group, 'cap-overflow-cells@example.test');
        $this->actingAs($actor);

        $this->fillSlotRange($group, $this->date, '09:00', '10:00', 6, false, 'cap-ovfc');

        $date->update(['date_max_publishers' => 1]);

        $component = Livewire::actingAs($actor)
            ->test(EventEdit::class, ['groupId' => $group->id, 'date' => $this->date]);

        $slot = $component->get('day_data')['table']["'0900'"];

        $this->assertSame([], $slot['cells'], 'Mind az öt előre foglalt cella elfogyott.');
        $this->assertSame('full', $slot['status'], 'A sáv megtelt.');
        $this->assertSame(6, $this->eventCount($group), 'Egyetlen esemény sem veszett el.');
    }
}
