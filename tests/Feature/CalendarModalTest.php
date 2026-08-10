<?php

namespace Tests\Feature;

use App\Http\Livewire\Events\Modal as EventsModal;
use App\Models\GroupDate;
use Livewire\Livewire;

class CalendarModalTest extends FeatureTestCase
{
    // Helper: creates a GroupDate factory
    private function makeGroupDate($group, string $date): void
    {
        GroupDate::factory()->create([
            'group_id' => $group->id,
            'date' => $date,
            'date_start' => $date.' 08:00:00',
            'date_end' => $date.' 12:00:00',
        ]);
    }

    // =========================================================================
    // 1. Date navigation
    // =========================================================================

    public function test_set_date_navigates_to_new_date_and_resets_active_tab(): void
    {
        $user = $this->createUser(['email' => 'modal-setdate@example.test']);
        $group = $this->createGroup();
        $this->attachUserToGroup($user, $group, 'member', true);

        $date1 = now()->addDay()->toDateString();
        $date2 = now()->addDays(2)->toDateString();
        $this->makeGroupDate($group, $date1);
        $this->makeGroupDate($group, $date2);

        Livewire::actingAs($user)
            ->test(EventsModal::class)
            ->call('openModal', $date1, $group->id)
            ->call('setDate', $date2)
            ->assertSet('date', $date2)
            ->assertSet('active_tab', '');
    }

    // =========================================================================
    // 2. Time slot selection (setStart)
    // =========================================================================

    public function test_set_start_sets_active_tab_to_event_and_disables_polling(): void
    {
        $user = $this->createUser(['email' => 'modal-setstart@example.test']);
        $group = $this->createGroup();
        $this->attachUserToGroup($user, $group, 'member', true);

        $date = now()->addDay()->toDateString();
        $this->makeGroupDate($group, $date);

        Livewire::actingAs($user)
            ->test(EventsModal::class)
            ->call('openModal', $date, $group->id)
            ->call('setStart', strtotime($date.' 09:00'))
            ->assertSet('active_tab', 'event')
            ->assertSet('polling', false)
            ->assertEmittedTo('events.event-edit', 'setStart');
    }

    // =========================================================================
    // 3. Polling logic based on date
    // =========================================================================

    public function test_polling_is_true_after_opening_future_date(): void
    {
        $user = $this->createUser(['email' => 'modal-poll-future@example.test']);
        $group = $this->createGroup();
        $this->attachUserToGroup($user, $group, 'member', true);

        $date = now()->addDay()->toDateString();
        $this->makeGroupDate($group, $date);

        Livewire::actingAs($user)
            ->test(EventsModal::class)
            ->call('openModal', $date, $group->id)
            ->assertSet('polling', true);
    }

    public function test_polling_is_false_after_opening_past_date(): void
    {
        $user = $this->createUser(['email' => 'modal-poll-past@example.test']);
        $group = $this->createGroup();
        $this->attachUserToGroup($user, $group, 'member', true);

        $date = now()->subDay()->toDateString();
        $this->makeGroupDate($group, $date);

        Livewire::actingAs($user)
            ->test(EventsModal::class)
            ->call('openModal', $date, $group->id)
            ->assertSet('polling', false);
    }

    // =========================================================================
    // 4. Cancelling the edit
    // =========================================================================

    public function test_cancel_edit_resets_active_tab_and_resumes_polling(): void
    {
        $user = $this->createUser(['email' => 'modal-cancel-edit@example.test']);
        $group = $this->createGroup();
        $this->attachUserToGroup($user, $group, 'member', true);

        $date = now()->addDay()->toDateString();
        $this->makeGroupDate($group, $date);

        Livewire::actingAs($user)
            ->test(EventsModal::class)
            ->call('openModal', $date, $group->id)
            ->call('setStart', strtotime($date.' 09:00'))
            ->assertSet('active_tab', 'event')
            ->call('cancelEdit')
            ->assertSet('active_tab', '')
            ->assertSet('polling', true);
    }

    // =========================================================================
    // 5. Empty modal state
    // =========================================================================

    public function test_modal_renders_empty_view_when_no_date_set(): void
    {
        $user = $this->createUser(['email' => 'modal-empty@example.test']);

        Livewire::actingAs($user)
            ->test(EventsModal::class)
            ->assertSet('date', null)
            ->assertSet('show_content', false);
    }
}
