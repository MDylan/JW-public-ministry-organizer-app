<?php

namespace Tests\Feature\Events;

use App\Http\Livewire\Events\EventEdit;
use App\Models\Event;
use App\Models\Group;
use App\Models\User;
use Livewire\Livewire;
use Tests\Feature\FeatureTestCase;

/**
 * TODO 07.1: az Events\EventEdit::saveEvent() kapacitás-kapuja.
 *
 * Ez az alkalmazás központi üzleti szabálya, és eddig NULLA lefedettsége volt:
 * a CalendarEventEditTest beállít date_max_publishers => 3-at, de egyetlen
 * tesztje sem tölt meg egy sávot, így a kapacitás-ág soha nem futott le.
 *
 * A kapacitás SÁVONKÉNT érvényes, nem eseményenként. A getInfo()
 * (EventEdit.php:228-326) felépíti a nap tábláját, végigjárja a meglévő
 * eseményeket, és minden érintett sávon számlálót növel; a saveEvent()
 * (:475-485) ugyanezzel a lépésközzel járja végig a kért tartományt.
 *
 * FONTOS - a jóváhagyásos túljelentkezés a saveEvent()-ben NEM úgy működik,
 * ahogy a kód olvasata sugallja. Részletek a
 * test_pending_applications_do_not_consume_publisher_capacity tesztnél.
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
     * Új tag, aki a komponensen keresztül próbál jelentkezni.
     */
    private function newMember(Group $group, string $email): User
    {
        $user = $this->createUser(['email' => $email]);
        $this->attachUserToGroup($user, $group, 'member');

        return $user;
    }

    /**
     * A tényleges jelentkezés a komponensen át - ugyanaz az útvonal, amit a
     * böngésző is bejár.
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
    // 1. Sávonkénti maximum jóváhagyás nélküli csoportban
    // =========================================================================

    public function test_bookings_are_accepted_up_to_the_per_slot_maximum(): void
    {
        $group = $this->createGroup(['need_approval' => 0]);
        $this->createEventDate($group, $this->date, ['date_max_publishers' => 3]);

        $actor = $this->newMember($group, 'cap-third@example.test');
        $this->actingAs($actor);

        // Két hely már foglalt, a harmadik még szabad.
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

        // A tábla nem változott.
        $this->assertSame(3, $this->eventCount($group));
    }

    public function test_capacity_is_counted_per_slot_not_per_day(): void
    {
        $group = $this->createGroup(['need_approval' => 0]);
        $this->createEventDate($group, $this->date, ['date_max_publishers' => 1]);

        $actor = $this->newMember($group, 'cap-next-slot@example.test');
        $this->actingAs($actor);

        // A 09:00-s sáv betelt, de a 10:00-s érintetlen.
        $this->fillSlotRange($group, $this->date, '09:00', '10:00', 1, true, 'cap-slot');

        $this->book($group, $actor, '10:00', '11:00')->assertHasNoErrors();

        $this->assertSame(2, $this->eventCount($group));
    }

    // =========================================================================
    // 2. Jóváhagyásos csoport: túljelentkezés
    // =========================================================================

    public function test_approval_groups_accept_more_applications_than_the_maximum(): void
    {
        // A felhasználó második szabálya: ha jóváhagyás kell, több jelentkező
        // is lehet, mint a maximum - a fölösleget majd az elbírálás szűri.
        config(['events.max_columns' => 4]);

        $group = $this->createGroup(['need_approval' => 1]);
        $this->createEventDate($group, $this->date, ['date_max_publishers' => 3]);

        $actor = $this->newMember($group, 'cap-over-4th@example.test');
        $this->actingAs($actor);

        // Három FÜGGŐ jelentkezés - pont a maximum, elfogadva még egy sem.
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
        // A megemelt plafon max_publishers + events.max_columns = 3 + 4 = 7.
        config(['events.max_columns' => 4]);

        $group = $this->createGroup(['need_approval' => 1]);
        $this->createEventDate($group, $this->date, ['date_max_publishers' => 3]);

        $actor = $this->newMember($group, 'cap-ceiling@example.test');
        $this->actingAs($actor);

        $this->fillSlotRange($group, $this->date, '09:00', '10:00', 7, false, 'cap-ceil');

        $component = $this->book($group, $actor, '09:00', '10:00')
            ->assertHasErrors(['start', 'end']);

        // KARAKTERIZÁLÓ: a plafont NEM a publishers-ellenőrzés tartja, hanem az
        // érvényes időpontok szűrése - ezért a hibaüzenet invalid_value, nem
        // reach_max_publisher. Lásd a lenti publishers-tesztet.
        $this->assertContains(__('event.invalid_value'), $component->lastErrorBag->get('start'));
        $this->assertNotContains(__('event.reach_max_publisher'), $component->lastErrorBag->get('start'));

        $this->assertSame(7, $this->eventCount($group));
    }

    public function test_the_raised_ceiling_collapses_once_the_accepted_count_reaches_the_maximum(): void
    {
        // Ugyanaz a csoport és sáv, mint a túljelentkezéses tesztben - az
        // egyetlen különbség, hogy a három esemény ELFOGADOTT. Ettől a limit
        // 7-ről visszaesik 3-ra, és a negyedik jelentkezés elbukik.
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
        // Kontraszt: azonos maximum, azonos számú függő esemény - de
        // need_approval nélkül nincs megemelt plafon. (Jóváhagyás nélküli
        // csoportban a status=0 állapot csak adatimportból állhat elő, a
        // komponens mindent azonnal elfogad - ezért gyártjuk factoryval.)
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
        // A 4-es szám nem lehet bedrótozva a szabályba: max_columns = 1
        // mellett a plafon 1 + 1 = 2.
        config(['events.max_columns' => 1]);

        $group = $this->createGroup(['need_approval' => 1]);
        $this->createEventDate($group, $this->date, ['date_max_publishers' => 1]);

        $actor = $this->newMember($group, 'cap-cols@example.test');
        $this->actingAs($actor);

        // Egy függő jelentkezés: a plafon alatt vagyunk, a második átmegy.
        $this->fillSlotRange($group, $this->date, '09:00', '10:00', 1, false, 'cap-col1');
        $this->book($group, $actor, '09:00', '10:00')->assertHasNoErrors();

        // Most már ketten vannak - a harmadik elbukik.
        $third = $this->newMember($group, 'cap-cols-third@example.test');
        $this->book($group, $third, '09:00', '10:00')->assertHasErrors(['start', 'end']);

        $this->assertSame(2, $this->eventCount($group));
    }

    // =========================================================================
    // 3. A publishers/accepted számlálás - a felfedezett eltérés rögzítése
    // =========================================================================

    public function test_pending_applications_do_not_consume_publisher_capacity(): void
    {
        // KARAKTERIZÁLÓ TESZT egy látens hibáról.
        //
        // Az EventEdit::getInfo() (:288-291) a publishers ÉS az accepted
        // számlálót is CSAK status == 1 esetén növeli, mindig együtt. A két
        // érték tehát ebben a komponensben mindig azonos.
        //
        // Ettől a saveEvent() (:477-482) jóváhagyásos ága algebrailag
        // összeomlik: ha accepted >= max, a limit max, és publishers(= accepted)
        // >= max igaz; ha accepted < max, a limit max + max_columns, de
        // publishers(= accepted) < max < max + max_columns, tehát hamis. A
        // "+ events.max_columns" ág SOHA nem érvényesül a mentés-ellenőrzésben.
        //
        // A túljelentkezési plafont valójában a $slots tömb (:286) tartja, ami
        // MINDEN eseményt számol - ezért kap a felhasználó invalid_value hibát
        // reach_max_publisher helyett, amikor a plafonba ütközik.
        //
        // A javítás a TODO 77-be tartozik (Events\Modal deduplikáció), ahol
        // eldől, melyik számolás a helyes - a Modal ugyanis MINDEN eseményre
        // növeli a publishers-t (Modal.php:352).
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
    // 4. Saját esemény és túlcsordulás
    // =========================================================================

    public function test_a_member_cannot_book_the_same_slot_twice_in_the_same_group(): void
    {
        // A getInfo() (:292-294) a jelentkező SAJÁT eseményeinek sávjait
        // disabled_slots-ba teszi, kapacitástól függetlenül. Ez a csoporton
        // belüli dupla foglalás védelme - a cross-group busy vizsgálat
        // (EventOverlapTest) ugyanezt más csoportokra végzi.
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
        // MEGFORDÍTVA a v1-patch B11 javításával.
        //
        // A getInfo() minden sávhoz (max_publishers + events.max_columns) cellát
        // foglal, és eseményenként egyet elhasznál. Ha egy sávon több esemény
        // van, mint ahány cella, a cellalista kiürül, és a korábbi feltétel
        // nélküli min(array_keys([])) PHP 8 alatt ValueError-t dobott - amitől a
        // nap MEGNYITHATATLANNÁ vált, nem csak hibásan rajzolttá.
        //
        // Ez elérhető állapot, nem elméleti: az események a régi, magasabb
        // maximum mellett jönnek létre, majd egy admin lejjebb viszi a
        // date_max_publishers-t, vagy egy jövőbeli csoportmódosítás írja felül.
        // A meglévő eseményeket ilyenkor senki nem törli.
        //
        // A túlcsordult esemény most külön oszlopot kap; a nap megnyílik.
        config(['events.max_columns' => 4]);

        $group = $this->createGroup(['need_approval' => 1]);
        $date = $this->createEventDate($group, $this->date, ['date_max_publishers' => 6]);

        $actor = $this->newMember($group, 'cap-overflow@example.test');
        $this->actingAs($actor);

        $this->fillSlotRange($group, $this->date, '09:00', '10:00', 6, false, 'cap-ovf');

        // Az admin visszaveszi a maximumot: 1 + 4 = 5 cella marad 6 eseményre.
        $date->update(['date_max_publishers' => 1]);

        Livewire::actingAs($actor)
            ->test(EventEdit::class, ['groupId' => $group->id, 'date' => $this->date])
            ->assertOk();
    }

    public function test_the_overflowing_slot_is_still_described_correctly(): void
    {
        // A B11 érdemi fele: nem elég, hogy nem hasal el - a sávnak helyesen
        // kell leírva lennie utána is. Öt cellára hat esemény jut, tehát az
        // összes előre foglalt cella elfogy, és a sáv megtelik.
        //
        // (A `publishers` számláló szándékosan nem szerepel itt: azt a
        // getInfo() csak ELFOGADOTT eseményekre növeli, a hat jelentkezés
        // viszont függő.)
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
