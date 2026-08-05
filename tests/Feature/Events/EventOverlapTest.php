<?php

namespace Tests\Feature\Events;

use App\Http\Livewire\Events\EventEdit;
use App\Models\Event;
use App\Models\Group;
use App\Models\User;
use Livewire\Livewire;
use Tests\Feature\FeatureTestCase;

/**
 * TODO 07.1: időpont-átfedések kezelése.
 *
 * Két, egymástól független szabály él egymás mellett:
 *
 * 1. CSOPORTON BELÜL a foglalás sávonként történik. Egy 08:00-10:00-s esemény
 *    a 08:00-s ÉS a 09:00-s sávot is elfoglalja, ezért egy 09:00-12:00-s kérés
 *    a 09:00-s sávon ütközik - miközben a 10:00-12:00 még szabad. Ez a
 *    felhasználó eredeti példája, és önálló tesztet kap.
 *
 * 2. CSOPORTOK KÖZÖTT a saveEvent() (:583-601) egy nyers lekérdezéssel nézi,
 *    hogy a jelentkező nincs-e már szolgálatban máshol ugyanabban az időben.
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
    // 1. A felhasználó eredeti példája
    // =========================================================================

    public function test_an_eight_to_ten_booking_blocks_a_nine_to_twelve_application(): void
    {
        // "Valaki 8:00-10:00-ig jelentkezik, másvalaki 9:00-12:00-ig - akkor a
        // 9:00-10:00 időpontra már nem lehet jelentkezni, mert betelt."
        $group = $this->createGroup(['need_approval' => 0]);
        $this->createEventDate($group, $this->date, [
            'date_max_publishers' => 1,
            'date_min_time'       => 60,
        ]);

        $first = $this->newMember($group, 'ov-first@example.test');
        $second = $this->newMember($group, 'ov-second@example.test');

        $this->actingAs($first);
        $this->createEventInRange($group, $first, $this->date, '08:00', '10:00');

        // A 09:00-12:00 kérés a 09:00-s sávon ütközik.
        $this->book($group, $second, '09:00', '12:00')->assertHasErrors(['start', 'end']);

        $this->assertSame(1, Event::where('group_id', $group->id)->count());
    }

    public function test_the_free_remainder_of_the_range_is_still_bookable(): void
    {
        // Ugyanaz a kiindulás, de a 10:00-12:00 tartomány érintetlen.
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
        // Ugyanaz a szabály 30 perces lépésközzel: a 08:00-10:00 esemény négy
        // fél órás sávot foglal, tehát a 09:30 is ütközik - de a 10:00 nem.
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
    // 2. A betelt sávok eltűnnek a választható időpontok közül
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

        // A betelt sávok kezdésként nem választhatók...
        $this->assertArrayNotHasKey($this->ts('08:00'), $dayData['selects']['start']);
        $this->assertArrayNotHasKey($this->ts('09:00'), $dayData['selects']['start']);
        $this->assertArrayHasKey($this->ts('10:00'), $dayData['selects']['start']);

        // ...és a rájuk következő időpont befejezésként sem.
        $this->assertArrayNotHasKey($this->ts('09:00'), $dayData['selects']['end']);
        $this->assertArrayNotHasKey($this->ts('10:00'), $dayData['selects']['end']);
        $this->assertArrayHasKey($this->ts('11:00'), $dayData['selects']['end']);
        $this->assertArrayHasKey($this->ts('12:00'), $dayData['selects']['end']);
    }

    public function test_a_slot_becomes_ready_once_the_minimum_publisher_count_is_met(): void
    {
        // A 'ready' státusz azt jelzi, hogy a sáv már működőképes, de még
        // fér rá jelentkező. Csak a telítettség alatt érhető el (elseif ág).
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

        // A 'ready' sáv továbbra is választható.
        $this->assertArrayHasKey($this->ts('09:00'), $dayData['selects']['start']);
    }

    public function test_a_pending_only_slot_does_not_become_ready(): void
    {
        // A 'ready' az accepted számlálóra épül, ami csak elfogadott
        // eseményekre nő - függő jelentkezés nem teszi működőképessé a sávot.
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
    // 3. Letiltott sávok
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
    // 4. Csoportok közötti ütközés (busy)
    // =========================================================================

    /**
     * @return array{0: Group, 1: Group, 2: User} [célcsoport, másik csoport, jelentkező]
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
        // A feltétel szigorú egyenlőtlenség (start < end ÉS end > start), ezért
        // a pontosan érintkező szolgálatok nem ütköznek.
        [$target, $other, $user] = $this->twoGroupSetup('busy-touch');

        $this->createEventInRange($other, $user, $this->date, '08:00', '10:00');

        $this->book($target, $user, '10:00', '12:00')->assertHasNoErrors();

        $this->assertSame(1, Event::where('group_id', $target->id)->count());
    }

    public function test_a_pending_event_in_another_group_does_not_block(): void
    {
        // A lekérdezés status = 1-re szűr: a még el nem bírált jelentkezés
        // nem foglalja le a jelentkezőt.
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

        // Tömeges törlés, ahogy a Groups\DeleteGroup is teszi: a GroupObserver
        // deleted() ága egy nem létező mezőt olvas, ezért Eloquent-törléssel
        // végzetes hibát dobna (TODO 05-ben rögzített látens hiba).
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
        // A lekérdezés kizárja a célcsoportot (group_id != groupId), tehát a
        // csoporton belüli ütközést NEM a busy fogja meg, hanem a sávonkénti
        // kapacitás és a saját eseményekre tett disabled_slots jelölés.
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
