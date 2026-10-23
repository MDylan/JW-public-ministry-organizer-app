<?php

namespace Tests\Feature;

use Illuminate\Support\Facades\Route;

/**
 * Roadmap TODO 55.2: the debug-mode error page after spatie/laravel-ignition.
 *
 * Ignition used to render the page a developer sees when an exception escapes
 * with APP_DEBUG on. Laravel 11 ships its own renderer
 * (Illuminate\Foundation\Exceptions\Renderer), so the package and the four it
 * pulled in were removed. These cases pin what that must not cost: the
 * exception still reaches the developer in debug mode, and still does not
 * reach anyone else without it.
 */
class DebugErrorPageTest extends FeatureTestCase
{
    private const PROBE = 'debug-page-probe-55-2';

    protected function setUp(): void
    {
        parent::setUp();

        Route::middleware('web')->get('/__test/debug-page', function () {
            throw new \RuntimeException(self::PROBE);
        });
    }

    public function test_ignition_is_gone(): void
    {
        $this->assertFalse(class_exists('Spatie\LaravelIgnition\IgnitionServiceProvider'));
        $this->assertFalse(class_exists('Spatie\Ignition\Ignition'));

        // Handler::renderExceptionContent() in debug mode: a bound
        // ExceptionRenderer contract wins (that was ignition), otherwise the
        // framework's Renderer, otherwise Symfony's plain page.
        $this->assertFalse(app()->has(\Illuminate\Contracts\Foundation\ExceptionRenderer::class));
        $this->assertTrue(app()->bound(\Illuminate\Foundation\Exceptions\Renderer\Renderer::class));
    }

    public function test_debug_mode_shows_the_exception_on_the_frameworks_page(): void
    {
        config(['app.debug' => true]);

        $this->get('/__test/debug-page')
            ->assertStatus(500)
            ->assertSee(self::PROBE)
            ->assertSee('RuntimeException');
    }

    public function test_without_debug_mode_the_exception_stays_on_the_server(): void
    {
        config(['app.debug' => false]);

        $this->get('/__test/debug-page')
            ->assertStatus(500)
            ->assertDontSee(self::PROBE);
    }
}
