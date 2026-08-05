<?php

namespace Tests\Feature\Auth;

use App\Models\Group;
use App\Models\GroupUser;
use App\Models\User;
use Illuminate\Support\Facades\Gate;
use Tests\Feature\FeatureTestCase;

/**
 * TODO 07.2: az AuthServiceProvider öt gate closure-je.
 *
 * Ma csak az is-admin és az is-translator van érintve, azok is közvetve, a
 * RouteMiddlewareRegressionTest route-jain keresztül. Az is-groupcreator,
 * is-groupservant és is-groupadmin closure-jei - amelyek a csoportkezelés
 * teljes jogosultsági felületét adják - lefedetlenek.
 *
 * A gate-ek a Livewire komponenseken kívülről is hívhatók (Gate::allows a
 * ListGroups::createGroup()-ban, @can a nézetekben, can: middleware a
 * route-okon), ezért a closure-öket közvetlenül mérjük.
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
        // A harmadik ág a meglepő: a translator szerep is csoportot hozhat
        // létre, pedig a neve fordítási jogosultságot sugall
        // (AuthServiceProvider.php:37-41). Ma semmi nem védi.
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
        // Az is-groupcreator kizárólag a users.role oszlopból dolgozik: hiába
        // adminisztrátor valaki egy csoportban, új csoportot nem hozhat létre.
        $group = $this->createGroup();
        $user = $this->userWithRole('activated', 'gate-groupadmin-only@example.test');
        $this->attachUserToGroup($user, $group, 'admin');

        $this->assertFalse($this->allows($user->fresh(), 'is-groupcreator'));
    }

    // =========================================================================
    // is-groupservant - userGroupsEditable(): admin vagy roler tagság
    // =========================================================================

    public function test_group_servant_gate_is_granted_by_global_role_without_any_membership(): void
    {
        $this->assertTrue($this->allows($this->userWithRole('mainAdmin', 'gs-ma@example.test'), 'is-groupservant'));
        $this->assertTrue($this->allows($this->userWithRole('translator', 'gs-tr@example.test'), 'is-groupservant'));
    }

    public function test_a_group_creator_without_membership_is_not_a_group_servant(): void
    {
        // A groupCreator NEM szerepel az is-groupservant globális ágában -
        // csak a mainAdmin és a translator. Enélkül tagságra van szüksége.
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
        // userGroupsEditable() wherePivotNotNull('accepted_at') - egy még el
        // nem fogadott meghívás nem ad jogosultságot.
        $group = $this->createGroup();
        $user = $this->userWithRole('activated', 'gs-pending@example.test');
        $this->attachUserToGroup($user, $group, 'admin', false);

        $this->assertFalse($this->allows($user->fresh(), 'is-groupservant'));
    }

    public function test_a_withdrawn_membership_does_not_grant_the_group_servant_gate(): void
    {
        // userGroupsEditable() wherePivot('deleted_at', null) - a csoportból
        // kilépett adminisztrátor elveszíti a jogosultságot.
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
    // is-groupadmin - userGroupsDeletable(): csak admin tagság
    // =========================================================================

    public function test_a_roler_is_a_group_servant_but_not_a_group_admin(): void
    {
        // Ez a két gate közti egyetlen érdemi különbség: az is-groupservant
        // az ['admin','roler'] halmazt nézi, az is-groupadmin csak az
        // 'admin'-t. A helpers.php a hírlevél-célzáshoz mindkettőt használja.
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
        // A closure a User példányt adja vissza (AuthServiceProvider.php:29-31),
        // nem bool-t; a Gate a truthy kiértékelésre támaszkodik. Ugyanez a
        // mintázat a hasRole()-ban is: true VAGY null a visszatérési érték,
        // sosem false (User.php:132-134).
        $user = $this->userWithRole('registered', 'gate-loggedin@example.test');

        $this->assertTrue($this->allows($user, 'logged-in'));
        $this->assertNull($user->hasRole('mainAdmin'));
        $this->assertTrue($user->hasRole('registered'));
    }

    // =========================================================================
    // A gate-nevek kis-nagybetű érzékenysége - látens hiba
    // =========================================================================

    public function test_a_group_creator_never_receives_group_creator_newsletters(): void
    {
        // KARAKTERIZÁLÓ TESZT egy éles hatású hibáról.
        //
        // A helpers.php:68 can('is-groupCreator')-t kér, a gate viszont
        // 'is-groupcreator' néven van definiálva (AuthServiceProvider.php:37).
        // A Laravel a gate-eket kulcs szerinti tömbben tartja, tehát a nevek
        // kis-nagybetű érzékenyek: ez a feltétel MINDIG hamis.
        //
        // Következmény: a 'groupCreators'-nek célzott hírlevelek csak a
        // mainAdmin-hoz jutnak el, az is-admin ág miatt. Érintett:
        // Admin\AdminNewsletters, Partials\NavBar, Partials\SideMenu.
        //
        // Javítás: roadmap TODO 33. Itt szándékosan a hibás viselkedést
        // rögzítjük, hogy az upgrade ismert alapról induljon.
        $creator = $this->userWithRole('groupCreator', 'nl-gc@example.test');
        $this->actingAs($creator);

        $this->assertNotContains('groupCreators', pwbs_get_newsletter_roles());
    }

    public function test_a_main_admin_does_receive_group_creator_newsletters(): void
    {
        // A mainAdmin csak azért kapja meg, mert az is-admin ág átviszi -
        // ez bizonyítja, hogy az elírt gate-név az egyetlen ok a fenti
        // teszt eredményére.
        $admin = $this->userWithRole('mainAdmin', 'nl-ma@example.test');
        $this->actingAs($admin);

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
    // A gate-ek relációt olvasnak, nem lekérdezést - cache-elési kockázat
    // =========================================================================

    public function test_the_group_gates_read_a_lazily_loaded_relation(): void
    {
        // Az is-groupservant a $user->userGroupsEditable dinamikus property-t
        // olvassa, nem a ->userGroupsEditable() lekérdezést. A reláció az
        // első olvasáskor cache-elődik a modellen, tehát a kérésen belül
        // létrejövő új tagságot NEM veszi észre, amíg a modellt nem frissítik.
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
