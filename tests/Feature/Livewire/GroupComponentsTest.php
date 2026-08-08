<?php

namespace Tests\Feature\Livewire;

use App\Http\Livewire\Groups\DeleteGroup;
use App\Http\Livewire\Groups\History;
use App\Http\Livewire\Groups\NewsList;
use App\Http\Livewire\Groups\Statistics;
use App\Models\Event;
use App\Models\Group;
use App\Models\GroupNews;
use App\Models\GroupNewsUserLogs;
use App\Models\GroupUser;
use App\Models\LogHistory;
use App\Models\User;
use Livewire\Livewire;
use Tests\Feature\FeatureTestCase;

/**
 * TODO 07: the remaining Groups components that were smoke-only.
 *
 * Covers Groups\DeleteGroup, Groups\History, Groups\NewsList and
 * Groups\Statistics. Groups\ListUsers is deliberately excluded - its role
 * assignment logic is the subject of TODO 07.2.
 */
class GroupComponentsTest extends FeatureTestCase
{
    private Group $group;
    private User $admin;
    private User $member;

    protected function setUp(): void
    {
        parent::setUp();

        $this->group = $this->createGroup();
        $this->admin = $this->createUser(['email' => 'grp-admin@example.test']);
        $this->member = $this->createUser(['email' => 'grp-member@example.test']);
        $this->attachUserToGroup($this->admin, $this->group, 'admin');
        $this->attachUserToGroup($this->member, $this->group);
        $this->actingAs($this->admin);
    }

    // --- Groups\DeleteGroup ---

    public function test_delete_group_mounts_with_the_bound_group_and_no_user_deletion(): void
    {
        Livewire::actingAs($this->admin)
            ->test(DeleteGroup::class, ['group' => $this->group])
            ->assertSet('deleteUsers', false)
            ->assertOk();
    }

    public function test_delete_group_detaches_the_admin_and_removes_the_group(): void
    {
        Livewire::actingAs($this->admin)
            ->test(DeleteGroup::class, ['group' => $this->group])
            ->call('deleteGroup');

        // A csoport soft-delete-elődik, és a hívó tagsága megszűnik.
        $this->assertNull(Group::find($this->group->id));
        $this->assertSame(
            0,
            GroupUser::where('group_id', $this->group->id)->where('user_id', $this->admin->id)->count()
        );
    }

    public function test_delete_group_does_nothing_for_a_plain_member(): void
    {
        // A GroupDelete controller a userGroupsDeletable reláción keresztül
        // dolgozik, így egy sima tag hívása nem töröl semmit.
        Livewire::actingAs($this->member)
            ->test(DeleteGroup::class, ['group' => $this->group])
            ->call('deleteGroup');

        $this->assertNotNull(Group::find($this->group->id));
    }

    public function test_delete_group_detaches_child_groups_from_their_parent(): void
    {
        $child = Group::factory()->asChildOf($this->group)->create();

        Livewire::actingAs($this->admin)
            ->test(DeleteGroup::class, ['group' => $this->group])
            ->call('deleteGroup');

        $this->assertNull($child->fresh()->parent_group_id);
    }

    // --- Groups\History ---

    public function test_history_mount_defaults_to_the_current_month(): void
    {
        Livewire::actingAs($this->admin)
            ->test(History::class, ['group' => $this->group->id])
            ->assertSet('year', date('Y'))
            ->assertSet('month', date('m'))
            ->assertOk();
    }

    public function test_history_set_month_switches_the_selected_period(): void
    {
        // A hónaplista a csoport létrehozásától indul, ezért a csoportot
        // visszadátumozzuk, hogy legyen választható korábbi hónap.
        // A created_at nincs a fillable listán, ezért query builderrel írjuk:
        // a hónapválasztó a csoport létrehozásától a mai napig épül fel.
        Group::where('id', $this->group->id)->update(['created_at' => now()->subMonths(3)]);
        $target = now()->subMonth()->format('Y-m-01');

        Livewire::actingAs($this->admin)
            ->test(History::class, ['group' => $this->group->id])
            ->set('state.month', $target)
            ->call('setMonth')
            ->assertSet('year', now()->subMonth()->format('Y'))
            ->assertSet('month', now()->subMonth()->format('m'));
    }

    public function test_history_ignores_a_month_outside_the_offered_list(): void
    {
        Livewire::actingAs($this->admin)
            ->test(History::class, ['group' => $this->group->id])
            ->set('state.month', '1999-01-01')
            ->call('setMonth')
            // Változatlan marad, mert az érték nincs a months tömbben.
            ->assertSet('year', date('Y'))
            ->assertSet('month', date('m'));
    }

    public function test_history_renders_log_entries_for_the_group(): void
    {
        LogHistory::factory()->forGroup($this->group)->causedBy($this->admin)->create();

        Livewire::actingAs($this->admin)
            ->test(History::class, ['group' => $this->group->id])
            ->assertOk();
    }

    // --- Groups\NewsList ---

    public function test_news_list_records_that_the_user_has_seen_the_news(): void
    {
        // A render mellékhatása: frissíti a felhasználó olvasási naplóját.
        Livewire::actingAs($this->member)
            ->test(NewsList::class, ['group' => $this->group])
            ->assertOk();

        $this->assertDatabaseHas('group_news_user_logs', [
            'group_id' => $this->group->id,
            'user_id' => $this->member->id,
        ]);
    }

