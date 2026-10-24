<?php

namespace Tests\Feature\Livewire;

use App\Livewire\Groups\ListGroups;
use App\Models\Group;
use App\Models\GroupUser;
use App\Models\User;
use Illuminate\Support\Facades\Mail;
use Livewire\Livewire;
use Tests\Feature\FeatureTestCase;

/**
 * TODO 07: the uncovered half of Groups\ListGroups.
 *
 * The existing LivewireComponentInteractionTest covers accepting and rejecting
 * invitations plus the group-creation happy path. This file covers the rest:
 * leaving a group, deleting one, and requesting the group-creator privilege.
 *
 * The authorization boundaries around createGroup() belong to TODO 07.2.
 */
class ListGroupsTest extends FeatureTestCase
{
    private Group $group;
    private User $admin;

    protected function setUp(): void
    {
        parent::setUp();

        $this->group = $this->createGroup();
        $this->admin = $this->createUser(['email' => 'lg-admin@example.test', 'role' => 'groupCreator']);
        $this->attachUserToGroup($this->admin, $this->group, 'admin');
        $this->actingAs($this->admin);
    }

    // --- leaving the group ---

    public function test_leaving_is_refused_when_no_other_admin_remains(): void
    {
        // The pwbs_check_group_other_admins guard: the last administrator
        // cannot leave the group on its own.
        Livewire::actingAs($this->admin)
            ->test(ListGroups::class)
            ->call('confirmLogoutModal', $this->group->id)
            ->assertDispatched('sweet-error', title: __('group.logout.error'), message: __('group.logout.no_admin'))
            ->assertSet('groupBeeingLogout', null);
    }

    public function test_leaving_is_offered_when_another_admin_exists(): void
    {
        $secondAdmin = $this->createUser(['email' => 'lg-admin2@example.test']);
        $this->attachUserToGroup($secondAdmin, $this->group, 'admin');

        Livewire::actingAs($this->admin)
            ->test(ListGroups::class)
            ->call('confirmLogoutModal', $this->group->id)
            ->assertDispatched('show-logout-confirmation')
            ->assertSet('groupBeeingLogout', $this->group->id);
    }

    public function test_confirming_the_exit_detaches_the_membership(): void
    {
        // The page's confirmation dialog navigates to groups.logout; that
        // route, behind password.confirm, is the exit (roadmap TODO 70).
        $secondAdmin = $this->createUser(['email' => 'lg-admin3@example.test']);
        $this->attachUserToGroup($secondAdmin, $this->group, 'admin');

        $this->actingAs($this->admin)
            ->withSession($this->passwordConfirmedSession())
            ->get(route('groups.logout', ['group' => $this->group->id]))
            ->assertRedirect(route('groups'));

        $this->assertSame(
            0,
            GroupUser::where('group_id', $this->group->id)->where('user_id', $this->admin->id)->count()
        );
    }

    public function test_the_exit_route_refuses_the_last_admin(): void
    {
        // The guard also runs on the exit itself, not only when the modal
        // opens - the route is reachable without the page, and a co-admin
        // may have left in the meantime.
        $this->actingAs($this->admin)
            ->withSession($this->passwordConfirmedSession())
            ->get(route('groups.logout', ['group' => $this->group->id]))
            ->assertRedirect(route('groups'))
            ->assertSessionHas('message', __('group.logout.no_admin'));

        $this->assertSame(
            1,
            GroupUser::where('group_id', $this->group->id)->where('user_id', $this->admin->id)->count()
        );
    }

    public function test_the_component_has_no_exit_of_its_own(): void
    {
        // logoutConfirmed() skipped the route's password confirmation and the
        // page never called it (TODO 70).
        $this->expectException(\Livewire\Exceptions\MethodNotFoundException::class);

        Livewire::actingAs($this->admin)
            ->test(ListGroups::class)
            ->call('logoutConfirmed');
    }

    // --- invitations: only a real, still pending one (roadmap TODO 70) ---

    public function test_accepting_without_an_invitation_joins_nothing(): void
    {
        // accept() used to sync() the pivot, which attaches a missing row:
        // any user could join any group, and its child groups, by id.
        $stranger = $this->createUser(['email' => 'lg-stranger@example.test']);
        $child = $this->createChildGroup($this->group);

        Livewire::actingAs($stranger)
            ->test(ListGroups::class)
            ->call('accept', $this->group->id)
            ->assertDispatched('error');

        $this->assertSame(0, GroupUser::withTrashed()->where('user_id', $stranger->id)->count());
        $this->assertSame(0, GroupUser::withTrashed()->where('user_id', $stranger->id)->where('group_id', $child->id)->count());
    }

