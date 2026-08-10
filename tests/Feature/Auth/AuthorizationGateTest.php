<?php

namespace Tests\Feature\Auth;

use App\Models\Group;
use App\Models\GroupUser;
use App\Models\User;
use Illuminate\Support\Facades\Gate;
use Tests\Feature\FeatureTestCase;

/**
 * TODO 07.2: the AuthServiceProvider's five gate closures.
 *
 * Today only is-admin and is-translator are touched, and even those
 * indirectly, through RouteMiddlewareRegressionTest's routes. The
 * is-groupcreator, is-groupservant, and is-groupadmin closures - which
 * provide the entire authorization surface for group management - are
 * uncovered.
 *
 * The gates can also be called from outside the Livewire components
 * (Gate::allows in ListGroups::createGroup(), @can in the views, can:
 * middleware on the routes), so we measure the closures directly.
 */
class AuthorizationGateTest extends FeatureTestCase
{
    private function allows(User $user, string $ability): bool
    {
        return Gate::forUser($user)->allows($ability);
    }

    private function userWithRole(string $role, string $email): User
    {
        return $this->createUser(['role' => $role, 'email' => $email]);
    }

    // =========================================================================
    // is-groupcreator
    // =========================================================================

    public function test_group_creator_gate_is_granted_to_main_admin_group_creator_and_translator(): void
    {
        // The third branch is the surprising one: the translator role can also
        // create a group, even though its name suggests translation
        // permissions (AuthServiceProvider.php:37-41). Today nothing guards
        // against this.
        $this->assertTrue($this->allows($this->userWithRole('mainAdmin', 'gate-ma@example.test'), 'is-groupcreator'));
        $this->assertTrue($this->allows($this->userWithRole('groupCreator', 'gate-gc@example.test'), 'is-groupcreator'));
        $this->assertTrue($this->allows($this->userWithRole('translator', 'gate-tr@example.test'), 'is-groupcreator'));
    }

    public function test_group_creator_gate_is_denied_to_ordinary_roles(): void
    {
        $this->assertFalse($this->allows($this->userWithRole('activated', 'gate-act@example.test'), 'is-groupcreator'));
        $this->assertFalse($this->allows($this->userWithRole('registered', 'gate-reg@example.test'), 'is-groupcreator'));
    }

    public function test_a_group_membership_does_not_grant_the_group_creator_gate(): void
    {
        // is-groupcreator works exclusively off the users.role column: it
        // makes no difference that someone is an admin within a group, they
        // still cannot create a new group.
        $group = $this->createGroup();
        $user = $this->userWithRole('activated', 'gate-groupadmin-only@example.test');
        $this->attachUserToGroup($user, $group, 'admin');

        $this->assertFalse($this->allows($user->fresh(), 'is-groupcreator'));
    }

    // =========================================================================
    // is-groupservant - userGroupsEditable(): admin or roler membership
    // =========================================================================

    public function test_group_servant_gate_is_granted_by_global_role_without_any_membership(): void
    {
        $this->assertTrue($this->allows($this->userWithRole('mainAdmin', 'gs-ma@example.test'), 'is-groupservant'));
        $this->assertTrue($this->allows($this->userWithRole('translator', 'gs-tr@example.test'), 'is-groupservant'));
    }

    public function test_a_group_creator_without_membership_is_not_a_group_servant(): void
    {
        // groupCreator is NOT part of is-groupservant's global branch - only
        // mainAdmin and translator are. Without that, it needs membership.
        $this->assertFalse($this->allows($this->userWithRole('groupCreator', 'gs-gc@example.test'), 'is-groupservant'));
    }

    /**
     * @dataProvider groupServantMembershipProvider
     */
    public function test_group_servant_gate_depends_on_the_membership_role(string $role, bool $expected): void
    {
        $group = $this->createGroup();
        $user = $this->userWithRole('activated', 'gs-role-'.$role.'@example.test');
        $this->attachUserToGroup($user, $group, $role);

        $this->assertSame($expected, $this->allows($user->fresh(), 'is-groupservant'));
    }

    public function groupServantMembershipProvider(): array
    {
        return [
            'member' => ['member', false],
            'helper' => ['helper', false],
            'roler'  => ['roler', true],
            'admin'  => ['admin', true],
        ];
    }

