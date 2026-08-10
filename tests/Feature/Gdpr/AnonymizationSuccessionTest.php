<?php

namespace Tests\Feature\Gdpr;

use App\Models\Group;
use App\Models\GroupUser;
use App\Models\User;
use Tests\Feature\FeatureTestCase;

/**
 * TODO 12.2: the condition for anonymization is SUCCESSION, not role.
 *
 * Previously gdpr:anonymize-inactive worked with a role list
 * (whereNotIn('role', ['mainAdmin','groupCreator'])). This was wrong in two
 * directions: it protected a groupCreator whose every group is covered by
 * someone else, and it did not protect a group admin who is the only one in
 * their group.
 *
 * The new rule:
 *   1. mainAdmin can only be anonymized if another, non-anonymized mainAdmin
 *      remains.
 *   2. a group admin only if pwbs_check_group_other_admins() holds true for
 *      EVERY one of their groups - the same condition that leaving a group
 *      also enforces.
 *
 * The rule lives in User::anonymize(), so it applies on all three paths: the
 * project's command, the package's 00:00 command, and the profile-page GDPR
 * request.
 */
class AnonymizationSuccessionTest extends FeatureTestCase
{
    private int $ttl;

    protected function setUp(): void
    {
        parent::setUp();

        $this->ttl = (int) config('gdpr.settings.ttl');
        config(['gdpr.enabled' => true]);
    }

    private function inactiveUser(string $email, array $attributes = []): User
    {
        return $this->createUser(array_merge([
            'email' => $email,
            'role' => 'registered',
            'isAnonymized' => 0,
            'last_activity' => now()->subMonths($this->ttl + 1),
        ], $attributes));
    }

    /**
     * FeatureTestCase::setUp() creates an owner@example.test mainAdmin, so by
     * default there is ALWAYS a second main admin. For the cases rule 1 blocks,
     * this has to be removed first.
     */
    private function removeTheDefaultMainAdmin(): void
    {
        User::where('email', 'owner@example.test')->update(['role' => 'activated']);
    }

    private function assertAnonymized(User $user, string $message = ''): void
    {
        $fresh = User::find($user->id);

        $this->assertSame(1, (int) $fresh->isAnonymized, $message ?: 'A felhasználót anonimizálni kellett volna.');
    }

    private function assertNotAnonymized(User $user, string $message = ''): void
    {
        $fresh = User::find($user->id);

        $this->assertSame(0, (int) $fresh->isAnonymized, $message ?: 'A felhasználót nem lett volna szabad anonimizálni.');
    }

    // =========================================================================
    // Rule 1 - main admin succession
    // =========================================================================

    public function test_the_last_main_admin_is_not_anonymized(): void
    {
        // Today this case passes through the role filter (mainAdmin is excluded),
        // but for the wrong reason: not because they are the last one, but because
        // they are a main admin. The new rule must still block it even if the role
        // filter is no longer there.
        $this->removeTheDefaultMainAdmin();

        $sole = $this->inactiveUser('sole-main-admin@example.test', ['role' => 'mainAdmin']);

        $sole->anonymize();

        $this->assertNotAnonymized($sole);
        $this->assertSame('mainAdmin', User::find($sole->id)->role, 'A szerepét sem veszítheti el.');
        $this->assertSame('sole-main-admin@example.test', User::find($sole->id)->email);
    }

    public function test_a_main_admin_with_an_active_successor_is_anonymized(): void
    {
        // setUp()'s owner@example.test main admin remains as the successor - and
        // this is the essence of the behaviour change: the role alone no longer
        // protects.
        $inactive = $this->inactiveUser('replaceable-admin@example.test', ['role' => 'mainAdmin']);

        $inactive->anonymize();

        $this->assertAnonymized($inactive);
        $this->assertSame('registered', User::find($inactive->id)->role);
    }

    public function test_an_already_anonymized_main_admin_is_not_a_successor(): void
    {
        // The package's 00:00 command produces exactly this: it anonymizes but
        // leaves the row in place. If this counted as a successor, the chain
        // reaction would also work at the main-admin level.
        $this->removeTheDefaultMainAdmin();

        $this->createUser([
            'email' => 'ghost-admin@example.test',
            'role' => 'mainAdmin',
            'isAnonymized' => 1,
        ]);

        $sole = $this->inactiveUser('real-admin@example.test', ['role' => 'mainAdmin']);

        $sole->anonymize();

        $this->assertNotAnonymized($sole, 'Egy anonimizált főadmin nem utód.');
    }

