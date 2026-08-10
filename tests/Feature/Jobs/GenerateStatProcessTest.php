<?php

namespace Tests\Feature\Jobs;

use App\Jobs\GenerateStatProcess;
use App\Models\DayStat;
use App\Models\Event;
use App\Models\Group;
use App\Models\GroupDate;
use App\Models\User;
use Tests\Feature\FeatureTestCase;

/**
 * TODO 05: real handle() execution.
 *
 * This job rebuilds the per-slot DayStat rows for one group and one day. It is
 * the data source behind the calendar's colour coding, and it is dispatched
 * from App\Classes\GenerateStat whenever events change.
 *
 * Two behaviours matter for the upgrade: the slot-stepping arithmetic (shared
 * with the capacity rules covered by TODO 07.1) and the fact that it writes
 * through DayStat::insert(), which bypasses casts, observers and timestamps.
 */
class GenerateStatProcessTest extends FeatureTestCase
{
    private Group $group;
    private User $member;
    private string $date;

    protected function setUp(): void
    {
        parent::setUp();

        $this->date = now()->addDay()->toDateString();
        $this->group = $this->createGroup();
        $this->member = $this->createUser(['email' => 'stat-member@example.test']);
        $this->attachUserToGroup($this->member, $this->group);

        // EventObserver unconditionally reads auth()->user()->id, so writing
        // an event needs a logged-in context. See: roadmap TODO 10.
        $this->actingAs($this->member);

        // 08:00-12:00, hourly slots => 4 slots.
        GroupDate::factory()->create([
            'group_id' => $this->group->id,
            'date' => $this->date,
            'date_start' => $this->date.' 08:00:00',
            'date_end' => $this->date.' 12:00:00',
            'date_min_time' => 60,
            'date_min_publishers' => 1,
            'date_max_publishers' => 3,
        ]);
    }

    private function createEvent(string $start, string $end, int $status = 1): Event
    {
        return Event::factory()->create([
            'group_id' => $this->group->id,
            'user_id' => $this->member->id,
            'day' => $this->date,
            'start' => $this->date.' '.$start,
            'end' => $this->date.' '.$end,
            'status' => $status,
            'accepted_at' => $status === 1 ? now() : null,
            'accepted_by' => $status === 1 ? $this->member->id : null,
        ]);
    }

    private function statsBySlot(): array
    {
        return DayStat::where('group_id', $this->group->id)
            ->where('day', $this->date)
            ->get()
            ->mapWithKeys(fn ($s) => [substr((string) $s->getRawOriginal('time_slot'), 11, 5) => (int) $s->events])
            ->all();
    }

    public function test_handle_creates_one_row_per_slot_with_zero_events(): void
    {
        (new GenerateStatProcess($this->group->id, $this->date, false))->handle();

        $this->assertSame(
            ['08:00' => 0, '09:00' => 0, '10:00' => 0, '11:00' => 0],
            $this->statsBySlot()
        );
    }

    public function test_handle_counts_an_accepted_event_on_every_slot_it_spans(): void
    {
        // 09:00-11:00 spans two slots: 09:00 and 10:00.
        $this->createEvent('09:00:00', '11:00:00');

        (new GenerateStatProcess($this->group->id, $this->date, false))->handle();

        $this->assertSame(
            ['08:00' => 0, '09:00' => 1, '10:00' => 1, '11:00' => 0],
            $this->statsBySlot()
        );
    }

    public function test_handle_ignores_pending_events(): void
    {
        // Only accepted events count (day_events_accepted).
        $this->createEvent('09:00:00', '10:00:00', 0);

        (new GenerateStatProcess($this->group->id, $this->date, false))->handle();

        $this->assertSame(
            ['08:00' => 0, '09:00' => 0, '10:00' => 0, '11:00' => 0],
            $this->statsBySlot()
        );
    }

    public function test_handle_sums_overlapping_events_on_the_shared_slot(): void
    {
        // This is the statistical counterpart of the TODO 07.1 overlap rule:
        // 08:00-10:00 and 09:00-12:00 overlap on the 09:00 slot.
        $other = $this->createUser(['email' => 'stat-member2@example.test']);
        $this->attachUserToGroup($other, $this->group);

        $this->createEvent('08:00:00', '10:00:00');
        Event::factory()->create([
            'group_id' => $this->group->id,
            'user_id' => $other->id,
            'day' => $this->date,
            'start' => $this->date.' 09:00:00',
            'end' => $this->date.' 12:00:00',
            'status' => 1,
            'accepted_at' => now(),
            'accepted_by' => $other->id,
        ]);

        (new GenerateStatProcess($this->group->id, $this->date, false))->handle();

        $this->assertSame(
            ['08:00' => 1, '09:00' => 2, '10:00' => 1, '11:00' => 1],
            $this->statsBySlot()
        );
    }

