<?php

namespace Tests\Feature\Groups;

use App\Http\Livewire\Groups\ListUsers;
use App\Models\Group;
use App\Models\User;
use App\Notifications\GroupParentGroupAttachedNotification;
use App\Notifications\GroupParentGroupDetachedNotification;
use App\Notifications\GroupUserAddedNotification;
use App\Notifications\GroupUserLogoutNotification;
use Illuminate\Support\Facades\Notification;
use Livewire\Livewire;
use Tests\Feature\FeatureTestCase;

/**
 * TODO 11: a szülő-gyerek csoportkapcsolat.
 *
 * A GroupParentGroupAttachedNotification és a
 * GroupParentGroupDetachedNotification volt az utolsó két értesítés, amit
 * csak a levélszerződés szintjén mértünk. Mindkettőt a Groups\ListUsers
 * váltja ki - és velük együtt jön be az a logika, ami eddig teljesen
 * fedetlen volt: a linkToGroup() a kapcsolat felvétele mellett
 * ÁTSZINKRONIZÁLJA a gyerekcsoport tagságát a szülőéhez (:585-610).
 *
 * A sync() teljes cserét jelent: aki a szülőben nincs benne, az kikerül a
 * gyerekcsoportból is. Ez a TODO leglényegesebb, eddig sehol le nem írt
 * viselkedése.
 */
class GroupHierarchyLinkTest extends FeatureTestCase
{
    private Group $parent;
    private Group $child;
    private User $actor;

    protected function setUp(): void
    {
        parent::setUp();

        $this->parent = $this->createGroup(['name' => 'Szülő csoport']);
        $this->child = $this->createGroup(['name' => 'Gyerek csoport']);

        // A linkToGroup() két külön jogosultságot vár: a 403-őr a FORRÁS
        // csoport adminságát nézi (:504), a user_admin_groups() szűrője
        // pedig a CÉL csoportét (:523).
        $this->actor = $this->createUser(['email' => 'link-actor@example.test']);
        $this->attachUserToGroup($this->actor, $this->parent, 'admin');
        $this->attachUserToGroup($this->actor, $this->child, 'admin');
    }

    private function listUsers(?User $user = null, ?Group $group = null)
    {
        return Livewire::actingAs($user ?? $this->actor)
            ->test(ListUsers::class, ['group' => ($group ?? $this->child)->id]);
    }

    private function link($targetId, ?User $user = null, ?Group $group = null)
    {
        return $this->listUsers($user, $group)
            ->set('new_parent_group_id', $targetId)
            ->call('linkToGroup');
    }

    private function memberOf(Group $group, string $email, string $role = 'member', array $attributes = []): User
    {
        $user = $this->createUser(array_merge(['email' => $email], $attributes));
        $this->attachUserToGroup($user, $group, $role);

        return $user->fresh();
    }

    // =========================================================================
    // 1. A kapcsolat felvétele
    // =========================================================================

    public function test_linking_sets_the_parent_and_notifies_the_child_group_admins(): void
    {
        Notification::fake();

        $coAdmin = $this->memberOf($this->child, 'link-coadmin@example.test', 'admin');
        $this->attachUserToGroup($coAdmin, $this->parent, 'member');

        $this->link($this->parent->id)->assertHasNoErrors();

        $this->assertSame(
            $this->parent->id,
            $this->child->fresh()->parent_group_id,
            'A parent_group_id beáll.'
        );

        Notification::assertSentTo($this->actor, GroupParentGroupAttachedNotification::class);
        Notification::assertSentTo($coAdmin, GroupParentGroupAttachedNotification::class);
    }

    public function test_the_attach_notification_names_both_groups_and_the_acting_admin(): void
    {
        Notification::fake();

        $this->link($this->parent->id)->assertHasNoErrors();

        Notification::assertSentTo(
            $this->actor,
            GroupParentGroupAttachedNotification::class,
            function ($notification) {
                $property = new \ReflectionProperty($notification, 'data');
                $property->setAccessible(true);
                $payload = $property->getValue($notification);

                return $payload['groupName'] === 'Szülő csoport'
                    && $payload['childGroupName'] === 'Gyerek csoport'
                    && $payload['userName'] === $this->actor->name;
            }
        );
    }