    public function test_a_pending_membership_does_not_grant_the_group_servant_gate(): void
    {
        // userGroupsEditable() wherePivotNotNull('accepted_at') - an
        // invitation that has not yet been accepted does not grant permission.
        $group = $this->createGroup();
        $user = $this->userWithRole('activated', 'gs-pending@example.test');
        $this->attachUserToGroup($user, $group, 'admin', false);

        $this->assertFalse($this->allows($user->fresh(), 'is-groupservant'));
    }

    public function test_a_withdrawn_membership_does_not_grant_the_group_servant_gate(): void
    {
        // userGroupsEditable() wherePivot('deleted_at', null) - an admin who
        // has left the group loses the permission.
        $group = $this->createGroup();
        $user = $this->userWithRole('activated', 'gs-withdrawn@example.test');

        GroupUser::factory()
            ->forUser($user)
            ->forGroup($group)
            ->asGroupAdmin()
            ->accepted()
            ->withdrawn()
            ->create();

        $this->assertFalse($this->allows($user->fresh(), 'is-groupservant'));
    }

    // =========================================================================
    // is-groupadmin - userGroupsDeletable(): admin membership only
    // =========================================================================

    public function test_a_roler_is_a_group_servant_but_not_a_group_admin(): void
    {
        // This is the only substantive difference between the two gates:
        // is-groupservant looks at the ['admin','roler'] set, is-groupadmin
        // only at 'admin'. helpers.php uses both for newsletter targeting.
        $group = $this->createGroup();
        $roler = $this->userWithRole('activated', 'ga-roler@example.test');
        $this->attachUserToGroup($roler, $group, 'roler');

        $roler = $roler->fresh();
        $this->assertTrue($this->allows($roler, 'is-groupservant'));
        $this->assertFalse($this->allows($roler, 'is-groupadmin'));
    }

    public function test_a_group_admin_membership_grants_the_group_admin_gate(): void
    {
        $group = $this->createGroup();
        $admin = $this->userWithRole('activated', 'ga-admin@example.test');
        $this->attachUserToGroup($admin, $group, 'admin');

        $this->assertTrue($this->allows($admin->fresh(), 'is-groupadmin'));
    }

    public function test_group_admin_gate_is_granted_by_global_role_without_any_membership(): void
    {
        $this->assertTrue($this->allows($this->userWithRole('mainAdmin', 'ga-ma@example.test'), 'is-groupadmin'));
        $this->assertTrue($this->allows($this->userWithRole('translator', 'ga-tr@example.test'), 'is-groupadmin'));
    }

    // =========================================================================
    // is-admin, is-translator, logged-in
    // =========================================================================

    public function test_admin_and_translator_gates_separate_the_two_global_roles(): void
    {
        $admin = $this->userWithRole('mainAdmin', 'gate-adm@example.test');
        $translator = $this->userWithRole('translator', 'gate-tra@example.test');

        $this->assertTrue($this->allows($admin, 'is-admin'));
        $this->assertTrue($this->allows($admin, 'is-translator'));

        $this->assertFalse($this->allows($translator, 'is-admin'));
        $this->assertTrue($this->allows($translator, 'is-translator'));
    }

    public function test_the_logged_in_gate_returns_the_user_instance_rather_than_a_boolean(): void
    {
        // The closure returns the User instance (AuthServiceProvider.php:29-31),
        // not a bool; the Gate relies on truthy evaluation. The same pattern
        // appears in hasRole() too: the return value is true OR null, never
        // false (User.php:132-134).
        $user = $this->userWithRole('registered', 'gate-loggedin@example.test');

        $this->assertTrue($this->allows($user, 'logged-in'));
        $this->assertNull($user->hasRole('mainAdmin'));
        $this->assertTrue($user->hasRole('registered'));
    }

    // =========================================================================
    // Case sensitivity of gate names - a latent bug
    // =========================================================================

