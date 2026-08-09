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
 * TODO 10: a rendszer-okozó (causer_id = 0) a FOGADÓ oldalon.
 *
 * A TODO 10 egységesítette, hogy bejelentkezés hiányában 0 kerül a causer
 * helyére - de a 0-t a fogadó oldalnak is kezelnie kell. Két hely olvasta a
 * causer NEVÉT közvetlenül modellről, és mindkettő elszállt volna:
 *
 *   - GroupDayDeletedProcess::handle(): User::find(0) null, majd ->name
 *   - CalculateDatesEvents::generate(): if($user_id) hamis 0-ra, tehát az
 *     auth()->user() tartalékra esett - ami sorkezelőben MINDIG null
 *
 * A név közvetlenül értesítés szövegébe kerül, és a Laravel a PHP
 * figyelmeztetéseket ErrorException-né alakítja, tehát ez valódi hiba lett
 * volna, nem néma null.
 *
 * A TODO 10.2 törölte a GroupDayDeletedProcess-t, tehát az első útvonal
 * megszűnt. A causerNameFor() három ága - a 0, a valódi azonosító és az
 * IDŐKÖZBEN TÖRÖLT felhasználó - így itt már mind a CalculateDatesEvents-en
 * keresztül van lefedve; ez az az útvonal, ami élesben tényleg fut.
 *
 * Miért nem derült ki korábban: a meglévő jobtesztek mindig valódi, létező
 * causerrel futottak, a tesztkörnyezet pedig QUEUE_CONNECTION=sync - vagyis
 * a jobok a hitelesített kérésen BELÜL futnak le, ahol az auth() még ad
 * felhasználót. Élesben QUEUE_CONNECTION=database, tehát külön
 * sorkezelő-folyamatban futnának, auth nélkül.
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

    /** Letiltott nap: a generate() minden rajta lévő eseményt töröl. */
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
    // CalculateDatesEvents - az egyetlen megmaradt fogadó oldal
    // =========================================================================

    public function test_calculate_dates_events_runs_with_a_system_causer(): void
    {
        // Ez az útvonal a GroupDayUpdatedProcess-en át érhető el, ami a
        // causer azonosítóját továbbadja. A generate() korábban
        // if($user_id)-t vizsgált - ami 0-ra HAMIS -, és az auth()->user()
        // tartalékra esett vissza. Sorkezelőben az mindig null.
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
        // A Livewire komponensek user_id nélkül (false alapértékkel) hívják,
        // és ott a bejelentkezett felhasználó a helyes okozó - ezt a
        // tartalékot meg kellett tartani.
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
        // Nem csak a 0 problémás: egy időközben TÖRÖLT felhasználó
        // azonosítójára is null-t ad a User::find(). Az azonosító a hívás
        // pillanatában rögzül, a feldolgozás pedig később fut - addig a
        // felhasználó eltűnhet.
        //
        // Ez az ág korábban a GroupDayDeletedProcess-en volt lefedve; a job
        // törlésével (TODO 10.2) ide került át, arra az útvonalra, ami
        // élesben tényleg fut.
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