    public function test_linking_pulls_the_parent_members_into_the_child_group(): void
    {
        Notification::fake();

        $fromParent = $this->memberOf($this->parent, 'link-parent-member@example.test');

        $this->link($this->parent->id)->assertHasNoErrors();

        $this->assertNotNull(
            $this->child->groupUsers()->where('user_id', $fromParent->id)->first(),
            'A szülő tagja bekerül a gyerekcsoportba.'
        );

        Notification::assertSentTo($fromParent, GroupUserAddedNotification::class);
    }

    public function test_an_unverified_parent_member_is_attached_but_not_notified(): void
    {
        // A :593-595 és :600-602 whereNotNull('email_verified_at') szűrője:
        // a tagság megjön, a levél nem.
        Notification::fake();

        $guest = $this->memberOf($this->parent, 'link-guest@example.test', 'member', [
            'email_verified_at' => null,
        ]);

        $this->link($this->parent->id)->assertHasNoErrors();

        $this->assertNotNull(
            $this->child->groupUsers()->where('user_id', $guest->id)->first()
        );
        Notification::assertNotSentTo($guest, GroupUserAddedNotification::class);
    }

    public function test_a_child_only_member_is_thrown_out_by_the_membership_sync(): void
    {
        // A :585 sync()-je TELJES cserét végez a szülő taglistájára, tehát
        // aki csak a gyerekcsoportban volt benne, azt kilépteti - és a
        // GroupUserMoves::detach() ki is értesíti róla. A csoportok
        // összekapcsolása így egy tagsági művelet is, nem csak egy
        // adminisztratív link.
        Notification::fake();

        $childOnly = $this->memberOf($this->child, 'link-childonly@example.test');

        $this->link($this->parent->id)->assertHasNoErrors();

        $this->assertNull(
            $this->child->groupUsers()->where('user_id', $childOnly->id)->first(),
            'A csak-gyerekcsoportos tag kikerül.'
        );
        Notification::assertSentTo($childOnly, GroupUserLogoutNotification::class);
    }

    public function test_linking_copies_the_signs_from_the_parent(): void
    {
        Notification::fake();

        $this->parent->update(['signs' => ['car' => true]]);

        $this->link($this->parent->id)->assertHasNoErrors();

        $this->assertSame(['car' => true], $this->child->fresh()->signs);
        $this->assertSame(
            ['signs' => false],
            $this->child->fresh()->copy_from_parent,
            'A copy_from_parent a render() által feltöltött copy_fields-ből épül.'
        );
    }

    // =========================================================================
    // 2. Az öt validációs ág - mindegyik némán, értesítés nélkül áll meg
    // =========================================================================

    public function test_linking_without_a_selection_fails(): void
    {
        Notification::fake();

        $this->link(0)->assertHasErrors('parent_group_id');

        $this->assertNull($this->child->fresh()->parent_group_id);
        Notification::assertNothingSent();
    }

    public function test_a_group_cannot_be_linked_to_itself(): void
    {
        Notification::fake();

        $this->link($this->child->id)->assertHasErrors('parent_group_id');

        $this->assertNull($this->child->fresh()->parent_group_id);
        Notification::assertNothingSent();
    }

    public function test_linking_to_a_group_where_the_actor_is_not_an_admin_fails(): void
    {
        Notification::fake();

        $foreign = $this->createGroup(['name' => 'Idegen csoport']);
        $this->attachUserToGroup($this->actor, $foreign, 'roler');

        $this->link($foreign->id)->assertHasErrors('parent_group_id');

        $this->assertNull($this->child->fresh()->parent_group_id);
        Notification::assertNothingSent();
    }

    public function test_a_child_group_cannot_be_used_as_a_parent(): void
    {
        // A user_admin_groups() eleve kiszűri a gyerekcsoportokat
        // (whereNull('groups.parent_group_id')), ezért erre az esetre KÉT
        // hibaüzenet is bekerül a hibazsákba: az error_not_in_group és az
        // error_this_is_child. A felhasználó így egy félrevezető "nem vagy
        // tagja" üzenetet is kap. Karakterizálva, nem javítva.
        Notification::fake();

        $grandParent = $this->createGroup(['name' => 'Nagyszülő csoport']);
        $alreadyChild = $this->createChildGroup($grandParent, ['name' => 'Már gyerek']);
        $this->attachUserToGroup($this->actor, $alreadyChild, 'admin');

        $this->link($alreadyChild->id)->assertHasErrors('parent_group_id');

        $this->assertNull($this->child->fresh()->parent_group_id);
        Notification::assertNothingSent();
    }

