<?php

namespace Tests\Feature;

use App\Http\Livewire\Groups\SpecialDateModal;
use App\Models\GroupDate;
use Illuminate\Support\Facades\Queue;
use Livewire\Livewire;
class GroupSpecialDateModalTest extends FeatureTestCase
{
    // =========================================================================
    // 1. Hozzáférés-vezérlés
    // =========================================================================

    public function test_editor_can_open_modal(): void
    {
        $editor = $this->createUser(['email' => 'special-open@example.test']);
        $group = $this->createGroup();
        $this->attachUserToGroup($editor, $group, 'roler', true);

        Livewire::actingAs($editor)
            ->test(SpecialDateModal::class, ['groupId' => $group->id])
            ->call('openModal')
            ->assertDispatchedBrowserEvent('show-modal');
    }

    public function test_member_cannot_open_modal(): void
    {
        $member = $this->createUser(['email' => 'special-member@example.test']);
        $group = $this->createGroup();
        $this->attachUserToGroup($member, $group, 'member', true);

        // abort(403) a getGroupData()-ban fut le — a show-modal browser event nem dispatched
        Livewire::actingAs($member)
            ->test(SpecialDateModal::class, ['groupId' => $group->id])
            ->call('openModal')
            ->assertNotDispatchedBrowserEvent('show-modal');
    }

    // =========================================================================
    // 2. Különleges dátum mentés
    // =========================================================================

    public function test_editor_can_save_new_special_date(): void
    {
        Queue::fake();

        $editor = $this->createUser(['email' => 'special-save@example.test']);
        $group = $this->createGroup();
        $this->attachUserToGroup($editor, $group, 'roler', true);
        $date = now()->addDays(2)->toDateString();

        Livewire::actingAs($editor)
            ->test(SpecialDateModal::class, ['groupId' => $group->id])
            ->call('openModal', $date)
            ->set('state.date', $date)
            ->set('state.date_status', 2)
            ->set('state.date_start', '08:00')
            ->set('state.date_end', '12:00')
            ->set('state.note', 'Teszt különleges nap')
            ->call('saveDate')
            ->assertHasNoErrors()
            ->assertDispatchedBrowserEvent('hide-modal');

        $this->assertDatabaseHas('group_dates', [
            'group_id' => $group->id,
            'date' => $date,
            'note' => 'Teszt különleges nap',
        ]);
    }

    public function test_editor_can_update_existing_special_date(): void
    {
        Queue::fake();

        $editor = $this->createUser(['email' => 'special-update@example.test']);
        $group = $this->createGroup();
        $this->attachUserToGroup($editor, $group, 'roler', true);
        $date = now()->addDays(3)->toDateString();

        GroupDate::factory()->create([
            'group_id' => $group->id,
            'date' => $date,
            'date_status' => 2,
            'note' => 'Régi megjegyzés',
        ]);

        Livewire::actingAs($editor)
            ->test(SpecialDateModal::class, ['groupId' => $group->id])
            ->call('openModal', $date)
            ->set('state.date', $date)
            ->set('state.note', 'Frissített megjegyzés')
            ->call('saveDate')
            ->assertHasNoErrors()
            ->assertDispatchedBrowserEvent('hide-modal');

        $this->assertDatabaseHas('group_dates', [
            'group_id' => $group->id,
            'date' => $date,
            'note' => 'Frissített megjegyzés',
        ]);
    }

    // =========================================================================
    // 3. Validáció
    // =========================================================================

    public function test_savedate_validation_fails_when_note_is_missing(): void
    {
        $editor = $this->createUser(['email' => 'special-val@example.test']);
        $group = $this->createGroup();
        $this->attachUserToGroup($editor, $group, 'roler', true);
        $date = now()->addDays(2)->toDateString();

        Livewire::actingAs($editor)
            ->test(SpecialDateModal::class, ['groupId' => $group->id])
            ->call('openModal', $date)
            ->set('state.date', $date)
            ->set('state.date_start', '08:00')
            ->set('state.date_end', '12:00')
            ->call('saveDate')
            ->assertHasErrors(['note']);
    }

    public function test_savedate_validation_fails_when_date_is_in_the_past(): void
    {
        $editor = $this->createUser(['email' => 'special-val-past@example.test']);
        $group = $this->createGroup();
        $this->attachUserToGroup($editor, $group, 'roler', true);

        // openModal nélkül közvetlenül state-t állítjuk: múltbeli dátum
        Livewire::actingAs($editor)
            ->test(SpecialDateModal::class, ['groupId' => $group->id])
            ->call('openModal')
            ->set('state.date', now()->subDays(3)->toDateString())
            ->set('state.date_start', '08:00')
            ->set('state.date_end', '12:00')
            ->set('state.note', 'Múltbeli nap')
            ->call('saveDate')
            ->assertHasErrors(['date']);
    }

    // =========================================================================
    // 4. Különleges dátum törlés
    // =========================================================================

    public function test_editor_can_delete_future_date(): void
    {
        $editor = $this->createUser(['email' => 'special-del@example.test']);
        $group = $this->createGroup();
        $this->attachUserToGroup($editor, $group, 'roler', true);
        $date = now()->addDays(2)->toDateString();

        GroupDate::factory()->create([
            'group_id' => $group->id,
            'date' => $date,
        ]);

        Livewire::actingAs($editor)
            ->test(SpecialDateModal::class, ['groupId' => $group->id])
            ->call('openModal', $date)
            ->call('deleteDate')
            ->assertDispatchedBrowserEvent('hide-modal');

        $this->assertDatabaseMissing('group_dates', [
            'group_id' => $group->id,
            'date' => $date,
        ]);
    }

    public function test_cannot_delete_past_date(): void
    {
        $editor = $this->createUser(['email' => 'special-del-past@example.test']);
        $group = $this->createGroup();
        $this->attachUserToGroup($editor, $group, 'roler', true);
        $date = now()->subDays(2)->toDateString();

        GroupDate::factory()->create([
            'group_id' => $group->id,
            'date' => $date,
        ]);

        Livewire::actingAs($editor)
            ->test(SpecialDateModal::class, ['groupId' => $group->id])
            ->call('openModal', $date)
            ->call('deleteDate')
            ->assertDispatchedBrowserEvent('error');

        $this->assertDatabaseHas('group_dates', [
            'group_id' => $group->id,
            'date' => $date,
        ]);
    }

    // =========================================================================
    // 5. Törlés megerősítés
    // =========================================================================

    public function test_delete_date_confirmation_dispatches_browser_event(): void
    {
        $editor = $this->createUser(['email' => 'special-confirm@example.test']);
        $group = $this->createGroup();
        $this->attachUserToGroup($editor, $group, 'roler', true);
        $date = now()->addDays(2)->toDateString();

        Livewire::actingAs($editor)
            ->test(SpecialDateModal::class, ['groupId' => $group->id])
            ->call('openModal', $date)
            ->call('deleteDateConfirmation')
            ->assertDispatchedBrowserEvent('show-deletion-confirmation');
    }
}