    public function test_a_batch_of_inactive_main_admins_keeps_exactly_one(): void
    {
        // The nightly command loops over users. With two inactive main admins,
        // the first one still has a successor (the other one), so it gets
        // anonymized - but the second one no longer has one, so it stays. Order-
        // dependent, but the outcome is guaranteed: the system never ends up
        // without a main admin.
        $this->removeTheDefaultMainAdmin();

        $first = $this->inactiveUser('batch-admin-1@example.test', ['role' => 'mainAdmin']);
        $second = $this->inactiveUser('batch-admin-2@example.test', ['role' => 'mainAdmin']);

        $this->artisan('gdpr:anonymize-inactive')->assertExitCode(0);

        $survivors = User::whereIn('id', [$first->id, $second->id])
            ->where('isAnonymized', 0)
            ->count();

        $this->assertSame(1, $survivors, 'Pontosan egy főadminnak kell megmaradnia.');
        $this->assertSame(
            1,
            User::where('role', 'mainAdmin')->where('isAnonymized', 0)->count(),
            'Az oldal nem maradhat aktív főadmin nélkül.'
        );
    }

    // =========================================================================
    // Rule 2 - group admin succession
    // =========================================================================

    public function test_the_only_admin_of_a_group_is_not_anonymized(): void
    {
        // Today's role filter LETS THIS CASE THROUGH: the user's role is
        // 'registered', the filter does not care about their admin status within
        // the group. The group is left without an owner.
        $user = $this->inactiveUser('sole-group-admin@example.test');
        $group = $this->createGroup(['name' => 'Gazdátlan csoport']);
        $this->attachUserToGroup($user, $group, 'admin');

        $user->anonymize();

        $this->assertNotAnonymized($user);
    }

    public function test_a_second_active_admin_unblocks_the_anonymization(): void
    {
        $user = $this->inactiveUser('handover@example.test');
        $group = $this->createGroup(['name' => 'Átadható csoport']);
        $this->attachUserToGroup($user, $group, 'admin');

        $successor = $this->createUser(['email' => 'successor@example.test']);
        $this->attachUserToGroup($successor, $group, 'admin');

        $user->anonymize();

        $this->assertAnonymized($user);
    }

    public function test_an_anonymized_second_admin_is_not_a_successor(): void
    {
        // THIS IS THE CHAIN REACTION. Group::groupAdmins() did not filter on
        // isAnonymized, so an already anonymized admin counted as a successor -
        // and since the package's command leaves the membership in place, all of
        // the group's real admins could have been emptied one after another.
        $user = $this->inactiveUser('next-in-line@example.test');
        $group = $this->createGroup(['name' => 'Láncreakció']);
        $this->attachUserToGroup($user, $group, 'admin');

        $ghost = $this->createUser(['email' => 'ghost-member@example.test', 'isAnonymized' => 1]);
        $this->attachUserToGroup($ghost, $group, 'admin');

        $user->anonymize();

        $this->assertNotAnonymized($user, 'Egy anonimizált csoportadmin nem utód.');
    }

    public function test_a_pending_second_admin_is_not_a_successor(): void
    {
        // An unaccepted invitation is not membership yet: the invitee never
        // actually joined the group, so it cannot be handed over to them.
        $user = $this->inactiveUser('pending-handover@example.test');
        $group = $this->createGroup(['name' => 'Függő meghívás']);
        $this->attachUserToGroup($user, $group, 'admin');

        $invited = $this->createUser(['email' => 'invited@example.test']);
        $this->attachUserToGroup($invited, $group, 'admin', false);

        $user->anonymize();

        $this->assertNotAnonymized($user, 'A függő meghívás nem utódlás.');
    }

