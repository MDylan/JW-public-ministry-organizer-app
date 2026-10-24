<?php

namespace Tests\Feature\Auth;

use Illuminate\Routing\Route;
use Tests\TestCase;

/**
 * Roadmap TODO 69: routes/fortify.php against Fortify's own route file.
 *
 * The application keeps its own copy of Fortify's routes (the reason is in
 * that file's header), and a copy goes stale silently: Fortify adds a route
 * in a minor release and the application simply never registers it. That is
 * how the confirmation and secret-key routes went missing until TODO 69.
 *
 * So this reads vendor/laravel/fortify/routes/routes.php - method, path,
 * controller action and name of every definition - and compares it with the
 * routes this application registers for Fortify's controllers. Middleware is
 * deliberately not compared: the application's own middleware is the point of
 * keeping the copy.
 */
class FortifyRouteParityTest extends TestCase
{
    /**
     * Fortify routes the application deliberately does not register, each
     * mapped to the application's own route (routes/web.php) that takes its
     * name and its job.
     */
    private const DELIBERATELY_ABSENT = [
        'GET /email/verify' => 'verification.notice',
        'GET /user/confirm-password' => 'password.confirm',
        'POST /user/confirm-password' => 'password.confirm.store',
    ];

    public function test_every_fortify_route_is_registered_or_deliberately_absent(): void
    {
        $vendor = $this->vendorRoutes();
        $registered = $this->registeredFortifyRoutes();

        $missing = array_diff_key($vendor, $registered, self::DELIBERATELY_ABSENT);

        $this->assertSame([], $missing, 'Fortify defines these routes and routes/fortify.php does not.');
    }

    public function test_every_registered_fortify_route_matches_fortifys_definition(): void
    {
        $vendor = $this->vendorRoutes();

        foreach ($this->registeredFortifyRoutes() as $key => $route) {
            $this->assertArrayHasKey($key, $vendor, "{$key} is not a route Fortify defines.");
            $this->assertSame($vendor[$key], $route, "{$key} differs from Fortify's definition.");
        }
    }

    public function test_the_deliberately_absent_routes_are_replaced_by_the_application(): void
    {
        $vendor = $this->vendorRoutes();

        foreach (self::DELIBERATELY_ABSENT as $key => $name) {
            $this->assertArrayHasKey($key, $vendor, "{$key} is no longer a Fortify route; drop it from the list.");
            $this->assertSame($name, $vendor[$key]['name'], "Fortify renamed {$key}.");

            $own = app('router')->getRoutes()->getByName($name);
            $this->assertNotNull($own, "{$key} is listed as replaced by {$name}, but no route has that name.");
            $this->assertStringStartsNotWith('Laravel\\Fortify\\', $own->getActionName(), "{$name} is Fortify's route, not the application's.");
        }
    }

    /**
     * @return array<string, array{action: string, name: string|null}>
     */
    private function vendorRoutes(): array
    {
        $source = file_get_contents(base_path('vendor/laravel/fortify/routes/routes.php'));

        preg_match_all('/^use (Laravel\\\\(?:Fortify|Passkeys)\\\\Http\\\\Controllers\\\\(\w+));$/m', $source, $uses, PREG_SET_ORDER);
        $classes = [];
        foreach ($uses as [, $fqcn, $short]) {
            $classes[$short] = $fqcn;
        }

        $routes = [];
        foreach (array_slice(explode('Route::', $source), 1) as $chunk) {
            if (! preg_match("/^(get|post|put|patch|delete)\(RoutePath::for\('[^']+',\s*'([^']+)'\),\s*\[(\w+)::class,\s*'(\w+)'\]\)/", $chunk, $m)) {
                continue;
            }

            preg_match("/->name\('([^']+)'\)/", $chunk, $name);

            $routes[strtoupper($m[1]).' '.$m[2]] = [
                'action' => ($classes[$m[3]] ?? $m[3]).'@'.$m[4],
                'name' => $name[1] ?? null,
            ];
        }

        $this->assertGreaterThan(20, count($routes), 'The vendor route file was not parsed - its shape changed.');

        return $routes;
    }

    /**
     * @return array<string, array{action: string, name: string|null}>
     */
    private function registeredFortifyRoutes(): array
    {
        $routes = [];

        /** @var Route $route */
        foreach (app('router')->getRoutes()->getRoutes() as $route) {
            // Fortify's controllers, and since Fortify 1.41 laravel/passkeys'
            // (Fortify's route file names those too).
            $action = $route->getActionName();
            if (! str_starts_with($action, 'Laravel\\Fortify\\Http\\Controllers\\')
                && ! str_starts_with($action, 'Laravel\\Passkeys\\Http\\Controllers\\')) {
                continue;
            }

            // Invokable controllers register as Class, Fortify's file names them
            // Class@__invoke.
            if (! str_contains($action, '@')) {
                $action .= '@__invoke';
            }

            $method = collect($route->methods())->reject(fn ($m) => $m === 'HEAD')->first();
            $routes[$method.' /'.ltrim($route->uri(), '/')] = [
                'action' => $action,
                'name' => $route->getName(),
            ];
        }

        return $routes;
    }
}
