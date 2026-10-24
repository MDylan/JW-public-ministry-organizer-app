<?php

namespace Tests\Feature\Security;

use App\Livewire\Events\EventEdit;
use App\Livewire\Events\Modal;
use App\Livewire\Home;
use App\Models\Event;
use App\Models\Group;
use App\Models\GroupUser;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Livewire\Features\SupportLockedProperties\CannotUpdateLockedPropertyException;
use Livewire\Livewire;
use Tests\Feature\FeatureTestCase;

/**
 * Roadmap TODO 70: the day modal and the event form act only on their own
 * group, for the current user's current role.
 *
 * A Livewire action does not pass the page route's groupMember/groupAdmin,
 * and an unlocked public property is the client's to rewrite. Each case
 * below was an attack that worked before TODO 70 - measured with a
 * throwaway probe - and is now refused: deleting or rewriting another
 * group's event from one's own form, bulk-accepting another group's
 * pending events, booking in the name of someone outside the group,
 * reading a group one was only invited to, and keeping a removed role on
 * an open page.
 */
class EventAuthorizationTest extends FeatureTestCase
{
    private Group $own;
    private Group $other;
    private string $date;

    protected function setUp(): void
    {
        parent::setUp();

        $this->own = $this->createGroup(['need_approval' => 1]);
        $this->other = $this->createGroup(['need_approval' => 1]);
        $this->date = now()->addDays(3)->toDateString();
        $this->createEventDate($this->own, $this->date);
        $this->createEventDate($this->other, $this->date);
    }

    private function memberOf(Group $group, string $role, string $email, bool $accepted = true): User
    {
        $user = $this->createUser(['email' => $email]);
        $this->attachUserToGroup($user, $group, $role, $accepted);

        return $user;
    }

    private function eventOf(Group $group, string $email, bool $accepted = true): Event
    {
        $owner = $this->memberOf($group, 'member', $email);
        $this->actingAs($owner);

        return $this->createEventInRange($group, $owner, $this->date, '09:00', '10:00', $accepted);
    }

    private function form(User $user, Group $group)
    {
        return Livewire::actingAs($user)->test(EventEdit::class, ['groupId' => $group->id, 'date' => $this->date]);
    }

    // --- the event form -------------------------------------------------

    public function test_the_forms_ids_and_role_cannot_be_written_by_the_client(): void
    {
        $helper = $this->memberOf($this->own, 'helper', 'ea-locked@example.test');
        $foreign = $this->eventOf($this->other, 'ea-locked-owner@example.test');

        foreach (['eventId' => $foreign->id, 'groupId' => $this->other->id, 'role' => 'admin', 'editEvent.id' => $foreign->id] as $property => $value) {
            try {
                $this->form($helper, $this->own)->set($property, $value);
                $this->fail("{$property} was writable.");
            } catch (CannotUpdateLockedPropertyException) {
                $this->addToAssertionCount(1);
            }
        }
    }

    public function test_a_helper_cannot_open_or_delete_another_groups_event(): void
    {
        $helper = $this->memberOf($this->own, 'helper', 'ea-delete@example.test');
        $foreign = $this->eventOf($this->other, 'ea-delete-owner@example.test');

        // editForm() is the listener the day modal calls with an event id.
        $this->form($helper, $this->own)
            ->call('editForm', $foreign->id)
            ->assertSet('editEvent', null)
            ->call('deleteConfirmed');

        $this->assertNotNull(Event::find($foreign->id));
    }

    public function test_a_helper_still_deletes_an_event_of_the_own_group(): void
    {
        $helper = $this->memberOf($this->own, 'helper', 'ea-delete-own@example.test');
        $event = $this->eventOf($this->own, 'ea-delete-own-owner@example.test');

        $this->form($helper, $this->own)
            ->call('editForm', $event->id)
            ->call('deleteConfirmed')
            ->assertDispatched('success');

        $this->assertNull(Event::find($event->id));
    }

    public function test_a_servant_cannot_book_for_someone_outside_the_group(): void
    {
        $helper = $this->memberOf($this->own, 'helper', 'ea-book@example.test');
        $outsider = $this->memberOf($this->other, 'member', 'ea-book-outsider@example.test');

        $this->form($helper, $this->own)
            ->call('setStart', strtotime($this->date.' 09:00:00'))
            ->set('state.user_id', $outsider->id)
            ->set('state.end', strtotime($this->date.' 10:00:00'))
            ->call('saveEvent')
            ->assertHasErrors('user_id');

        $this->assertSame(0, Event::where('user_id', $outsider->id)->count());
    }