    public function test_an_admin_present_only_in_the_parent_group_does_not_unblock(): void
    {
        // A known property of pwbs_check_group_other_admins() (TODO 07.2): it
        // also looks through child groups, and requires an admin who covers EVERY
        // group. This deliberately stays as is - leaving a group also enforces
        // this, and the two must not drift apart.
        $user = $this->inactiveUser('parent-and-child@example.test');
        $parent = $this->createGroup(['name' => 'Szülő csoport']);
        $child = $this->createChildGroup($parent, ['name' => 'Gyermek csoport']);

        $this->attachUserToGroup($user, $parent, 'admin');
        $this->attachUserToGroup($user, $child, 'admin');

        $partial = $this->createUser(['email' => 'parent-only@example.test']);
        $this->attachUserToGroup($partial, $parent, 'admin');

        $user->anonymize();

        $this->assertNotAnonymized($user, 'Csak a szülőt lefedő admin nem old fel.');
    }

    public function test_a_plain_member_is_never_blocked(): void
    {
        $user = $this->inactiveUser('plain-member@example.test');
        $group = $this->createGroup(['name' => 'Sima tagság']);
        $this->attachUserToGroup($user, $group, 'member');

        $user->anonymize();

        $this->assertAnonymized($user);
    }

    public function test_a_roler_is_never_blocked(): void
    {
        // A roler can edit the group, but they are not its responsible party -
        // the succession rule only applies to the admin role, just as it does for
        // leaving a group.
        $user = $this->inactiveUser('roler@example.test');
        $group = $this->createGroup(['name' => 'Roler csoportja']);
        $this->attachUserToGroup($user, $group, 'roler');

        $successor = $this->createUser(['email' => 'group-owner@example.test']);
        $this->attachUserToGroup($successor, $group, 'admin');

        $user->anonymize();

        $this->assertAnonymized($user);
    }

    public function test_a_group_creator_without_groups_is_now_anonymizable(): void
    {
        // DELIBERATE BEHAVIOUR CHANGE. The groupCreator role used to protect on
        // its own; from now on only whether there is a group to hand over matters.
        // Whoever creates a group becomes an admin in it
        // (ListGroups::createGroup()), so rule 2 covers it anyway.
        $creator = $this->inactiveUser('idle-creator@example.test', ['role' => 'groupCreator']);

        $creator->anonymize();

        $this->assertAnonymized($creator);
        $this->assertSame('registered', User::find($creator->id)->role);
    }

    public function test_a_group_creator_with_an_unmanned_group_is_blocked(): void
    {
        $creator = $this->inactiveUser('busy-creator@example.test', ['role' => 'groupCreator']);
        $group = $this->createGroup(['name' => 'Alapított csoport']);
        $this->attachUserToGroup($creator, $group, 'admin');

        $creator->anonymize();

        $this->assertNotAnonymized($creator);
        $this->assertSame('groupCreator', User::find($creator->id)->role);
    }

    public function test_every_group_must_have_a_successor_not_just_one(): void
    {
        // Two separate groups: one has a successor, the other does not. The
        // rule applies to every group, so this is a blocked case.
        $user = $this->inactiveUser('two-groups@example.test');

        $covered = $this->createGroup(['name' => 'Ellátott csoport']);
        $this->attachUserToGroup($user, $covered, 'admin');
        $successor = $this->createUser(['email' => 'covers-one@example.test']);
        $this->attachUserToGroup($successor, $covered, 'admin');

        $uncovered = $this->createGroup(['name' => 'Ellátatlan csoport']);
        $this->attachUserToGroup($user, $uncovered, 'admin');

        $user->anonymize();

        $this->assertNotAnonymized($user, 'Egyetlen fedetlen csoport is blokkol.');
    }

    // =========================================================================
    // All three paths see the same rule
    // =========================================================================

    public function test_the_project_command_respects_the_rule(): void
    {
        $user = $this->inactiveUser('project-path@example.test');
        $group = $this->createGroup(['name' => 'Projekt parancs']);
        $this->attachUserToGroup($user, $group, 'admin');

        $this->artisan('gdpr:anonymize-inactive')->assertExitCode(0);

        $this->assertNotAnonymized($user);
    }

