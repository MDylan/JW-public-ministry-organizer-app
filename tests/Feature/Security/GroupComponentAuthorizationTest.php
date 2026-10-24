<?php

namespace Tests\Feature\Security;

use App\Http\Livewire\Groups\History;
use App\Http\Livewire\Groups\Messages;
use App\Http\Livewire\Groups\PosterEditModal;
use App\Http\Livewire\Groups\Statistics;
use App\Http\Livewire\Groups\UpdateGroupForm;
use App\Models\Group;
use App\Models\GroupMessage;
use App\Models\GroupUser;
use App\Models\User;
use Livewire\Livewire;
use Tests\Feature\FeatureTestCase;

/**
 * Roadmap TODO 70 (ported to the v1 line by TODO 70.1): the group pages' components re-check the role on every
 * request and cannot be pointed at another group.
 *
 * groupAdmin / groupMember guard the first render only. Before, the
 * statistics page showed another group's members and reports once its
 * groupId was rewritten, an editor of one board could wipe messages on any
 * other, the board's settings were the client's, and a demoted editor kept
 * every open page. A pending invitation to be a roler counted as the role
 * for the poster and special-date modals.
 */
class GroupComponentAuthorizationTest extends FeatureTestCase
{
    private Group $own;
    private Group $other;

    protected function setUp(): void
    {
        parent::setUp();

        $this->own = $this->createGroup(['messages_on' => 1]);
        $this->other = $this->createGroup(['messages_on' => 1]);
    }

    private function memberOf(Group $group, string $role, string $email, bool $accepted = true): User
    {
        $user = $this->createUser(['email' => $email]);
        $this->attachUserToGroup($user, $group, $role, $accepted);

        return $user;
    }

    private function demote(User $user, Group $group): void
    {
        GroupUser::where('group_id', $group->id)->where('user_id', $user->id)->update(['group_role' => 'member']);
    }

    private function assertLocked($component, array $values): void
    {
        foreach ($values as $property => $value) {
            $component->set($property, $value)->assertForbidden();
        }
    }

    // --- statistics and history ------------------------------------------

    public function test_the_statistics_cannot_be_pointed_at_another_group(): void
    {
        $admin = $this->memberOf($this->own, 'admin', 'gc-stat@example.test');

        $this->assertLocked(
            Livewire::actingAs($admin)->test(Statistics::class, ['group' => $this->own->id]),
            ['groupId' => $this->other->id]
        );
    }

    public function test_the_statistics_and_history_refuse_a_plain_member_and_a_demoted_editor(): void
    {
        $member = $this->memberOf($this->own, 'member', 'gc-stat-member@example.test');
        $admin = $this->memberOf($this->own, 'admin', 'gc-stat-admin@example.test');

        foreach ([Statistics::class, History::class] as $component) {
            Livewire::actingAs($member)->test($component, ['group' => $this->own->id])->assertForbidden();
        }

        $statistics = Livewire::actingAs($admin)->test(Statistics::class, ['group' => $this->own->id]);
        $history = Livewire::actingAs($admin)->test(History::class, ['group' => $this->own->id]);
        $this->demote($admin, $this->own);

        $statistics->call('applyDateRange')->assertForbidden();
        $history->call('setMonth')->assertForbidden();
    }

    // --- the message board -----------------------------------------------

    public function test_an_editor_cannot_wipe_a_message_on_another_board(): void
    {
        $admin = $this->memberOf($this->own, 'admin', 'gc-msg@example.test');
        $author = $this->memberOf($this->other, 'member', 'gc-msg-author@example.test');
        $foreign = GroupMessage::create(['group_id' => $this->other->id, 'user_id' => $author->id, 'message' => 'foreign', 'priority' => 0]);
        $mine = GroupMessage::create(['group_id' => $this->own->id, 'user_id' => $author->id, 'message' => 'mine', 'priority' => 0]);

        Livewire::actingAs($admin)->test(Messages::class, ['group' => $this->own])
            ->call('deleteMessage', $foreign->id)
            ->call('deleteMessage', $mine->id);

        $this->assertSame('foreign', $foreign->fresh()->message);
        $this->assertNull($mine->fresh()->message);
    }

    public function test_the_boards_group_and_settings_cannot_be_written_by_the_client(): void
    {
        $member = $this->memberOf($this->own, 'member', 'gc-msg-locked@example.test');

        $this->assertLocked(
            Livewire::actingAs($member)->test(Messages::class, ['group' => $this->own]),
            ['group_id' => $this->other->id, 'group_write' => 0, 'group_priority' => 1, 'group_name' => 'Spoofed', 'privilege.write' => true]
        );
    }

    public function test_the_board_refuses_a_non_member(): void
    {
        $outsider = $this->memberOf($this->other, 'admin', 'gc-msg-outsider@example.test');

        Livewire::actingAs($outsider)->test(Messages::class, ['group' => $this->own])->assertForbidden();
    }

    // --- group settings and the editor modals ----------------------------

    public function test_a_demoted_editor_loses_the_open_settings_page(): void
    {
        $admin = $this->memberOf($this->own, 'admin', 'gc-settings@example.test');
        $form = Livewire::actingAs($admin)->test(UpdateGroupForm::class, ['group' => $this->own]);

        $this->demote($admin, $this->own);

        $form->call('literatureEditCancel')->assertForbidden();
    }

    public function test_a_pending_roler_is_not_an_editor(): void
    {
        $pending = $this->memberOf($this->own, 'roler', 'gc-pending@example.test', accepted: false);
        $accepted = $this->memberOf($this->own, 'roler', 'gc-accepted@example.test');

        $editors = $this->own->editors()->pluck('users.id')->all();
        $this->assertContains($accepted->id, $editors);
        $this->assertNotContains($pending->id, $editors);

        Livewire::actingAs($pending)->test(PosterEditModal::class)
            ->call('openModal', $this->own->id, 0)
            ->assertForbidden();
    }
}