    public function test_a_group_creator_receives_group_creator_newsletters(): void
    {
        // REVERSED by the v1-patch D1 fix (TODO 33), with the user's explicit
        // approval, because this changes production behavior.
        //
        // helpers.php requested can('is-groupCreator'), but the gate is
        // defined under the name 'is-groupcreator' (AuthServiceProvider.php:37).
        // Laravel keeps gates in a key-indexed array, so the names are
        // case-sensitive: the condition was ALWAYS false, and newsletters
        // targeted at 'groupCreators' only reached the mainAdmin, via the
        // is-admin branch. Affected: Admin\AdminNewsletters,
        // Partials\NavBar, Partials\SideMenu.
        $creator = $this->userWithRole('groupCreator', 'nl-gc@example.test');
        $this->actingAs($creator);

        $this->assertContains('groupCreators', pwbs_get_newsletter_roles());
    }

    public function test_a_main_admin_does_receive_group_creator_newsletters(): void
    {
        // mainAdmin received it even before the fix, but via the is-admin
        // branch. Now both branches carry it through - the test is still
        // valuable because mainAdmin must keep receiving it even if the
        // is-groupcreator gate's definition is ever narrowed.
        $admin = $this->userWithRole('mainAdmin', 'nl-ma@example.test');
        $this->actingAs($admin);

        $this->assertContains('groupCreators', pwbs_get_newsletter_roles());
    }

    public function test_a_translator_also_receives_group_creator_newsletters(): void
    {
        // The D1 fix's SECOND, less obvious consequence: the is-groupcreator
        // gate (AuthServiceProvider.php:37-41) admits the translator role too,
        // alongside mainAdmin and groupCreator. As long as helpers.php
        // requested the misspelled name, this effect did not exist; now it
        // does. This is intentional - the gate's definition is the authority
        // contract, not the typo - but recorded because a newsletter recipient
        // list expanding without explanation would be a surprise.
        $translator = $this->userWithRole('translator', 'nl-tr@example.test');
        $this->actingAs($translator);

        $this->assertContains('groupCreators', pwbs_get_newsletter_roles());
    }

    public function test_newsletter_roles_reflect_the_group_servant_and_group_admin_gates(): void
    {
        $group = $this->createGroup();
        $roler = $this->userWithRole('activated', 'nl-roler@example.test');
        $this->attachUserToGroup($roler, $group, 'roler');

        $this->actingAs($roler->fresh());
        $roles = pwbs_get_newsletter_roles();

        $this->assertContains('groupServants', $roles);
        $this->assertNotContains('groupAdmins', $roles);
        $this->assertNotContains('groupCreators', $roles);
    }

    public function test_a_plain_member_gets_no_newsletter_roles_at_all(): void
    {
        $group = $this->createGroup();
        $member = $this->userWithRole('activated', 'nl-member@example.test');
        $this->attachUserToGroup($member, $group, 'member');

        $this->actingAs($member->fresh());

        $this->assertSame([], pwbs_get_newsletter_roles());
    }

    public function test_a_group_admin_gets_both_servant_and_admin_newsletter_roles(): void
    {
        $group = $this->createGroup();
        $admin = $this->userWithRole('activated', 'nl-gadmin@example.test');
        $this->attachUserToGroup($admin, $group, 'admin');

        $this->actingAs($admin->fresh());

        $this->assertSame(['groupServants', 'groupAdmins'], pwbs_get_newsletter_roles());
    }

    // =========================================================================
    // The gates read a relation, not a query - a caching risk
    // =========================================================================

    public function test_the_group_gates_read_a_lazily_loaded_relation(): void
    {
        // is-groupservant reads the $user->userGroupsEditable dynamic
        // property, not the ->userGroupsEditable() query. The relation gets
        // cached on the model on first read, so it does NOT notice new
        // membership created within the request until the model is refreshed.
        $group = $this->createGroup();
        $user = $this->userWithRole('activated', 'gate-cache@example.test');

        $this->assertFalse($this->allows($user, 'is-groupservant'));

        $this->attachUserToGroup($user, $group, 'admin');

        $this->assertFalse(
            $this->allows($user, 'is-groupservant'),
            'A betöltött reláció miatt ugyanaz a modellpéldány még nem látja az új tagságot.'
        );
        $this->assertTrue(
            $this->allows($user->fresh(), 'is-groupservant'),
            'Friss modellpéldánnyal viszont már igen.'
        );
    }
}
