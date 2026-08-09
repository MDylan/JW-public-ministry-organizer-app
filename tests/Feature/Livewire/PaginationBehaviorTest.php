<?php

namespace Tests\Feature\Livewire;

use App\Http\Livewire\Admin\Users\ListUsers as AdminUserList;
use App\Http\Livewire\AppComponent;
use App\Http\Livewire\Groups\ListGroups;
use App\Http\Livewire\Groups\ListUsers as GroupUserList;
use Livewire\Livewire;
use Livewire\WithPagination;
use Tests\Feature\FeatureTestCase;

/**
 * TODO 08: az AppComponent lapozási szerződése.
 *
 * Az AppComponent.php:12 protected $paginationTheme = 'bootstrap' sora NEM
 * létezik Livewire 3-ban; a TODO 48 fogja átírni paginationView()
 * felülírásra. Ez a fájl méri azt, amit a TODO 48-nak újra kell termelnie.
 *
 * A roadmap TODO 08 szövege öt komponenst nevez meg (Admin\StaticPages,
 * Admin\AdminNewsletters, Groups\NewsList, Groups\History, Groups\ListUsers).
 * Ellenőrizve: ebből NÉGY nem lapoz, és három nem is AppComponent-
 * leszármazott. A ténylegesen lapozó három komponens - a paginate() és a
 * ->links() hívások alapján, teljes körűen:
 *
 *   Admin\Users\ListUsers   paginate(20)   admin/users/list-users.blade:135
 *   Groups\ListGroups       paginate(20)   groups/list-groups.blade:122
 *   Groups\ListUsers        kézi paginátor groups/list-users.blade:312
 */
class PaginationBehaviorTest extends FeatureTestCase
{
    // =========================================================================
    // 1. A téma
    // =========================================================================

    public function test_the_base_component_resolves_the_bootstrap_pagination_views(): void
    {
        // A WithPagination::paginationView() a 'livewire::' előtagot fűzi a
        // $paginationTheme elé, ami a vendor views/pagination/ könyvtárára
        // oldódik fel (LivewireServiceProvider:123-126). Ez a legközvetlenebb
        // mérés a $paginationTheme property-re, és pontosan ez az az érték,
        // amit a TODO 48-nak paginationView() felülírással kell adnia.
        $component = new AppComponent();

        $this->assertSame('livewire::bootstrap', $component->paginationView());
        $this->assertSame('livewire::simple-bootstrap', $component->paginationSimpleView());
    }

    /**
     * @dataProvider paginatingComponentProvider
     */
    public function test_every_paginating_component_inherits_the_bootstrap_theme(string $componentClass): void
    {
        $component = new $componentClass();

        $this->assertInstanceOf(AppComponent::class, $component);
        $this->assertContains(WithPagination::class, class_uses_recursive($componentClass));
        $this->assertSame('livewire::bootstrap', $component->paginationView());
    }

    public function paginatingComponentProvider(): array
    {
        return [
            'admin users'  => [AdminUserList::class],
            'group list'   => [ListGroups::class],
            'group users'  => [GroupUserList::class],
        ];
    }

    /**
     * @dataProvider paginatingComponentProvider
     */
    public function test_every_paginating_component_exposes_the_pagination_methods_the_view_calls(string $componentClass): void
    {
        // A vendor bootstrap sablon wire:click="previousPage('page')",
        // "nextPage('page')" és "gotoPage(N, 'page')" hívásokat renderel -
        // ezek a metódusnevek tehát szerződés a nézet felé, nem belső
        // részletek. A Livewire 3 mindegyiket megtartja, de a setPage()
        // implementációja megváltozik (lásd GroupUserListPaginationTest).
        foreach (['previousPage', 'nextPage', 'gotoPage', 'resetPage', 'setPage'] as $method) {
            $this->assertTrue(
                method_exists($componentClass, $method),
                $componentClass.'::'.$method.'() hiányzik.'
            );
        }
    }

    // =========================================================================
    // 2. A renderelt markup
    // =========================================================================

    public function test_the_rendered_pagination_uses_bootstrap_markup_and_not_tailwind(): void
    {
        // Ha a Livewire 3 átállás után a komponens csendben visszaesne az
        // alapértelmezett tailwind témára, a nézet továbbra is renderelne
        // lapozót - csak más CSS-osztályokkal, ami a bootstrap alapú
        // felületen elcsúszva jelenne meg. Ezért mindkét irányban mérünk.
        $user = $this->createUser(['email' => 'pagination-markup@example.test']);
        $this->attachUserToManyGroups($user, 25);

        Livewire::actingAs($user->fresh())
            ->test(ListGroups::class)
            ->assertSee('class="pagination"', false)
            ->assertSee('page-item', false)
            ->assertSee('page-link', false)
            ->assertSee('<nav>', false)
            // A tailwind sablon jellegzetes osztálya:
            ->assertDontSee('relative inline-flex', false);
    }

    public function test_the_pagination_buttons_call_the_livewire_methods_directly(): void
    {
        $user = $this->createUser(['email' => 'pagination-buttons@example.test']);
        $this->attachUserToManyGroups($user, 25);

        Livewire::actingAs($user->fresh())
            ->test(ListGroups::class)
            ->assertSee('wire:click="nextPage(\'page\')"', false)
            ->assertSee('wire:click="gotoPage(2, \'page\')"', false);
    }

    public function test_no_pagination_markup_is_rendered_below_the_page_size(): void
    {
        // A vendor sablon @if ($paginator->hasPages()) mögé rejti az egészet.
        // Enélkül a "van lapozó" assertion mindig igaz lenne.
        $user = $this->createUser(['email' => 'pagination-single@example.test']);
        $this->attachUserToManyGroups($user, 3);

        Livewire::actingAs($user->fresh())
            ->test(ListGroups::class)
            ->assertDontSee('class="pagination"', false)
            ->assertDontSee('wire:click="nextPage', false);
    }
}
