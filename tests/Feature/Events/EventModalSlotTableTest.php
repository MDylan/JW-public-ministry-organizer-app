<?php

namespace Tests\Feature\Events;

use App\Http\Livewire\Events\EventEdit;
use App\Http\Livewire\Events\Modal;
use App\Models\Group;
use App\Models\User;
use Livewire\Livewire;
use Tests\Feature\FeatureTestCase;

/**
 * TODO 07.1: az Events\Modal sáv-táblája.
 *
 * A Modal a naptár olvasó oldala, és eddig NEM volt közvetlen tesztje - a
 * CalendarEventsComponentTest az Events\Events komponenst fedi. Közben a
 * Modal::getInfo() (:71-393) az EventEdit::getInfo() közel szó szerinti
 * másolata, ugyanazzal a kapacitás-képlettel. A duplikáció megszüntetése a
 * TODO 77, és ezek a tesztek az előfeltételei: ezek mondják meg, hogy a
 * kivont szolgáltatásnak MELYIK viselkedést kell reprodukálnia.
 *
 * A komponens állapota privát ($day_data, $date_data, $day_events), ezért
 * assertSet() nem használható rá - a render() viszont átadja a nézetnek
 * (:551-558), így a viewData() a helyes eszköz.
 */
class EventModalSlotTableTest extends FeatureTestCase
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

    private function newMember(Group $group, string $email, string $role = 'member'): User
    {
        $user = $this->createUser(['email' => $email]);
        $this->attachUserToGroup($user, $group, $role);

        return $user;
    }

    private function modalDayData(Group $group, User $user): array
    {
        return Livewire::actingAs($user)
            ->test(Modal::class)
            ->call('openModal', $this->date, $group->id)
            ->viewData('day_data');
    }

    private function editorDayData(Group $group, User $user): array
    {
        return Livewire::actingAs($user)
            ->test(EventEdit::class, ['groupId' => $group->id, 'date' => $this->date])
            ->get('day_data');
    }

    // =========================================================================
    // 1. A két komponens azonos sávokat épít
    // =========================================================================

    public function test_the_modal_builds_the_same_slots_as_the_editor(): void
    {
        $group = $this->createGroup(['need_approval' => 0]);
        $this->createEventDate($group, $this->date, ['date_min_time' => 60]);

        $member = $this->newMember($group, 'modal-slots@example.test');
        $this->actingAs($member);

        $modal = $this->modalDayData($group, $member);
        $editor = $this->editorDayData($group, $member);

        $this->assertSame(
            array_keys($editor['table']),
            array_keys($modal['table']),
            'A két komponensnek ugyanazokat az idősávokat kell adnia.'
        );

        $this->assertSame(
            [$this->slotKey('08:00'), $this->slotKey('09:00'), $this->slotKey('10:00'), $this->slotKey('11:00')],
            array_keys($modal['table'])
        );
    }

    public function test_the_modal_respects_a_half_hour_step(): void
    {
        $group = $this->createGroup(['need_approval' => 0]);
        $this->createEventDate($group, $this->date, ['date_min_time' => 30]);

        $member = $this->newMember($group, 'modal-half@example.test');
        $this->actingAs($member);

        $this->assertCount(8, $this->modalDayData($group, $member)['table']);
    }

    // =========================================================================
    // 2. A publishers számlálás eltérése - a TODO 77 legfontosabb döntési pontja
    // =========================================================================

    public function test_the_modal_counts_pending_events_as_publishers_but_the_editor_does_not(): void
    {
        // KARAKTERIZÁLÓ TESZT a két másolat eltéréséről.
        //
        // Modal.php:352 minden eseményre növeli a publishers-t, és csak az
        // accepted-et köti status == 1-hez. Az EventEdit.php:288-291 viszont
        // MINDKETTŐT a status == 1-hez köti, ezért ott a két számláló mindig
        // azonos - és emiatt hal el a jóváhagyásos túljelentkezési ág a
        // saveEvent()-ben (lásd EventCapacityTest).
        //
        // Ugyanaz az adat tehát két különböző számot ad a naptárban és a
        // mentés-ellenőrzésben. A TODO 77 kivonásakor el kell dönteni, melyik
        // a helyes - ez a teszt teszi a döntést mérhetővé.
        $group = $this->createGroup(['need_approval' => 1]);
        $this->createEventDate($group, $this->date, ['date_max_publishers' => 3]);

        $applicant = $this->newMember($group, 'modal-pending-applicant@example.test');
        $viewer = $this->newMember($group, 'modal-pending-viewer@example.test', 'roler');

        $this->actingAs($applicant);
        $this->createEventInRange($group, $applicant, $this->date, '09:00', '10:00', false);

        $slotKey = $this->slotKey('09:00');

        $modalSlot = $this->modalDayData($group, $viewer)['table'][$slotKey];
        $editorSlot = $this->editorDayData($group, $viewer)['table'][$slotKey];

        $this->assertSame(1, $modalSlot['publishers'], 'A Modal a függő jelentkezést is számolja.');
        $this->assertSame(0, $modalSlot['accepted']);

        $this->assertSame(0, $editorSlot['publishers'], 'Az EventEdit ugyanezt nem számolja.');
        $this->assertSame(0, $editorSlot['accepted']);
    }

    public function test_both_components_agree_on_accepted_events(): void
    {
        // Elfogadott eseményekre a két számolás egybeesik - az eltérés kizárólag
        // a függő jelentkezéseknél jelentkezik.
        $group = $this->createGroup(['need_approval' => 1]);
        $this->createEventDate($group, $this->date, ['date_max_publishers' => 3]);

        $viewer = $this->newMember($group, 'modal-accepted-viewer@example.test', 'roler');
        $this->actingAs($viewer);

        $this->fillSlotRange($group, $this->date, '09:00', '10:00', 2, true, 'modal-acc');

        $slotKey = $this->slotKey('09:00');

        $modalSlot = $this->modalDayData($group, $viewer)['table'][$slotKey];
        $editorSlot = $this->editorDayData($group, $viewer)['table'][$slotKey];

        $this->assertSame(2, $modalSlot['publishers']);
        $this->assertSame(2, $modalSlot['accepted']);
        $this->assertSame($editorSlot['publishers'], $modalSlot['publishers']);
        $this->assertSame($editorSlot['accepted'], $modalSlot['accepted']);
    }

    // =========================================================================
    // 3. Sáv-státuszok a Modalban
    // =========================================================================

    public function test_a_saturated_slot_is_marked_full_in_the_modal(): void
    {
        $group = $this->createGroup(['need_approval' => 0]);
        $this->createEventDate($group, $this->date, [
            'date_max_publishers' => 1,
            'date_min_time'       => 60,
        ]);

        $viewer = $this->newMember($group, 'modal-full-viewer@example.test', 'roler');
        $this->actingAs($viewer);

        $this->fillSlotRange($group, $this->date, '08:00', '10:00', 1, true, 'modal-full');

        $table = $this->modalDayData($group, $viewer)['table'];

        $this->assertSame('full', $table[$this->slotKey('08:00')]['status']);
        $this->assertSame('full', $table[$this->slotKey('09:00')]['status']);
        $this->assertSame('free', $table[$this->slotKey('10:00')]['status']);
    }

    public function test_a_slot_becomes_ready_in_the_modal_once_the_minimum_is_met(): void
    {
        $group = $this->createGroup(['need_approval' => 0]);
        $this->createEventDate($group, $this->date, [
            'date_min_publishers' => 1,
            'date_max_publishers' => 3,
            'date_min_time'       => 60,
        ]);

        $viewer = $this->newMember($group, 'modal-ready-viewer@example.test', 'roler');
        $this->actingAs($viewer);

        $this->fillSlotRange($group, $this->date, '09:00', '10:00', 1, true, 'modal-ready');

        $table = $this->modalDayData($group, $viewer)['table'];

        $this->assertSame('ready', $table[$this->slotKey('09:00')]['status']);
        $this->assertSame('free', $table[$this->slotKey('10:00')]['status']);
    }

    public function test_disabled_slots_are_full_in_the_modal_too(): void
    {
        $group = $this->createGroup(['need_approval' => 0]);
        $this->createEventDate($group, $this->date, [
            'date_min_time'  => 60,
            'disabled_slots' => ['10:00' => true],
        ]);

        $viewer = $this->newMember($group, 'modal-disabled@example.test', 'roler');
        $this->actingAs($viewer);

        $table = $this->modalDayData($group, $viewer)['table'];

        $this->assertSame('full', $table[$this->slotKey('10:00')]['status']);
        $this->assertSame('free', $table[$this->slotKey('09:00')]['status']);
    }

    // =========================================================================
    // 4. A nap eseményei
    // =========================================================================

    public function test_the_modal_exposes_the_events_of_the_day_keyed_by_start_slot(): void
    {
        $group = $this->createGroup(['need_approval' => 0]);
        $this->createEventDate($group, $this->date, ['date_min_time' => 60]);

        $viewer = $this->newMember($group, 'modal-events-viewer@example.test', 'roler');
        $this->actingAs($viewer);

        $event = $this->createEventInRange($group, $viewer, $this->date, '09:00', '11:00');

        $dayEvents = Livewire::actingAs($viewer)
            ->test(Modal::class)
            ->call('openModal', $this->date, $group->id)
            ->viewData('day_events');

        $this->assertArrayHasKey($this->slotKey('09:00'), $dayEvents);
        $this->assertArrayHasKey($event->id, $dayEvents[$this->slotKey('09:00')]);

        $rendered = $dayEvents[$this->slotKey('09:00')][$event->id];

        $this->assertSame('09:00 - 11:00', $rendered['time']);
        // A magasság a lefedett sávok száma, a lépésközzel számolva. Az érték
        // FLOAT, mert ceil() lebegőpontosat ad vissza (Modal.php:321) - és így
        // kerül a nézet rowspan attribútumába is.
        $this->assertSame(2.0, $rendered['height']);
    }

    public function test_a_member_cannot_edit_another_members_event_from_the_modal(): void
    {
        $group = $this->createGroup(['need_approval' => 0]);
        $this->createEventDate($group, $this->date, ['date_min_time' => 60]);

        $owner = $this->newMember($group, 'modal-owner@example.test');
        $other = $this->newMember($group, 'modal-other@example.test');

        $this->actingAs($owner);
        $event = $this->createEventInRange($group, $owner, $this->date, '09:00', '10:00');

        $dayEvents = Livewire::actingAs($other)
            ->test(Modal::class)
            ->call('openModal', $this->date, $group->id)
            ->viewData('day_events');

        $this->assertSame(
            'disabled',
            $dayEvents[$this->slotKey('09:00')][$event->id]['editable'],
            'Idegen esemény nem szerkeszthető tagként.'
        );
    }

    public function test_a_roler_can_edit_any_event_from_the_modal(): void
    {
        $group = $this->createGroup(['need_approval' => 0]);
        $this->createEventDate($group, $this->date, ['date_min_time' => 60]);

        $owner = $this->newMember($group, 'modal-owner2@example.test');
        $roler = $this->newMember($group, 'modal-roler@example.test', 'roler');

        $this->actingAs($owner);
        $event = $this->createEventInRange($group, $owner, $this->date, '09:00', '10:00');

        $dayEvents = Livewire::actingAs($roler)
            ->test(Modal::class)
            ->call('openModal', $this->date, $group->id)
            ->viewData('day_events');

        $this->assertSame('', $dayEvents[$this->slotKey('09:00')][$event->id]['editable']);
    }
}
