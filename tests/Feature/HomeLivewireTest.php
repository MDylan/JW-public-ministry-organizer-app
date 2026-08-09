<?php

namespace Tests\Feature;

use App\Http\Livewire\Events\Modal as EventsModal;
use App\Http\Livewire\Groups\PosterEditModal;
use App\Http\Livewire\Home;
use App\Models\Event;
use App\Models\GroupDay;
use App\Models\GroupDate;
use App\Models\GroupUser;
use Illuminate\Support\Facades\DB;
use Livewire\Livewire;

class HomeLivewireTest extends FeatureTestCase
{
    // =========================================================================
    // 1. View / route tesztek
    // =========================================================================

    public function test_home_page_renders_for_authenticated_user_with_no_groups(): void
    {
        $user = $this->createUser(['email' => 'home-nogroup@example.test']);

        $this->actingAs($user)
            ->get(route('home.home'))
            ->assertStatus(200)
            ->assertSee(__('app.no_any_groups'));
    }

    public function test_home_page_renders_group_name_for_member(): void
    {
        $user = $this->createUser(['email' => 'home-member@example.test']);
        $group = $this->createGroup();
        $this->attachUserToGroup($user, $group, 'member', true);

        GroupDay::factory()->create(['group_id' => $group->id, 'day_number' => 1]);

        $this->actingAs($user)
            ->get(route('home.home'))
            ->assertStatus(200)
            ->assertSee($group->name);
    }

    public function test_home_page_shows_poster_title_when_active_poster_exists(): void
    {
        $user = $this->createUser(['email' => 'home-poster@example.test']);
        $group = $this->createGroup();
        $this->attachUserToGroup($user, $group, 'member', true);

        $this->createGroupPoster($group, [
            'show_date' => now()->toDateString(),
            'hide_date' => now()->addDays(7)->toDateString(),
        ]);

        $this->actingAs($user)
            ->get(route('home.home'))
            ->assertStatus(200)
            ->assertSee(__('group.poster.title'));
    }

    public function test_home_page_hides_poster_edit_button_for_member(): void
    {
        $user = $this->createUser(['email' => 'home-member-noedit@example.test']);
        $group = $this->createGroup();
        $this->attachUserToGroup($user, $group, 'member', true);

        $this->createGroupPoster($group);

        $response = $this->actingAs($user)
            ->get(route('home.home'))
            ->assertStatus(200);

        // Az "Új hirdetmény" gomb csak admin/roler-nek jelenik meg
        $response->assertDontSee('wire:click="$emitTo(\'groups.poster-edit-modal\', \'openModal\', '.$group->id.')"', false);
    }

    public function test_home_page_shows_poster_edit_button_for_group_admin(): void
    {
        $user = $this->createUser(['email' => 'home-roler-edit@example.test']);
        $group = $this->createGroup();
        $this->attachUserToGroup($user, $group, 'roler', true);

        $response = $this->actingAs($user)
            ->get(route('home.home'))
            ->assertStatus(200);

        $response->assertSee(__('group.poster.button'));
    }

    // =========================================================================
    // 2. Home Livewire komponens logika
    // =========================================================================

    public function test_polling_is_enabled_by_default(): void
    {
        $user = $this->createUser(['email' => 'home-poll-default@example.test']);

        Livewire::actingAs($user)
            ->test(Home::class)
            ->assertSet('polling', true);
    }

    public function test_polling_off_disables_polling(): void
    {
        $user = $this->createUser(['email' => 'home-poll-off@example.test']);

        Livewire::actingAs($user)
            ->test(Home::class)
            ->call('pollingOff')
            ->assertSet('polling', false);
    }

    public function test_polling_on_enables_polling_after_off(): void
    {
        $user = $this->createUser(['email' => 'home-poll-on@example.test']);

        Livewire::actingAs($user)
            ->test(Home::class)
            ->call('pollingOff')
            ->call('pollingOn')
            ->assertSet('polling', true);
    }

