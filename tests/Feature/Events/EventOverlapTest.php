<?php

namespace Tests\Feature\Events;

use App\Http\Livewire\Events\EventEdit;
use App\Models\Event;
use App\Models\Group;
use App\Models\User;
use Livewire\Livewire;
use Tests\Feature\FeatureTestCase;

/**
 * TODO 07.1: handling time-slot overlaps.
 *
 * Two independent rules coexist:
 *
 * 1. WITHIN A GROUP booking happens per slot. An 08:00-10:00 event
 *    occupies BOTH the 08:00 AND the 09:00 slot, so a 09:00-12:00 request
 *    conflicts on the 09:00 slot - while 10:00-12:00 is still free. This is the
 *    user's original example, and gets its own test.
 *
 * 2. BETWEEN GROUPS saveEvent() (:583-601) checks with a raw query whether
 *    the applicant is not already serving elsewhere at the same time.
 */
class EventOverlapTest extends FeatureTestCase
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

    private function newMember(Group $group, string $email): User
    {
        $user = $this->createUser(['email' => $email]);
        $this->attachUserToGroup($user, $group, 'member');

        return $user;
    }

    private function book(Group $group, User $user, string $from, string $to)
    {
        return Livewire::actingAs($user)
            ->test(EventEdit::class, ['groupId' => $group->id, 'date' => $this->date])
            ->call('setStart', $this->ts($from))
            ->set('state.end', $this->ts($to))
            ->call('saveEvent');
    }

    private function dayTable(Group $group, User $user): array
    {
        return Livewire::actingAs($user)
            ->test(EventEdit::class, ['groupId' => $group->id, 'date' => $this->date])
            ->get('day_data');
    }

    // =========================================================================
    // 1. The user's original example
    // =========================================================================

    public function test_an_eight_to_ten_booking_blocks_a_nine_to_twelve_application(): void
    {
        // "Someone applies for 8:00-10:00, someone else for 9:00-12:00 - then the
        // 9:00-10:00 time slot can no longer be applied for, because it's full."
        $group = $this->createGroup(['need_approval' => 0]);
        $this->createEventDate($group, $this->date, [
            'date_max_publishers' => 1,
            'date_min_time'       => 60,
        ]);

        $first = $this->newMember($group, 'ov-first@example.test');
        $second = $this->newMember($group, 'ov-second@example.test');

        $this->actingAs($first);
        $this->createEventInRange($group, $first, $this->date, '08:00', '10:00');

        // The 09:00-12:00 request conflicts on the 09:00 slot.
        $this->book($group, $second, '09:00', '12:00')->assertHasErrors(['start', 'end']);

        $this->assertSame(1, Event::where('group_id', $group->id)->count());
    }

    public function test_the_free_remainder_of_the_range_is_still_bookable(): void
    {
        // Same starting point, but the 10:00-12:00 range is untouched.
        $group = $this->createGroup(['need_approval' => 0]);
        $this->createEventDate($group, $this->date, [
            'date_max_publishers' => 1,
            'date_min_time'       => 60,
        ]);

        $first = $this->newMember($group, 'ov-rem-first@example.test');
        $second = $this->newMember($group, 'ov-rem-second@example.test');

        $this->actingAs($first);
        $this->createEventInRange($group, $first, $this->date, '08:00', '10:00');

        $this->book($group, $second, '10:00', '12:00')->assertHasNoErrors();

        $this->assertDatabaseHas('events', [
            'group_id' => $group->id,
            'user_id'  => $second->id,
            'start'    => $this->date.' 10:00',
        ]);
    }

    public function test_the_same_overlap_rule_holds_at_half_hour_granularity(): void
    {
        // Same rule at 30-minute granularity: the 08:00-10:00 event occupies four
        // half-hour slots, so 09:30 also conflicts - but 10:00 does not.
        $group = $this->createGroup(['need_approval' => 0]);
        $this->createEventDate($group, $this->date, [
            'date_max_publishers' => 1,
            'date_min_time'       => 30,
        ]);

        $first = $this->newMember($group, 'ov-half-first@example.test');
        $second = $this->newMember($group, 'ov-half-second@example.test');

        $this->actingAs($first);
        $this->createEventInRange($group, $first, $this->date, '08:00', '10:00');

        $this->book($group, $second, '09:30', '12:00')->assertHasErrors(['start', 'end']);

        $this->book($group, $second, '10:00', '12:00')->assertHasNoErrors();
    }

    // =========================================================================
    // 2. Saturated slots disappear from the selectable time options
    // =========================================================================

    public function test_saturated_slots_are_marked_full_and_removed_from_the_selects(): void
    {
        $group = $this->createGroup(['need_approval' => 0]);
        $this->createEventDate($group, $this->date, [
            'date_max_publishers' => 1,
            'date_min_time'       => 60,
        ]);

        $first = $this->newMember($group, 'ov-sel-first@example.test');
        $second = $this->newMember($group, 'ov-sel-second@example.test');

        $this->actingAs($first);
        $this->createEventInRange($group, $first, $this->date, '08:00', '10:00');

        $dayData = $this->dayTable($group, $second);

        $this->assertSame('full', $dayData['table'][$this->slotKey('08:00')]['status']);
        $this->assertSame('full', $dayData['table'][$this->slotKey('09:00')]['status']);
        $this->assertSame('free', $dayData['table'][$this->slotKey('10:00')]['status']);
        $this->assertSame('free', $dayData['table'][$this->slotKey('11:00')]['status']);

        // Saturated slots cannot be selected as a start...
        $this->assertArrayNotHasKey($this->ts('08:00'), $dayData['selects']['start']);
        $this->assertArrayNotHasKey($this->ts('09:00'), $dayData['selects']['start']);
        $this->assertArrayHasKey($this->ts('10:00'), $dayData['selects']['start']);

        // ...nor can the time slot following them be selected as an end.
        $this->assertArrayNotHasKey($this->ts('09:00'), $dayData['selects']['end']);
        $this->assertArrayNotHasKey($this->ts('10:00'), $dayData['selects']['end']);
        $this->assertArrayHasKey($this->ts('11:00'), $dayData['selects']['end']);
        $this->assertArrayHasKey($this->ts('12:00'), $dayData['selects']['end']);
    }

    public function test_a_slot_becomes_ready_once_the_minimum_publisher_count_is_met(): void
    {
        // The 'ready' status indicates that the slot is already functional, but there
        // is still room for an applicant. Only reachable below saturation (elseif branch).
        $group = $this->createGroup(['need_approval' => 0]);
        $this->createEventDate($group, $this->date, [
            'date_min_publishers' => 1,
            'date_max_publishers' => 3,
            'date_min_time'       => 60,
        ]);

        $first = $this->newMember($group, 'ov-ready-first@example.test');
        $observer = $this->newMember($group, 'ov-ready-observer@example.test');

        $this->actingAs($first);
        $this->createEventInRange($group, $first, $this->date, '09:00', '10:00');

        $dayData = $this->dayTable($group, $observer);

        $this->assertSame('ready', $dayData['table'][$this->slotKey('09:00')]['status']);
        $this->assertSame('free', $dayData['table'][$this->slotKey('10:00')]['status']);

        // The 'ready' slot remains selectable.
        $this->assertArrayHasKey($this->ts('09:00'), $dayData['selects']['start']);
    }

    public function test_a_pending_only_slot_does_not_become_ready(): void
    {
        // 'ready' is based on the accepted counter, which only increases for accepted
        // events - a pending application does not make the slot functional.
        $group = $this->createGroup(['need_approval' => 1]);
        $this->createEventDate($group, $this->date, [
            'date_min_publishers' => 1,
            'date_max_publishers' => 3,
            'date_min_time'       => 60,
        ]);

        $first = $this->newMember($group, 'ov-pending-first@example.test');
        $observer = $this->newMember($group, 'ov-pending-observer@example.test');

        $this->actingAs($first);
        $this->createEventInRange($group, $first, $this->date, '09:00', '10:00', false);

        $dayData = $this->dayTable($group, $observer);

        $this->assertSame('free', $dayData['table'][$this->slotKey('09:00')]['status']);
    }

    // =========================================================================
    // 3. Disabled slots
    // =========================================================================

    public function test_disabled_slots_are_full_even_without_any_event(): void
    {
        $group = $this->createGroup(['need_approval' => 0]);
        $this->createEventDate($group, $this->date, [
            'date_max_publishers' => 3,
            'date_min_time'       => 60,
            'disabled_slots'      => ['10:00' => true],
        ]);

        $member = $this->newMember($group, 'ov-disabled@example.test');
        $this->actingAs($member);

        $dayData = $this->dayTable($group, $member);

        $this->assertSame('full', $dayData['table'][$this->slotKey('10:00')]['status']);
        $this->assertSame('free', $dayData['table'][$this->slotKey('09:00')]['status']);

        $this->assertArrayNotHasKey($this->ts('10:00'), $dayData['selects']['start']);
        $this->assertArrayHasKey($this->ts('09:00'), $dayData['selects']['start']);
    }

    public function test_a_booking_into_a_disabled_slot_is_rejected(): void
    {
        $group = $this->createGroup(['need_approval' => 0]);
        $this->createEventDate($group, $this->date, [
            'date_max_publishers' => 3,
            'date_min_time'       => 60,
            'disabled_slots'      => ['10:00' => true],
        ]);

        $member = $this->newMember($group, 'ov-disabled-book@example.test');
        $this->actingAs($member);

        $this->book($group, $member, '10:00', '11:00')->assertHasErrors(['start']);

        $this->assertSame(0, Event::where('group_id', $group->id)->count());
    }

    // =========================================================================
    // 4. Conflict between groups (busy)
    // =========================================================================

    /**
     * @return array{0: Group, 1: Group, 2: User} [target group, other group, applicant]
     */
    private function twoGroupSetup(string $emailPrefix): array
    {
        $target = $this->createGroup(['need_approval' => 0]);
        $other = $this->createGroup(['need_approval' => 0]);

        $this->createEventDate($target, $this->date, ['date_max_publishers' => 3]);

        $user = $this->createUser(['email' => $emailPrefix.'@example.test']);
        $this->attachUserToGroup($user, $target, 'member');
        $this->attachUserToGroup($user, $other, 'member');

        $this->actingAs($user);

        return [$target, $other, $user];
    }

    public function test_an_accepted_event_in_another_group_blocks_an_overlapping_booking(): void
    {
        [$target, $other, $user] = $this->twoGroupSetup('busy-overlap');

        $this->createEventInRange($other, $user, $this->date, '09:00', '11:00');

        $this->book($target, $user, '10:00', '12:00')->assertHasErrors(['busy']);

        $this->assertSame(0, Event::where('group_id', $target->id)->count());
    }

    public function test_touching_ranges_in_another_group_are_allowed(): void
    {
        // The condition is a strict inequality (start < end AND end > start), so
        // exactly touching services do not conflict.
        [$target, $other, $user] = $this->twoGroupSetup('busy-touch');

        $this->createEventInRange($other, $user, $this->date, '08:00', '10:00');

        $this->book($target, $user, '10:00', '12:00')->assertHasNoErrors();

        $this->assertSame(1, Event::where('group_id', $target->id)->count());
    }

    public function test_a_pending_event_in_another_group_does_not_block(): void
    {
        // The query filters for status = 1: an application not yet reviewed
        // does not reserve the applicant.
        [$target, $other, $user] = $this->twoGroupSetup('busy-pending');

        $this->createEventInRange($other, $user, $this->date, '09:00', '11:00', false);

        $this->book($target, $user, '10:00', '12:00')->assertHasNoErrors();
    }

    public function test_a_soft_deleted_event_in_another_group_does_not_block(): void
    {
        [$target, $other, $user] = $this->twoGroupSetup('busy-deleted-event');

        $event = $this->createEventInRange($other, $user, $this->date, '09:00', '11:00');
        $event->delete();

        $this->book($target, $user, '10:00', '12:00')->assertHasNoErrors();
    }

    public function test_an_event_in_a_soft_deleted_group_does_not_block(): void
    {
        [$target, $other, $user] = $this->twoGroupSetup('busy-deleted-group');

        $this->createEventInRange($other, $user, $this->date, '09:00', '11:00');

        // Bulk delete, the same way Groups\DeleteGroup does it - meaning
        // without model events. (The Eloquent path has also worked since TODO 10,
        // but here we want to mimic the production code path.)
        Group::where('id', $other->id)->delete();

        $this->book($target, $user, '10:00', '12:00')->assertHasNoErrors();
    }

    public function test_another_users_event_elsewhere_does_not_block(): void
    {
        [$target, $other, $user] = $this->twoGroupSetup('busy-other-user');

        $stranger = $this->createUser(['email' => 'busy-stranger@example.test']);
        $this->attachUserToGroup($stranger, $other, 'member');
        $this->createEventInRange($other, $stranger, $this->date, '09:00', '11:00');

        $this->book($target, $user, '10:00', '12:00')->assertHasNoErrors();
    }

    public function test_the_busy_check_ignores_events_in_the_target_group_itself(): void
    {
        // The query excludes the target group (group_id != groupId), so a conflict
        // within the group is NOT caught by busy, but by the per-slot
        // capacity and the disabled_slots marking on one's own events.
        $group = $this->createGroup(['need_approval' => 0]);
        $this->createEventDate($group, $this->date, ['date_max_publishers' => 3]);

        $user = $this->newMember($group, 'busy-same-group@example.test');
        $this->actingAs($user);

        $this->createEventInRange($group, $user, $this->date, '09:00', '11:00');

        $component = $this->book($group, $user, '10:00', '12:00');

        $component->assertHasNoErrors(['busy']);
        $component->assertHasErrors(['start']);
    }
}
