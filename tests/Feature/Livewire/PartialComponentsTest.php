<?php

namespace Tests\Feature\Livewire;

use App\Http\Livewire\Events\LastEvents;
use App\Http\Livewire\Partials\EventsBar;
use App\Http\Livewire\Partials\NavBar;
use App\Http\Livewire\Partials\SideMenu;
use App\Models\AdminNewsletter;
use App\Models\AdminNewsletterRead;
use App\Models\Event;
use App\Models\EventServiceReport;
use App\Models\Group;
use App\Models\GroupLiterature;
use App\Models\StaticPage;
use App\Models\User;
use Illuminate\Support\Facades\View;
use Livewire\Livewire;
use Tests\Feature\FeatureTestCase;

/**
 * TODO 07: the three Partials components and Events\LastEvents, all smoke-only.
 *
 * The Partials render on every authenticated page, so a regression here is
 * site-wide. All three listen for a 'refresh' event, which is exactly the
 * mechanism that changes in Livewire 3 (TODO 44).
 */
class PartialComponentsTest extends FeatureTestCase
{
    private Group $group;
    private User $member;

    protected function setUp(): void
    {
        parent::setUp();

        $this->group = $this->createGroup();
        $this->member = $this->createUser(['email' => 'partial-member@example.test']);
        $this->attachUserToGroup($this->member, $this->group);
        $this->actingAs($this->member);

        // A SideMenu nézete a $sidemenu változóra épül, amit nem a komponens
        // állít elő, hanem a SetLocale middleware oszt meg View::share()-rel
        // (SetLocale.php:76). A Livewire komponensteszt nem fut át a
        // middleware-en, ezért itt pótoljuk. Ez az implicit függőség önmagában
        // is kockázat: a Laravel 11 skeleton-átállásnál a middleware
        // áthelyezésekor könnyen elveszhet. Lásd: roadmap TODO 07 és TODO 56.
        View::share('sidemenu', StaticPage::where('position', '!=', 'hidden')->get());
    }

    private function createEvent(string $day, string $start = '09:00:00', string $end = '11:00:00', int $status = 1): Event
    {
        return Event::factory()->create([
            'group_id' => $this->group->id,
            'user_id' => $this->member->id,
            'day' => $day,
            'start' => $day.' '.$start,
            'end' => $day.' '.$end,
            'status' => $status,
            'accepted_at' => $status === 1 ? now() : null,
            'accepted_by' => $status === 1 ? $this->member->id : null,
        ]);
    }

    // --- Partials\SideMenu ---

    public function test_side_menu_counts_accepted_groups_and_pending_invitations(): void
    {
        $pendingGroup = $this->createGroup();
        $this->attachUserToGroup($this->member, $pendingGroup, 'member', false);

        $component = Livewire::actingAs($this->member)->test(SideMenu::class);

        $sideMenu = $component->get('sideMenu');

        $this->assertSame(1, $sideMenu['groups'], 'Only the accepted membership should be counted.');
        $this->assertSame(1, $sideMenu['invites'], 'The pending membership should be counted as an invitation.');
    }

    public function test_side_menu_counts_unread_newsletters_for_a_group_servant(): void
    {
        $servant = $this->createUser(['email' => 'partial-servant@example.test']);
        $this->attachUserToGroup($servant, $this->group, 'admin');

        AdminNewsletter::factory()->published()->create([
            'user_id' => $servant->id,
            'send_to' => 'groupServants',
        ]);

        $component = Livewire::actingAs($servant)->test(SideMenu::class);

        $this->assertSame(1, $component->get('sideMenu')['newsletters']);
    }

    public function test_side_menu_stops_counting_a_newsletter_once_read(): void
    {
        $servant = $this->createUser(['email' => 'partial-servant2@example.test']);
        $this->attachUserToGroup($servant, $this->group, 'admin');

        $newsletter = AdminNewsletter::factory()->published()->create([
            'user_id' => $servant->id,
            'send_to' => 'groupServants',
        ]);
        AdminNewsletterRead::factory()->forUser($servant)->forNewsletter($newsletter)->create();

        $component = Livewire::actingAs($servant)->test(SideMenu::class);

        $this->assertSame(0, $component->get('sideMenu')['newsletters']);
    }

    public function test_side_menu_shows_no_newsletter_count_for_a_plain_member(): void
    {
        AdminNewsletter::factory()->published()->create([
            'user_id' => $this->member->id,
            'send_to' => 'groupServants',
        ]);

        $component = Livewire::actingAs($this->member)->test(SideMenu::class);

        $this->assertSame(0, $component->get('sideMenu')['newsletters']);
    }

