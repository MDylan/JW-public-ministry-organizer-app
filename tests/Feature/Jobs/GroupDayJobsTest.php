<?php

namespace Tests\Feature\Jobs;

use App\Jobs\GroupDayUpdatedProcess;
use App\Models\Event;
use App\Models\Group;
use App\Models\GroupDate;
use App\Models\User;
use Illuminate\Support\Facades\Notification;
use Tests\Feature\FeatureTestCase;

/**
 * TODO 05: real handle() execution for GroupDayUpdatedProcess.
 *
 * It is dispatched exclusively from GroupDayObserver, which is NOT registered
 * in EventServiceProvider - so it does not run in production today. It is
 * covered anyway, because its handle() delegates to CalculateDatesEvents,
 * which IS on the live path.
 *
 * TODO 10.2 removed its sibling, GroupDayDeletedProcess, and with it the
 * seven tests that lived here: the job was unreachable and its work is done
 * by the GroupDateHelper -> CalculateDateProcess -> CalculateDatesEvents
 * chain (see GroupDayTemplateCleanupTest for the evidence).
 *
 * Note the PHP-to-MySQL weekday remapping in the constructor: the caller
 * passes a PHP date('w') value (0 = Sunday) and the job converts it to the
 * MySQL WEEKDAY() convention (0 = Monday) before using it in raw SQL.
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

    /** Next given weekday (PHP date('w'): 0 = Sunday). */
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
        // 09:00-10:00 fits within the 08:00-12:00 window.
        $event = $this->createEventOn($day);

        (new GroupDayUpdatedProcess(now()->toDateString(), $this->group->id, 3, '08:00', '12:00', $this->causer->id))
            ->handle();

        $this->assertNotNull(Event::find($event->id));
    }
}
