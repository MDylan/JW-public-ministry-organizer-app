<?php

namespace Tests\Feature\Middleware;

use App\Http\Kernel;
use ReflectionClass;
use ReflectionProperty;
use Tests\TestCase;

/**
 * TODO 41: the HTTP kernel's middleware alias declaration.
 *
 * Laravel 9.19 renamed the kernel's `$routeMiddleware` property to
 * `$middlewareAliases` and marked the old name `@deprecated`. Laravel 10
 * still honours both - `Foundation\Http\Kernel::syncMiddlewareToRouter()`
 * merges them - which is precisely why nothing in the suite noticed that
 * this application was still on the deprecated one two framework majors
 * later. Laravel 11 deletes the kernel altogether (TODO 56), so the rename
 * is worth having before that port rather than inside it.
 *
 * THE SECOND CASE IS THE ONE WITH TEETH, and it is the DateCastingTest
 * pattern: ask the class what IT declares, not what it inherits. The base
 * kernel declares both properties, so a plain `property_exists()` is true
 * either way and would assert nothing at all.
 *
 * The first case says that the kernel's declaration is the WHOLE truth
 * about the router's alias map: an alias left behind in the deprecated
 * property, or registered somewhere else entirely through
 * `Route::aliasMiddleware()`, makes the two sides disagree.
 *
 * WHAT NEITHER CASE CATCHES, measured rather than assumed: renaming an
 * alias KEY leaves both green, because the router faithfully receives
 * whatever the kernel declares. `RouteMiddlewareRegressionTest` is what
 * fails then - misspelling `groupAdmin` here errors exactly one of its
 * cases - and that division of labour is the reason this file does not try
 * to re-assert the route table.
 */
class MiddlewareAliasContractTest extends TestCase
{
    /**
     * Every alias the kernel declares reaches the router, and none is added
     * behind the kernel's back.
     */
    public function test_the_declared_aliases_are_exactly_the_ones_the_router_holds(): void
    {
        $declared = $this->declaredAliases();

        $this->assertNotSame([], $declared, 'The kernel declares no middleware aliases at all.');
        $this->assertSame($declared, $this->app['router']->getMiddleware());
    }

    /**
     * The deprecated Laravel 9 spelling is gone from the application kernel.
     */
    public function test_the_kernel_does_not_declare_the_deprecated_route_middleware_property(): void
    {
        $ownDeclarations = array_filter(
            (new ReflectionClass(Kernel::class))->getProperties(),
            fn (ReflectionProperty $property) => $property->getName() === 'routeMiddleware'
                && $property->getDeclaringClass()->getName() === Kernel::class
        );

        $this->assertSame(
            [],
            $ownDeclarations,
            'App\Http\Kernel still declares $routeMiddleware, which Laravel 9.19 deprecated in favour of $middlewareAliases.'
        );
    }

    /**
     * Reads the kernel's own alias map, which is protected.
     *
     * @return array<string, string>
     */
    private function declaredAliases(): array
    {
        // No setAccessible() call: PHP 8.1 made it a no-op for reflection
        // reads, and this item is a poor place to leave a call that exists
        // only for a runtime the project no longer supports.
        return (new ReflectionProperty(Kernel::class, 'middlewareAliases'))
            ->getValue($this->app->make(Kernel::class));
    }
}
