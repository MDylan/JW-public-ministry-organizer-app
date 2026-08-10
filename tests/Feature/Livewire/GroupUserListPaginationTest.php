<?php

namespace Tests\Feature\Livewire;

use App\Http\Livewire\Groups\ListUsers;
use App\Models\Group;
use App\Models\User;
use Illuminate\Pagination\LengthAwarePaginator;
use Livewire\Livewire;
use Tests\Feature\FeatureTestCase;

/**
 * TODO 08: Groups\ListUsers's pagination - and TODO 48's riskiest point.
 *
 * This component does NOT use the framework's paginate(); instead it
 * manually builds a LengthAwarePaginator (:915-921):
 *
 *     $current_page = $this->page;
 *     if($current_page < 1) $current_page = 1;
 *     $users = new LengthAwarePaginator($itemsForCurrentPage, $total, 10, $current_page, [...]);
 *
 * $this->page is the Livewire 2 WithPagination trait's public $page
 * property, which setPage() writes TOGETHER WITH the $paginators array:
 *
 *     $this->paginators[$pageName] = $page;
 *     $this->{$pageName} = $page;      // <-- this line disappears in v3
 *
 * In Livewire 3, the trait's $page property is removed, and setPage() only
 * writes $paginators from then on. The component does also declare its own
 * public $page = 1 (:36), so the property does not disappear - but nobody
 * will update it. The pagination buttons call gotoPage(), which sets
 * paginators, while render() reads $page instead: the list would get stuck
 * on page 1 WITHOUT AN ERROR MESSAGE.
 *
 * The tests here measure exactly this coupling, so that they fail under
 * TODO 48 if the sync is lost.
 */
class GroupUserListPaginationTest extends FeatureTestCase
{
    private Group $group;
    private User $actor;

    protected function setUp(): void
    {
        parent::setUp();

        $this->group = $this->createGroup();

        // The actor is also a member of the group, so it is included in the list.
        //
        // The sort order goes by name_index, then email, and name_index is
        // computed by CalulcateUserNameIndexProcess, which UserObserver
        // triggers on every write: it sorts all users BY NAME and
        // renumbers them. That is why attachManyUsersToGroup() gives unique
        // "Page NNN" names - and 'Zzz Aktor' ends up at the end of the list,
        // making the pages' contents predictable.
        $this->actor = $this->createUser([
            'name'  => 'Zzz Aktor',
            'email' => 'zzz-actor@example.test',
        ]);
        $this->attachUserToGroup($this->actor, $this->group, 'admin');
    }

    private function list()
    {
        return Livewire::actingAs($this->actor->fresh())
            ->test(ListUsers::class, ['group' => $this->group->id]);
    }

    private function emailsOnPage($component): array
    {
        return $component->viewData('users')->pluck('email')->values()->all();
    }

    private function expectedEmails(int $from, int $to): array
    {
        $emails = [];
        for ($i = $from; $i <= $to; $i++) {
            $emails[] = 'page-'.str_pad((string) $i, 3, '0', STR_PAD_LEFT).'@example.test';
        }

        return $emails;
    }

    // =========================================================================
    // 1. Page size
    // =========================================================================

    public function test_the_member_list_breaks_at_ten_per_page(): void
    {
        // per_page is hardwired to 10 here (:914), not paginate()'s 20.
        $this->attachManyUsersToGroup($this->group, 25);

        $paginator = $this->list()->viewData('users');

        $this->assertInstanceOf(LengthAwarePaginator::class, $paginator);
        $this->assertSame(10, $paginator->perPage());
        $this->assertSame(26, $paginator->total(), '25 tag + az aktor.');
        $this->assertSame(3, $paginator->lastPage());
        $this->assertCount(10, $paginator->items());
    }

    // =========================================================================
    // 2. The coupling between $this->page and the page's contents - the v3 risk
    // =========================================================================

    public function test_going_to_the_second_page_actually_shows_the_second_ten(): void
    {
        // THIS TEST FAILS UNDER LIVEWIRE 3 if $this->page's synchronization
        // is lost: the list would return page 1's contents.
        //
        // The sort order is deterministic (name_index = 0 everywhere, then
        // email), so we can also assert on a specific order here.
        $this->attachManyUsersToGroup($this->group, 25);

        $this->assertSame(
            $this->expectedEmails(1, 10),
            $this->emailsOnPage($this->list())
        );

        $this->assertSame(
            $this->expectedEmails(11, 20),
            $this->emailsOnPage($this->list()->call('gotoPage', 2))
        );
    }

