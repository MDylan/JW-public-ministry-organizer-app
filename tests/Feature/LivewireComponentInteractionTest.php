<?php

namespace Tests\Feature;

use App\Http\Livewire\Admin\Users\ListUsers as AdminUserListComponent;
use App\Http\Livewire\Groups\ListGroups as GroupListComponent;
use App\Http\Livewire\Groups\UpdateGroupForm as GroupEditComponent;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Livewire\Livewire;

class LivewireComponentInteractionTest extends FeatureTestCase
{
    public function test_admin_users_component_allows_create_and_update_interaction_flow(): void
    {
        $admin = $this->createUser([
            'role' => 'mainAdmin',
            'email' => 'lw-admin@example.test',
        ]);

        $this->actingAs($admin);

        Livewire::test(AdminUserListComponent::class)
            ->call('addNew')
            ->assertDispatchedBrowserEvent('show-modal')
            ->set('state.name', 'Livewire Created User')
            ->set('state.email', 'lw-created@example.test')
            ->set('state.phone_number', '36201230000')
            ->set('state.role', 'groupCreator')
            ->call('createUser')
            ->assertDispatchedBrowserEvent('hide-modal');

        $created = User::where('email', 'lw-created@example.test')->firstOrFail();
        $this->assertSame('Livewire Created User', $created->name);
        $this->assertSame('groupCreator', $created->role);

        Livewire::test(AdminUserListComponent::class)
            ->call('edit', $created)
            ->assertSet('showEditModal', true)
            ->set('state.name', 'Livewire Updated User')
            ->set('state.phone_number', '36209998877')
            ->set('state.role', 'translator')
            ->call('updateUser')
            ->assertDispatchedBrowserEvent('hide-modal');

        $updated = $created->fresh();
        $this->assertSame('Livewire Updated User', $updated->name);
        $this->assertSame('translator', $updated->role);
        $this->assertSame('36209998877', $updated->phone_number);
    }

    public function test_admin_users_component_delete_and_search_interactions_work(): void
    {
        $admin = $this->createUser([
            'role' => 'mainAdmin',
            'email' => 'lw-admin-delete@example.test',
        ]);
        $target = $this->createUser([
            'email' => 'lw-target-delete@example.test',
            'role' => 'groupCreator',
        ]);

        Livewire::actingAs($admin)
            ->test(AdminUserListComponent::class)
            ->set('searchTerm', 'target')
            ->call('clearSearch')
            ->assertSet('searchTerm', null)
            ->call('confirmUserRemoval', $target->id)
            ->assertSet('userIdBeeingRemoved', $target->id)
            ->assertDispatchedBrowserEvent('show-deletion-confirmation')
            ->call('deleteUser')
            ->assertDispatchedBrowserEvent('success');

        $this->assertDatabaseMissing('users', ['id' => $target->id]);
    }

    public function test_groups_list_component_accept_and_reject_invitation_interactions_work(): void
    {
        $user = $this->createUser(['email' => 'lw-group-member@example.test']);
        $groupToAccept = $this->createGroup();
        $groupToReject = $this->createGroup();

        $this->attachUserToGroup($user, $groupToAccept, 'member', false);
        $this->attachUserToGroup($user, $groupToReject, 'member', false);

        Livewire::actingAs($user)
            ->test(GroupListComponent::class)
            ->call('accept', $groupToAccept->id)
            ->assertDispatchedBrowserEvent('success');

        $acceptedPivot = DB::table('group_user')
            ->where('user_id', $user->id)
            ->where('group_id', $groupToAccept->id)
            ->first();

        $this->assertNotNull($acceptedPivot);
        $this->assertNotNull($acceptedPivot->accepted_at);
        $this->assertNull($acceptedPivot->deleted_at);

        Livewire::actingAs($user)
            ->test(GroupListComponent::class)
            ->call('rejectModal', $groupToReject->id)
            ->assertSet('groupBeeingRejected', $groupToReject->id)
            ->assertDispatchedBrowserEvent('show-reject-confirmation')
            ->call('rejectConfirmed')
            ->assertDispatchedBrowserEvent('success');

        $this->assertSoftDeleted('group_user', [
            'user_id' => $user->id,
            'group_id' => $groupToReject->id,
        ]);
    }

    public function test_groups_route_renders_create_modal_and_group_creator_can_create_group(): void
    {
        $groupCreator = $this->createUser([
            'role' => 'groupCreator',
            'email' => 'lw-group-creator@example.test',
        ]);

        $routeResponse = $this->actingAs($groupCreator)->get(route('groups'));
        $routeResponse->assertStatus(200);
        $routeResponse->assertSee('wire:click="openModal(\'createGroup\')"', false);
        $routeResponse->assertSee('id="createGroup"', false);

        Livewire::actingAs($groupCreator)
            ->test(GroupListComponent::class)
            ->set('state.name', 'Livewire New Group')
            ->call('createGroup')
            ->assertDispatchedBrowserEvent('hide-modal');

        $createdGroup = $groupCreator
            ->fresh()
            ->userGroups()
            ->orderByDesc('groups.id')
            ->first();

        $this->assertNotNull($createdGroup);
        $this->assertSame('Livewire New Group', $createdGroup->name);
        $this->assertSame('admin', $createdGroup->pivot->group_role);
        $this->assertNotNull($createdGroup->pivot->accepted_at);
    }

