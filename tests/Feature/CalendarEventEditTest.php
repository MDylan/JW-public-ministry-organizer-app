<?php

namespace Tests\Feature;

use App\Http\Livewire\Events\EventEdit;
use App\Models\Event;
use Livewire\Livewire;

class CalendarEventEditTest extends FeatureTestCase
{
    // A korábbi privát makeGroupDate() helyett a közös
    // BuildsDomainFixtures::createEventDate() segédet használjuk - ugyanazokkal
    // az alapértékekkel, de az összes eseményteszt számára elérhetően.

    // =========================================================================
    // 1. Esemény létrehozás
    // =========================================================================

    public function test_create_event_is_accepted_when_group_needs_no_approval(): void
    {
        $user = $this->createUser(['email' => 'ee-create-auto@example.test']);
        $group = $this->createGroup(['need_approval' => 0]);
        $this->attachUserToGroup($user, $group, 'member', true);

        $date = now()->addDay()->toDateString();
        $this->createEventDate($group, $date);
        $startTs = strtotime($date.' 09:00:00');
        $endTs   = strtotime($date.' 10:00:00');

        $this->actingAs($user);

        Livewire::actingAs($user)
            ->test(EventEdit::class, ['groupId' => $group->id, 'date' => $date])
            ->call('setStart', $startTs)
            ->set('state.end', $endTs)
            ->call('saveEvent')
            ->assertHasNoErrors()
            ->assertDispatchedBrowserEvent('success');

        $this->assertDatabaseHas('events', [
            'group_id' => $group->id,
            'user_id'  => $user->id,
            'status'   => 1,
        ]);
    }

    public function test_create_event_is_pending_when_group_requires_approval(): void
    {
        $user = $this->createUser(['email' => 'ee-create-pending@example.test']);
        $group = $this->createGroup(['need_approval' => 1]);
        $this->attachUserToGroup($user, $group, 'member', true);

        $date = now()->addDay()->toDateString();
        $this->createEventDate($group, $date);
        $startTs = strtotime($date.' 09:00:00');
        $endTs   = strtotime($date.' 10:00:00');

        $this->actingAs($user);

        Livewire::actingAs($user)
            ->test(EventEdit::class, ['groupId' => $group->id, 'date' => $date])
            ->call('setStart', $startTs)
            ->set('state.end', $endTs)
            ->call('saveEvent')
            ->assertHasNoErrors();

        $this->assertDatabaseHas('events', [
            'group_id' => $group->id,
            'user_id'  => $user->id,
            'status'   => 0,
        ]);
    }

    // =========================================================================
    // 2. Esemény szerkesztés
    // =========================================================================

    public function test_edit_event_updates_existing_event(): void
    {
        $user = $this->createUser(['email' => 'ee-edit@example.test']);
        $group = $this->createGroup(['need_approval' => 0]);
        $this->attachUserToGroup($user, $group, 'member', true);

        $date = now()->addDay()->toDateString();
        $this->createEventDate($group, $date);

        $this->actingAs($user);
        $event = Event::factory()
            ->forGroup($group)
            ->forUser($user)
            ->accepted()
            ->onDate($date)
            ->create([
                'start' => $date.' 09:00:00',
                'end'   => $date.' 10:00:00',
            ]);

        $newEndTs = strtotime($date.' 11:00:00');

        Livewire::actingAs($user)
            ->test(EventEdit::class, ['groupId' => $group->id, 'date' => $date])
            ->call('editForm', $event->id)
            ->set('state.end', $newEndTs)
            ->call('saveEvent')
            ->assertHasNoErrors()
            ->assertDispatchedBrowserEvent('success');

        $this->assertDatabaseHas('events', [
            'id'  => $event->id,
            'end' => date('Y-m-d H:i', $newEndTs),
        ]);
    }

    // =========================================================================
    // 3. Esemény törlés
    // =========================================================================

    public function test_delete_event_soft_deletes_record(): void
    {
        $user = $this->createUser(['email' => 'ee-delete@example.test']);
        $group = $this->createGroup(['need_approval' => 0]);
        $this->attachUserToGroup($user, $group, 'member', true);

        $date = now()->addDay()->toDateString();
        $this->createEventDate($group, $date);

        $this->actingAs($user);
        $event = Event::factory()
            ->forGroup($group)
            ->forUser($user)
            ->accepted()
            ->onDate($date)
            ->create();

        Livewire::actingAs($user)
            ->test(EventEdit::class, ['groupId' => $group->id, 'date' => $date])
            ->call('editForm', $event->id)
            ->call('deleteConfirmed')
            ->assertDispatchedBrowserEvent('success');

        $this->assertSoftDeleted('events', ['id' => $event->id]);
    }