    public function test_the_last_page_holds_the_remainder_and_the_actor(): void
    {
        $this->attachManyUsersToGroup($this->group, 25);

        $this->assertSame(
            array_merge($this->expectedEmails(21, 25), ['zzz-actor@example.test']),
            $this->emailsOnPage($this->list()->call('gotoPage', 3))
        );
    }

    public function test_goto_page_writes_both_the_page_property_and_the_paginators_array(): void
    {
        // CHARACTERIZATION TEST for the hidden precondition.
        //
        // WithPagination::setPage() writes to TWO PLACES in v2, and the
        // component's behavior rests on this duality: render() reads $page,
        // while the pagination view works off $paginators. v3's setPage()
        // only writes $paginators from then on - this test makes that
        // difference measurable.
        $this->attachManyUsersToGroup($this->group, 25);

        $component = $this->list()->call('gotoPage', 2);

        $this->assertSame(2, $component->get('page'));
        $this->assertSame(['page' => 2], $component->get('paginators'));
    }

    public function test_next_and_previous_page_move_through_the_list(): void
    {
        $this->attachManyUsersToGroup($this->group, 25);

        $component = $this->list()->call('nextPage');
        $this->assertSame($this->expectedEmails(11, 20), $this->emailsOnPage($component));

        $component->call('nextPage');
        $this->assertSame(3, $component->get('page'));

        $component->call('previousPage');
        $this->assertSame($this->expectedEmails(11, 20), $this->emailsOnPage($component));
    }

    public function test_a_page_number_below_one_is_clamped_to_the_first_page(): void
    {
        // Two safeguards run one after another: setPage() raises a
        // non-positive value to 1, and render() (:916) checks it again. The
        // second one is dead code today, but it is justified to remain
        // because of the manual paginator.
        $this->attachManyUsersToGroup($this->group, 25);

        $component = $this->list()->call('gotoPage', 0);

        $this->assertSame(1, $component->get('page'));
        $this->assertSame($this->expectedEmails(1, 10), $this->emailsOnPage($component));
    }

    public function test_a_page_number_beyond_the_last_page_yields_an_empty_slice(): void
    {
        // The manual paginator does not correct upward: slice() returns an
        // empty array, while total() still shows the full element count. The
        // framework's paginate() behaves the same way, so this is not a
        // deviation - but the user sees an empty list under the pagination control.
        $this->attachManyUsersToGroup($this->group, 25);

        $paginator = $this->list()->call('gotoPage', 9)->viewData('users');

        $this->assertCount(0, $paginator->items());
        $this->assertSame(26, $paginator->total());
    }

    // =========================================================================
    // 3. Callers of resetPage() - and those who do not call it
    // =========================================================================

    public function test_searching_resets_the_cursor(): void
    {
        $this->attachManyUsersToGroup($this->group, 25);

        $component = $this->list()->call('gotoPage', 3);
        $this->assertSame(3, $component->get('page'));

        $component->set('searchTerm', 'page-0');

        $this->assertSame(1, $component->get('page'));
    }

    public function test_filter_myself_resets_the_cursor(): void
    {
        $this->attachManyUsersToGroup($this->group, 25);

        $component = $this->list()->call('gotoPage', 3)->call('filterMyself');

        $this->assertSame(1, $component->get('page'));
        $this->assertSame(['zzz-actor@example.test'], $this->emailsOnPage($component));
    }

    public function test_filter_off_resets_the_cursor(): void
    {
        $this->attachManyUsersToGroup($this->group, 25);

        $component = $this->list()->call('gotoPage', 3)->call('filterOff');

        $this->assertSame(1, $component->get('page'));
        $this->assertSame($this->expectedEmails(1, 10), $this->emailsOnPage($component));
    }