    public function test_a_group_that_already_has_children_cannot_become_a_child(): void
    {
        Notification::fake();

        $this->createChildGroup($this->child, ['name' => 'Unoka csoport']);

        $this->link($this->parent->id)->assertHasErrors('parent_group_id');

        $this->assertNull($this->child->fresh()->parent_group_id);
        Notification::assertNothingSent();
    }

    public function test_a_non_admin_cannot_link_at_all(): void
    {
        $roler = $this->memberOf($this->child, 'link-roler@example.test', 'roler');

        $this->link($this->parent->id, $roler)->assertForbidden();
    }

    // =========================================================================
    // 3. A kapcsolat bontása - a két irány nem szimmetrikus
    // =========================================================================

    private function linkedChild(): Group
    {
        $this->child->update([
            'parent_group_id' => $this->parent->id,
            'copy_from_parent' => ['signs' => false],
        ]);

        return $this->child->fresh();
    }

    public function test_detaching_from_the_parent_notifies_the_child_group_admins(): void
    {
        $this->linkedChild();
        Notification::fake();

        $this->listUsers()
            ->call('confirmParentDetach', $this->parent->id)
            ->call('detachParentGroup')
            ->assertHasNoErrors();

        $this->assertNull($this->child->fresh()->parent_group_id);
        Notification::assertSentTo($this->actor, GroupParentGroupDetachedNotification::class);
    }

    public function test_the_detach_notification_still_names_the_former_parent(): void
    {
        // A $data tömb a frissítés ELŐTT épül (:684-688), tehát a levélben
        // még benne van az elhagyott szülőcsoport neve.
        $this->linkedChild();
        Notification::fake();

        $this->listUsers()
            ->call('confirmParentDetach', $this->parent->id)
            ->call('detachParentGroup');

        Notification::assertSentTo(
            $this->actor,
            GroupParentGroupDetachedNotification::class,
            function ($notification) {
                $property = new \ReflectionProperty($notification, 'data');
                $property->setAccessible(true);
                $payload = $property->getValue($notification);

                return $payload['groupName'] === 'Szülő csoport'
                    && $payload['childGroupName'] === 'Gyerek csoport';
            }
        );
    }

    public function test_detaching_the_same_link_from_the_parent_side_notifies_nobody(): void
    {
        // KARAKTERIZÁLÓ TESZT, ASZIMMETRIA: a detachChildGroup() (:642)
        // ugyanazt a kapcsolatot bontja el, csak a másik oldalról - és
        // EGYETLEN értesítést sem küld. Ráadásul tömeges update()-tel
        // dolgozik (:651), tehát modell-esemény sem sül el, vagyis a
        // GroupObserver naplóbejegyzése is elmarad.
        $this->linkedChild();
        Notification::fake();

        $this->listUsers(null, $this->parent)
            ->call('confirmChildDetach', $this->child->id)
            ->call('detachChildGroup')
            ->assertHasNoErrors();

        $this->assertNull(
            $this->child->fresh()->parent_group_id,
            'A kapcsolat ugyanúgy megszűnik.'
        );
        Notification::assertNothingSent();
    }

    public function test_a_non_admin_cannot_detach_the_parent(): void
    {
        $this->linkedChild();

        $roler = $this->memberOf($this->child, 'detach-roler@example.test', 'roler');

        $this->listUsers($roler)
            ->call('confirmParentDetach', $this->parent->id)
            ->assertForbidden();
    }

    public function test_detaching_a_group_that_is_not_the_parent_is_rejected(): void
    {
        $this->linkedChild();

        $foreign = $this->createGroup(['name' => 'Idegen szülő']);

        $this->listUsers()
            ->call('confirmParentDetach', $foreign->id)
            ->assertForbidden();
    }
}
