<?php

namespace Tests\Feature\Commands;

use App\Models\DayStat;
use App\Models\Event;
use App\Models\EventServiceReport;
use App\Models\Group;
use App\Models\GroupDate;
use App\Models\GroupLiterature;
use App\Models\LogHistory;
use App\Models\User;
use App\Support\Retention\RetentionWindow;
use Illuminate\Support\Facades\Notification;
use Tests\Feature\FeatureTestCase;

/**
 * v1-patch E: the two retention purge commands.
 *
 * These are the only commands in the application that delete data
 * irreversibly and in bulk, so the cases below are weighted towards the ways
 * they could delete too much rather than too little: the disabled state, a
 * tampered setting value, and the exact boundary day.
 *
 * Note: .env.testing sets GDPR_ENABLED=false, so every event case that
 * expects work to happen must enable it explicitly.
 */
class RetentionCommandsTest extends FeatureTestCase
{
    private Group $group;
    private User $member;

    protected function setUp(): void
    {
        parent::setUp();

        $this->group = $this->createGroup();
        $this->member = $this->createUser(['email' => 'retention-member@example.test']);
        $this->attachUserToGroup($this->member, $this->group);
    }

    private function enableGdpr(): void
    {
        config(['gdpr.enabled' => true]);
    }

    private function eventOn(string $day): Event
    {
        return Event::factory()->create([
            'group_id' => $this->group->id,
            'user_id' => $this->member->id,
            'day' => $day,
            'start' => $day.' 09:00:00',
            'end' => $day.' 10:00:00',
            'status' => 1,
            'accepted_at' => now()->subYear(),
            'accepted_by' => $this->member->id,
        ]);
    }

    private function monthsAgo(int $months): string
    {
        return now()->subMonthsNoOverflow($months)->toDateString();
    }

    // --- gdpr:purge-old-events ---

    public function test_purge_old_events_is_a_no_op_when_gdpr_is_disabled(): void
    {
        config(['gdpr.enabled' => false]);
        $event = $this->eventOn($this->monthsAgo(36));

        $this->artisan('gdpr:purge-old-events')->assertExitCode(0);

        $this->assertNotNull(Event::find($event->id));
    }

    public function test_purge_old_events_deletes_events_past_the_retention_window(): void
    {
        $this->enableGdpr();
        $old = $this->eventOn($this->monthsAgo(14));

        $this->artisan('gdpr:purge-old-events')->assertExitCode(0);

        $this->assertSame(0, Event::withTrashed()->where('id', $old->id)->count());
    }

    public function test_purge_old_events_keeps_events_inside_the_retention_window(): void
    {
        $this->enableGdpr();
        $recent = $this->eventOn($this->monthsAgo(12));

        $this->artisan('gdpr:purge-old-events');

        $this->assertNotNull(Event::find($recent->id));
    }

    public function test_purge_old_events_also_removes_already_trashed_events(): void
    {
        $this->enableGdpr();
        $old = $this->eventOn($this->monthsAgo(14));
        $old->delete();

        $this->artisan('gdpr:purge-old-events');

        $this->assertSame(0, Event::withTrashed()->where('id', $old->id)->count());
    }

    /**
     * The day column is of type DATE, and the floor is the start of a day.
     * If the floor carried a time component, these two cases would flip
     * depending on what hour the scheduler ran at.
     */
    public function test_purge_old_events_treats_the_floor_day_itself_as_inside_the_window(): void
    {
        $this->enableGdpr();
        $floor = RetentionWindow::eventsFloor();

        $onFloor = $this->eventOn($floor->toDateString());
        $belowFloor = $this->eventOn($floor->copy()->subDay()->toDateString());

        $this->artisan('gdpr:purge-old-events');

        $this->assertNotNull(Event::find($onFloor->id));
        $this->assertSame(0, Event::withTrashed()->where('id', $belowFloor->id)->count());
    }

    /**
     * This is the most important test in this file. EventObserver::deleted()
     * sends a mail to the affected publisher and to every admin of the
     * group, and writes a log_histories row. Deleted model-instance by
     * model-instance, the first live run would trigger this for 121,000
     * events. The builder-level forceDelete() is therefore mandatory,
     * because it does not fire a model event - without this test, that
     * design decision would go unguarded.
     */
    public function test_purge_old_events_fires_no_model_events(): void
    {
        $this->enableGdpr();
        $this->eventOn($this->monthsAgo(14));

        // The fake comes ONLY after the fixture is created: EventObserver::created()
        // also sends a notification, and that is not what we care about here - what
        // matters is that the DELETION sends nothing.
        Notification::fake();
        $historyCount = LogHistory::count();

        $this->artisan('gdpr:purge-old-events');

        Notification::assertNothingSent();
        $this->assertSame($historyCount, LogHistory::count());
    }

    public function test_purge_old_events_cascades_to_the_service_reports(): void
    {
        $this->enableGdpr();
        $old = $this->eventOn($this->monthsAgo(14));

        $report = EventServiceReport::factory()->create([
            'event_id' => $old->id,
            'group_literature_id' => GroupLiterature::factory()->create(['group_id' => $this->group->id])->id,
        ]);

        $this->artisan('gdpr:purge-old-events');

        // If the table were not InnoDB, the cascade would not run, and this
        // row would be left orphaned - the service report would reference a
        // non-existent event.
        $this->assertSame(0, EventServiceReport::where('id', $report->id)->count());
    }

