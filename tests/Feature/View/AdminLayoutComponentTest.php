<?php

namespace Tests\Feature\View;

use Illuminate\Support\Facades\Blade;
use Tests\TestCase;

/**
 * Roadmap TODO 59: `<x-admin-layout>` is an anonymous component.
 *
 * Until TODO 59 the tag was an alias, `Blade::component('layouts.app',
 * 'admin-layout')`, registered from BladeComponentServiceProvider::register().
 * Blade resolves an alias that names a view rather than a class, but only on
 * an undocumented branch of ComponentTagCompiler::componentClass(). The layout
 * now lives at resources/views/components/admin-layout.blade.php, where Blade
 * finds it by name, and Livewire's full-page layout is the same file.
 *
 * Measured at the move: the five views that open with the tag, and three of
 * the route-mounted Livewire pages, rendered byte-identical HTML before and
 * after it.
 */
class AdminLayoutComponentTest extends TestCase
{
    private const VIEW = 'components.admin-layout';

    public function test_the_tag_is_not_an_alias_any_more(): void
    {
        $this->assertArrayNotHasKey('admin-layout', Blade::getClassComponentAliases());
        $this->assertFileDoesNotExist(app_path('Providers/BladeComponentServiceProvider.php'));
    }

    public function test_the_layout_lives_with_the_anonymous_components(): void
    {
        $this->assertTrue(view()->exists(self::VIEW));
        $this->assertFalse(view()->exists('layouts.app'), 'The pre-TODO 59 layout file is back.');
    }

    public function test_the_tag_compiles_to_the_component_view(): void
    {
        $compiled = Blade::compileString('<x-admin-layout></x-admin-layout>');

        $this->assertStringContainsString("'view' => '".self::VIEW."'", $compiled);
    }

    public function test_livewire_renders_full_pages_into_the_same_layout(): void
    {
        // The two consumers of the layout must not drift apart: a Livewire
        // page and a controller page share the navigation, the banners and
        // the scripts.
        $this->assertSame(self::VIEW, config('livewire.layout'));
    }
}