    public function test_a_member_books_only_for_themselves(): void
    {
        $member = $this->memberOf($this->own, 'member', 'ea-self@example.test');
        $colleague = $this->memberOf($this->own, 'member', 'ea-self-colleague@example.test');

        $this->form($member, $this->own)
            ->call('setStart', strtotime($this->date.' 09:00:00'))
            ->set('state.user_id', $colleague->id)
            ->set('state.end', strtotime($this->date.' 10:00:00'))
            ->call('saveEvent');

        $this->assertSame(0, Event::where('user_id', $colleague->id)->count());
        $this->assertSame(1, Event::where('user_id', $member->id)->where('group_id', $this->own->id)->count());
    }

    public function test_a_member_does_not_see_another_users_other_events(): void
    {
        $member = $this->memberOf($this->own, 'member', 'ea-others@example.test');
        $foreign = $this->eventOf($this->other, 'ea-others-owner@example.test');

        $this->form($member, $this->own)
            ->call('setStart', strtotime($this->date.' 09:00:00'))
            ->set('state.user_id', $foreign->user_id)
            ->call('change_user')
            ->assertSet('other_events', [])
            ->assertSet('state.user_id', $member->id);
    }

    public function test_a_removed_member_is_refused_on_the_next_action(): void
    {
        $helper = $this->memberOf($this->own, 'helper', 'ea-removed@example.test');
        $form = $this->form($helper, $this->own);

        GroupUser::where('group_id', $this->own->id)->where('user_id', $helper->id)->delete();

        $form->call('createForm')->assertForbidden();
    }

    public function test_the_form_does_not_open_for_a_group_the_user_is_only_invited_to(): void
    {
        $invitee = $this->memberOf($this->own, 'admin', 'ea-invitee@example.test', accepted: false);

        $this->form($invitee, $this->own)->assertSee(__('event.error.invalid_group'));
    }

    // --- the day modal --------------------------------------------------

    public function test_bulk_accept_touches_only_the_own_groups_events(): void
    {
        $admin = $this->memberOf($this->own, 'admin', 'ea-bulk@example.test');
        $foreign = $this->eventOf($this->other, 'ea-bulk-foreign@example.test', accepted: false);
        $own = $this->eventOf($this->own, 'ea-bulk-own@example.test', accepted: false);

        Livewire::actingAs($admin)->test(Modal::class)
            ->call('openModal', $this->date, $this->own->id)
            ->set('bulk_ids', [$foreign->id => $foreign->id, $own->id => $own->id])
            ->call('acceptBulkFinal');

        $this->assertSame(0, (int) $foreign->fresh()->status);
        $this->assertSame(1, (int) $own->fresh()->status);
    }

    public function test_bulk_reject_touches_only_the_own_groups_events(): void
    {
        $admin = $this->memberOf($this->own, 'admin', 'ea-bulkr@example.test');
        $foreign = $this->eventOf($this->other, 'ea-bulkr-foreign@example.test', accepted: false);

        Livewire::actingAs($admin)->test(Modal::class)
            ->call('openModal', $this->date, $this->own->id)
            ->set('bulk_ids', [$foreign->id => $foreign->id])
            ->call('rejectBulkFinal');

        $this->assertSame(0, (int) $foreign->fresh()->status);
    }

    public function test_the_day_modal_shows_nothing_to_an_invitee_or_an_outsider(): void
    {
        $this->eventOf($this->own, 'ea-modal-owner@example.test');

        foreach ([
            $this->memberOf($this->own, 'member', 'ea-modal-invitee@example.test', accepted: false),
            $this->memberOf($this->other, 'admin', 'ea-modal-outsider@example.test'),
        ] as $user) {
            Livewire::actingAs($user)->test(Modal::class)
                ->call('openModal', $this->date, $this->own->id)
                ->assertViewIs('livewire.events.modal-empty');
        }
    }

    public function test_a_poster_of_another_group_cannot_be_marked_read(): void
    {
        $member = $this->memberOf($this->own, 'member', 'ea-poster@example.test');
        $foreign = $this->createGroupPoster($this->other);
        $mine = $this->createGroupPoster($this->own);

        Livewire::actingAs($member)->test(Home::class)
            ->call('togglePosterRead', $foreign->id)
            ->call('togglePosterRead', $mine->id);

        $this->assertSame(0, DB::table('group_poster_reads')->where('poster_id', $foreign->id)->count());
        $this->assertSame(1, DB::table('group_poster_reads')->where('poster_id', $mine->id)->count());
    }
}