    public function test_open_events_modal_disables_polling_and_emits_to_modal(): void
    {
        $user = $this->createUser(['email' => 'home-open-modal@example.test']);
        $group = $this->createGroup();
        $this->attachUserToGroup($user, $group, 'member', true);
        GroupDay::factory()->create(['group_id' => $group->id, 'day_number' => 1]);

        $date = today()->toDateString();

        Livewire::actingAs($user)
            ->test(Home::class)
            ->call('openEventsModal', $group->id, $date)
            ->assertSet('polling', false)
            ->assertEmittedTo('events.modal', 'openModal');
    }

    public function test_set_order_up_reorders_groups_and_dispatches_success_event(): void
    {
        $user = $this->createUser(['email' => 'home-order-up@example.test']);
        $group1 = $this->createGroup();
        $group2 = $this->createGroup();

        GroupUser::factory()->forUser($user)->forGroup($group1)->asMember()->accepted()
            ->create(['list_order' => 0]);
        GroupUser::factory()->forUser($user)->forGroup($group2)->asMember()->accepted()
            ->create(['list_order' => 1]);

        Livewire::actingAs($user)
            ->test(Home::class)
            ->call('setOrder', $group2->id, 'up')
            ->assertDispatchedBrowserEvent('success');

        $order = DB::table('group_user')
            ->where('user_id', $user->id)
            ->where('group_id', $group2->id)
            ->value('list_order');

        $this->assertSame(0, (int) $order);
    }

    public function test_set_order_down_reorders_groups_and_dispatches_success_event(): void
    {
        $user = $this->createUser(['email' => 'home-order-down@example.test']);
        $group1 = $this->createGroup();
        $group2 = $this->createGroup();

        GroupUser::factory()->forUser($user)->forGroup($group1)->asMember()->accepted()
            ->create(['list_order' => 0]);
        GroupUser::factory()->forUser($user)->forGroup($group2)->asMember()->accepted()
            ->create(['list_order' => 1]);

        Livewire::actingAs($user)
            ->test(Home::class)
            ->call('setOrder', $group1->id, 'down')
            ->assertDispatchedBrowserEvent('success');

        $order = DB::table('group_user')
            ->where('user_id', $user->id)
            ->where('group_id', $group1->id)
            ->value('list_order');

        $this->assertSame(1, (int) $order);
    }

    public function test_toggle_poster_read_creates_read_record(): void
    {
        $user = $this->createUser(['email' => 'home-poster-read@example.test']);
        $group = $this->createGroup();
        $this->attachUserToGroup($user, $group, 'member', true);

        $poster = $this->createGroupPoster($group);

        Livewire::actingAs($user)
            ->test(Home::class)
            ->call('togglePosterRead', $poster->id);

        $this->assertDatabaseHas('group_poster_reads', [
            'poster_id' => $poster->id,
            'user_id' => $user->id,
        ]);
    }

    public function test_change_group_redirects_to_calendar(): void
    {
        $user = $this->createUser(['email' => 'home-change-group@example.test']);
        $group = $this->createGroup();
        $this->attachUserToGroup($user, $group, 'member', true);

        Livewire::actingAs($user)
            ->test(Home::class)
            ->call('changeGroup', $group->id)
            ->assertRedirect(route('calendar'));
    }

    // =========================================================================
    // 3. Poster-edit modal tesztek
    // =========================================================================

    public function test_poster_modal_opens_for_group_admin(): void
    {
        $user = $this->createUser(['email' => 'poster-modal-admin@example.test']);
        $group = $this->createGroup();
        $this->attachUserToGroup($user, $group, 'roler', true);

        Livewire::actingAs($user)
            ->test(PosterEditModal::class)
            ->call('openModal', $group->id)
            ->assertSet('openModal', true)
            ->assertDispatchedBrowserEvent('show-modal');
    }