    public function test_news_list_updates_the_existing_log_row_instead_of_adding_another(): void
    {
        GroupNewsUserLogs::factory()->forGroup($this->group)->forUser($this->member)->create();

        Livewire::actingAs($this->member)->test(NewsList::class, ['group' => $this->group]);
        Livewire::actingAs($this->member)->test(NewsList::class, ['group' => $this->group]);

        $this->assertSame(
            1,
            GroupNewsUserLogs::where('group_id', $this->group->id)->where('user_id', $this->member->id)->count()
        );
    }

    public function test_news_list_shows_the_groups_news(): void
    {
        $news = GroupNews::factory()->forGroup($this->group)->byUser($this->admin)->create();
        $locale = config('app.locale', 'hu');

        Livewire::actingAs($this->member)
            ->test(NewsList::class, ['group' => $this->group])
            ->assertSee($news->translate($locale)->title);
    }

    public function test_news_list_marks_an_admin_as_editor_but_not_a_member(): void
    {
        Livewire::actingAs($this->admin)
            ->test(NewsList::class, ['group' => $this->group])
            ->assertViewHas('editor', fn ($editor) => $editor > 0);

        Livewire::actingAs($this->member)
            ->test(NewsList::class, ['group' => $this->group])
            ->assertViewHas('editor', fn ($editor) => $editor == 0);
    }

    // --- Groups\Statistics ---

    public function test_statistics_mount_defaults_to_the_current_month(): void
    {
        // A v1-patch B3 után a komponens nem $year/$month párban gondolkodik,
        // hanem dátumtartományban - ez az, amit a nézet is kínál. Az alapérték
        // változatlanul az aktuális hónap.
        Livewire::actingAs($this->admin)
            ->test(Statistics::class, ['group' => $this->group->id])
            ->assertSet('startDate', date('Y-m-').'01')
            ->assertSet('endDate', date('Y-m-t'))
            ->assertOk();
    }

    public function test_statistics_applies_the_submitted_date_range(): void
    {
        // MEGFORDÍTVA a v1-patch B3 javításával.
        //
        // A Groups\Statistics-ban a `public $months` deklaráció ki volt
        // kommentelve, miközben a getMonthListFromDate() és a setMonth()
        // továbbra is használta. $this->months így dinamikus property lett,
        // amit a Livewire nem perzisztál a kérések között, tehát az isset()
        // mindig hamisra futott és a setMonth() semmit nem csinált. A gomb
        // mégis "működött", mert BÁRMELY Livewire-akció újraküldi a
        // wire:model.defer mezőket - a hatás a metódustól független volt.
        //
        // A hónapválasztót a nézetben már régen date-range páros váltotta fel,
        // ezért a maradékok (getMonthListFromDate, setMonth törzse, $months,
        // $year, $month, $current_month) törölve lettek, az akció pedig a
        // szerepét megnevező applyDateRange() nevet kapta. A dinamikus property
        // PHP 8.2-es deprecationje ezzel a Phase 8 elől is eltűnt.
        $target = now()->subMonth();

        Livewire::actingAs($this->admin)
            ->test(Statistics::class, ['group' => $this->group->id])
            ->set('startDate', $target->format('Y-m-01'))
            ->set('endDate', $target->format('Y-m-t'))
            ->call('applyDateRange')
            ->assertSet('startDate', $target->format('Y-m-01'))
            ->assertSet('endDate', $target->format('Y-m-t'))
            ->assertOk();
    }

    public function test_the_statistics_month_selector_leftovers_are_gone(): void
    {
        // A B3 lényege a holt kód eltávolítása; ha bármelyik darab visszatér,
        // a dinamikus property csapdája is vele jön.
        // Csak a KÓDRA állítunk, nem a magyarázó kommentre - az szándékosan
        // leírja, mi volt itt korábban.
        $this->assertFalse(
            method_exists(Statistics::class, 'setMonth'),
            'A setMonth() nem térhet vissza.'
        );
        $this->assertFalse(
            method_exists(Statistics::class, 'getMonthListFromDate'),
            'A getMonthListFromDate() nem térhet vissza.'
        );
        $this->assertTrue(method_exists(Statistics::class, 'applyDateRange'));

        foreach (['months', 'year', 'month', 'current_month'] as $property) {
            $this->assertFalse(
                property_exists(Statistics::class, $property),
                $property.': a hónapválasztó maradéka visszakerült.'
            );
        }

        $view = file_get_contents(resource_path('views/livewire/groups/statistics.blade.php'));
        $this->assertStringNotContainsString('setMonth', $view);
    }

    public function test_statistics_renders_with_accepted_events_present(): void
    {
        $day = now()->toDateString();

        Event::factory()->create([
            'group_id' => $this->group->id,
            'user_id' => $this->member->id,
            'day' => $day,
            'start' => $day.' 09:00:00',
            'end' => $day.' 11:00:00',
            'status' => 1,
            'accepted_at' => now(),
            'accepted_by' => $this->admin->id,
        ]);

        Livewire::actingAs($this->admin)
            ->test(Statistics::class, ['group' => $this->group->id])
            ->assertOk();
    }

    public function test_statistics_accepts_the_sub_group_and_all_event_filters(): void
    {
        Livewire::actingAs($this->admin)
            ->test(Statistics::class, ['group' => $this->group->id])
            ->set('filter_sub_group', true)
            ->set('filter_all_event', true)
            ->assertOk()
            ->assertSet('filter_sub_group', true)
            ->assertSet('filter_all_event', true);
    }
}
