<?php

namespace Tests\Feature\Commands;

use App\Models\AdminNewsletter;
use App\Models\Group;
use App\Models\GroupDay;
use App\Models\GroupFutureChange;
use App\Models\LogHistory;
use App\Models\User;
use App\Notifications\Newsletter;
use Illuminate\Support\Facades\Notification;
use Tests\Feature\FeatureTestCase;

/**
 * TODO 06: the everyMinute closure, now split into two runnable commands.
 *
 * The original closure applied scheduled group changes and sent newsletters in
 * one anonymous block that ran every single minute, with no way to invoke or
 * test either half.
 */
class NewsletterAndGroupChangeCommandsTest extends FeatureTestCase
{
    private User $creator;

    protected function setUp(): void
    {
        parent::setUp();

        $this->creator = $this->createUser([
            'email' => 'nl-creator@example.test',
            'role' => 'groupCreator',
        ]);
        $this->actingAs($this->creator);
    }

    private function dueNewsletter(string $sendTo = 'groupCreators'): AdminNewsletter
    {
        return AdminNewsletter::factory()->create([
            'user_id' => $this->creator->id,
            'date' => today(),
            'status' => 1,
            'send_newsletter' => 1,
            'sent_time' => null,
            'send_to' => $sendTo,
        ]);
    }

    // --- newsletters:send-due ---

    public function test_send_due_delivers_to_group_creators_and_admins(): void
    {
        Notification::fake();
        $newsletter = $this->dueNewsletter('groupCreators');

        $this->artisan('newsletters:send-due')->assertExitCode(0);

        Notification::assertSentTo($this->creator, Newsletter::class);
        $this->assertNotNull($newsletter->fresh()->sent_time, 'sent_time must be stamped to prevent resending.');
    }

    public function test_send_due_marks_the_newsletter_as_sent_so_it_is_not_resent(): void
    {
        Notification::fake();
        $this->dueNewsletter();

        $this->artisan('newsletters:send-due');
        Notification::fake(); // reset the counter
        $this->artisan('newsletters:send-due');

        Notification::assertNothingSent();
    }

    public function test_send_due_ignores_unpublished_newsletters(): void
    {
        Notification::fake();
        $this->dueNewsletter()->update(['status' => 0]);

        $this->artisan('newsletters:send-due');

        Notification::assertNothingSent();
    }

    public function test_send_due_ignores_newsletters_dated_for_another_day(): void
    {
        Notification::fake();
        $this->dueNewsletter()->update(['date' => today()->addDay()]);

        $this->artisan('newsletters:send-due');

        Notification::assertNothingSent();
    }

    public function test_send_due_targets_group_admins(): void
    {
        Notification::fake();

        $group = $this->createGroup();
        $groupAdmin = $this->createUser(['email' => 'nl-group-admin@example.test']);
        $this->attachUserToGroup($groupAdmin, $group, 'admin');

        $this->dueNewsletter('groupAdmins');

        $this->artisan('newsletters:send-due');

        Notification::assertSentTo($groupAdmin, Newsletter::class);
    }

    public function test_an_unknown_recipient_group_no_longer_blocks_the_queue(): void
    {
        // REVERSED by the v1-patch B1 fix.
        //
        // On an unknown send_to value the command exited via `return`, which
        // also skipped the REMAINING newsletters in the loop - not just the
        // current one. sent_time was not written either, so it retried every
        // minute, permanently stuck at the front of the queue: a single
        // mistyped recipient value blocked every further newsletter
        // indefinitely.
        //
        // Now it skips the broken row, delivers the ones behind it, and
        // signals via a non-zero exit code that something was skipped.
        Notification::fake();

        // The broken newsletter is placed earlier in the queue (lower id).
        $broken = $this->dueNewsletter('somethingUnknown');
        $valid = $this->dueNewsletter('groupCreators');

        $this->artisan('newsletters:send-due')->assertExitCode(1);

        Notification::assertSentTo($this->creator, Newsletter::class);

        $this->assertNotNull(
            $valid->fresh()->sent_time,
            'A hibás mögött álló hírlevél kimegy.'
        );

        // The broken row is NOT marked as delivered - it was not sent - but it
        // no longer blocks anyone either.
        $this->assertNull(
            $broken->fresh()->sent_time,
            'A kihagyott hírlevél nem lehet kézbesítettnek jelölve.'
        );
    }

    public function test_a_valid_batch_still_reports_success(): void
    {
        // Control experiment for B1: the non-zero exit code is ONLY a skip
        // signal, it did not become the command's default state.
        Notification::fake();

        $this->dueNewsletter('groupCreators');

        $this->artisan('newsletters:send-due')->assertExitCode(0);
    }

    // --- groups:apply-future-changes ---

