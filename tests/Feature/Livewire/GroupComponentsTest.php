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

        // The group gets soft-deleted, and the caller's membership ends.
        $this->assertNull(Group::find($this->group->id));
        $this->assertSame(
            0,
            GroupUser::where('group_id', $this->group->id)->where('user_id', $this->admin->id)->count()
        );
    }

    public function test_delete_group_does_nothing_for_a_plain_member(): void
    {
        // The GroupDelete controller works through the userGroupsDeletable
        // relation, so a plain member's call deletes nothing.
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
        // The month list starts from the group's creation date, so we
        // backdate the group so that there is an earlier month to select.
        // created_at is not on the fillable list, so we write it with the
        // query builder: the month picker is built from the group's creation
        // date up to today.
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
            // Stays unchanged because the value is not in the months array.
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
        // A side effect of the render: it updates the user's reading log.
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
        // After v1-patch B3, the component no longer thinks in a $year/$month
        // pair, but in a date range - which is what the view offers too. The
        // default value is still the current month.
        Livewire::actingAs($this->admin)
            ->test(Statistics::class, ['group' => $this->group->id])
            ->assertSet('startDate', date('Y-m-').'01')
            ->assertSet('endDate', date('Y-m-t'))
            ->assertOk();
    }

    public function test_statistics_applies_the_submitted_date_range(): void
    {
        // REVERSED by the v1-patch B3 fix.
        //
        // In Groups\Statistics, the `public $months` declaration was
        // commented out, while getMonthListFromDate() and setMonth() kept
        // using it. $this->months thus became a dynamic property, which
        // Livewire does not persist across requests, so isset() always
        // evaluated to false and setMonth() did nothing. The button still
        // "worked" nonetheless, because ANY Livewire action resubmits the
        // wire:model.defer fields - the effect was independent of the method.
        //
        // The month picker in the view had long since been replaced by a
        // date-range pair, so the leftovers (getMonthListFromDate,
        // setMonth's body, $months, $year, $month, $current_month) were
        // deleted, and the action was given the name applyDateRange(),
        // naming its role. The dynamic property's PHP 8.2 deprecation
        // disappeared along with it, ahead of Phase 8 too.
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
        // B3's essence is removing the dead code; if any piece comes back,
        // the dynamic property trap comes back with it.
        // We assert only on the CODE, not on the explanatory comment - that
        // deliberately describes what used to be here.
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