    public function test_the_online_filter_resets_the_cursor_like_every_other_filter(): void
    {
        // REVERSED by the v1-patch B2 fix.
        //
        // Four methods called resetPage() - updatedSearchTerm(),
        // filterMyself(), filterIcon(), and filterOff() - while
        // filterOnline() and filterInactive() did NOT, even though they
        // narrow the result set just the same way. So while standing on page
        // 3, clicking the "online" filter left the list empty even though
        // there were hits: to the user, it looked as if nobody was online.
        $this->attachManyUsersToGroup($this->group, 25);

        User::where('email', 'page-001@example.test')->update(['last_activity' => now()]);

        $component = $this->list()->call('gotoPage', 3)->call('filterOnline');

        $this->assertSame(1, $component->get('page'), 'A szűrés visszaáll az első oldalra.');

        $paginator = $component->viewData('users');
        $this->assertSame(1, $paginator->total(), 'Egy online tag van.');
        $this->assertCount(1, $paginator->items(), 'És most látszik is.');
    }

    public function test_the_inactive_filter_resets_the_cursor_too(): void
    {
        // filterInactive() was the other half of the same defect; alongside
        // filterOnline(), this too falls under the scope of v1-patch B2.
        $this->attachManyUsersToGroup($this->group, 25);

        $component = $this->list()->call('gotoPage', 3)->call('filterInactive');

        $this->assertSame(1, $component->get('page'), 'A szűrés visszaáll az első oldalra.');
    }

    // =========================================================================
    // 4. The relationship between role and page count
    // =========================================================================

    public function test_a_non_editor_sees_fewer_pages_because_pending_members_are_hidden(): void
    {
        // render() (:878-881) only shows not-yet-accepted memberships to an
        // editor (admin/roler). So the page count is role-dependent - the
        // same group has a different extent for two different users.
        $this->attachManyUsersToGroup($this->group, 12, 'member', true);
        $this->attachManyUsersToGroup($this->group, 8, 'member', false, 'pending');

        $viewer = $this->createUser(['email' => 'zzy-viewer@example.test']);
        $this->attachUserToGroup($viewer, $this->group, 'member');

        // The actor is admin: sees everyone (12 + 8 + actor + viewer = 22).
        $this->assertSame(22, $this->list()->viewData('users')->total());

        $asViewer = Livewire::actingAs($viewer->fresh())
            ->test(ListUsers::class, ['group' => $this->group->id]);

        $this->assertSame(14, $asViewer->viewData('users')->total(), '12 elfogadott + aktor + viewer.');
        $this->assertSame(2, $asViewer->viewData('users')->lastPage());
    }

    // =========================================================================
    // 5. Search over the paginated set
    // =========================================================================

    public function test_the_search_filters_in_memory_after_the_query(): void
    {
        // Search does NOT run in the database: render() (:902-911) filters
        // the already-queried collection, because the name is stored
        // encrypted. So pagination happens over the filtered collection, and
        // total() shows the number of hits - not the group's size.
        $this->attachManyUsersToGroup($this->group, 25);

        $paginator = $this->list()->set('searchTerm', 'page-01')->viewData('users');

        $this->assertSame(10, $paginator->total(), 'page-010 .. page-019.');
        $this->assertSame(1, $paginator->lastPage());
    }

    public function test_a_search_with_no_hits_yields_an_empty_paginator(): void
    {
        $this->attachManyUsersToGroup($this->group, 25);

        $paginator = $this->list()->set('searchTerm', 'nincs-ilyen')->viewData('users');

        $this->assertSame(0, $paginator->total());
        $this->assertCount(0, $paginator->items());
    }

    // =========================================================================
    // 6. The rendered pagination control
    // =========================================================================

    public function test_the_pagination_control_renders_every_page_link_in_a_small_list(): void
    {
        // The view is the ONLY place where links() gets an argument:
        // {{$users->onEachSide(1)->links()}} (list-users.blade.php:312).
        // With a small page count, Laravel's windowing shows every page,
        // without a separator.
        $this->attachManyUsersToGroup($this->group, 25);

        $this->list()
            ->assertSee('wire:click="gotoPage(2, \'page\')"', false)
            ->assertSee('wire:click="gotoPage(3, \'page\')"', false)
            ->assertDontSee('<span class="page-link">...</span>', false);
    }

    public function test_no_pagination_control_is_rendered_for_a_single_page(): void
    {
        $this->attachManyUsersToGroup($this->group, 5);

        $this->list()->assertDontSee('class="pagination"', false);
    }
}
