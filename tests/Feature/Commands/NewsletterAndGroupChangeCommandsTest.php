<?php

namespace Tests\Feature\Commands;

use App\Models\AdminNewsletter;
use App\Models\GroupFutureChange;
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
        Notification::fake(); // számláló nullázása
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

    public function test_an_unknown_recipient_group_silently_blocks_the_whole_queue(): void
    {
        // Jellemzés-teszt egy meglévő hibáról, nem elvárt viselkedés.
        //
        // Ismeretlen send_to értéknél az eredeti closure return-nel lépett ki,
        // ami a ciklus HÁTRALÉVŐ hírleveleit is kihagyta. A sent_time sem
        // íródik ki, így a parancs percenként újrapróbálkozik és tartósan
        // blokkolja a mögötte állókat. A viselkedés az upgrade alatt
        // szándékosan változatlan. Lásd: roadmap TODO 06.
        Notification::fake();

        // A hibás hírlevél kerül előbb a sorba (kisebb id).
        $this->dueNewsletter('somethingUnknown');
        $valid = $this->dueNewsletter('groupCreators');

        $this->artisan('newsletters:send-due')->assertExitCode(0);

        Notification::assertNothingSent();
        $this->assertNull($valid->fresh()->sent_time, 'The valid newsletter behind the broken one is never sent.');
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

        // A változás feldolgozás után nem marad függőben.
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
}