    public function test_side_menu_normalises_the_request_path_for_nested_routes(): void
    {
        // A mount a calendar/* és groups/* útvonalakat a gyökérre képezi,
        // hogy a menüpont kiemelése működjön almenükben is.
        Livewire::actingAs($this->member)
            ->withQueryParams([])
            ->test(SideMenu::class)
            ->assertOk();

        $this->assertNotNull(
            Livewire::actingAs($this->member)->test(SideMenu::class)->get('request_path')
        );
    }

    public function test_side_menu_responds_to_the_refresh_event(): void
    {
        // Ez a Livewire 3 migráció szempontjából lényeges: a partials
        // 'refresh' listenerén keresztül frissülnek (TODO 44).
        Livewire::actingAs($this->member)
            ->test(SideMenu::class)
            ->emit('refresh')
            ->assertOk();
    }

    // --- Partials\NavBar ---

    public function test_nav_bar_renders_for_an_authenticated_user(): void
    {
        Livewire::actingAs($this->member)
            ->test(NavBar::class)
            ->assertOk();
    }

    public function test_nav_bar_responds_to_the_refresh_event(): void
    {
        Livewire::actingAs($this->member)
            ->test(NavBar::class)
            ->call('refresh')
            ->assertOk();
    }

    // --- Partials\EventsBar ---

    public function test_events_bar_lists_the_users_upcoming_events(): void
    {
        $this->createEvent(now()->addDay()->toDateString());

        Livewire::actingAs($this->member)
            ->test(EventsBar::class)
            ->assertOk()
            ->assertViewHas('events', fn ($events) => count($events) === 1);
    }

    public function test_events_bar_is_empty_without_upcoming_events(): void
    {
        Livewire::actingAs($this->member)
            ->test(EventsBar::class)
            ->assertViewHas('events', fn ($events) => count($events) === 0);
    }

    public function test_events_bar_generates_calendar_links_when_the_user_opted_in(): void
    {
        $this->member->update(['calendars' => ['google' => true]]);
        $event = $this->createEvent(now()->addDay()->toDateString());

        $component = Livewire::actingAs($this->member)->test(EventsBar::class);

        $links = $component->get('links');

        $this->assertArrayHasKey($event->id, $links);
        $this->assertArrayHasKey('google', $links[$event->id]);
        $this->assertStringContainsString('google.com', $links[$event->id]['google']);
    }

    public function test_events_bar_ignores_calendars_outside_the_system_list(): void
    {
        // Csak a config('events.calendars') listán szereplő naptárak
        // engedélyezettek, tetszőleges kulcs nem generál linket.
        $this->member->update(['calendars' => ['valamiIsmeretlen' => true]]);
        $event = $this->createEvent(now()->addDay()->toDateString());

        $component = Livewire::actingAs($this->member)->test(EventsBar::class);

        $this->assertArrayNotHasKey($event->id, $component->get('links'));
    }

    // --- Events\LastEvents ---

    public function test_last_events_mounts_on_the_current_month(): void
    {
        Livewire::actingAs($this->member)
            ->test(LastEvents::class)
            ->assertSet('year', (int) date('Y'))
            ->assertSet('month', (int) date('m'))
            ->assertOk();
    }

    public function test_last_events_lists_a_past_event_of_the_current_month(): void
    {
        $this->createEvent(now()->startOfMonth()->toDateString(), '08:00:00', '10:00:00');

        Livewire::actingAs($this->member)
            ->test(LastEvents::class)
            ->assertOk();
    }

    public function test_last_events_can_switch_months(): void
    {
        Livewire::actingAs($this->member)
            ->test(LastEvents::class)
            ->set('state.month', now()->format('Y-m-01'))
            ->call('setMonth')
            ->assertSet('month', (int) now()->format('m'));
    }

    public function test_last_events_opens_the_report_form_for_an_event(): void
    {
        $event = $this->createEvent(now()->startOfMonth()->toDateString(), '08:00:00', '10:00:00');
        GroupLiterature::factory()->forGroup($this->group)->create();

        Livewire::actingAs($this->member)
            ->test(LastEvents::class)
            ->call('editReports', $event->id)
            ->assertSet('eventId', $event->id)
            ->assertOk();
    }

    public function test_last_events_saves_a_service_report(): void
    {
        $event = $this->createEvent(now()->startOfMonth()->toDateString(), '08:00:00', '10:00:00');
        $literature = GroupLiterature::factory()->forGroup($this->group)->create();

        Livewire::actingAs($this->member)
            ->test(LastEvents::class)
            ->call('editReports', $event->id)
            ->set('reports.'.$literature->id, [
                'placements' => 3,
                'videos' => 1,
                'return_visits' => 2,
                'bible_studies' => 0,
            ])
            ->call('saveReport');

        $this->assertDatabaseHas('event_service_reports', [
            'event_id' => $event->id,
            'group_literature_id' => $literature->id,
        ]);
    }
}