    public function test_the_model_call_respects_the_rule(): void
    {
        // TODO 33.2 replaced a test for the Dialect package's 00:00 command,
        // which is gone with the package. The reason that test existed still
        // stands, so it is asserted against the model API instead: the guard
        // lives in User::anonymize() rather than in a command precisely because
        // callers reach it directly. DeleteGroupDataProcess:91 and the
        // 2024_12_01_223022 backfill migration both do exactly this.
        $user = $this->inactiveUser('model-path@example.test');
        $group = $this->createGroup(['name' => 'Modellhívás']);
        $this->attachUserToGroup($user, $group, 'admin');

        $this->assertFalse($user->fresh()->anonymize(), 'A blokkolt hívás false-szal tér vissza.');

        $this->assertNotAnonymized($user);
        $this->assertSame('model-path@example.test', User::find($user->id)->email);
    }

    public function test_the_project_command_does_not_detach_a_blocked_user(): void
    {
        // ORDERING REQUIREMENT. The command originally detached memberships
        // FIRST, and only anonymized afterwards. If the guard blocked after the
        // fact, the user would be left without membership but not anonymized -
        // and precisely the proof of succession would be lost.
        $user = $this->inactiveUser('keep-membership@example.test');
        $group = $this->createGroup(['name' => 'Megmaradó tagság']);
        $this->attachUserToGroup($user, $group, 'admin');

        $this->artisan('gdpr:anonymize-inactive')->assertExitCode(0);

        $this->assertDatabaseHas('group_user', [
            'user_id' => $user->id,
            'group_id' => $group->id,
            'deleted_at' => null,
        ]);
    }

    public function test_the_project_command_still_detaches_an_eligible_user(): void
    {
        $user = $this->inactiveUser('eligible@example.test');
        $group = $this->createGroup(['name' => 'Bontható tagság']);
        $this->attachUserToGroup($user, $group, 'admin');
        $successor = $this->createUser(['email' => 'takes-over@example.test']);
        $this->attachUserToGroup($successor, $group, 'admin');

        $this->artisan('gdpr:anonymize-inactive')->assertExitCode(0);

        $this->assertAnonymized($user);
        $this->assertDatabaseMissing('group_user', [
            'user_id' => $user->id,
            'group_id' => $group->id,
            'deleted_at' => null,
        ]);
    }

    // =========================================================================
    // The profile-page GDPR request
    // =========================================================================

    public function test_the_profile_request_is_blocked_with_an_explanation(): void
    {
        // The GDPR request must not silently disappear: the user has to know
        // what they need to do (hand over their group).
        $user = $this->createUser(['email' => 'blocked-request@example.test']);
        $group = $this->createGroup(['name' => 'Átadandó csoport']);
        $this->attachUserToGroup($user, $group, 'admin');

        $this->actingAs($user);

        $response = $this->getWithPasswordConfirmation(route('user.askToDelete'));

        $response->assertRedirect(route('user.profile'));

        // Checking for mere presence is not enough: the profileFull middleware
        // also uses the same key for the same redirect. The group's name makes it
        // unambiguous that it was the succession rule that spoke up.
        $this->assertStringContainsString(
            'Átadandó csoport',
            (string) session('profile_message')
        );

        $this->assertNotAnonymized($user);
    }

    public function test_the_signed_deletion_link_is_blocked_too(): void
    {
        // The signed link is valid for 60 hours, during which state can change -
        // which is why the second step must also verify.
        $user = $this->createUser(['email' => 'blocked-link@example.test']);
        $group = $this->createGroup(['name' => 'Link ág']);
        $this->attachUserToGroup($user, $group, 'admin');

        $this->actingAs($user)
            ->get($this->signedRoute('user.deletepersonaldata', ['id' => $user->id]))
            ->assertRedirect(route('user.profile'));

        $this->assertNotAnonymized($user);
        $this->assertAuthenticated('web');
        $this->assertDatabaseHas('group_user', [
            'user_id' => $user->id,
            'group_id' => $group->id,
            'deleted_at' => null,
        ]);
    }

    public function test_an_eligible_user_can_still_delete_their_data(): void
    {
        $user = $this->createUser(['email' => 'allowed-request@example.test']);
        $group = $this->createGroup(['name' => 'Ellátott csoport']);
        $this->attachUserToGroup($user, $group, 'admin');
        $successor = $this->createUser(['email' => 'stays@example.test']);
        $this->attachUserToGroup($successor, $group, 'admin');

        $this->actingAs($user)
            ->get($this->signedRoute('user.deletepersonaldata', ['id' => $user->id]))
            ->assertRedirect('login');

        $this->assertAnonymized($user);
        $this->assertGuest();
    }

