<?php

namespace Tests\Feature\Livewire;

use App\Http\Livewire\Groups\Messages;
use App\Models\Event;
use App\Models\Group;
use App\Models\GroupMessage;
use App\Models\GroupUser;
use App\Models\User;
use App\Notifications\GroupPriorityMessageNotification;
use Illuminate\Support\Facades\Notification;
use Livewire\Livewire;
use Tests\Feature\FeatureTestCase;

/**
 * TODO 07: Groups\Messages was smoke-only (mounts and returns 200).
 *
 * Its checkPrivilege() is a four-stage decision that gates reading, writing and
 * deleting, and sendMessage() fans out priority notifications. None of it was
 * covered.
 */
class GroupMessagesTest extends FeatureTestCase
{
    private Group $group;
    private User $member;

    protected function setUp(): void
    {
        parent::setUp();

        $this->group = $this->createGroup(['messages_on' => 1, 'messages_write' => 0, 'messages_priority' => 1]);
        $this->member = $this->createUser(['email' => 'msg-member@example.test']);
        $this->attachUserToGroup($this->member, $this->group);
        $this->actingAs($this->member);
    }

    /** An active event provides the "has privilege" base case. */
    private function giveMemberAnActiveEvent(?User $user = null): Event
    {
        $user ??= $this->member;

        return Event::factory()->create([
            'group_id' => $this->group->id,
            'user_id' => $user->id,
            'day' => now()->toDateString(),
            'start' => now()->addHour()->format('Y-m-d H:i:s'),
            'end' => now()->addHours(3)->format('Y-m-d H:i:s'),
            'status' => 1,
            'accepted_at' => now(),
            'accepted_by' => $user->id,
        ]);
    }

    // --- checkPrivilege ---

    public function test_member_without_an_upcoming_event_can_neither_read_nor_write(): void
    {
        Livewire::actingAs($this->member)
            ->test(Messages::class, ['group' => $this->group])
            ->call('checkPrivilege')
            ->assertSet('privilege.read', false)
            ->assertSet('privilege.write', false)
            ->assertSet('privilege.delete', false);
    }

    public function test_an_upcoming_event_grants_read_and_write(): void
    {
        $this->giveMemberAnActiveEvent();

        Livewire::actingAs($this->member)
            ->test(Messages::class, ['group' => $this->group])
            ->call('checkPrivilege')
            ->assertSet('privilege.read', true)
            ->assertSet('privilege.write', true);
    }

    public function test_group_level_write_lock_revokes_write_but_keeps_read(): void
    {
        $lockedGroup = $this->createGroup(['messages_on' => 1, 'messages_write' => 1]);
        $this->attachUserToGroup($this->member, $lockedGroup);

        Event::factory()->create([
            'group_id' => $lockedGroup->id,
            'user_id' => $this->member->id,
            'day' => now()->toDateString(),
            'start' => now()->addHour()->format('Y-m-d H:i:s'),
            'end' => now()->addHours(3)->format('Y-m-d H:i:s'),
            'status' => 1,
            'accepted_at' => now(),
            'accepted_by' => $this->member->id,
        ]);

        Livewire::actingAs($this->member)
            ->test(Messages::class, ['group' => $lockedGroup])
            ->call('checkPrivilege')
            ->assertSet('privilege.read', true)
            ->assertSet('privilege.write', false);
    }

    public function test_per_member_message_use_1_blocks_writing_even_with_an_event(): void
    {
        $this->giveMemberAnActiveEvent();
        GroupUser::where('user_id', $this->member->id)
            ->where('group_id', $this->group->id)
            ->update(['message_use' => 1]);

        Livewire::actingAs($this->member)
            ->test(Messages::class, ['group' => $this->group])
            ->call('checkPrivilege')
            ->assertSet('privilege.write', false);
    }

    public function test_per_member_message_use_2_grants_access_without_any_event(): void
    {
        GroupUser::where('user_id', $this->member->id)
            ->where('group_id', $this->group->id)
            ->update(['message_use' => 2]);

        Livewire::actingAs($this->member)
            ->test(Messages::class, ['group' => $this->group])
            ->call('checkPrivilege')
            ->assertSet('privilege.read', true)
            ->assertSet('privilege.write', true);
    }