    public function test_poster_modal_does_not_open_for_member(): void
    {
        $user = $this->createUser(['email' => 'poster-modal-member@example.test']);
        $group = $this->createGroup();
        $this->attachUserToGroup($user, $group, 'member', true);

        // Member nem szerkeszthet postert: abort(403) megakadályozza a modal megnyílását
        Livewire::actingAs($user)
            ->test(PosterEditModal::class)
            ->call('openModal', $group->id)
            ->assertSet('openModal', false);
    }

    public function test_poster_save_creates_new_poster_in_database(): void
    {
        $user = $this->createUser(['email' => 'poster-create@example.test']);
        $group = $this->createGroup();
        $this->attachUserToGroup($user, $group, 'roler', true);

        Livewire::actingAs($user)
            ->test(PosterEditModal::class)
            ->call('openModal', $group->id)
            ->set('state.show_date', today()->toDateString())
            ->set('state.info', 'Teszt hirdetmény szöveg')
            ->call('savePoster')
            ->assertHasNoErrors()
            ->assertDispatchedBrowserEvent('hide-modal');

        $this->assertDatabaseHas('group_posters', ['group_id' => $group->id]);
    }

    public function test_poster_save_validates_required_fields(): void
    {
        $user = $this->createUser(['email' => 'poster-validate@example.test']);
        $group = $this->createGroup();
        $this->attachUserToGroup($user, $group, 'roler', true);

        Livewire::actingAs($user)
            ->test(PosterEditModal::class)
            ->call('openModal', $group->id)
            ->call('savePoster')
            ->assertHasErrors(['show_date', 'info']);
    }

    public function test_poster_update_saves_changes_to_existing_poster(): void
    {
        $user = $this->createUser(['email' => 'poster-update@example.test']);
        $group = $this->createGroup();
        $this->attachUserToGroup($user, $group, 'roler', true);

        $poster = $this->createGroupPoster($group, ['info' => 'Eredeti szöveg']);

        Livewire::actingAs($user)
            ->test(PosterEditModal::class)
            ->call('openModal', $group->id, $poster->id)
            ->set('state.show_date', today()->toDateString())
            ->set('state.info', 'Frissített szöveg')
            ->call('savePoster')
            ->assertHasNoErrors()
            ->assertDispatchedBrowserEvent('hide-modal');

        $this->assertDatabaseHas('group_posters', ['id' => $poster->id]);
    }

    public function test_poster_delete_removes_poster_from_database(): void
    {
        $user = $this->createUser(['email' => 'poster-delete@example.test']);
        $group = $this->createGroup();
        $this->attachUserToGroup($user, $group, 'roler', true);

        $poster = $this->createGroupPoster($group);

        Livewire::actingAs($user)
            ->test(PosterEditModal::class)
            ->call('openModal', $group->id, $poster->id)
            ->call('deletePoster')
            ->assertDispatchedBrowserEvent('hide-modal');

        $this->assertDatabaseMissing('group_posters', ['id' => $poster->id]);
    }

    // =========================================================================
    // 4. Events modal tesztek
    // =========================================================================

    public function test_events_modal_opens_for_valid_date_and_group(): void
    {
        $user = $this->createUser(['email' => 'events-modal-open@example.test']);
        $group = $this->createGroup();
        $this->attachUserToGroup($user, $group, 'member', true);

        $date = now()->addDay()->toDateString();
        GroupDate::factory()->create([
            'group_id' => $group->id,
            'date' => $date,
            'date_start' => $date.' 08:00:00',
            'date_end' => $date.' 12:00:00',
        ]);

        Livewire::actingAs($user)
            ->test(EventsModal::class)
            ->call('openModal', $date, $group->id)
            ->assertSet('show_content', true)
            ->assertSet('date', $date)
            ->assertDispatchedBrowserEvent('show-modal');
    }