    // =========================================================================
    // The rule's source: the same one leaving a group uses
    // =========================================================================

    public function test_the_rule_uses_the_same_helper_as_leaving_a_group(): void
    {
        // If the two drift apart, the user ends up in a state where they cannot
        // leave the group but can delete their data (or the other way around).
        // That is why the same helper decides in both places.
        $user = $this->inactiveUser('same-rule@example.test');
        $group = $this->createGroup(['name' => 'Közös szabály']);
        $this->attachUserToGroup($user, $group, 'admin');

        $this->assertFalse(
            pwbs_check_group_other_admins($group->id, $user->id),
            'A csoportelhagyás is tiltaná.'
        );

        $user->anonymize();
        $this->assertNotAnonymized($user);

        $successor = $this->createUser(['email' => 'unblocks-both@example.test']);
        $this->attachUserToGroup($successor, $group, 'admin');

        $this->assertTrue(
            pwbs_check_group_other_admins($group->id, $user->id),
            'A csoportelhagyás is engedné.'
        );

        $user->fresh()->anonymize();
        $this->assertAnonymized($user);
    }

    public function test_a_withdrawn_membership_no_longer_blocks(): void
    {
        // A soft-deleted membership is not membership: someone who has already
        // left the group has nothing to hand over.
        $user = $this->inactiveUser('withdrawn@example.test');
        $group = $this->createGroup(['name' => 'Elhagyott csoport']);
        $this->attachUserToGroup($user, $group, 'admin');

        GroupUser::where('user_id', $user->id)->where('group_id', $group->id)->delete();

        $user->fresh()->anonymize();

        $this->assertAnonymized($user);
    }

    public function test_the_blocked_user_stays_in_the_batch_for_the_next_run(): void
    {
        // Blocking does not finalize anything: as soon as a successor arrives,
        // the next run performs the anonymization. This matters because a GDPR
        // request does not go away just because it cannot be fulfilled today.
        $user = $this->inactiveUser('deferred@example.test');
        $group = $this->createGroup(['name' => 'Halasztott']);
        $this->attachUserToGroup($user, $group, 'admin');

        $this->artisan('gdpr:anonymize-inactive');
        $this->assertNotAnonymized($user);

        $successor = $this->createUser(['email' => 'arrives-later@example.test']);
        $this->attachUserToGroup($successor, $group, 'admin');

        $this->artisan('gdpr:anonymize-inactive');
        $this->assertAnonymized($user);
    }

    public function test_the_command_reports_the_skipped_users(): void
    {
        $blocked = $this->inactiveUser('reported@example.test');
        $group = $this->createGroup(['name' => 'Jelentett csoport']);
        $this->attachUserToGroup($blocked, $group, 'admin');

        $this->artisan('gdpr:anonymize-inactive')
            ->expectsOutput('Anonymized 0 inactive user(s).')
            ->expectsOutput('Skipped 1 user(s) with no successor.')
            ->assertExitCode(0);
    }

    // =========================================================================
    // The succession condition from the Group relation
    // =========================================================================

    public function test_the_active_admins_relation_filters_anonymized_and_pending(): void
    {
        $group = $this->createGroup(['name' => 'Szűrt adminok']);

        $active = $this->createUser(['email' => 'active-admin@example.test']);
        $this->attachUserToGroup($active, $group, 'admin');

        $anonymized = $this->createUser(['email' => 'anonymized-admin@example.test', 'isAnonymized' => 1]);
        $this->attachUserToGroup($anonymized, $group, 'admin');

        $pending = $this->createUser(['email' => 'pending-admin@example.test']);
        $this->attachUserToGroup($pending, $group, 'admin', false);

        $ids = Group::find($group->id)->activeAdmins()->pluck('users.id')->all();

        $this->assertContains($active->id, $ids);
        $this->assertNotContains($anonymized->id, $ids, 'Anonimizált admin nem aktív admin.');
        $this->assertNotContains($pending->id, $ids, 'Függő meghívás nem aktív admin.');

        // groupAdmins() itself does NOT change: its ten other call sites check
        // the caller's own permission (wherePivot('user_id', Auth::id())).
        $this->assertCount(3, Group::find($group->id)->groupAdmins()->get());
    }
}