    public function test_member_cannot_delete_another_members_event(): void
    {
        $attacker = $this->createUser(['email' => 'ee-attacker@example.test']);
        $owner    = $this->createUser(['email' => 'ee-owner@example.test']);
        $group = $this->createGroup(['need_approval' => 0]);
        $this->attachUserToGroup($attacker, $group, 'member', true);
        $this->attachUserToGroup($owner, $group, 'member', true);

        $date = now()->addDay()->toDateString();
        $this->createEventDate($group, $date);

        $this->actingAs($owner);
        $event = Event::factory()
            ->forGroup($group)
            ->forUser($owner)
            ->accepted()
            ->onDate($date)
            ->create();

        Livewire::actingAs($attacker)
            ->test(EventEdit::class, ['groupId' => $group->id, 'date' => $date])
            ->call('editForm', $event->id)
            ->call('deleteConfirmed');

        $this->assertDatabaseHas('events', ['id' => $event->id, 'deleted_at' => null]);
    }

    public function test_admin_can_delete_any_members_event(): void
    {
        $roler  = $this->createUser(['email' => 'ee-roler-del@example.test']);
        $member = $this->createUser(['email' => 'ee-member-del@example.test']);
        $group = $this->createGroup(['need_approval' => 0]);
        $this->attachUserToGroup($roler, $group, 'roler', true);
        $this->attachUserToGroup($member, $group, 'member', true);

        $date = now()->addDay()->toDateString();
        $this->createEventDate($group, $date);

        $this->actingAs($member);
        $event = Event::factory()
            ->forGroup($group)
            ->forUser($member)
            ->accepted()
            ->onDate($date)
            ->create();

        Livewire::actingAs($roler)
            ->test(EventEdit::class, ['groupId' => $group->id, 'date' => $date])
            ->call('editForm', $event->id)
            ->call('deleteConfirmed')
            ->assertDispatchedBrowserEvent('success');

        $this->assertSoftDeleted('events', ['id' => $event->id]);
    }

    // =========================================================================
    // 4. Jogosultság: status és user_id beállítása
    // =========================================================================

    public function test_admin_can_force_accepted_status_on_create_with_approval_group(): void
    {
        $roler = $this->createUser(['email' => 'ee-roler-status@example.test']);
        $group = $this->createGroup(['need_approval' => 1]);
        $this->attachUserToGroup($roler, $group, 'roler', true);

        $date = now()->addDay()->toDateString();
        $this->createEventDate($group, $date);
        $startTs = strtotime($date.' 09:00:00');
        $endTs   = strtotime($date.' 10:00:00');

        $this->actingAs($roler);

        Livewire::actingAs($roler)
            ->test(EventEdit::class, ['groupId' => $group->id, 'date' => $date])
            ->call('setStart', $startTs)
            ->set('state.end', $endTs)
            ->set('state.status', 1)
            ->call('saveEvent')
            ->assertHasNoErrors();

        $this->assertDatabaseHas('events', [
            'group_id' => $group->id,
            'user_id'  => $roler->id,
            'status'   => 1,
        ]);
    }

    public function test_member_cannot_force_accepted_status_on_create(): void
    {
        $user = $this->createUser(['email' => 'ee-member-status@example.test']);
        $group = $this->createGroup(['need_approval' => 1]);
        $this->attachUserToGroup($user, $group, 'member', true);

        $date = now()->addDay()->toDateString();
        $this->createEventDate($group, $date);
        $startTs = strtotime($date.' 09:00:00');
        $endTs   = strtotime($date.' 10:00:00');

        $this->actingAs($user);

        Livewire::actingAs($user)
            ->test(EventEdit::class, ['groupId' => $group->id, 'date' => $date])
            ->call('setStart', $startTs)
            ->set('state.end', $endTs)
            ->set('state.status', 1)
            ->call('saveEvent')
            ->assertHasNoErrors();

        // Member nem tud status=1-et kényszeríteni
        $this->assertDatabaseHas('events', [
            'group_id' => $group->id,
            'user_id'  => $user->id,
            'status'   => 0,
        ]);
    }

    public function test_admin_users_list_is_populated(): void
    {
        $roler = $this->createUser(['email' => 'ee-roler-users@example.test']);
        $group = $this->createGroup();
        $this->attachUserToGroup($roler, $group, 'roler', true);

        $date = now()->addDay()->toDateString();
        $this->createEventDate($group, $date);

        Livewire::actingAs($roler)
            ->test(EventEdit::class, ['groupId' => $group->id, 'date' => $date])
            ->assertSet('users', function ($users) {
                return count($users) > 0;
            });
    }

