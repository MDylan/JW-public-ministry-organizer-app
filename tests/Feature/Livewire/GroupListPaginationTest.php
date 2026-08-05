<?php

namespace Tests\Feature\Livewire;

use App\Http\Livewire\Groups\ListGroups;
use App\Models\Group;
use App\Models\GroupUser;
use App\Models\User;
use Livewire\Livewire;
use Tests\Feature\FeatureTestCase;

/**
 * TODO 08: a Groups\ListGroups lapozása.
 *
 * A render() a userGroups() RELÁCIÓN paginál (:229), nem egy önálló
 * lekérdezésen - a lapszám tehát a reláció pivot-szűrőivel együtt alakul ki.
 * Ez a metszéspont a lapozás és a jogosultsági szűrés között, és ma semmi
 * nem védi.
 */
class GroupListPaginationTest extends FeatureTestCase
{
    private User $user;

    protected function setUp(): void
    {
        parent::setUp();

        $this->user = $this->createUser(['email' => 'glp-user@example.test']);
    }

    private function list()
    {
        return Livewire::actingAs($this->user->fresh())->test(ListGroups::class);
    }

    private function idsOnPage($component): array
    {
        return $component->viewData('groups')->pluck('id')->all();
    }

    // =========================================================================
    // 1. Lapméret és lapszámok
    // =========================================================================

    public function test_the_group_list_breaks_at_twenty_per_page(): void
    {
        $this->attachUserToManyGroups($this->user, 25);

        $paginator = $this->list()->viewData('groups');

        $this->assertSame(20, $paginator->count());
        $this->assertSame(25, $paginator->total());
        $this->assertSame(2, $paginator->lastPage());
    }

    public function test_the_pages_are_disjoint_and_together_cover_every_membership(): void
    {
        $groups = $this->attachUserToManyGroups($this->user, 25);

        $firstPage = $this->idsOnPage($this->list());
        $secondPage = $this->idsOnPage($this->list()->call('gotoPage', 2));

        $this->assertCount(20, $firstPage);
        $this->assertCount(5, $secondPage);
        $this->assertEmpty(array_intersect($firstPage, $secondPage));

        $this->assertEqualsCanonicalizing(
            collect($groups)->pluck('id')->all(),
            array_merge($firstPage, $secondPage)
        );
    }

    public function test_only_the_users_own_groups_are_paginated(): void
    {
        // Egy másik felhasználó csoportjai nem tolják el a lapszámot: a
        // relációs szűrés a lapozás előtt hat.
        $this->attachUserToManyGroups($this->user, 5);

        $stranger = $this->createUser(['email' => 'glp-stranger@example.test']);
        $this->attachUserToManyGroups($stranger, 25);

        $paginator = $this->list()->viewData('groups');

        $this->assertSame(5, $paginator->total());
        $this->assertSame(1, $paginator->lastPage());
    }

    // =========================================================================
    // 2. A reláció szűrői a lapozáson át
    // =========================================================================

    public function test_a_withdrawn_membership_is_excluded_from_the_total(): void
    {
        // A userGroups() wherePivot('deleted_at', null) szűrője a lapozás
        // ELŐTT hat, tehát egy kilépett tagság nem növeli a lapszámot -
        // különben az utolsó lapon üres helyek jelennének meg.
        $this->attachUserToManyGroups($this->user, 21);

        $this->assertSame(2, $this->list()->viewData('groups')->lastPage());

        $extra = Group::factory()->create(['name' => 'Kilépett csoport']);
        GroupUser::factory()
            ->forUser($this->user)
            ->forGroup($extra)
            ->accepted()
            ->withdrawn()
            ->create();

        $paginator = $this->list()->viewData('groups');

        $this->assertSame(21, $paginator->total(), 'A kilépett tagság nem számít bele.');
        $this->assertNotContains($extra->id, $paginator->pluck('id')->all());
    }

    public function test_pending_invitations_are_counted_on_the_same_pages(): void
    {
        // A userGroups() - a groupsAccepted()-tel ellentétben - NEM szűri az
        // el nem fogadott tagságokat. A meghívások tehát ugyanazon a lapon
        // jelennek meg, mint a valódi csoportok, és beleszámítanak a
        // lapszámba. Rögzítjük, mert a nézet külön dobozban mutatja őket.
        $this->attachUserToManyGroups($this->user, 18, 'member', true);
        $pending = $this->attachUserToManyGroups($this->user, 4, 'member', false);

        $paginator = $this->list()->viewData('groups');

        $this->assertSame(22, $paginator->total());
        $this->assertSame(2, $paginator->lastPage());

        $allIds = array_merge(
            $paginator->pluck('id')->all(),
            $this->list()->call('gotoPage', 2)->viewData('groups')->pluck('id')->all()
        );

        foreach ($pending as $group) {
            $this->assertContains($group->id, $allIds);
        }
    }

    public function test_a_soft_deleted_group_drops_out_of_the_pagination(): void
    {
        $groups = $this->attachUserToManyGroups($this->user, 21);

        $this->assertSame(21, $this->list()->viewData('groups')->total());
        $this->assertSame(2, $this->list()->viewData('groups')->lastPage());

        // Tömeges törlés, ahogy az éles kód is teszi - modell-események
        // nélkül. (Az Eloquent út a TODO 10 óta szintén működik.)
        Group::where('id', $groups[0]->id)->delete();

        $paginator = $this->list()->viewData('groups');

        $this->assertSame(20, $paginator->total());
        $this->assertSame(1, $paginator->lastPage(), 'A törléssel egy egész lap eltűnt.');
        $this->assertNotContains($groups[0]->id, $paginator->pluck('id')->all());
    }

    // =========================================================================
    // 3. Navigáció és megjelenés
    // =========================================================================

    public function test_next_and_previous_page_move_the_cursor(): void
    {
        $this->attachUserToManyGroups($this->user, 25);

        $component = $this->list();
        $this->assertSame(1, $component->viewData('groups')->currentPage());

        $component->call('nextPage');
        $this->assertSame(2, $component->viewData('groups')->currentPage());

        $component->call('previousPage');
        $this->assertSame(1, $component->viewData('groups')->currentPage());
    }

    public function test_the_pagination_control_appears_only_above_the_page_size(): void
    {
        $this->attachUserToManyGroups($this->user, 20);
        $this->list()->assertDontSee('class="pagination"', false);

        $this->attachUserToManyGroups($this->user, 1);
        $this->list()->assertSee('class="pagination"', false);
    }

    public function test_a_user_without_groups_gets_an_empty_paginator(): void
    {
        $paginator = $this->list()->viewData('groups');

        $this->assertSame(0, $paginator->total());
        $this->assertSame(1, $paginator->lastPage());
    }
}