    public function test_events_modal_hides_and_disables_polling_on_close(): void
    {
        $user = $this->createUser(['email' => 'events-modal-close@example.test']);
        $group = $this->createGroup();
        $this->attachUserToGroup($user, $group, 'member', true);

        $date = now()->addDay()->toDateString();
        GroupDate::factory()->create([
            'group_id' => $group->id,
            'date' => $date,
            'date_start' => $date.' 08:00:00',
            'date_end' => $date.' 12:00:00',
        ]);

        Livewire::actingAs($user)
            ->test(EventsModal::class)
            ->call('openModal', $date, $group->id)
            ->call('hiddenModal')
            ->assertSet('polling', false)
            ->assertSet('show_content', false);
    }

    public function test_bulk_accept_updates_event_status_to_accepted_for_admin(): void
    {
        $admin = $this->createUser(['email' => 'bulk-accept-admin@example.test']);
        $member = $this->createUser(['email' => 'bulk-accept-member@example.test']);
        $group = $this->createGroup();
        $this->attachUserToGroup($admin, $group, 'roler', true);
        $this->attachUserToGroup($member, $group, 'member', true);

        $date = now()->addDay()->toDateString();
        GroupDate::factory()->create([
            'group_id' => $group->id,
            'date' => $date,
            'date_start' => $date.' 08:00:00',
            'date_end' => $date.' 12:00:00',
        ]);

        // Az observer auth()->user()->id-t használ, ezért be kell jelentkezni az event létrehozása előtt
        $this->actingAs($member);
        $event = Event::factory()
            ->forGroup($group)
            ->forUser($member)
            ->pending()
            ->onDate($date)
            ->create();

        Livewire::actingAs($admin)
            ->test(EventsModal::class)
            ->call('openModal', $date, $group->id)
            ->call('setBulk')
            ->call('bulk', $event->id)
            ->call('acceptBulk')
            ->assertDispatchedBrowserEvent('success');

        $this->assertDatabaseHas('events', ['id' => $event->id, 'status' => 1]);
    }

    public function test_bulk_accept_does_not_change_events_for_member(): void
    {
        $member = $this->createUser(['email' => 'bulk-accept-member-only@example.test']);
        $group = $this->createGroup();
        $this->attachUserToGroup($member, $group, 'member', true);

        $date = now()->addDay()->toDateString();
        GroupDate::factory()->create([
            'group_id' => $group->id,
            'date' => $date,
            'date_start' => $date.' 08:00:00',
            'date_end' => $date.' 12:00:00',
        ]);

        $this->actingAs($member);
        $event = Event::factory()
            ->forGroup($group)
            ->forUser($member)
            ->pending()
            ->onDate($date)
            ->create();

        Livewire::actingAs($member)
            ->test(EventsModal::class)
            ->call('openModal', $date, $group->id)
            ->call('setBulk')
            ->call('bulk', $event->id)
            ->call('acceptBulkFinal');

        // Member szerepkörrel a bulk accept nem módosítja az event státuszát
        $this->assertDatabaseHas('events', ['id' => $event->id, 'status' => 0]);
    }

    public function test_bulk_reject_updates_event_status_to_rejected_for_admin(): void
    {
        $admin = $this->createUser(['email' => 'bulk-reject-admin@example.test']);
        $member = $this->createUser(['email' => 'bulk-reject-member@example.test']);
        $group = $this->createGroup();
        $this->attachUserToGroup($admin, $group, 'roler', true);
        $this->attachUserToGroup($member, $group, 'member', true);

        $date = now()->addDay()->toDateString();
        GroupDate::factory()->create([
            'group_id' => $group->id,
            'date' => $date,
            'date_start' => $date.' 08:00:00',
            'date_end' => $date.' 12:00:00',
        ]);

        $this->actingAs($member);
        $event = Event::factory()
            ->forGroup($group)
            ->forUser($member)
            ->pending()
            ->onDate($date)
            ->create();

        Livewire::actingAs($admin)
            ->test(EventsModal::class)
            ->call('openModal', $date, $group->id)
            ->call('setBulk')
            ->call('bulk', $event->id)
            ->call('rejectBulkFinal')
            ->assertDispatchedBrowserEvent('success');

        $this->assertDatabaseHas('events', ['id' => $event->id, 'status' => 2]);
    }
}