    public function test_member_users_list_is_empty(): void
    {
        $user = $this->createUser(['email' => 'ee-member-users@example.test']);
        $group = $this->createGroup();
        $this->attachUserToGroup($user, $group, 'member', true);

        $date = now()->addDay()->toDateString();
        $this->createEventDate($group, $date);

        Livewire::actingAs($user)
            ->test(EventEdit::class, ['groupId' => $group->id, 'date' => $date])
            ->assertSet('users', []);
    }

    // =========================================================================
    // 5. Validáció
    // =========================================================================

    public function test_validation_fails_when_start_and_end_are_missing(): void
    {
        $user = $this->createUser(['email' => 'ee-val-missing@example.test']);
        $group = $this->createGroup();
        $this->attachUserToGroup($user, $group, 'member', true);

        $date = now()->addDay()->toDateString();
        $this->createEventDate($group, $date);

        Livewire::actingAs($user)
            ->test(EventEdit::class, ['groupId' => $group->id, 'date' => $date])
            ->call('saveEvent')
            ->assertHasErrors(['start', 'end']);
    }

    public function test_validation_fails_when_end_is_before_start(): void
    {
        $user = $this->createUser(['email' => 'ee-val-order@example.test']);
        $group = $this->createGroup();
        $this->attachUserToGroup($user, $group, 'member', true);

        $date = now()->addDay()->toDateString();
        $this->createEventDate($group, $date);
        $startTs = strtotime($date.' 10:00:00');
        $endTs   = strtotime($date.' 09:00:00'); // vége < kezdet

        Livewire::actingAs($user)
            ->test(EventEdit::class, ['groupId' => $group->id, 'date' => $date])
            ->set('state.start', $startTs)
            ->set('state.end', $endTs)
            ->call('saveEvent')
            ->assertHasErrors(['start', 'end']);
    }

    public function test_validation_fails_when_comment_exceeds_80_chars(): void
    {
        $user = $this->createUser(['email' => 'ee-val-comment@example.test']);
        $group = $this->createGroup();
        $this->attachUserToGroup($user, $group, 'member', true);

        $date = now()->addDay()->toDateString();
        $this->createEventDate($group, $date);
        $startTs = strtotime($date.' 09:00:00');
        $endTs   = strtotime($date.' 10:00:00');

        Livewire::actingAs($user)
            ->test(EventEdit::class, ['groupId' => $group->id, 'date' => $date])
            ->call('setStart', $startTs)
            ->set('state.end', $endTs)
            ->set('state.comment', str_repeat('a', 81))
            ->call('saveEvent')
            ->assertHasErrors(['comment']);
    }

    public function test_validation_passes_with_exactly_80_char_comment(): void
    {
        $user = $this->createUser(['email' => 'ee-val-comment80@example.test']);
        $group = $this->createGroup(['need_approval' => 0]);
        $this->attachUserToGroup($user, $group, 'member', true);

        $date = now()->addDay()->toDateString();
        $this->createEventDate($group, $date);
        $startTs = strtotime($date.' 09:00:00');
        $endTs   = strtotime($date.' 10:00:00');

        $this->actingAs($user);

        Livewire::actingAs($user)
            ->test(EventEdit::class, ['groupId' => $group->id, 'date' => $date])
            ->call('setStart', $startTs)
            ->set('state.end', $endTs)
            ->set('state.comment', str_repeat('a', 80))
            ->call('saveEvent')
            ->assertHasNoErrors();
    }

    // =========================================================================
    // 6. Törlés megerősítés browser event
    // =========================================================================

    public function test_confirm_delete_dispatches_browser_event(): void
    {
        $user = $this->createUser(['email' => 'ee-confirm-del@example.test']);
        $group = $this->createGroup();
        $this->attachUserToGroup($user, $group, 'member', true);

        $date = now()->addDay()->toDateString();
        $this->createEventDate($group, $date);

        $this->actingAs($user);
        $event = Event::factory()
            ->forGroup($group)
            ->forUser($user)
            ->accepted()
            ->onDate($date)
            ->create();

        Livewire::actingAs($user)
            ->test(EventEdit::class, ['groupId' => $group->id, 'date' => $date])
            ->call('editForm', $event->id)
            ->call('confirmEventDelete')
            ->assertDispatchedBrowserEvent('show-deletion-confirmation');
    }
}