    public function test_handle_replaces_previous_stats_rather_than_appending(): void
    {
        $this->createEvent('09:00:00', '10:00:00');
        (new GenerateStatProcess($this->group->id, $this->date, false))->handle();
        $this->assertSame(4, DayStat::where('group_id', $this->group->id)->count());

        (new GenerateStatProcess($this->group->id, $this->date, false))->handle();

        $this->assertSame(4, DayStat::where('group_id', $this->group->id)->count());
    }

    public function test_handle_clears_the_run_job_flag_on_the_group_date(): void
    {
        GroupDate::where('group_id', $this->group->id)->update(['run_job' => 1]);

        (new GenerateStatProcess($this->group->id, $this->date, false))->handle();

        $this->assertSame(0, (int) GroupDate::where('group_id', $this->group->id)->value('run_job'));
    }

    public function test_handle_writes_no_stats_for_a_disabled_day(): void
    {
        GroupDate::where('group_id', $this->group->id)->update(['date_status' => 0]);
        $this->createEvent('09:00:00', '10:00:00');

        (new GenerateStatProcess($this->group->id, $this->date, false))->handle();

        $this->assertSame(0, DayStat::where('group_id', $this->group->id)->count());
    }

    /**
     * v1-patch E6. GroupDateHelper::generateDate()'s past-date protection is
     * broken: on the $date_info branch it evaluates the toArray(), discards
     * it, then falls through to the updateOrCreate. A group-template edit
     * therefore also dispatches this job for days below the retention floor
     * - and since the source events there are already deleted, a brand-new,
     * all-zero day_stats row would be created claiming that nobody served
     * there.
     */
    public function test_handle_writes_nothing_below_the_group_data_retention_floor(): void
    {
        $oldDate = now()->subMonths(14)->toDateString();
        config(['settings_group_data_retention' => '12']);

        GroupDate::factory()->create([
            'group_id' => $this->group->id,
            'date' => $oldDate,
            'date_start' => $oldDate.' 08:00:00',
            'date_end' => $oldDate.' 12:00:00',
            'date_min_time' => 60,
            'date_min_publishers' => 1,
            'date_max_publishers' => 3,
        ]);

        (new GenerateStatProcess($this->group->id, $oldDate, false))->handle();

        $this->assertSame(0, DayStat::where('group_id', $this->group->id)->where('day', $oldDate)->count());
    }

    public function test_handle_still_writes_below_the_floor_while_retention_is_off(): void
    {
        $oldDate = now()->subMonths(14)->toDateString();
        config(['settings_group_data_retention' => '0']);

        GroupDate::factory()->create([
            'group_id' => $this->group->id,
            'date' => $oldDate,
            'date_start' => $oldDate.' 08:00:00',
            'date_end' => $oldDate.' 12:00:00',
            'date_min_time' => 60,
            'date_min_publishers' => 1,
            'date_max_publishers' => 3,
        ]);

        (new GenerateStatProcess($this->group->id, $oldDate, false))->handle();

        $this->assertSame(4, DayStat::where('group_id', $this->group->id)->where('day', $oldDate)->count());
    }

    public function test_handle_with_force_reset_deletes_the_group_date_first(): void
    {
        (new GenerateStatProcess($this->group->id, $this->date, true))->handle();

        // The forceReset branch drops the GroupDate row before recalculating.
        $this->assertSame(0, GroupDate::where('group_id', $this->group->id)->where('date', $this->date)->count());
    }

    public function test_handle_with_force_reset_survives_a_missing_group_date(): void
    {
        // REVERSED by the v1-patch A6 fix.
        //
        // Previously the forceReset branch unconditionally called
        // ->first()->delete(), so for a missing GroupDate it called a method
        // on null. The queue runs the job even if the row has disappeared
        // since the dispatch (concurrent deletion, group deletion, manual
        // data fix) - in that case the job died with a fatal, even though
        // the whole point of the reset is that the row should NOT be there.
        //
        // Now the missing row is the desired end state, not an error: the
        // job runs to completion.
        GroupDate::where('group_id', $this->group->id)->delete();

        (new GenerateStatProcess($this->group->id, $this->date, true))->handle();

        $this->assertSame(
            0,
            GroupDate::where('group_id', $this->group->id)->where('date', $this->date)->count(),
            'A forceReset után a nap GroupDate sora nem létezhet.'
        );
    }
}
