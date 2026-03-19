<?php

namespace Tests\Feature;

use App\Http\Livewire\Events\Events;
use App\Models\Event;
use App\Models\GroupDate;
use Livewire\Livewire;

class CalendarEventsComponentTest extends FeatureTestCase
{
    // =========================================================================
    // 1. Komponens renderelés
    // =========================================================================

    public function test_component_renders_error_for_user_with_no_groups(): void
    {
        $user = $this->createUser(['email' => 'cal-nogroup@example.test']);

        Livewire::actingAs($user)
            ->test(Events::class)
            ->assertSee(__('group.notInGroup'));
    }

    public function test_component_renders_for_user_with_group(): void
    {
        $user = $this->createUser(['email' => 'cal-hasgroup@example.test']);
        $group = $this->createGroup();
        $this->attachUserToGroup($user, $group, 'member', true);

        session(['groupId' => $group->id]);

        Livewire::actingAs($user)
            ->test(Events::class)
            ->assertDontSee(__('group.notInGroup'));
    }

    // =========================================================================
    // 2. mount() paraméter kezelés
    // =========================================================================

    public function test_mount_sets_year_and_month_from_valid_params(): void
    {
        $user = $this->createUser(['email' => 'cal-mount-params@example.test']);
        $group = $this->createGroup();
        $this->attachUserToGroup($user, $group, 'member', true);

        session(['groupId' => $group->id]);

        Livewire::actingAs($user)
            ->test(Events::class, ['year' => 2025, 'month' => 6])
            ->assertSet('year', 2025)
            ->assertSet('month', 6);
    }

    public function test_mount_ignores_invalid_year_and_renders_without_error(): void
    {
        $user = $this->createUser(['email' => 'cal-mount-bad-year@example.test']);
        $group = $this->createGroup();
        $this->attachUserToGroup($user, $group, 'member', true);

        // Az érvénytelen év nem okoz 500-as hibát — a komponens az aktuális évre vált
        $this->actingAs($user)
            ->get(route('calendar', ['year' => 1990, 'month' => 3]))
            ->assertStatus(200);
    }

    public function test_mount_ignores_invalid_month_and_uses_current(): void
    {
        $user = $this->createUser(['email' => 'cal-mount-bad-month@example.test']);
        $group = $this->createGroup();
        $this->attachUserToGroup($user, $group, 'member', true);

        session(['groupId' => $group->id]);

        Livewire::actingAs($user)
            ->test(Events::class, ['year' => 2025, 'month' => 0])
            ->assertSet('month', (int) date('m'));
    }

    public function test_mount_uses_current_date_when_no_params(): void
    {
        $user = $this->createUser(['email' => 'cal-mount-no-params@example.test']);
        $group = $this->createGroup();
        $this->attachUserToGroup($user, $group, 'member', true);

        session(['groupId' => $group->id]);

        Livewire::actingAs($user)
            ->test(Events::class)
            ->assertSet('year', (int) date('Y'))
            ->assertSet('month', (int) date('m'));
    }

    // =========================================================================
    // 3. Csoportváltás
    // =========================================================================

    public function test_change_group_emits_to_modal_component(): void
    {
        $user = $this->createUser(['email' => 'cal-changegroup@example.test']);
        $group1 = $this->createGroup();
        $group2 = $this->createGroup();
        $this->attachUserToGroup($user, $group1, 'member', true);
        $this->attachUserToGroup($user, $group2, 'member', true);

        // render() mindig session('groupId')-ból olvassa a form_groupId-t,
        // ezért a célt a sessionbe állítjuk, hogy changeGroup() azt emittálja
        session(['groupId' => $group2->id]);

        Livewire::actingAs($user)
            ->test(Events::class)
            ->call('changeGroup')
            ->assertEmittedTo('events.modal', 'setGroup', $group2->id);
    }

    // =========================================================================
    // 4. Modal megnyitás
    // =========================================================================

    public function test_open_events_modal_disables_polling_and_emits(): void
    {
        $user = $this->createUser(['email' => 'cal-openmodal@example.test']);
        $group = $this->createGroup();
        $this->attachUserToGroup($user, $group, 'member', true);
        $date = now()->addDay()->toDateString();

        session(['groupId' => $group->id]);

        Livewire::actingAs($user)
            ->test(Events::class)
            ->call('openEventsModal', $date)
            ->assertSet('polling', false)
            ->assertEmittedTo('events.modal', 'openModal');
    }

    // =========================================================================
    // 5. Nem jóváhagyott események láthatósága szerepkör szerint
    // =========================================================================

    public function test_pending_events_visible_to_roler_in_view(): void
    {
        $roler = $this->createUser(['email' => 'cal-roler@example.test']);
        $member = $this->createUser(['email' => 'cal-member-pending@example.test']);
        $group = $this->createGroup();
        $this->attachUserToGroup($roler, $group, 'roler', true);
        $this->attachUserToGroup($member, $group, 'member', true);

        $date = now()->addDay()->toDateString();
        $this->createGroupDate($group, $date);

        $this->actingAs($member);
        Event::factory()
            ->forGroup($group)
            ->forUser($member)
            ->pending()
            ->onDate($date)
            ->create();

        session(['groupId' => $group->id]);

        Livewire::actingAs($roler)
            ->test(Events::class)
            ->assertViewHas('notAcceptedEvents', function ($events) use ($date) {
                return isset($events[$date]);
            });
    }

    public function test_pending_events_not_visible_to_regular_member(): void
    {
        $member1 = $this->createUser(['email' => 'cal-member1@example.test']);
        $member2 = $this->createUser(['email' => 'cal-member2@example.test']);
        $group = $this->createGroup();
        $this->attachUserToGroup($member1, $group, 'member', true);
        $this->attachUserToGroup($member2, $group, 'member', true);

        $date = now()->addDay()->toDateString();
        $this->createGroupDate($group, $date);

        $this->actingAs($member2);
        Event::factory()
            ->forGroup($group)
            ->forUser($member2)
            ->pending()
            ->onDate($date)
            ->create();

        session(['groupId' => $group->id]);

        Livewire::actingAs($member1)
            ->test(Events::class)
            ->assertViewHas('notAcceptedEvents', []);
    }

    // =========================================================================
    // 6. Polling
    // =========================================================================

    public function test_polling_on_and_off_toggle(): void
    {
        $user = $this->createUser(['email' => 'cal-polling@example.test']);
        $group = $this->createGroup();
        $this->attachUserToGroup($user, $group, 'member', true);

        session(['groupId' => $group->id]);

        Livewire::actingAs($user)
            ->test(Events::class)
            ->assertSet('polling', true)
            ->call('pollingOff')
            ->assertSet('polling', false)
            ->call('pollingOn')
            ->assertSet('polling', true);
    }
}
