<?php

namespace Tests\Feature\Commands;

use App\Models\Group;
use App\Models\GroupUser;
use App\Models\User;
use App\Notifications\UserWillBeAnonymizeNotification;
use App\Notifications\UserWillBeAnyonimizeAdminNotification;
use Illuminate\Support\Facades\Notification;
use Tests\Feature\FeatureTestCase;

/**
 * TODO 06: the two GDPR scheduler closures, now runnable commands.
 *
 * These were the largest and least visible closures in Console\Kernel - roughly
 * 90 lines including a four-way raw join - and they delete or anonymize real
 * user data daily. They had no coverage whatsoever.
 *
 * Note: .env.testing sets GDPR_ENABLED=false, so every test that expects work
 * to happen must enable it explicitly.
 */
class GdprCommandsTest extends FeatureTestCase
{
    private int $ttl;

    protected function setUp(): void
    {
        parent::setUp();

        $this->ttl = (int) config('gdpr.settings.ttl'); // defaults to 6 months
    }

    private function enableGdpr(): void
    {
        config(['gdpr.enabled' => true]);
    }

    /**
     * The editor notification window is narrow and date-level: the command uses
     * whereBetween with Y-m-d formatted bounds, where both the lower bound
     * (ttl - 6 days) and the upper bound (ttl - 7 days) round to midnight. A
     * time within the same day already falls outside the upper bound, so we
     * target the middle of the day.
     */
    private function editorWarningWindowDate(): \Carbon\Carbon
    {
        return now()->subMonths($this->ttl)->addDays(6)->startOfDay()->addHours(12);
    }

    private function inactiveUser(array $attributes = []): User
    {
        return $this->createUser(array_merge([
            'email' => 'inactive-'.uniqid().'@example.test',
            'role' => 'registered',
            'isAnonymized' => 0,
            'last_activity' => now()->subMonths($this->ttl + 1),
        ], $attributes));
    }

    // --- gdpr:anonymize-inactive ---

    public function test_anonymize_is_a_no_op_when_gdpr_is_disabled(): void
    {
        config(['gdpr.enabled' => false]);
        $user = $this->inactiveUser(['email' => 'still-here@example.test']);

        $this->artisan('gdpr:anonymize-inactive')->assertExitCode(0);

        $this->assertSame('still-here@example.test', User::find($user->id)->email);
    }

    public function test_anonymize_processes_users_past_the_retention_period(): void
    {
        $this->enableGdpr();
        $user = $this->inactiveUser(['email' => 'to-anonymize@example.test']);

        $this->artisan('gdpr:anonymize-inactive')->assertExitCode(0);

        $fresh = User::find($user->id);
        $this->assertNotNull($fresh, 'The row must survive; only its data is anonymized.');
        $this->assertSame(1, (int) $fresh->isAnonymized);
        $this->assertNotSame('to-anonymize@example.test', $fresh->email);
    }

    public function test_anonymize_detaches_group_memberships_first(): void
    {
        $this->enableGdpr();
        $group = $this->createGroup();
        $user = $this->inactiveUser();
        $this->attachUserToGroup($user, $group);

        $this->artisan('gdpr:anonymize-inactive');

        $this->assertSame(0, GroupUser::where('user_id', $user->id)->count());
    }

    public function test_anonymize_skips_recently_active_users(): void
    {
        $this->enableGdpr();
        $active = $this->inactiveUser([
            'email' => 'active@example.test',
            'last_activity' => now()->subDay(),
        ]);

        $this->artisan('gdpr:anonymize-inactive');

        $this->assertSame('active@example.test', User::find($active->id)->email);
    }

    /**
     * Since TODO 12.2 the role alone does NOT protect - succession decides.
     *
     * This test previously recorded that the command skips the mainAdmin
     * and groupCreator roles. That rule was wrong in two directions: it protected
     * a groupCreator whose every group is covered by someone else, and it did not
     * protect a group admin who is the only one in their group. The detailed
     * succession cases are measured by tests/Feature/Gdpr/AnonymizationSuccessionTest.php;
     * here we only record that the command really did let go of the role list.
     */
    public function test_anonymize_no_longer_protects_by_role_alone(): void
    {
        $this->enableGdpr();
        // FeatureTestCase::setUp()'s owner@example.test main admin remains
        // as successor, and the groupCreator has no group - both are
        // anonymizable.
        $admin = $this->inactiveUser(['email' => 'admin@example.test', 'role' => 'mainAdmin']);
        $creator = $this->inactiveUser(['email' => 'creator@example.test', 'role' => 'groupCreator']);

        $this->artisan('gdpr:anonymize-inactive');

        $this->assertNotSame('admin@example.test', User::find($admin->id)->email);
        $this->assertNotSame('creator@example.test', User::find($creator->id)->email);
        $this->assertSame('registered', User::find($admin->id)->role);
    }

    public function test_anonymize_protects_a_user_who_has_nobody_to_hand_over_to(): void
    {
        $this->enableGdpr();

        $creator = $this->inactiveUser(['email' => 'creator@example.test', 'role' => 'groupCreator']);
        $group = Group::factory()->create(['parent_group_id' => null]);
        $this->attachUserToGroup($creator, $group, 'admin');

        $this->artisan('gdpr:anonymize-inactive');

        $this->assertSame('creator@example.test', User::find($creator->id)->email);
        $this->assertSame(0, (int) User::find($creator->id)->isAnonymized);
    }

