<?php

namespace Tests\Feature;

use App\Http\Livewire\Events\EventEdit;
use App\Http\Livewire\Events\Modal;
use App\Http\Livewire\Groups\Messages;
use App\Http\Livewire\Groups\PosterEditModal;
use App\Http\Livewire\Groups\SpecialDateModal;
use App\Http\Livewire\Partials\EventsBar;
use App\Http\Livewire\Partials\NavBar;
use App\Http\Livewire\Partials\SideMenu;
use App\Models\Event;
use Illuminate\Support\Facades\View;
use Livewire\Livewire;

class LivewireNestedComponentsTest extends FeatureTestCase
{
    public function test_events_modal_component_smoke(): void
    {
        $user = $this->createUser(['email' => 'modal-user@example.test']);

        $this->actingAs($user);
        Livewire::test(Modal::class, ['groupId' => 0]);

        $this->assertTrue(true);
    }

    public function test_event_edit_component_smoke(): void
    {
        $user = $this->createUser(['email' => 'event-edit-user@example.test']);
        $group = $this->createGroup();
        $this->attachUserToGroup($user, $group, 'roler', true);

        $date = now()->addDay()->startOfDay();
        $this->createGroupDate($group, $date->toDateString());

        $this->actingAs($user);
        Livewire::test(EventEdit::class, [
            'groupId' => $group->id,
            'date' => $date,
        ]);

        $this->assertTrue(true);
    }

    public function test_messages_component_smoke(): void
    {
        $user = $this->createUser(['email' => 'messages-user@example.test']);
        $group = $this->createGroup([
            'messages_write' => 1,
            'messages_priority' => 1,
        ]);

        $this->attachUserToGroup($user, $group, 'roler', true);
        $this->actingAs($user);

        Event::factory()->create([
            'group_id' => $group->id,
            'user_id' => $user->id,
            'day' => now()->toDateString(),
            'start' => now()->subHour()->format('Y-m-d H:i:s'),
            'end' => now()->addHours(2)->format('Y-m-d H:i:s'),
            'status' => 1,
        ]);

        Livewire::test(Messages::class, ['group' => $group]);

        $this->assertTrue(true);
    }

    public function test_poster_edit_modal_component_smoke(): void
    {
        $user = $this->createUser(['email' => 'poster-user@example.test']);

        $this->actingAs($user);
        Livewire::test(PosterEditModal::class);

        $this->assertTrue(true);
    }

    public function test_special_date_modal_component_smoke(): void
    {
        $user = $this->createUser(['email' => 'special-user@example.test']);
        $group = $this->createGroup();
        $this->attachUserToGroup($user, $group, 'roler', true);

        $this->actingAs($user);
        Livewire::test(SpecialDateModal::class, ['groupId' => $group->id]);

        $this->assertTrue(true);
    }

    public function test_partial_components_smoke(): void
    {
        $user = $this->createUser([
            'email' => 'partial-user@example.test',
            'calendars' => ['google' => true],
        ]);
        $group = $this->createGroup();
        $this->attachUserToGroup($user, $group, 'member', true);
        $this->actingAs($user);

        Event::factory()->create([
            'group_id' => $group->id,
            'user_id' => $user->id,
            'day' => now()->addDay()->toDateString(),
            'status' => 1,
        ]);

        View::share('sidemenu', collect());

        Livewire::test(NavBar::class);
        Livewire::test(SideMenu::class);
        Livewire::test(EventsBar::class);

        $this->assertTrue(true);
    }
}
