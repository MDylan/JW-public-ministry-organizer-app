<?php

namespace Tests\Feature;

use App\Http\Livewire\Groups\PosterEditModal;
use App\Models\GroupPosters;
use Livewire\Livewire;
class GroupPosterEditModalTest extends FeatureTestCase
{
    // =========================================================================
    // 1. Access control
    // =========================================================================

    public function test_editor_can_open_modal_for_new_poster(): void
    {
        $editor = $this->createUser(['email' => 'poster-editor@example.test']);
        $group = $this->createGroup();
        $this->attachUserToGroup($editor, $group, 'roler', true);

        Livewire::actingAs($editor)
            ->test(PosterEditModal::class)
            ->call('openModal', $group->id)
            ->assertSet('openModal', true)
            ->assertDispatchedBrowserEvent('show-modal');
    }

    public function test_member_cannot_open_modal(): void
    {
        $member = $this->createUser(['email' => 'poster-member@example.test']);
        $group = $this->createGroup();
        $this->attachUserToGroup($member, $group, 'member', true);

        // abort(403) runs in getGroupData() before $this->openModal is set to true
        Livewire::actingAs($member)
            ->test(PosterEditModal::class)
            ->call('openModal', $group->id)
            ->assertSet('openModal', false);
    }

    // =========================================================================
    // 2. Poster creation
    // =========================================================================

    public function test_editor_can_create_poster(): void
    {
        $editor = $this->createUser(['email' => 'poster-create@example.test']);
        $group = $this->createGroup();
        $this->attachUserToGroup($editor, $group, 'roler', true);

        Livewire::actingAs($editor)
            ->test(PosterEditModal::class)
            ->call('openModal', $group->id)
            ->set('state.show_date', now()->toDateString())
            ->set('state.info', 'Teszt hirdetmény tartalom')
            ->call('savePoster')
            ->assertHasNoErrors()
            ->assertDispatchedBrowserEvent('hide-modal');

        // info field is encrypted, so we check via the model
        $poster = GroupPosters::where('group_id', $group->id)->first();
        $this->assertNotNull($poster);
        $this->assertEquals('Teszt hirdetmény tartalom', $poster->info);
    }

    // =========================================================================
    // 3. Poster editing
    // =========================================================================

    public function test_editor_can_edit_existing_poster(): void
    {
        $editor = $this->createUser(['email' => 'poster-edit@example.test']);
        $group = $this->createGroup();
        $this->attachUserToGroup($editor, $group, 'roler', true);
        $poster = $this->createGroupPoster($group);

        Livewire::actingAs($editor)
            ->test(PosterEditModal::class)
            ->call('openModal', $group->id, $poster->id)
            ->set('state.info', 'Frissített hirdetmény tartalom')
            ->call('savePoster')
            ->assertHasNoErrors()
            ->assertDispatchedBrowserEvent('hide-modal');

        // info field is encrypted, so we check via the model
        $this->assertEquals('Frissített hirdetmény tartalom', $poster->fresh()->info);
    }

    // =========================================================================
    // 4. Poster deletion
    // =========================================================================

    public function test_editor_can_delete_poster(): void
    {
        $editor = $this->createUser(['email' => 'poster-delete@example.test']);
        $group = $this->createGroup();
        $this->attachUserToGroup($editor, $group, 'roler', true);
        $poster = $this->createGroupPoster($group);

        Livewire::actingAs($editor)
            ->test(PosterEditModal::class)
            ->call('openModal', $group->id, $poster->id)
            ->call('deletePoster')
            ->assertDispatchedBrowserEvent('hide-modal');

        $this->assertDatabaseMissing('group_posters', ['id' => $poster->id]);
    }

    public function test_delete_poster_confirmation_dispatches_browser_event(): void
    {
        $editor = $this->createUser(['email' => 'poster-confirm@example.test']);
        $group = $this->createGroup();
        $this->attachUserToGroup($editor, $group, 'roler', true);
        $poster = $this->createGroupPoster($group);

        Livewire::actingAs($editor)
            ->test(PosterEditModal::class)
            ->call('openModal', $group->id, $poster->id)
            ->call('deletePosterConfirmation')
            ->assertDispatchedBrowserEvent('show-deletion-confirmation');
    }

    // =========================================================================
    // 5. Validation
    // =========================================================================

    public function test_validation_fails_when_info_is_missing(): void
    {
        $editor = $this->createUser(['email' => 'poster-val-info@example.test']);
        $group = $this->createGroup();
        $this->attachUserToGroup($editor, $group, 'roler', true);

        Livewire::actingAs($editor)
            ->test(PosterEditModal::class)
            ->call('openModal', $group->id)
            ->set('state.show_date', now()->toDateString())
            ->call('savePoster')
            ->assertHasErrors(['info']);
    }

    public function test_validation_fails_when_hide_date_is_before_show_date(): void
    {
        $editor = $this->createUser(['email' => 'poster-val-hide@example.test']);
        $group = $this->createGroup();
        $this->attachUserToGroup($editor, $group, 'roler', true);

        Livewire::actingAs($editor)
            ->test(PosterEditModal::class)
            ->call('openModal', $group->id)
            ->set('state.show_date', now()->addDays(5)->toDateString())
            ->set('state.hide_date', now()->toDateString())
            ->set('state.info', 'Teszt tartalom')
            ->call('savePoster')
            ->assertHasErrors(['hide_date']);
    }

    // =========================================================================
    // 6. Modal back-navigation (hiddenModal)
    // =========================================================================

    public function test_hidden_modal_emits_to_events_modal_when_from_date_is_set(): void
    {
        $editor = $this->createUser(['email' => 'poster-hidden@example.test']);
        $group = $this->createGroup();
        $this->attachUserToGroup($editor, $group, 'roler', true);
        $date = now()->addDay()->toDateString();

        Livewire::actingAs($editor)
            ->test(PosterEditModal::class)
            ->call('hiddenModal', ['fromDate' => $date, 'groupId' => $group->id])
            ->assertEmittedTo('events.modal', 'openModal', $date, $group->id);
    }

    // =========================================================================
    // 7. Opening from a date (openModalFromDate)
    // =========================================================================

    public function test_open_modal_from_date_sets_from_date_and_opens_modal(): void
    {
        $editor = $this->createUser(['email' => 'poster-fromdate@example.test']);
        $group = $this->createGroup();
        $this->attachUserToGroup($editor, $group, 'roler', true);
        $date = now()->addDay()->toDateString();

        Livewire::actingAs($editor)
            ->test(PosterEditModal::class)
            ->call('openModalFromDate', $group->id, $date)
            ->assertSet('fromDate', $date)
            ->assertSet('openModal', true);
    }
}