    public function test_a_group_editor_always_gets_full_privileges(): void
    {
        $editor = $this->createUser(['email' => 'msg-editor@example.test']);
        $this->attachUserToGroup($editor, $this->group, 'admin');

        Livewire::actingAs($editor)
            ->test(Messages::class, ['group' => $this->group])
            ->call('checkPrivilege')
            ->assertSet('privilege.read', true)
            ->assertSet('privilege.write', true)
            ->assertSet('privilege.delete', true);
    }

    // --- sendMessage ---

    public function test_sending_a_message_persists_it(): void
    {
        $this->giveMemberAnActiveEvent();

        Livewire::actingAs($this->member)
            ->test(Messages::class, ['group' => $this->group])
            ->set('message', 'Szia mindenkinek!')
            ->call('sendMessage')
            ->assertHasNoErrors();

        $message = GroupMessage::where('group_id', $this->group->id)->first();

        $this->assertNotNull($message);
        $this->assertSame('Szia mindenkinek!', $message->message);
        $this->assertSame($this->member->id, $message->user_id);
    }

    public function test_sending_resets_the_input_afterwards(): void
    {
        $this->giveMemberAnActiveEvent();

        Livewire::actingAs($this->member)
            ->test(Messages::class, ['group' => $this->group])
            ->set('message', 'Elküldött üzenet')
            ->call('sendMessage')
            ->assertSet('message', '');
    }

    public function test_a_member_without_write_privilege_stores_nothing(): void
    {
        Livewire::actingAs($this->member)
            ->test(Messages::class, ['group' => $this->group])
            ->set('message', 'Nem mehet ki')
            ->call('sendMessage');

        $this->assertSame(0, GroupMessage::where('group_id', $this->group->id)->count());
    }

    public function test_message_validation_rejects_too_short_and_too_long_text(): void
    {
        $this->giveMemberAnActiveEvent();

        Livewire::actingAs($this->member)
            ->test(Messages::class, ['group' => $this->group])
            ->set('message', 'ab')
            ->call('sendMessage')
            ->assertHasErrors(['message']);

        Livewire::actingAs($this->member)
            ->test(Messages::class, ['group' => $this->group])
            ->set('message', str_repeat('a', 251))
            ->call('sendMessage')
            ->assertHasErrors(['message']);
    }

    public function test_the_throttle_rule_blocks_the_fourth_message_within_a_minute(): void
    {
        $this->giveMemberAnActiveEvent();

        $component = Livewire::actingAs($this->member)->test(Messages::class, ['group' => $this->group]);

        for ($i = 1; $i <= 3; $i++) {
            $component->set('message', "Uzenet szama {$i}")->call('sendMessage')->assertHasNoErrors();
        }

        $component->set('message', 'Ez mar tul sok')->call('sendMessage')->assertHasErrors(['message']);

        $this->assertSame(3, GroupMessage::where('group_id', $this->group->id)->count());
    }

    // --- priority notifications ---

    public function test_a_priority_message_notifies_editors_and_opted_in_members(): void
    {
        Notification::fake();
        $this->giveMemberAnActiveEvent();

        $editor = $this->createUser(['email' => 'msg-admin@example.test']);
        $this->attachUserToGroup($editor, $this->group, 'admin');

        $optedIn = $this->createUser(['email' => 'msg-opted@example.test']);
        $this->attachUserToGroup($optedIn, $this->group);
        GroupUser::where('user_id', $optedIn->id)
            ->where('group_id', $this->group->id)
            ->update(['message_send_priority' => 1]);

        Livewire::actingAs($this->member)
            ->test(Messages::class, ['group' => $this->group])
            ->set('message', 'Fontos bejelentés')
            ->set('message_priority', 1)
            ->call('sendMessage')
            ->assertHasNoErrors();

        Notification::assertSentTo($editor, GroupPriorityMessageNotification::class);
        Notification::assertSentTo($optedIn, GroupPriorityMessageNotification::class);
    }

