<?php

namespace Tests\Feature\Security;

use App\Livewire\Events\LastEvents;
use App\Livewire\Groups\ListGroups;
use App\Models\Event;
use App\Models\GroupLiterature;
use App\Models\User;
use Livewire\Livewire;
use Tests\Feature\FeatureTestCase;

/**
 * Roadmap TODO 70, the two low-severity findings outside the group pages:
 * the service report form trusted its literature keys and fataled on an
 * event that is not the user's, and the group-creator request - one e-mail
 * to the site admin each - had no limit.
 */
class ReportAndRequestHardeningTest extends FeatureTestCase
{
    private function eventOf(User $user, $group): Event
    {
        $this->actingAs($user);

        return Event::factory()->forGroup($group)->forUser($user)->accepted()->create([
            'day' => now()->toDateString(),
            'start' => now()->toDateString().' 08:00:00',
            'end' => now()->toDateString().' 09:00:00',
        ]);
    }

    public function test_the_report_form_refuses_another_users_event_without_a_fatal(): void
    {
        $group = $this->createGroup();
        $owner = $this->createUser(['email' => 'rr-owner@example.test']);
        $this->attachUserToGroup($owner, $group);
        GroupLiterature::factory()->forGroup($group)->create();
        $event = $this->eventOf($owner, $group);
        $other = $this->createUser(['email' => 'rr-other@example.test']);

        Livewire::actingAs($other)->test(LastEvents::class)
            ->call('editReports', $event->id)
            ->assertDispatched('sweet-error')
            ->assertNotDispatched('show-modal');
    }

    public function test_a_report_is_saved_only_for_the_events_own_literature(): void
    {
        $group = $this->createGroup();
        $member = $this->createUser(['email' => 'rr-member@example.test']);
        $this->attachUserToGroup($member, $group);
        $own = GroupLiterature::factory()->forGroup($group)->create();
        $foreign = GroupLiterature::factory()->forGroup($this->createGroup())->create();
        $event = $this->eventOf($member, $group);
        $report = ['placements' => 1, 'videos' => 0, 'return_visits' => 0, 'bible_studies' => 0];

        Livewire::actingAs($member)->test(LastEvents::class)
            ->call('editReports', $event->id)
            ->set('reports.'.$own->id, $report)
            ->set('reports.'.$foreign->id, $report)
            ->call('saveReport');

        $this->assertDatabaseHas('event_service_reports', ['event_id' => $event->id, 'group_literature_id' => $own->id]);
        $this->assertDatabaseMissing('event_service_reports', ['group_literature_id' => $foreign->id]);
    }

    public function test_the_group_creator_request_is_limited(): void
    {
        $user = $this->createUser(['email' => 'rr-creator@example.test', 'phone_number' => '36301234567']);

        $component = Livewire::actingAs($user)->test(ListGroups::class);
        for ($i = 1; $i <= 3; $i++) {
            $component->set('state.congregation', 'Példa Gyülekezet')
                ->set('state.reason', 'Szeretnék új csoportot létrehozni a városrészünkben.')
                ->call('requestGroupCreatorPrivilege')
                ->assertHasNoErrors();
        }

        $component->set('state.congregation', 'Példa Gyülekezet')
            ->set('state.reason', 'Szeretnék új csoportot létrehozni a városrészünkben.')
            ->call('requestGroupCreatorPrivilege')
            ->assertHasErrors('reason');
    }
}
