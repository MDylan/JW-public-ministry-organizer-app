<?php

namespace Tests\Feature\Livewire;

use App\Http\Livewire\Admin\Users\ListUsers as AdminUserList;
use App\Http\Livewire\AppComponent;
use App\Http\Livewire\Groups\ListGroups;
use App\Http\Livewire\Groups\ListUsers as GroupUserList;
use Livewire\Livewire;
use Livewire\WithPagination;
use Tests\Feature\FeatureTestCase;
use PHPUnit\Framework\Attributes\DataProvider;

/**
 * TODO 08: the AppComponent pagination contract.
 *
 * Under Livewire 2, AppComponent set `protected $paginationTheme = 'bootstrap'`
 * and the WithPagination trait provided paginationView(). Livewire 3 still
 * reads the property, but moved the two methods onto its pagination hook, so
 * TODO 48 declared them on AppComponent instead. The markup behind
 * livewire::bootstrap is the project's Bootstrap 4 override
 * (resources/views/vendor/livewire/bootstrap.blade.php), not the package's
 * Bootstrap 5 view.
 *
 * The roadmap TODO 08 text names five components (Admin\StaticPages,
 * Admin\AdminNewsletters, Groups\NewsList, Groups\History, Groups\ListUsers).
 * Verified: of these, FOUR do not paginate, and three are not even
 * AppComponent descendants. The three components that actually paginate - based on
 * the paginate() and ->links() calls, exhaustively:
 *
 *   Admin\Users\ListUsers   paginate(20)   admin/users/list-users.blade:135
 *   Groups\ListGroups       paginate(20)   groups/list-groups.blade:122
 *   Groups\ListUsers        manual paginator groups/list-users.blade:312
 */
class PaginationBehaviorTest extends FeatureTestCase
{
    // =========================================================================
    // 1. The theme
    // =========================================================================

    public function test_the_base_component_resolves_the_bootstrap_pagination_views(): void
    {
        // Livewire 3's pagination hook asks the component for these views
        // (SupportPagination::paginationView()). 'livewire::' resolves to
        // resources/views/vendor/livewire first, then to the package.
        $component = new AppComponent();

        $this->assertSame('livewire::bootstrap', $component->paginationView());
        $this->assertSame('livewire::simple-bootstrap', $component->paginationSimpleView());
    }

    #[DataProvider('paginatingComponentProvider')]
    public function test_every_paginating_component_inherits_the_bootstrap_theme(string $componentClass): void
    {
        $component = new $componentClass();

        $this->assertInstanceOf(AppComponent::class, $component);
        $this->assertContains(WithPagination::class, class_uses_recursive($componentClass));
        $this->assertSame('livewire::bootstrap', $component->paginationView());
    }

    public static function paginatingComponentProvider(): array
    {
        return [
            'admin users'  => [AdminUserList::class],
            'group list'   => [ListGroups::class],
            'group users'  => [GroupUserList::class],
        ];
    }

    #[DataProvider('paginatingComponentProvider')]
    public function test_every_paginating_component_exposes_the_pagination_methods_the_view_calls(string $componentClass): void
    {
        // The vendor bootstrap template renders wire:click="previousPage('page')",
        // "nextPage('page')" and "gotoPage(N, 'page')" calls - these method
        // names are therefore a contract with the view, not internal
        // details. Livewire 3 keeps all of them, but the setPage()
        // implementation changes (see GroupUserListPaginationTest).
        foreach (['previousPage', 'nextPage', 'gotoPage', 'resetPage', 'setPage'] as $method) {
            $this->assertTrue(
                method_exists($componentClass, $method),
                $componentClass.'::'.$method.'() hiányzik.'
            );
        }
    }

    // =========================================================================
    // 2. The rendered markup
    // =========================================================================

    public function test_the_rendered_pagination_uses_bootstrap_markup_and_not_tailwind(): void
    {
        // If after the Livewire 3 migration the component silently fell back to
        // the default tailwind theme, the view would still render a
        // paginator - just with different CSS classes, which would appear
        // misaligned on the bootstrap-based UI. That's why we measure in both directions.
        $user = $this->createUser(['email' => 'pagination-markup@example.test']);
        $this->attachUserToManyGroups($user, 25);

        Livewire::actingAs($user->fresh())
            ->test(ListGroups::class)
            ->assertSee('class="pagination"', false)
            ->assertSee('page-item', false)
            ->assertSee('page-link', false)
            ->assertSee('<nav>', false)
            // The tailwind template's characteristic class:
            ->assertDontSee('relative inline-flex', false)
            // Livewire 3's own bootstrap view: Bootstrap 5 classes, a
            // "Showing X to Y of Z results" line and a scroll to the top on
            // every click. Seeing either means the project override is gone.
            ->assertDontSee('fw-semibold', false)
            ->assertDontSee('scrollIntoView', false);
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
        // The vendor template hides the whole thing behind @if ($paginator->hasPages()).
        // Without this, the "pagination exists" assertion would always be true.
        $user = $this->createUser(['email' => 'pagination-single@example.test']);
        $this->attachUserToManyGroups($user, 3);

        Livewire::actingAs($user->fresh())
            ->test(ListGroups::class)
            ->assertDontSee('class="pagination"', false)
            ->assertDontSee('wire:click="nextPage', false);
    }
}