    public function test_accepting_does_not_bring_back_a_removed_membership(): void
    {
        $removed = $this->createUser(['email' => 'lg-removed@example.test']);
        $this->attachUserToGroup($removed, $this->group, 'member');
        GroupUser::where('group_id', $this->group->id)->where('user_id', $removed->id)->delete();

        Livewire::actingAs($removed)
            ->test(ListGroups::class)
            ->call('accept', $this->group->id)
            ->assertDispatched('error');

        $this->assertSame(0, GroupUser::where('group_id', $this->group->id)->where('user_id', $removed->id)->count());
    }

    public function test_accepting_an_invitation_accepts_the_child_group_invitations_too(): void
    {
        $child = $this->createChildGroup($this->group);
        $invitee = $this->createUser(['email' => 'lg-invitee@example.test']);
        $this->attachUserToGroup($invitee, $this->group, 'member', accepted: false);
        $this->attachUserToGroup($invitee, $child, 'member', accepted: false);

        Livewire::actingAs($invitee)
            ->test(ListGroups::class)
            ->call('accept', $this->group->id)
            ->assertDispatched('success');

        foreach ([$this->group, $child] as $group) {
            $this->assertNotNull(GroupUser::where('group_id', $group->id)->where('user_id', $invitee->id)->value('accepted_at'));
        }
    }

    public function test_rejecting_cannot_leave_an_accepted_membership(): void
    {
        // The last admin "rejecting" their own group used to leave it without
        // an admin, past the exit route's checks.
        Livewire::actingAs($this->admin)
            ->test(ListGroups::class)
            ->call('rejectModal', $this->group->id)
            ->call('rejectConfirmed')
            ->assertDispatched('error');

        $this->assertSame(
            'admin',
            GroupUser::where('group_id', $this->group->id)->where('user_id', $this->admin->id)->value('group_role')
        );
    }

    // --- deleting the group ---

    public function test_group_deletion_is_not_available_from_this_component(): void
    {
        // The confirmGroupRemoval() and deleteGroup() methods are
        // commented out (ListGroups.php:26-49), as is the 'deleteGroup'
        // entry in the $listeners array. Deletion happens through the
        // Groups\DeleteGroup component, which GroupComponentsTest covers.
        //
        // This test pins down the current state: if someone re-enables
        // the methods, this is where it will surface that the behavior needs rethinking.
        $this->expectException(\Livewire\Exceptions\MethodNotFoundException::class);

        Livewire::actingAs($this->admin)
            ->test(ListGroups::class)
            ->call('confirmGroupRemoval', $this->group->id);
    }

    // --- requesting the group-creator privilege ---

    public function test_requesting_the_creator_privilege_opens_an_empty_form(): void
    {
        Livewire::actingAs($this->admin)
            ->test(ListGroups::class)
            ->set('state.congregation', 'valami')
            ->call('askGroupCreatorPrivilege')
            ->assertSet('state', [])
            ->assertDispatched('show-modal');
    }

    public function test_the_privilege_request_validates_its_fields(): void
    {
        Mail::fake();
        $this->admin->update(['phone_number' => '36301234567']);

        Livewire::actingAs($this->admin)
            ->test(ListGroups::class)
            ->set('state.congregation', 'ab')
            ->set('state.reason', 'rovid')
            ->call('requestGroupCreatorPrivilege')
            ->assertHasErrors(['congregation', 'reason']);

        Mail::assertNothingSent();
    }

    public function test_the_privilege_request_succeeds_and_closes_the_modal(): void
    {
        $this->admin->update(['phone_number' => '36301234567']);

        // Not Mail::fake(): the method calls raw Mail::send() with a view,
        // not a Mailable class, so MailFake's assertSent() cannot
        // catch it. phpunit.xml has MAIL_MAILER=array set, so the email
        // does not go out anywhere - here we verify that sending
        // completes without error and the modal closes.
        Livewire::actingAs($this->admin)
            ->test(ListGroups::class)
            ->set('state.congregation', 'Példa Gyülekezet')
            ->set('state.reason', 'Szeretnék új csoportot létrehozni a városrészünkben.')
            ->call('requestGroupCreatorPrivilege')
            ->assertHasNoErrors()
            ->assertDispatched('hide-modal');
    }

    public function test_the_privilege_request_requires_a_numeric_phone_number(): void
    {
        Mail::fake();
        // The phone number does not come from the form but from the profile -
        // with an empty profile, the request fails validation.
        $this->admin->update(['phone_number' => null]);

        Livewire::actingAs($this->admin)
            ->test(ListGroups::class)
            ->set('state.congregation', 'Példa Gyülekezet')
            ->set('state.reason', 'Szeretnék új csoportot létrehozni a városrészünkben.')
            ->call('requestGroupCreatorPrivilege')
            ->assertHasErrors(['phone']);
    }

    // --- modal helper ---

    public function test_open_modal_dispatches_the_browser_event(): void
    {
        Livewire::actingAs($this->admin)
            ->test(ListGroups::class)
            ->call('openModal', 'createGroup')
            ->assertDispatched('show-modal');
    }
}
