<?php

namespace Tests\Feature\Security;

use App\Http\Livewire\Groups\ListUsers;
use App\Models\Group;
use App\Models\GroupUser;
use App\Models\User;
use App\Notifications\FinishRegistration;
use Illuminate\Support\Facades\Notification;
use Livewire\Livewire;
use Tests\Feature\FeatureTestCase;

/**
 * Roadmap TODO 70 (ported to the v1 line by TODO 70.1): the member list's editor actions.
 *
 * Before, the role came from any membership row (a pending invitation to be
 * an admin passed every admin check), a roler could remove an admin they
 * could not demote, the removal acted on whatever id the client set,
 * inviting an existing account's e-mail address revealed - and let the
 * editor overwrite - that account's name and phone, and a plain member's
 * search and total revealed hidden members and private addresses.
 */
class GroupMemberListAuthorizationTest extends FeatureTestCase
{
    private Group $group;
    private User $admin;

    protected function setUp(): void
    {
        parent::setUp();

        $this->group = $this->createGroup();
        $this->admin = $this->memberOf('admin', 'ml-admin@example.test');
    }

    private function memberOf(string $role, string $email, bool $accepted = true, array $attributes = []): User
    {
        $user = $this->createUser(array_merge(['email' => $email], $attributes));
        $this->attachUserToGroup($user, $this->group, $role, $accepted);

        return $user->fresh();
    }

    private function list(User $user)
    {
        return Livewire::actingAs($user)->test(ListUsers::class, ['group' => $this->group->id]);
    }

    public function test_the_lists_group_and_the_ids_it_acts_on_cannot_be_written_by_the_client(): void
    {
        foreach (['groupId' => 1, 'selected_user.id' => 1, 'userIdBeeingRemoved' => 1, 'detachId' => 1] as $property => $value) {
            $this->list($this->admin)->set($property, $value)->assertForbidden();
        }
    }

    public function test_a_pending_invitation_to_be_an_admin_is_not_the_role(): void
    {
        $pending = $this->memberOf('admin', 'ml-pending-admin@example.test', accepted: false);

        $this->list($pending)->assertForbidden();
    }

    public function test_a_roler_cannot_remove_an_admin(): void
    {
        $roler = $this->memberOf('roler', 'ml-roler@example.test');
        $this->memberOf('admin', 'ml-admin2@example.test');

        $this->list($roler)
            ->call('confirmUserRemoval', $this->admin->id)
            ->assertDispatchedBrowserEvent('sweet-error', fn ($name, $data) => ($data['message'] ?? null) === __('group.error_no_right_to_remove_admin'))
            ->assertSet('userIdBeeingRemoved', null);

        $this->assertSame(1, GroupUser::where('group_id', $this->group->id)->where('user_id', $this->admin->id)->count());
    }

    public function test_an_editor_removes_only_someone_in_the_group(): void
    {
        $stranger = $this->createUser(['email' => 'ml-stranger@example.test']);

        $this->list($this->admin)->call('confirmUserRemoval', $stranger->id)->assertForbidden();

        $this->assertSame(0, GroupUser::withTrashed()->where('user_id', $stranger->id)->count());
    }

    public function test_a_pending_invitation_does_not_open_the_accounts_profile(): void
    {
        $invited = $this->memberOf('member', 'ml-invited@example.test', accepted: false, attributes: [
            'name' => 'Private Person',
            'phone_number' => '36201234567',
        ]);

        $this->list($this->admin)
            ->assertDontSee('Private Person')
            ->assertDontSee('36201234567')
            ->call('editUser', $invited->id)
            ->assertSet('selected_user.profile', false)
            ->assertSet('state.user.phone_number', null)
            ->set('state.user.name', 'Overwritten')
            ->set('state.user.phone_number', '36300000000')
            ->call('updateUser');

        $fresh = $invited->fresh();
        $this->assertSame('Private Person', $fresh->name);
        $this->assertSame('36201234567', $fresh->phone_number);
    }

    public function test_an_accepted_members_profile_is_still_the_groups(): void
    {
        $member = $this->memberOf('member', 'ml-member@example.test', attributes: ['name' => 'Old Name']);

        $this->list($this->admin)
            ->call('editUser', $member->id)
            ->assertSet('selected_user.profile', true)
            ->set('state.user.name', 'New Name')
            ->call('updateUser')
            ->assertHasNoErrors();

        $this->assertSame('New Name', $member->fresh()->name);
    }

    public function test_a_member_neither_counts_nor_finds_a_hidden_member_or_a_private_address(): void
    {
        $member = $this->memberOf('member', 'ml-plain@example.test');
        $hidden = $this->memberOf('member', 'ml-hidden@example.test', attributes: ['name' => 'Zzhidden Member']);
        GroupUser::where('group_id', $this->group->id)->where('user_id', $hidden->id)->update(['hidden' => 1]);
        $this->memberOf('member', 'zzprivate@example.test', attributes: ['name' => 'Visible Member', 'show_fields' => ['email' => false]]);

        $this->list($member)
            ->set('searchTerm', 'Zzhidden')
            ->assertViewHas('users', fn ($users) => $users->total() === 0)
            ->set('searchTerm', 'zzprivate')
            ->assertViewHas('users', fn ($users) => $users->total() === 0)
            ->set('searchTerm', '')
            ->assertViewHas('users', fn ($users) => collect($users->items())->pluck('id')->doesntContain($hidden->id));
    }

    public function test_the_finish_registration_link_lasts_seven_days(): void
    {
        Notification::fake();

        $this->list($this->admin)
            ->set('new_users', 'ml-brand-new@example.test')
            ->call('createUser');

        $user = User::where('email', 'ml-brand-new@example.test')->firstOrFail();
        Notification::assertSentTo($user, FinishRegistration::class, function ($notification) {
            $data = (fn () => $this->data)->call($notification);
            parse_str((string) parse_url($data['url'], PHP_URL_QUERY), $query);

            return abs((int) $query['expires'] - now()->addDays(7)->timestamp) < 60;
        });
    }
}
