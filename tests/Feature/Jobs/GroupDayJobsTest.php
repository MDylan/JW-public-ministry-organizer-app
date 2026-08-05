<?php

namespace Tests\Feature\Jobs;

use App\Jobs\GroupDayDeletedProcess;
use App\Jobs\GroupDayUpdatedProcess;
use App\Models\DayStat;
use App\Models\Event;
use App\Models\Group;
use App\Models\GroupDate;
use App\Models\User;
use App\Notifications\EventDeletedNotification;
use Illuminate\Support\Facades\Notification;
use Tests\Feature\FeatureTestCase;

/**
 * TODO 05: real handle() execution for the two GroupDay jobs.
 *
 * Both are dispatched exclusively from GroupDayObserver, which is NOT
 * registered in EventServiceProvider - so neither runs in production today.
 * They are covered anyway, because they hold real logic that would silently
 * rot across five framework upgrades, and because the observer may be
 * activated later (roadmap TODO 10).
 *
 * Note the constructor's PHP-to-MySQL weekday remapping: the caller passes a
 * PHP date('w') value (0 = Sunday) and the job converts it to the MySQL
 * WEEKDAY() convention (0 = Monday) before using it in raw SQL.
 */
class GroupDayJobsTest extends FeatureTestCase
{
    private Group $group;
    private User $member;
    private User $causer;

    protected function setUp(): void
    {
        parent::setUp();

        $this->group = $this->createGroup();
        $this->member = $this->createUser(['email' => 'gd-member@example.test']);
        $this->causer = $this->createUser(['email' => 'gd-causer@example.test', 'name' => 'Szervező']);
        $this->attachUserToGroup($this->member, $this->group);
        $this->actingAs($this->causer);
    }

    /** Következő adott hétköznap (PHP date('w'): 0 = vasárnap). */
    private function nextWeekday(int $phpDayOfWeek): string
    {
        $date = now()->addDay();
        while ((int) $date->format('w') !== $phpDayOfWeek) {
            $date = $date->addDay();
        }

        return $date->toDateString();
    }

    private function createEventOn(string $day, int $status = 1): Event
    {
        return Event::factory()->create([
            'group_id' => $this->group->id,
            'user_id' => $this->member->id,
            'day' => $day,
            'start' => $day.' 09:00:00',
            'end' => $day.' 10:00:00',
            'status' => $status,
            'accepted_at' => $status === 1 ? now() : null,
            'accepted_by' => $status === 1 ? $this->causer->id : null,
        ]);
    }

    // --- GroupDayDeletedProcess ---

    public function test_deleted_process_removes_events_falling_on_the_removed_weekday(): void
    {
        Notification::fake();

        $phpDay = 3; // szerda
        $day = $this->nextWeekday($phpDay);
        $event = $this->createEventOn($day);

        (new GroupDayDeletedProcess(now()->toDateString(), $this->group->id, $phpDay, '08:00', '12:00', $this->causer->id))
            ->handle();

        $this->assertNull(Event::find($event->id));
    }

    public function test_deleted_process_leaves_events_on_other_weekdays_alone(): void
    {
        Notification::fake();

        $keptDay = $this->nextWeekday(2); // kedd
        $kept = $this->createEventOn($keptDay);

        (new GroupDayDeletedProcess(now()->toDateString(), $this->group->id, 3, '08:00', '12:00', $this->causer->id))
            ->handle();

        $this->assertNotNull(Event::find($kept->id));
    }

    public function test_deleted_process_notifies_the_affected_user(): void
    {
        Notification::fake();

        $day = $this->nextWeekday(3);
        $this->createEventOn($day);

        (new GroupDayDeletedProcess(now()->toDateString(), $this->group->id, 3, '08:00', '12:00', $this->causer->id))
            ->handle();

        Notification::assertSentTo($this->member, EventDeletedNotification::class);
    }

    public function test_deleted_process_skips_special_dates(): void
    {
        Notification::fake();

        $day = $this->nextWeekday(3);
        // date_status = 2 jelöli a különleges napot, ezt a job nem bántja.
        GroupDate::factory()->create([
            'group_id' => $this->group->id,
            'date' => $day,
            'date_status' => 2,
        ]);
        $event = $this->createEventOn($day);

        (new GroupDayDeletedProcess(now()->toDateString(), $this->group->id, 3, '08:00', '12:00', $this->causer->id))
            ->handle();

        $this->assertNotNull(Event::find($event->id));
        // Célzott assert: az esemény létrehozása maga is küld értesítést az
        // EventObserver-en keresztül, ezért csak a job értesítését vizsgáljuk.
        Notification::assertNotSentTo($this->member, EventDeletedNotification::class);
    }

    public function test_deleted_process_purges_group_dates_and_stats_for_the_weekday(): void
    {
        Notification::fake();

        $day = $this->nextWeekday(3);
        GroupDate::factory()->create([
            'group_id' => $this->group->id,
            'date' => $day,
            'date_status' => 1,
        ]);
        DayStat::factory()->create([
            'group_id' => $this->group->id,
            'day' => $day,
            'time_slot' => $day.' 09:00:00',
        ]);

        (new GroupDayDeletedProcess(now()->toDateString(), $this->group->id, 3, '08:00', '12:00', $this->causer->id))
            ->handle();

        $this->assertDatabaseMissing('group_dates', ['group_id' => $this->group->id, 'date' => $day]);
        $this->assertSame(0, DayStat::where('day', $day)->count());
    }

    public function test_deleted_process_ignores_past_days(): void
    {
        Notification::fake();

        // A lekérdezés e.day >= $date feltétellel szűr; a referenciadátum a
        // jövőben van, ezért a mai eseményt nem érinti.
        $past = $this->createEventOn(now()->toDateString());

        (new GroupDayDeletedProcess(
            now()->addMonth()->toDateString(),
            $this->group->id,
            (int) now()->format('w'),
            '08:00',
            '12:00',
            $this->causer->id
        ))->handle();

        $this->assertNotNull(Event::find($past->id));
    }

    public function test_deleted_process_runs_cleanly_with_nothing_to_delete(): void
    {
        Notification::fake();

        (new GroupDayDeletedProcess(now()->toDateString(), $this->group->id, 3, '08:00', '12:00', $this->causer->id))
            ->handle();

        Notification::assertNothingSent();
        $this->assertSame(0, Event::where('group_id', $this->group->id)->count());
    }

    // --- GroupDayUpdatedProcess ---

    public function test_updated_process_runs_cleanly_when_nothing_is_affected(): void
    {
        Notification::fake();

        (new GroupDayUpdatedProcess(now()->toDateString(), $this->group->id, 3, '08:00', '12:00', $this->causer->id))
            ->handle();

        Notification::assertNothingSent();
    }

    public function test_updated_process_leaves_events_inside_the_new_window_untouched(): void
    {
        Notification::fake();

        $day = $this->nextWeekday(3);
        GroupDate::factory()->create([
            'group_id' => $this->group->id,
            'date' => $day,
            'date_start' => $day.' 08:00:00',
            'date_end' => $day.' 12:00:00',
        ]);
        // 09:00-10:00 belefér a 08:00-12:00 ablakba.
        $event = $this->createEventOn($day);

        (new GroupDayUpdatedProcess(now()->toDateString(), $this->group->id, 3, '08:00', '12:00', $this->causer->id))
            ->handle();

        $this->assertNotNull(Event::find($event->id));
    }
}