    public function test_anonymize_skips_already_anonymized_users(): void
    {
        $this->enableGdpr();
        $already = $this->inactiveUser(['email' => 'already@example.test', 'isAnonymized' => 1]);

        $this->artisan('gdpr:anonymize-inactive');

        // Stays unchanged, does not undergo another round of anonymization.
        $this->assertSame('already@example.test', User::find($already->id)->email);
    }

    // --- gdpr:notify-anonymization ---

    public function test_notify_is_a_no_op_when_gdpr_is_disabled(): void
    {
        Notification::fake();
        config(['gdpr.enabled' => false]);
        $this->inactiveUser(['last_activity' => now()->subMonths($this->ttl)->addDays(10)]);

        $this->artisan('gdpr:notify-anonymization')->assertExitCode(0);

        Notification::assertNothingSent();
    }

    public function test_notify_warns_users_approaching_the_retention_limit(): void
    {
        Notification::fake();
        $this->enableGdpr();

        // The command looks for a last activity older than the ttl - 15 days threshold.
        $user = $this->inactiveUser(['last_activity' => now()->subMonths($this->ttl)->addDays(10)]);

        $this->artisan('gdpr:notify-anonymization')->assertExitCode(0);

        Notification::assertSentTo($user, UserWillBeAnonymizeNotification::class);
    }

    public function test_notify_leaves_recently_active_users_alone(): void
    {
        Notification::fake();
        $this->enableGdpr();

        $recent = $this->inactiveUser(['last_activity' => now()->subDays(3)]);

        $this->artisan('gdpr:notify-anonymization');

        Notification::assertNotSentTo($recent, UserWillBeAnonymizeNotification::class);
    }

    /**
     * TODO 12.2: the warning follows the same eligibility condition
     * as anonymization.
     *
     * This matters in both directions. Whoever becomes eligible for anonymization
     * MUST receive the advance notification - under the old role list a
     * groupCreator would have disappeared without warning. But whoever is
     * blocked by succession must not receive it: the query is not a window but a
     * threshold (last_activity <= ttl - 15 days), so the blocked user would
     * receive an email DAILY about a deletion that will never happen.
     */
    public function test_notify_warns_a_group_creator_who_will_be_anonymized(): void
    {
        Notification::fake();
        $this->enableGdpr();

        $creator = $this->inactiveUser(['role' => 'groupCreator']);

        $this->artisan('gdpr:notify-anonymization');

        Notification::assertSentTo($creator, UserWillBeAnonymizeNotification::class);
    }

    public function test_notify_stays_silent_for_a_user_the_succession_rule_blocks(): void
    {
        Notification::fake();
        $this->enableGdpr();

        $group = Group::factory()->create(['parent_group_id' => null]);
        $soleAdmin = $this->inactiveUser();
        $this->attachUserToGroup($soleAdmin, $group, 'admin');

        $this->artisan('gdpr:notify-anonymization');

        Notification::assertNotSentTo($soleAdmin, UserWillBeAnonymizeNotification::class);
    }

    public function test_notify_alerts_group_editors_about_members_in_the_warning_window(): void
    {
        Notification::fake();
        $this->enableGdpr();

        // The editor notification uses a narrow, 6-7 day window before
        // the retention period expires.
        $group = Group::factory()->create(['parent_group_id' => null]);
        $member = $this->inactiveUser([
            'last_activity' => $this->editorWarningWindowDate(),
        ]);
        $editor = $this->createUser(['email' => 'editor@example.test']);

        $this->attachUserToGroup($member, $group);
        $this->attachUserToGroup($editor, $group, 'admin');

        $this->artisan('gdpr:notify-anonymization')->assertExitCode(0);

        Notification::assertSentTo($editor, UserWillBeAnyonimizeAdminNotification::class);
    }

    public function test_notify_decrypts_names_from_the_raw_join(): void
    {
        Notification::fake();
        $this->enableGdpr();

        // The editor branch uses a raw DB query, so it manually decrypts the
        // encrypted User.name and Group.name columns. If this
        // breaks, the command fails with a DecryptException, not silently.
        $group = Group::factory()->create(['parent_group_id' => null, 'name' => 'Titkos Csoport']);
        $member = $this->inactiveUser([
            'name' => 'Titkos Tag',
            'last_activity' => $this->editorWarningWindowDate(),
        ]);
        $editor = $this->createUser(['email' => 'editor2@example.test']);

        $this->attachUserToGroup($member, $group);
        $this->attachUserToGroup($editor, $group, 'roler');

        $this->artisan('gdpr:notify-anonymization')->assertExitCode(0);

        Notification::assertSentTo(
            $editor,
            UserWillBeAnyonimizeAdminNotification::class,
            function ($notification) use ($member) {
                $rendered = $notification->toMail($member)->render();

                return str_contains($rendered, 'Titkos Tag');
            }
        );
    }

    public function test_notify_ignores_child_groups_for_the_editor_alert(): void
    {
        Notification::fake();
        $this->enableGdpr();

        // The join filters with a whereNull('G.parent_group_id') condition: the
        // editors of subgroups do not receive a notification.
        $parent = Group::factory()->create(['parent_group_id' => null]);
        $child = Group::factory()->asChildOf($parent)->create();

        $member = $this->inactiveUser([
            'last_activity' => $this->editorWarningWindowDate(),
        ]);
        $childEditor = $this->createUser(['email' => 'child-editor@example.test']);

        $this->attachUserToGroup($member, $child);
        $this->attachUserToGroup($childEditor, $child, 'admin');

        $this->artisan('gdpr:notify-anonymization');

        Notification::assertNotSentTo($childEditor, UserWillBeAnyonimizeAdminNotification::class);
    }
}
