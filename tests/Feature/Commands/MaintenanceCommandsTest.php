<?php

namespace Tests\Feature\Commands;

use App\Models\Event;
use App\Models\Group;
use App\Models\GroupMessage;
use App\Models\LogHistory;
use App\Models\Settings;
use App\Models\StaticPage;
use App\Models\Statistics;
use App\Models\User;
use Tests\Feature\FeatureTestCase;

/**
 * TODO 06: the scheduler's maintenance closures, now runnable commands.
 *
 * Before this change none of these could be invoked or tested individually -
 * they were ~200 lines of anonymous closures inside Console\Kernel::schedule().
 */
class MaintenanceCommandsTest extends FeatureTestCase
{
    private Group $group;
    private User $member;

    protected function setUp(): void
    {
        parent::setUp();

        $this->group = $this->createGroup();
        $this->member = $this->createUser(['email' => 'cmd-member@example.test']);
        $this->attachUserToGroup($this->member, $this->group);
        $this->actingAs($this->member);
    }

    // --- users:purge-unverified ---

    public function test_purge_unverified_deletes_users_older_than_a_week_without_verification(): void
    {
        $stale = $this->createUser([
            'email' => 'stale@example.test',
            'email_verified_at' => null,
            'created_at' => now()->subDays(8),
        ]);

        $this->artisan('users:purge-unverified')->assertExitCode(0);

        $this->assertNull(User::find($stale->id));
    }

    public function test_purge_unverified_keeps_recent_unverified_users(): void
    {
        $recent = $this->createUser([
            'email' => 'recent@example.test',
            'email_verified_at' => null,
            'created_at' => now()->subDays(2),
        ]);

        $this->artisan('users:purge-unverified');

        $this->assertNotNull(User::find($recent->id));
    }

    public function test_purge_unverified_keeps_verified_users_regardless_of_age(): void
    {
        $verified = $this->createUser([
            'email' => 'old-verified@example.test',
            'email_verified_at' => now()->subYear(),
            'created_at' => now()->subYear(),
        ]);

        $this->artisan('users:purge-unverified');

        $this->assertNotNull(User::find($verified->id));
    }

    public function test_purge_unverified_never_touches_an_anonymized_user(): void
    {
        // TODO 33.2 put email_verified_at into User::$gdprNullFields, which
        // dropped every anonymized user straight into this command's selection:
        // no verification timestamp, and created_at is months old by the time
        // the retention window closes. This runs hourly at :50, so an
        // anonymized row would have been hard-deleted within the hour - leaving
        // events.user_id and group_user.user_id dangling, and contradicting
        // AnonymizationTest, which requires the row to survive with its data
        // replaced. CONTROL: remove the isAnonymized filter from
        // PurgeUnverifiedUsers and this test fails while the three above pass.
        $anonymized = $this->createUser([
            'email' => 'anonymized-purge@example.test',
            'email_verified_at' => null,
            'isAnonymized' => 1,
            'created_at' => now()->subMonths(7),
        ]);

        $this->artisan('users:purge-unverified')->assertExitCode(0);

        $this->assertNotNull(
            User::find($anonymized->id),
            'An anonymized row is retained deliberately; it must not be swept up as unverified.'
        );
    }

    // --- events:expire-pending ---

    public function test_expire_pending_marks_started_pending_events_as_denied(): void
    {
        $event = Event::factory()->create([
            'group_id' => $this->group->id,
            'user_id' => $this->member->id,
            'day' => now()->subDay()->toDateString(),
            'start' => now()->subDay()->format('Y-m-d H:i:s'),
            'end' => now()->subDay()->addHour()->format('Y-m-d H:i:s'),
            'status' => 0,
        ]);

        $this->artisan('events:expire-pending')->assertExitCode(0);

        $this->assertSame(2, (int) Event::find($event->id)->status);
    }

    public function test_expire_pending_leaves_future_pending_events_alone(): void
    {
        $event = Event::factory()->create([
            'group_id' => $this->group->id,
            'user_id' => $this->member->id,
            'day' => now()->addDay()->toDateString(),
            'start' => now()->addDay()->format('Y-m-d H:i:s'),
            'end' => now()->addDay()->addHour()->format('Y-m-d H:i:s'),
            'status' => 0,
        ]);

        $this->artisan('events:expire-pending');

        $this->assertSame(0, (int) Event::find($event->id)->status);
    }