    public function test_apply_future_changes_runs_for_changes_dated_today(): void
    {
        $group = $this->createGroup();
        GroupFutureChange::factory()->create([
            'group_id' => $group->id,
            'user_id' => $this->creator->id,
            'change_date' => today()->toDateString(),
            'group' => ['min_publishers' => 2, 'max_publishers' => 5],
            'days' => [],
            'disabled_slots' => [],
        ]);

        $this->artisan('groups:apply-future-changes')->assertExitCode(0);

        // The change no longer remains pending after processing.
        $this->assertSame(0, GroupFutureChange::where('group_id', $group->id)->count());
    }

    public function test_apply_future_changes_leaves_future_dated_changes_pending(): void
    {
        $group = $this->createGroup();
        GroupFutureChange::factory()->create([
            'group_id' => $group->id,
            'user_id' => $this->creator->id,
            'change_date' => today()->addWeek()->toDateString(),
        ]);

        $this->artisan('groups:apply-future-changes');

        $this->assertSame(1, GroupFutureChange::where('group_id', $group->id)->count());
    }

    public function test_apply_future_changes_runs_cleanly_with_nothing_pending(): void
    {
        $this->artisan('groups:apply-future-changes')->assertExitCode(0);

        $this->assertSame(0, GroupFutureChange::count());
    }

    /**
     * TODO 10.1: the branch that actually writes the day template.
     *
     * The tests above run with an empty days array, so the Eloquent writes in
     * updateGroupFutureChanges::initChanges() (updateOrCreate and
     * $del->delete()) never ran. These are exactly the calls that the
     * GroupDayObserver would fire on, if it were registered.
     */
    public function test_apply_future_changes_writes_the_day_template(): void
    {
        $group = $this->createGroup();
        GroupDay::factory()->create([
            'group_id' => $group->id, 'day_number' => 1,
            'start_time' => '08:00', 'end_time' => '16:00',
        ]);
        GroupDay::factory()->create([
            'group_id' => $group->id, 'day_number' => 3,
            'start_time' => '08:00', 'end_time' => '16:00',
        ]);

        GroupFutureChange::factory()->create([
            'group_id' => $group->id,
            'user_id' => $this->creator->id,
            'change_date' => today()->toDateString(),
            'group' => ['min_publishers' => 1, 'max_publishers' => 3],
            'days' => [
                // narrowing, deletion (day_number === false), and a new day all at once
                1 => ['day_number' => '1', 'start_time' => '10:00', 'end_time' => '12:00'],
                3 => ['day_number' => false, 'start_time' => '08:00', 'end_time' => '16:00'],
                5 => ['day_number' => '5', 'start_time' => '09:00', 'end_time' => '11:00'],
            ],
            'disabled_slots' => [],
        ]);

        $this->artisan('groups:apply-future-changes')->assertExitCode(0);

        $days = GroupDay::where('group_id', $group->id)->get()->keyBy('day_number');

        $this->assertSame('10:00', $days[1]->start_time);
        $this->assertSame('12:00', $days[1]->end_time);
        $this->assertFalse($days->has(3), 'A false day_number a nap törlését jelenti.');
        $this->assertSame('09:00', $days[5]->start_time);
    }

    /**
     * TODO 10.1: in production this command is run by the scheduler, where
     * there is no logged-in user - before the TODO 10 causer work,
     * GroupObserver::updated() simply skipped logging in this case.
     */
    public function test_apply_future_changes_runs_without_an_authenticated_user(): void
    {
        auth()->logout();
        $this->assertGuest();

        $group = $this->createGroup(['max_publishers' => 3]);
        GroupDay::factory()->create([
            'group_id' => $group->id, 'day_number' => 2,
            'start_time' => '08:00', 'end_time' => '16:00',
        ]);

        GroupFutureChange::factory()->create([
            'group_id' => $group->id,
            'user_id' => $this->creator->id,
            'change_date' => today()->toDateString(),
            'group' => ['max_publishers' => 6],
            'days' => [
                2 => ['day_number' => '2', 'start_time' => '09:00', 'end_time' => '15:00'],
            ],
            'disabled_slots' => [],
        ]);

        $this->artisan('groups:apply-future-changes')->assertExitCode(0);

        $this->assertSame(6, (int) $group->fresh()->max_publishers);
        $this->assertSame(
            '09:00',
            GroupDay::where('group_id', $group->id)->where('day_number', 2)->first()->start_time
        );

        // A system-caused change is logged too, causer_id = 0.
        $history = LogHistory::where('model_type', Group::class)
            ->where('model_id', $group->id)
            ->where('event', 'updated')
            ->latest('id')
            ->first();

        $this->assertNotNull($history, 'Az ütemezett változás is nyomot hagy.');
        $this->assertSame(0, (int) $history->causer_id);
    }
}
