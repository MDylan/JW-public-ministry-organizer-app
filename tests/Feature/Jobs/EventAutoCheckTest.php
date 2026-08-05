<?php

namespace Tests\Feature\Jobs;

use App\Jobs\EventAutoCheck;
use App\Models\Event;
use App\Models\Group;
use App\Models\GroupDate;
use App\Models\User;
use Tests\Feature\FeatureTestCase;

/**
 * TODO 05: characterization tests for EventAutoCheck.
 *
 * This job is DEAD CODE and unfinished. Both dispatch sites in EventObserver
 * (:58 and :144) are commented out, and the handle() body carries an explicit
 * "IMPORTANT!!! THIS IS NOT FINISHED YET!!!" marker, an empty foreach loop,
 * and an invalid SQL operator ('=<' instead of '<=') whose result is
 * overwritten before use.
 *
 * These tests do not assert intended behaviour - there is no agreed intended
 * behaviour. They pin down the early-return guards so that five framework
 * upgrades cannot silently change what this code does if it is ever revived.
 * See roadmap TODO 05 notes.
 *
 * Note: handle() is documented as `@return void` but actually returns false
 * from its guard clauses. The assertions below deliberately check the real
 * runtime value, not the docblock's claim.
 */
class EventAutoCheckTest extends FeatureTestCase
{
    private User $member;

    protected function setUp(): void
    {
        parent::setUp();

        $this->member = $this->createUser(['email' => 'auto-member@example.test']);
        $this->actingAs($this->member);
    }

    private function makeEvent(Group $group): Event
    {
        $this->attachUserToGroup($this->member, $group);
        $date = now()->addDay()->toDateString();

        GroupDate::factory()->create([
            'group_id' => $group->id,
            'date' => $date,
            'date_start' => $date.' 08:00:00',
            'date_end' => $date.' 12:00:00',
            'date_min_time' => 60,
        ]);

        return Event::factory()->create([
            'group_id' => $group->id,
            'user_id' => $this->member->id,
            'day' => $date,
            'start' => $date.' 09:00:00',
            'end' => $date.' 10:00:00',
            'status' => 0,
        ]);
    }

    public function test_handle_returns_false_when_the_group_needs_no_approval(): void
    {
        // Első őrfeltétel: need_approval = 0 esetén azonnal kilép.
        $group = $this->createGroup(); // need_approval alapból 0
        $event = $this->makeEvent($group);

        $result = (new EventAutoCheck($event, strtotime($event->start), strtotime($event->end)))->handle();

        $this->assertFalse($result);
    }

    public function test_handle_returns_false_when_neither_auto_approval_nor_auto_back_is_set(): void
    {
        // Második őrfeltétel: jóváhagyás kell, de sem auto_approval,
        // sem auto_back nincs bekapcsolva.
        $group = Group::factory()->requiresApproval()->create();
        $event = $this->makeEvent($group);

        $result = (new EventAutoCheck($event, strtotime($event->start), strtotime($event->end)))->handle();

        $this->assertFalse($result);
    }

    public function test_handle_returns_false_for_a_disabled_day(): void
    {
        $group = Group::factory()->withAutoApproval()->create();
        $event = $this->makeEvent($group);
        GroupDate::where('group_id', $group->id)->update(['date_status' => 0]);

        $result = (new EventAutoCheck($event, strtotime($event->start), strtotime($event->end)))->handle();

        $this->assertFalse($result);
    }

    public function test_handle_fatals_once_it_reaches_the_slot_building_loop(): void
    {
        // A job nemcsak befejezetlen, hanem futásképtelen is: az őrfeltételeken
        // túljutva az EventAutoCheck.php:115 sor $event->id-t olvas, miközben
        // az $events elemei tömbök (a ciklus fentebb $event['end']-et használ).
        // Vegyes tömb/objektum hozzáférés ugyanabban a ciklusban.
        //
        // Ez megerősíti, hogy a job soha nem futott le éles környezetben -
        // ha lefutott volna, azonnal elszállt volna.
        $group = Group::factory()->withAutoApproval()->create(['min_publishers' => 3]);
        $event = $this->makeEvent($group);

        $this->expectException(\Throwable::class);

        (new EventAutoCheck($event, strtotime($event->start), strtotime($event->end)))->handle();
    }

    public function test_every_dispatch_site_is_commented_out(): void
    {
        // Ez a teszt azt rögzíti, hogy a job ma nem fut. Ha valaki
        // visszakapcsolja a dispatchet, ez elbukik - és akkor a fenti
        // jellemzés-teszteket valódi elvárásokra kell cserélni, a
        // befejezetlen handle() törzs befejezésével együtt.
        $observer = file_get_contents(app_path('Observers/EventObserver.php'));

        preg_match_all('/^\s*(\/\/\s*)?EventAutoCheck::dispatch/m', $observer, $matches, PREG_SET_ORDER);

        $this->assertNotEmpty($matches, 'EventAutoCheck dispatch sites disappeared from EventObserver.');

        foreach ($matches as $match) {
            $this->assertNotEmpty(
                $match[1] ?? '',
                'An EventAutoCheck dispatch has been re-enabled - the job is unfinished, see its handle() body.'
            );
        }
    }
}