    public function test_purge_old_events_dry_run_deletes_nothing(): void
    {
        $this->enableGdpr();
        $old = $this->eventOn($this->monthsAgo(14));

        $this->artisan('gdpr:purge-old-events', ['--dry-run' => true])->assertExitCode(0);

        $this->assertNotNull(Event::find($old->id));
    }

    // --- maintenance:purge-old-group-data ---

    private function dayStatOn(string $day): DayStat
    {
        return DayStat::factory()->create([
            'group_id' => $this->group->id,
            'day' => $day,
            'time_slot' => $day.' 09:00:00',
            'events' => 2,
        ]);
    }

    private function groupDateOn(string $day): GroupDate
    {
        return GroupDate::factory()->create([
            'group_id' => $this->group->id,
            'date' => $day,
            'date_start' => $day.' 08:00:00',
            'date_end' => $day.' 12:00:00',
        ]);
    }

    public function test_purge_old_group_data_is_a_no_op_while_the_setting_is_disabled(): void
    {
        config(['settings_group_data_retention' => '0']);
        $stat = $this->dayStatOn($this->monthsAgo(36));

        $this->artisan('maintenance:purge-old-group-data')->assertExitCode(0);

        $this->assertNotNull(DayStat::find($stat->id));
    }

    public function test_purge_old_group_data_is_a_no_op_while_the_setting_is_missing(): void
    {
        config(['settings_group_data_retention' => null]);
        $stat = $this->dayStatOn($this->monthsAgo(36));

        $this->artisan('maintenance:purge-old-group-data');

        $this->assertNotNull(DayStat::find($stat->id));
    }

    /**
     * The settings table's value comes from the UI, and $state is a public
     * Livewire property. A non-whitelisted value, cast, would give a
     * zero-month window, i.e. it would delete everything up to TODAY. Here
     * too the behaviour must be a no-op, not "more cautious" behaviour.
     */
    public function test_purge_old_group_data_is_a_no_op_on_a_tampered_setting_value(): void
    {
        config(['settings_group_data_retention' => 'abc']);
        $stat = $this->dayStatOn($this->monthsAgo(36));
        $recent = $this->dayStatOn($this->monthsAgo(1));

        $this->artisan('maintenance:purge-old-group-data');

        $this->assertNotNull(DayStat::find($stat->id));
        $this->assertNotNull(DayStat::find($recent->id));
    }

    public function test_purge_old_group_data_honours_the_one_year_window(): void
    {
        config(['settings_group_data_retention' => '12']);
        $old = $this->dayStatOn($this->monthsAgo(13));
        $recent = $this->dayStatOn($this->monthsAgo(11));

        $this->artisan('maintenance:purge-old-group-data')->assertExitCode(0);

        $this->assertNull(DayStat::find($old->id));
        $this->assertNotNull(DayStat::find($recent->id));
    }

    public function test_purge_old_group_data_honours_the_two_year_window(): void
    {
        config(['settings_group_data_retention' => '24']);
        $old = $this->dayStatOn($this->monthsAgo(25));
        $recent = $this->dayStatOn($this->monthsAgo(23));

        $this->artisan('maintenance:purge-old-group-data');

        $this->assertNull(DayStat::find($old->id));
        $this->assertNotNull(DayStat::find($recent->id));
    }

    /**
     * Groups\Statistics builds the daily rows from group_dates. If only
     * day_stats disappeared, the UI would not show an empty table, but
     * would claim the group served 0 hours out of N available.
     */
    public function test_purge_old_group_data_removes_the_group_dates_on_the_same_floor(): void
    {
        config(['settings_group_data_retention' => '12']);
        $old = $this->groupDateOn($this->monthsAgo(13));
        $recent = $this->groupDateOn($this->monthsAgo(11));

        $this->artisan('maintenance:purge-old-group-data');

        $this->assertNull(GroupDate::find($old->id));
        $this->assertNotNull(GroupDate::find($recent->id));
    }

    public function test_purge_old_group_data_runs_even_while_gdpr_is_disabled(): void
    {
        config(['gdpr.enabled' => false, 'settings_group_data_retention' => '12']);
        $old = $this->dayStatOn($this->monthsAgo(13));

        $this->artisan('maintenance:purge-old-group-data');

        $this->assertNull(DayStat::find($old->id));
    }

    public function test_purge_old_group_data_dry_run_deletes_nothing(): void
    {
        config(['settings_group_data_retention' => '12']);
        $old = $this->dayStatOn($this->monthsAgo(13));
        $oldDate = $this->groupDateOn($this->monthsAgo(13));

        $this->artisan('maintenance:purge-old-group-data', ['--dry-run' => true])->assertExitCode(0);

        $this->assertNotNull(DayStat::find($old->id));
        $this->assertNotNull(GroupDate::find($oldDate->id));
    }
}
