<?php

namespace Tests\Feature\Livewire;

use App\Http\Livewire\Admin\Users\ListUsers;
use App\Models\User;
use Livewire\Livewire;
use Tests\Feature\FeatureTestCase;

/**
 * TODO 08: pagination of Admin\Users\ListUsers.
 *
 * The component uses the framework's paginate(20) (:134), so the
 * page number is bound by Paginator::currentPageResolver to WithPagination's
 * $paginators array. This is the path that stays fine on its own
 * under Livewire 3 - unlike Groups\ListUsers's manual paginator.
 */
class AdminUserListPaginationTest extends FeatureTestCase
{
    private User $admin;

    protected function setUp(): void
    {
        parent::setUp();

        // FeatureTestCase already creates a mainAdmin owner@example.test
        // user, which also appears in the list - the fixture numbers
        // include this too.
        $this->admin = $this->createUser([
            'role'  => 'mainAdmin',
            'email' => 'aup-admin@example.test',
        ]);
    }

    /**
     * With an empty search, render() lists only mainAdmin / translator / groupCreator
     * roles (:123-125), so the fixture must supply users with those roles.
     */
    private function seedListedUsers(int $count, int $offset = 0): void
    {
        for ($i = 1; $i <= $count; $i++) {
            $this->createUser([
                'role'  => 'groupCreator',
                'email' => 'aup-'.str_pad((string) ($offset + $i), 3, '0', STR_PAD_LEFT).'@example.test',
            ]);
        }
    }

    private function idsOnPage($component): array
    {
        return $component->viewData('users')->pluck('id')->all();
    }

    private function list()
    {
        return Livewire::actingAs($this->admin)->test(ListUsers::class);
    }

    // =========================================================================
    // 1. Page size and page counts
    // =========================================================================

    public function test_the_first_page_holds_twenty_users(): void
    {
        $this->seedListedUsers(23); // + owner + admin = 25 listed

        $paginator = $this->list()->viewData('users');

        $this->assertSame(20, $paginator->count());
        $this->assertSame(25, $paginator->total());
        $this->assertSame(2, $paginator->lastPage());
        $this->assertSame(1, $paginator->currentPage());
    }

    public function test_the_second_page_holds_the_remainder(): void
    {
        $this->seedListedUsers(23);

        $paginator = $this->list()->call('gotoPage', 2)->viewData('users');

        $this->assertSame(5, $paginator->count());
        $this->assertSame(2, $paginator->currentPage());
    }

    public function test_the_pages_are_disjoint_and_together_cover_every_user(): void
    {
        // render() orders by ->latest(), and users created in a loop
        // can have created_at falling on the same second - so we do NOT
        // assert on order, but on what we expect from pagination:
        // no overlap and full coverage.
        $this->seedListedUsers(23);

        $firstPage = $this->idsOnPage($this->list());
        $secondPage = $this->idsOnPage($this->list()->call('gotoPage', 2));

        $this->assertCount(20, $firstPage);
        $this->assertCount(5, $secondPage);
        $this->assertEmpty(array_intersect($firstPage, $secondPage), 'A két lap nem fedhet át.');

        $listedRoles = ['mainAdmin', 'translator', 'groupCreator'];
        $expected = User::whereIn('role', $listedRoles)->pluck('id')->all();

        $this->assertEqualsCanonicalizing($expected, array_merge($firstPage, $secondPage));
    }

    public function test_next_and_previous_page_move_the_cursor(): void
    {
        $this->seedListedUsers(23);

        $component = $this->list();
        $this->assertSame(1, $component->viewData('users')->currentPage());

        $component->call('nextPage');
        $this->assertSame(2, $component->viewData('users')->currentPage());

        $component->call('previousPage');
        $this->assertSame(1, $component->viewData('users')->currentPage());
    }

    public function test_previous_page_does_not_go_below_the_first_page(): void
    {
        $this->seedListedUsers(23);

        $component = $this->list()->call('previousPage');

        $this->assertSame(1, $component->viewData('users')->currentPage());
    }

    // =========================================================================
    // 2. The relationship between search and pagination
    // =========================================================================

    public function test_searching_resets_the_cursor_to_the_first_page(): void
    {
        // updatedSearchTerm() (:116-118) is the only resetPage() caller in
        // this component. If this were missing, a high page number on the
        // narrowed result set would produce an empty list - from the user's
        // perspective it would look as if there were no results.
        $this->seedListedUsers(23);

        $component = $this->list()->call('gotoPage', 2);
        $this->assertSame(2, $component->viewData('users')->currentPage());

        $component->set('searchTerm', 'aup-0');

        $this->assertSame(1, $component->viewData('users')->currentPage());
    }

    public function test_a_search_switches_to_email_matching_and_shrinks_the_result_set(): void
    {
        // When searching, render() drops the role filter and matches on an
        // email fragment instead (:120-127) - pagination therefore operates on a different set.
        $this->seedListedUsers(23);
        $this->createUser(['role' => 'registered', 'email' => 'kereses-cel@example.test']);

        $paginator = $this->list()->set('searchTerm', 'kereses-cel')->viewData('users');

        $this->assertSame(1, $paginator->total());
        $this->assertSame('kereses-cel@example.test', $paginator->first()->email);
    }

    public function test_a_search_with_many_matches_still_paginates(): void
    {
        $this->seedListedUsers(23);

        $paginator = $this->list()->set('searchTerm', 'aup-')->viewData('users');

        $this->assertSame(24, $paginator->total(), 'A 23 seed + az aup-admin.');
        $this->assertSame(20, $paginator->count());
        $this->assertSame(2, $paginator->lastPage());
    }

    // =========================================================================
    // 3. Display of the paginator
    // =========================================================================

    public function test_the_pagination_control_is_rendered_only_when_there_is_more_than_one_page(): void
    {
        $this->seedListedUsers(3); // + owner + admin = 5

        $this->list()->assertDontSee('class="pagination"', false);

        $this->seedListedUsers(23, 3);

        $this->list()->assertSee('class="pagination"', false);
    }
}