    public function test_groups_edit_route_requires_group_admin_and_password_confirmation_and_renders_form(): void
    {
        $group = $this->createGroup();
        $editor = $this->createUser(['email' => 'lw-group-editor@example.test']);
        $member = $this->createUser(['email' => 'lw-group-member-no-edit@example.test']);

        $this->attachUserToGroup($editor, $group, 'roler', true);
        $this->attachUserToGroup($member, $group, 'member', true);

        $this->actingAs($member)
            ->withSession($this->passwordConfirmedSession())
            ->get(route('groups.edit', ['group' => $group->id]))
            ->assertForbidden();

        $this->flushSession();
        $passwordConfirmRedirect = $this->actingAs($editor)
            ->get(route('groups.edit', ['group' => $group->id]));

        $passwordConfirmRedirect->assertStatus(302);
        $this->assertStringContainsString(
            route('password.confirm', [], false),
            $passwordConfirmRedirect->headers->get('Location', '')
        );

        $editPage = $this->actingAs($editor)
            ->withSession(array_merge(
                $this->passwordConfirmedSession(),
                ['password_hash_web' => $editor->password]
            ))
            ->get(route('groups.edit', ['group' => $group->id]));

        $editPage->assertStatus(200);
        foreach ([
            'wire:submit.prevent="updateGroup"',
            'wire:model.defer="state.name"',
            'wire:model.defer="state.replyTo"',
            'wire:model.defer="state.max_extend_days"',
            'wire:model="state.need_approval"',
            'wire:model.defer="state.min_publishers"',
            'wire:model.defer="state.max_publishers"',
            'wire:model="state.min_time"',
            'wire:model.defer="state.max_time"',
            'wire:model.defer="state.color_default"',
            'wire:model.defer="state.color_empty"',
            'wire:model.defer="state.color_someone"',
            'wire:model.defer="state.color_minimum"',
            'wire:model.defer="state.color_maximum"',
            'wire:model.defer="state.showPhone"',
            'wire:model="days.0.day_number"',
            'wire:model="state.messages_on"',
            'wire:model="change_date"',
        ] as $field) {
            $editPage->assertSee($field, false);
        }

        foreach ([
            'id="inputName"',
            'id="replyTo"',
            'id="max_extend_days"',
            'id="need_approval"',
            'id="min_publishers"',
            'id="max_publishers"',
            'id="min_time"',
            'id="max_time"',
            'id="messages_on"',
            'id="date_from"',
        ] as $elementId) {
            $editPage->assertSee($elementId, false);
        }
    }

    public function test_groups_edit_component_submits_all_core_form_fields_successfully(): void
    {
        $group = $this->createGroup();
        $editor = $this->createUser(['email' => 'lw-group-edit-submit@example.test']);
        $this->attachUserToGroup($editor, $group, 'roler', true);

        Livewire::actingAs($editor)
            ->test(GroupEditComponent::class, ['group' => $group])
            ->set('state.name', 'Updated Group Name')
            ->set('state.replyTo', 'updated-group@example.test')
            ->set('state.max_extend_days', 30)
            ->set('state.need_approval', 1)
            ->set('state.min_publishers', 2)
            ->set('state.max_publishers', 4)
            ->set('state.min_time', 60)
            ->set('state.max_time', 180)
            ->set('state.color_default', '#112233')
            ->set('state.color_empty', '#223344')
            ->set('state.color_someone', '#334455')
            ->set('state.color_minimum', '#445566')
            ->set('state.color_maximum', '#556677')
            ->set('state.showPhone', 1)
            ->set('state.messages_on', 1)
            ->set('state.messages_write', 1)
            ->set('state.messages_priority', 1)
            ->set('state.weather_enabled', 0)
            ->set('days.1.day_number', '1')
            ->set('days.1.start_time', '08:00')
            ->set('days.1.end_time', '10:00')
            ->set('change_date', now()->toDateString())
            ->call('updateGroup')
            ->assertHasNoErrors()
            ->assertRedirect(route('groups'));

        $freshGroup = $group->fresh();
        $this->assertSame('Updated Group Name', $freshGroup->name);
        $this->assertSame('updated-group@example.test', $freshGroup->replyTo);
        $this->assertSame(30, (int) $freshGroup->max_extend_days);
        $this->assertSame(1, (int) $freshGroup->need_approval);
        $this->assertSame(2, (int) $freshGroup->min_publishers);
        $this->assertSame(4, (int) $freshGroup->max_publishers);
        $this->assertSame(60, (int) $freshGroup->min_time);
        $this->assertSame(180, (int) $freshGroup->max_time);
        $this->assertSame('#112233', $freshGroup->color_default);
        $this->assertSame('#223344', $freshGroup->color_empty);
        $this->assertSame('#334455', $freshGroup->color_someone);
        $this->assertSame('#445566', $freshGroup->color_minimum);
        $this->assertSame('#556677', $freshGroup->color_maximum);
        $this->assertSame(1, (int) $freshGroup->showPhone);
        $this->assertSame(1, (int) $freshGroup->messages_on);
        $this->assertSame(1, (int) $freshGroup->messages_write);
        $this->assertSame(1, (int) $freshGroup->messages_priority);
        $this->assertSame(0, (int) $freshGroup->weather_enabled);

        $savedDay = $freshGroup->days()->where('day_number', 1)->first();
        $this->assertNotNull($savedDay);
        $this->assertSame('08:00', $savedDay->start_time);
        $this->assertSame('10:00', $savedDay->end_time);
    }
}
