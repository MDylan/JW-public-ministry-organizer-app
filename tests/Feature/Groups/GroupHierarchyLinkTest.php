<?php

namespace Tests\Feature\Groups;

use App\Http\Livewire\Groups\ListUsers;
use App\Models\Group;
use App\Models\LogHistory;
use App\Models\User;
use App\Notifications\GroupParentGroupAttachedNotification;
use App\Notifications\GroupParentGroupDetachedNotification;
use App\Notifications\GroupUserAddedNotification;
use App\Notifications\GroupUserLogoutNotification;
use Illuminate\Support\Facades\Notification;
use Livewire\Livewire;
use Tests\Feature\FeatureTestCase;

/**
 * TODO 11: the parent-child group relationship.
 *
 * GroupParentGroupAttachedNotification and
 * GroupParentGroupDetachedNotification were the last two notifications we
 * only measured at the mail-contract level. Both are triggered by
 * Groups\ListUsers - and along with them comes logic that was until now
 * completely uncovered: linkToGroup(), besides establishing the
 * relationship, RE-SYNCHRONIZES the child group's membership to the
 * parent's (:585-610).
 *
 * sync() means a full replacement: anyone not in the parent is also removed
 * from the child group. This is the TODO's most important, so-far-nowhere
 * documented behaviour.
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

        // linkToGroup() expects two separate authorizations: the 403 guard
        // checks admin status on the SOURCE group (:504), while
        // user_admin_groups()'s filter checks it on the TARGET group (:523).
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
    // 1. Establishing the relationship
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
        // The whereNotNull('email_verified_at') filter at :593-595 and
        // :600-602: the membership is created, the mail is not.
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
        // The sync() at :585 performs a FULL replacement of the parent's
        // member list, so anyone who was only in the child group gets
        // removed from it - and GroupUserMoves::detach() also notifies them
        // about it. Linking the groups is thus also a membership operation,
        // not just an administrative link.
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
    // 2. The five validation branches - each stops silently, with no notification
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
        // REVERSED by the v1-patch B8 fix.
        //
        // user_admin_groups() already filters out child groups
        // (whereNull('groups.parent_group_id')), so for this case TWO error
        // messages ended up in the error bag: error_not_in_group AND
        // error_this_is_child. The user therefore also read that "you are
        // not an administrator in it," even though they in fact are - the
        // group is just already linked to something else.
        //
        // Now a single message naming the real reason is shown.
        Notification::fake();

        $grandParent = $this->createGroup(['name' => 'Nagyszülő csoport']);
        $alreadyChild = $this->createChildGroup($grandParent, ['name' => 'Már gyerek']);
        $this->attachUserToGroup($this->actor, $alreadyChild, 'admin');

        $component = $this->link($alreadyChild->id);
        $component->assertHasErrors('parent_group_id');

        $this->assertSame(
            [__('group.link.error_this_is_child')],
            $component->instance()->getErrorBag()->get('parent_group_id'),
            'Pontosan egy üzenet, és az a valódi ok.'
        );

        $this->assertNull($this->child->fresh()->parent_group_id);
        Notification::assertNothingSent();
    }

    public function test_a_group_the_actor_does_not_administer_still_says_so(): void
    {
        // B8's control experiment: the other branch's message must not
        // disappear. For a foreign TOP-LEVEL group, "you are not an
        // administrator in it" remains the correct and only reason given.
        Notification::fake();

        $foreign = $this->createGroup(['name' => 'Idegen csoport']);

        $component = $this->link($foreign->id);
        $component->assertHasErrors('parent_group_id');

        $this->assertSame(
            [__('group.link.error_not_in_group')],
            $component->instance()->getErrorBag()->get('parent_group_id')
        );

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
    // 3. Breaking the relationship - the two directions are not symmetric
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
        // The $data array is built BEFORE the update (:697-703), so the mail
        // still contains the name of the abandoned parent group. TODO 11.1
        // moved it below the authorization guards, but deliberately kept it
        // above the update().
        $this->linkedChild();
        Notification::fake();

        // The mail renders userName (line_4), so we also measure it in the
        // payload: without this, a null-safe "fix" (auth()->user()?->name)
        // would leave the suite green while an empty name went out in the
        // mail - exactly the bug TODO 10 found in
        // EventObserver::deleted().
        $actorName = $this->actor->name;
        $this->assertNotEmpty(
            $actorName,
            'A fixture neve nem lehet üres, különben az alábbi állítás vákuum.'
        );

        $this->listUsers()
            ->call('confirmParentDetach', $this->parent->id)
            ->call('detachParentGroup');

        Notification::assertSentTo(
            $this->actor,
            GroupParentGroupDetachedNotification::class,
            function ($notification) use ($actorName) {
                $property = new \ReflectionProperty($notification, 'data');
                $property->setAccessible(true);
                $payload = $property->getValue($notification);

                return $payload['groupName'] === 'Szülő csoport'
                    && $payload['childGroupName'] === 'Gyerek csoport'
                    && $payload['userName'] === $actorName;
            }
        );
    }

    public function test_detaching_the_same_link_from_the_parent_side_notifies_the_same_people(): void
    {
        // REVERSED by the v1-patch B6 fix.
        //
        // detachChildGroup() breaks the same relationship, just from the
        // other side - and previously it sent NOT A SINGLE notification,
        // while detachParentGroup() does. So those affected got informed or
        // not depending on which screen someone used to make the change.
        //
        // The payload shape is deliberately identical to the parent-side
        // branch: groupName is the parent, childGroupName is the child,
        // userName is the actor.
        $this->linkedChild();
        $actorName = $this->actor->name;
        Notification::fake();

        $this->listUsers(null, $this->parent)
            ->call('confirmChildDetach', $this->child->id)
            ->call('detachChildGroup')
            ->assertHasNoErrors();

        $this->assertNull(
            $this->child->fresh()->parent_group_id,
            'A kapcsolat ugyanúgy megszűnik.'
        );

        Notification::assertSentTo(
            $this->actor,
            GroupParentGroupDetachedNotification::class,
            function ($notification) use ($actorName) {
                $property = new \ReflectionProperty($notification, 'data');
                $property->setAccessible(true);
                $payload = $property->getValue($notification);

                return $payload['groupName'] === 'Szülő csoport'
                    && $payload['childGroupName'] === 'Gyerek csoport'
                    && $payload['userName'] === $actorName;
            }
        );
    }

    public function test_detaching_a_child_group_writes_an_audit_record(): void
    {
        // The other half of B6: the previous mass ->childGroups()->update()
        // bypassed the Eloquent events, so the GroupObserver log entry was
        // also skipped. THIS SAME relationship, broken from the parent side,
        // was logged.
        $this->linkedChild();

        $before = LogHistory::where('group_id', $this->child->id)->count();

        $this->listUsers(null, $this->parent)
            ->call('confirmChildDetach', $this->child->id)
            ->call('detachChildGroup')
            ->assertHasNoErrors();

        $this->assertGreaterThan(
            $before,
            LogHistory::where('group_id', $this->child->id)->count(),
            'A lecsatolásnak nyoma kell maradjon a naplóban.'
        );
    }

    public function test_detaching_an_unknown_child_group_is_forbidden_not_fatal(): void
    {
        // The third part of B6: the ->first() can also be null, and the
        // previous `!$selected_group->id` in that case called a property on
        // NULL, so a fatal came instead of a 403 - the same class of bug that
        // TODO 11.1 fixed for detachParentGroup().
        $this->linkedChild();

        $this->listUsers(null, $this->parent)
            ->set('detachId', 999999)
            ->call('detachChildGroup')
            ->assertForbidden();
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

    public function test_an_unauthenticated_call_is_rejected_with_403_not_a_fatal_error(): void
    {
        // TODO 11.1: the $data array used to be built BEFORE the
        // authorization guard, and read auth()->user()->name - so without a
        // logged-in user an ErrorException (500) came where 403 is the
        // correct response.
        //
        // Same bug family as the getRole() fixed in TODO 10: a missing
        // precondition check at the start of the call chain. Every sibling
        // method of the class - detachChildGroup() (:642),
        // confirmParentDetach() (:663), setCopyInfo() (:845) - starts with
        // this same guard.
        //
        // The groupMember middleware is present on the route, so this is not
        // an active security hole. The path reachable in production: the
        // session is alive, but the user is not logged in (logged out in
        // another tab), and the method is in the $listeners array (:59-68),
        // so it can also be called standalone on the Livewire message
        // endpoint.
        $this->linkedChild();

        $component = $this->listUsers()
            ->call('confirmParentDetach', $this->parent->id);

        Notification::fake();

        // Livewire::actingAs() only calls auth()->guard()->setUser(), so
        // forgetting the guards restores the guest state.
        $this->app['auth']->forgetGuards();

        $component->call('detachParentGroup')->assertForbidden();

        $this->assertNotNull(
            $this->child->fresh()->parent_group_id,
            'A kapcsolat nem bomolhat el hitelesítés nélkül.'
        );
        Notification::assertNothingSent();
    }
}
