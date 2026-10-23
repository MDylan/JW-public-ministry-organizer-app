<?php

namespace Tests\Feature;

use Tests\TestCase;

/**
 * Roadmap TODO 56: the application runs on Laravel 11's slim skeleton.
 *
 * bootstrap/app.php configures routing, middleware and exceptions; the
 * application's providers are listed in bootstrap/providers.php. These cases
 * pin the shape - what the stacks resolve to is MiddlewareStackContractTest's
 * and EventListenerContractTest's job, and the route load order
 * RouteContractSnapshotTest's.
 */
class SlimSkeletonTest extends TestCase
{
    /** Deleted by TODO 56; their contents moved, see each entry. */
    private const REMOVED = [
        'app/Http/Kernel.php',                       // bootstrap/app.php withMiddleware()
        'app/Console/Kernel.php',                    // routes/console.php
        'app/Exceptions/Handler.php',                // bootstrap/app.php withExceptions()
        'app/Providers/RouteServiceProvider.php',    // withRouting(); the api limiter to AppServiceProvider
        'app/Providers/EventServiceProvider.php',    // listeners discovered; observers to AppServiceProvider
        'app/Providers/BroadcastServiceProvider.php', // was never registered
    ];

    public function test_the_laravel_10_skeleton_files_are_gone(): void
    {
        foreach (self::REMOVED as $path) {
            $this->assertFileDoesNotExist(base_path($path));
        }
    }

    public function test_the_contracts_resolve_to_the_frameworks_classes(): void
    {
        $this->assertSame(
            \Illuminate\Foundation\Http\Kernel::class,
            get_class($this->app->make(\Illuminate\Contracts\Http\Kernel::class))
        );
        $this->assertSame(
            \Illuminate\Foundation\Console\Kernel::class,
            get_class($this->app->make(\Illuminate\Contracts\Console\Kernel::class))
        );
        $this->assertSame(
            \Illuminate\Foundation\Exceptions\Handler::class,
            get_class($this->app->make(\Illuminate\Contracts\Debug\ExceptionHandler::class))
        );
    }

    public function test_the_application_providers_and_their_order(): void
    {
        $this->assertSame([
            \App\Providers\AppServiceProvider::class,
            \App\Providers\AuthServiceProvider::class,
            \App\Providers\BladeComponentServiceProvider::class,
            \MDylan\LaraUpdater\LaraUpdaterServiceProvider::class,
            \App\Providers\FortifyServiceProvider::class,
        ], require base_path('bootstrap/providers.php'));

        // config/app.php no longer lists providers; with the framework config
        // not merged (bootstrap/app.php), RegisterProviders falls back to the
        // framework's defaults. Ask the loaded application, not the file.
        $loaded = array_keys($this->app->getLoadedProviders());
        $this->assertContains(\Illuminate\Foundation\Providers\FoundationServiceProvider::class, $loaded);
        $this->assertContains(\App\Providers\FortifyServiceProvider::class, $loaded);
        $this->assertNotContains('App\Providers\RouteServiceProvider', $loaded);
        $this->assertNotContains('App\Providers\EventServiceProvider', $loaded);
    }

    public function test_the_framework_configuration_is_still_not_merged(): void
    {
        // The TODO 56 decision: config/ stays complete and authoritative.
        $this->assertFalse($this->app->shouldMergeFrameworkConfiguration());
        $this->assertArrayNotHasKey('s3', config('filesystems.disks'));
    }
}