    public function test_expire_pending_does_not_touch_accepted_events(): void
    {
        $event = Event::factory()->create([
            'group_id' => $this->group->id,
            'user_id' => $this->member->id,
            'day' => now()->subDay()->toDateString(),
            'start' => now()->subDay()->format('Y-m-d H:i:s'),
            'end' => now()->subDay()->addHour()->format('Y-m-d H:i:s'),
            'status' => 1,
            'accepted_at' => now()->subDays(2),
            'accepted_by' => $this->member->id,
        ]);

        $this->artisan('events:expire-pending');

        $this->assertSame(1, (int) Event::find($event->id)->status);
    }

    // --- maintenance:purge-log-history ---

    public function test_purge_log_history_removes_entries_older_than_three_months(): void
    {
        $old = LogHistory::factory()->create(['created_at' => now()->subMonths(4)]);
        $recent = LogHistory::factory()->create(['created_at' => now()->subDays(3)]);

        $this->artisan('maintenance:purge-log-history')->assertExitCode(0);

        $this->assertNull(LogHistory::find($old->id));
        $this->assertNotNull(LogHistory::find($recent->id));
    }

    // --- maintenance:daily-cleanup ---

    public function test_daily_cleanup_force_deletes_old_trashed_events(): void
    {
        $event = Event::factory()->create([
            'group_id' => $this->group->id,
            'user_id' => $this->member->id,
            'day' => now()->subMonths(4)->toDateString(),
            'start' => now()->subMonths(4)->format('Y-m-d H:i:s'),
            'end' => now()->subMonths(4)->addHour()->format('Y-m-d H:i:s'),
        ]);
        $event->delete();

        $this->artisan('maintenance:daily-cleanup')->assertExitCode(0);

        $this->assertSame(0, Event::withTrashed()->where('id', $event->id)->count());
    }

    public function test_daily_cleanup_keeps_recently_trashed_events(): void
    {
        $event = Event::factory()->create([
            'group_id' => $this->group->id,
            'user_id' => $this->member->id,
            'day' => now()->subDays(2)->toDateString(),
            'start' => now()->subDays(2)->format('Y-m-d H:i:s'),
            'end' => now()->subDays(2)->addHour()->format('Y-m-d H:i:s'),
        ]);
        $event->delete();

        $this->artisan('maintenance:daily-cleanup');

        $this->assertSame(1, Event::withTrashed()->where('id', $event->id)->count());
    }

    public function test_daily_cleanup_removes_group_messages_older_than_a_week(): void
    {
        $old = GroupMessage::factory()->create([
            'group_id' => $this->group->id,
            'user_id' => $this->member->id,
            'created_at' => now()->subDays(10),
        ]);
        $recent = GroupMessage::factory()->create([
            'group_id' => $this->group->id,
            'user_id' => $this->member->id,
            'created_at' => now()->subDay(),
        ]);

        $this->artisan('maintenance:daily-cleanup');

        $this->assertNull(GroupMessage::find($old->id));
        $this->assertNotNull(GroupMessage::find($recent->id));
    }

    // --- statistics:record-daily-users / record-active-users ---

    public function test_record_daily_users_writes_a_statistics_row(): void
    {
        Statistics::query()->delete();
        $this->member->update(['last_activity' => now()->subHours(2)]);

        $this->artisan('statistics:record-daily-users')->assertExitCode(0);

        // A 'dialy_users' elgépelés szándékosan megmarad: az Admin\Statistics
        // komponens és a meglévő adatsorok is erre a stringre épülnek.
        $row = Statistics::where('type', 'dialy_users')->first();

        $this->assertNotNull($row);
        $this->assertGreaterThanOrEqual(1, (int) $row->number);
    }

    public function test_record_active_users_counts_only_the_last_hour(): void
    {
        Statistics::query()->delete();
        $this->member->update(['last_activity' => now()->subMinutes(10)]);
        $this->createUser(['email' => 'idle@example.test', 'last_activity' => now()->subDays(3)]);

        $this->artisan('statistics:record-active-users')->assertExitCode(0);

        $row = Statistics::where('type', 'active_users')->first();

        $this->assertNotNull($row);
        $this->assertSame(1, (int) $row->number);
    }

    // --- scheduler:heartbeat ---

    public function test_heartbeat_records_the_last_run_timestamp(): void
    {
        Settings::where('name', 'last_schedule_run')->delete();

        $this->artisan('scheduler:heartbeat')->assertExitCode(0);

        $this->assertDatabaseHas('settings', ['name' => 'last_schedule_run']);
    }

    public function test_heartbeat_updates_rather_than_duplicates_on_repeated_runs(): void
    {
        $this->artisan('scheduler:heartbeat');
        $this->artisan('scheduler:heartbeat');

        $this->assertSame(1, Settings::where('name', 'last_schedule_run')->count());
    }
}