    public function test_a_normal_message_sends_no_notifications(): void
    {
        Notification::fake();
        $this->giveMemberAnActiveEvent();

        $editor = $this->createUser(['email' => 'msg-admin2@example.test']);
        $this->attachUserToGroup($editor, $this->group, 'admin');

        Livewire::actingAs($this->member)
            ->test(Messages::class, ['group' => $this->group])
            ->set('message', 'Hétköznapi üzenet')
            ->set('message_priority', 0)
            ->call('sendMessage');

        Notification::assertNotSentTo($editor, GroupPriorityMessageNotification::class);
    }

    public function test_priority_notifications_are_suppressed_when_the_group_disables_them(): void
    {
        Notification::fake();

        $quietGroup = $this->createGroup(['messages_on' => 1, 'messages_write' => 0, 'messages_priority' => 0]);
        $this->attachUserToGroup($this->member, $quietGroup);
        $editor = $this->createUser(['email' => 'msg-admin3@example.test']);
        $this->attachUserToGroup($editor, $quietGroup, 'admin');

        Event::factory()->create([
            'group_id' => $quietGroup->id,
            'user_id' => $this->member->id,
            'day' => now()->toDateString(),
            'start' => now()->addHour()->format('Y-m-d H:i:s'),
            'end' => now()->addHours(3)->format('Y-m-d H:i:s'),
            'status' => 1,
            'accepted_at' => now(),
            'accepted_by' => $this->member->id,
        ]);

        Livewire::actingAs($this->member)
            ->test(Messages::class, ['group' => $quietGroup])
            ->set('message', 'Fontos, de a csoport tiltja')
            ->set('message_priority', 1)
            ->call('sendMessage');

        Notification::assertNotSentTo($editor, GroupPriorityMessageNotification::class);
    }

    // --- deleteMessage ---

    public function test_a_member_can_blank_their_own_message(): void
    {
        $message = GroupMessage::factory()->create([
            'group_id' => $this->group->id,
            'user_id' => $this->member->id,
            'message' => 'Sajat uzenet',
        ]);

        Livewire::actingAs($this->member)
            ->test(Messages::class, ['group' => $this->group])
            ->call('deleteMessage', $message->id);

        // Deletion actually just nulls out the text; the row remains.
        $this->assertNull(GroupMessage::find($message->id)->message);
    }

    public function test_a_member_cannot_blank_someone_elses_message(): void
    {
        $other = $this->createUser(['email' => 'msg-other@example.test']);
        $this->attachUserToGroup($other, $this->group);

        $message = GroupMessage::factory()->create([
            'group_id' => $this->group->id,
            'user_id' => $other->id,
            'message' => 'Masik tag uzenete',
        ]);

        Livewire::actingAs($this->member)
            ->test(Messages::class, ['group' => $this->group])
            ->call('deleteMessage', $message->id);

        $this->assertSame('Masik tag uzenete', GroupMessage::find($message->id)->message);
    }

    public function test_an_editor_can_blank_any_message(): void
    {
        $editor = $this->createUser(['email' => 'msg-editor2@example.test']);
        $this->attachUserToGroup($editor, $this->group, 'admin');

        $message = GroupMessage::factory()->create([
            'group_id' => $this->group->id,
            'user_id' => $this->member->id,
            'message' => 'Moderalando uzenet',
        ]);

        Livewire::actingAs($editor)
            ->test(Messages::class, ['group' => $this->group])
            ->call('deleteMessage', $message->id);

        $this->assertNull(GroupMessage::find($message->id)->message);
    }

    // --- other interactions ---

    public function test_change_priority_toggles_the_flag(): void
    {
        Livewire::actingAs($this->member)
            ->test(Messages::class, ['group' => $this->group])
            ->assertSet('message_priority', 0)
            ->call('changePriority')
            ->assertSet('message_priority', 1)
            ->call('changePriority')
            ->assertSet('message_priority', 0);
    }

    public function test_increase_messages_pages_in_thirty_more(): void
    {
        Livewire::actingAs($this->member)
            ->test(Messages::class, ['group' => $this->group])
            ->assertSet('message_number', 30)
            ->call('increaseMessages')
            ->assertSet('message_number', 60);
    }

    public function test_mount_copies_the_group_settings_onto_the_component(): void
    {
        Livewire::actingAs($this->member)
            ->test(Messages::class, ['group' => $this->group])
            ->assertSet('group_id', $this->group->id)
            ->assertSet('group_priority', 1)
            ->assertSet('group_write', 0);
    }
}
