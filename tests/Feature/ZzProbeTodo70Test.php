<?php

namespace Tests\Feature;

use App\Http\Livewire\Events\EventEdit;
use App\Http\Livewire\Events\Modal;
use App\Http\Livewire\Groups\ListGroups;
use App\Http\Livewire\Groups\Messages;
use App\Http\Livewire\Groups\NewsEdit;
use App\Http\Livewire\Groups\Statistics;
use App\Models\Event;
use App\Models\GroupMessage;
use App\Models\GroupNews;
use App\Models\GroupUser;
use Livewire\Livewire;

/**
 * THROWAWAY probe for roadmap TODO 70.1 on the v1 line: each case asserts
 * that the hole TODO 70 measured on v2-dev is REAL here too (the attack
 * succeeds). Never committed.
 */
class ZzProbeTodo70Test extends FeatureTestCase
{
    public function test_probe_accept_joins_any_group_without_an_invitation(): void
    {
        $victimGroup = $this->createGroup(['name' => 'Victim']);
        $attacker = $this->createUser(['email' => 'p1@example.test']);

        Livewire::actingAs($attacker)->test(ListGroups::class)->call('accept', $victimGroup->id);

        $row = GroupUser::where('group_id', $victimGroup->id)->where('user_id', $attacker->id)->first();
        $this->assertNotNull($row, 'no row');
        $this->assertNotNull($row->accepted_at, 'not accepted');
    }

    public function test_probe_news_delete_controller_deletes_another_groups_news(): void
    {
        $a = $this->createGroup();
        $b = $this->createGroup();
        $admin = $this->createUser(['email' => 'p2@example.test']);
        $this->attachUserToGroup($admin, $a, 'admin');
        $newsB = $this->createGroupNews($b, $this->createUser(['email' => 'p2b@example.test']));

        $this->actingAs($admin)->withSession($this->passwordConfirmedSession())
            ->get(route('groups.news_delete', ['group' => $a->id, 'new' => $newsB->id]));

        $this->assertNull(GroupNews::find($newsB->id), 'B news survived');
    }

    public function test_probe_news_edit_delete_by_state_id(): void
    {
        $a = $this->createGroup();
        $b = $this->createGroup();
        $admin = $this->createUser(['email' => 'p3@example.test']);
        $this->attachUserToGroup($admin, $a, 'admin');
        $own = $this->createGroupNews($a, $admin);
        $newsB = $this->createGroupNews($b, $this->createUser(['email' => 'p3b@example.test']));

        Livewire::actingAs($admin)->test(NewsEdit::class, ['group' => $a->id, 'new' => $own->id])
            ->set('state.id', $newsB->id)
            ->call('deleteConfirmed');

        $this->assertNull(GroupNews::find($newsB->id), 'B news survived');
    }

    public function test_probe_statistics_group_swap_reads_another_group(): void
    {
        $a = $this->createGroup();
        $b = $this->createGroup();
        $admin = $this->createUser(['email' => 'p4@example.test']);
        $this->attachUserToGroup($admin, $a, 'admin');
        $secret = $this->createUser(['email' => 'p4b@example.test', 'name' => 'Zzsecret Member']);
        $this->attachUserToGroup($secret, $b, 'member');

        Livewire::actingAs($admin)->test(Statistics::class, ['group' => $a->id])
            ->set('groupId', $b->id)
            ->assertSee('Zzsecret Member');
    }

    public function test_probe_messages_delete_in_another_group(): void
    {
        $a = $this->createGroup(['messages_on' => 1]);
        $b = $this->createGroup(['messages_on' => 1]);
        $admin = $this->createUser(['email' => 'p5@example.test']);
        $this->attachUserToGroup($admin, $a, 'admin');
        $other = $this->createUser(['email' => 'p5b@example.test']);
        $msg = GroupMessage::create(['group_id' => $b->id, 'user_id' => $other->id, 'message' => 'hello', 'priority' => 0]);

        Livewire::actingAs($admin)->test(Messages::class, ['group' => $a])
            ->call('deleteMessage', $msg->id);

        $this->assertNull($msg->fresh()->message, 'B message survived');
    }

    public function test_probe_modal_bulk_accepts_another_groups_event(): void
    {
        $a = $this->createGroup();
        $b = $this->createGroup();
        $admin = $this->createUser(['email' => 'p6@example.test']);
        $this->attachUserToGroup($admin, $a, 'admin');
        $other = $this->createUser(['email' => 'p6b@example.test']);
        $this->attachUserToGroup($other, $b, 'member');
        $date = now()->addDays(3)->toDateString();
        $this->createEventDate($a, $date);
        $this->actingAs($other);
        $event = $this->createEventInRange($b, $other, $date, '08:00', '10:00', false);

        Livewire::actingAs($admin)->test(Modal::class)
            ->call('openModal', $date, $a->id)
            ->set('bulk_ids', [$event->id => $event->id])
            ->call('acceptBulkFinal');

        $this->assertSame(1, (int) $event->fresh()->status, 'B event not accepted');
    }

    public function test_probe_event_edit_deletes_another_groups_event(): void
    {
        $a = $this->createGroup();
        $b = $this->createGroup();
        $helper = $this->createUser(['email' => 'p7@example.test']);
        $this->attachUserToGroup($helper, $a, 'helper');
        $other = $this->createUser(['email' => 'p7b@example.test']);
        $this->attachUserToGroup($other, $b, 'member');
        $date = now()->addDays(3)->toDateString();
        $this->createEventDate($a, $date);
        $this->actingAs($other);
        $event = $this->createEventInRange($b, $other, $date, '08:00', '10:00');

        $component = Livewire::actingAs($helper)->test(EventEdit::class, ['groupId' => $a->id, 'date' => $date]);
        // One request, as the browser sends it: syncInput + callMethod.
        $response = $component->callEndpoint('POST', '/livewire/message/events.event-edit', [
            'fingerprint' => $component->payload['fingerprint'],
            'serverMemo' => $component->payload['serverMemo'],
            'updates' => [
                ['type' => 'syncInput', 'payload' => ['id' => 'a1', 'name' => 'eventId', 'value' => $event->id]],
                ['type' => 'callMethod', 'payload' => ['id' => 'a2', 'method' => 'deleteConfirmed', 'params' => []]],
            ],
        ]);
        fwrite(STDERR, "
[event-edit] status ".$response->getStatusCode().' '.($response->exception ? $response->exception->getMessage() : '')."
");

        $this->assertNull(Event::find($event->id), 'B event survived');
    }
}
