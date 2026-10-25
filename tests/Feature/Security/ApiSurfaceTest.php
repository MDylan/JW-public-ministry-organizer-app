<?php

namespace Tests\Feature\Security;

use Illuminate\Routing\Route;
use Tests\Feature\FeatureTestCase;

/**
 * Roadmap TODO 71: the application has no API surface and no CORS layer.
 *
 * `routes/api.php` was the framework's stock stub: one `GET /api/user` behind
 * `auth:api`, the `token` guard. No client called it, and no migration ever
 * created the `api_token` column that guard looks users up by, so it never
 * authenticated anyone on any install - all it did was keep a TokenGuard
 * reachable (PKSA-b41t-wbzk-9q51, see TODO 61). `config/cors.php` covered
 * `api/*` and `sanctum/csrf-cookie` (Sanctum was never installed) with a
 * static `Access-Control-Allow-Origin: *`.
 *
 * The developer decided to remove all of it: the route file, the `api`
 * guard, the `throttle:api` limiter, `config/cors.php` and `HandleCors` from
 * the global stack. This file replaces CorsHeadersTest, which TODO 35 wrote
 * to pin the old CORS contract across the fruitcake/laravel-cors swap; what
 * it pinned is exactly what is now gone.
 *
 * The framework still registers its default `api` middleware group
 * (Middleware::getMiddlewareGroups() always adds it); MiddlewareStackContractTest
 * pins its content, and the case below shows no route carries it.
 */
class ApiSurfaceTest extends FeatureTestCase
{
    private const ORIGIN = 'https://kliens.example.test';

    public function test_no_route_lives_under_the_api_prefix(): void
    {
        $uris = [];

        foreach (app('router')->getRoutes() as $route) {
            if (str_starts_with($route->uri(), 'api/') || $route->uri() === 'api') {
                $uris[] = $route->uri();
            }
        }

        $this->assertSame([], $uris);
        $this->assertFileDoesNotExist(base_path('routes/api.php'));
    }

    public function test_no_route_carries_the_api_group_or_the_token_guard(): void
    {
        $carriers = [];

        foreach (app('router')->getRoutes() as $route) {
            /** @var Route $route */
            $middleware = $route->middleware();

            if (in_array('api', $middleware, true) || in_array('auth:api', $middleware, true)) {
                $carriers[] = $route->uri();
            }
        }

        $this->assertSame([], $carriers);
    }

    public function test_the_former_endpoint_is_not_found_and_carries_no_cors_header(): void
    {
        $response = $this->call('GET', '/api/user', [], [], [], [
            'HTTP_ORIGIN' => self::ORIGIN,
            'HTTP_ACCEPT' => 'application/json',
        ]);

        $response->assertNotFound();
        $response->assertHeaderMissing('Access-Control-Allow-Origin');
    }

    public function test_a_preflight_is_no_longer_answered(): void
    {
        // HandleCors answered this itself with a 204 before routing ran.
        // Without it the router has nothing for the path.
        $response = $this->call('OPTIONS', '/api/user', [], [], [], [
            'HTTP_ORIGIN' => self::ORIGIN,
            'HTTP_ACCESS_CONTROL_REQUEST_METHOD' => 'GET',
        ]);

        $this->assertNotSame(204, $response->getStatusCode());
        $response->assertHeaderMissing('Access-Control-Allow-Origin');
        $response->assertHeaderMissing('Access-Control-Allow-Methods');
    }

    public function test_a_web_page_carries_no_cors_header(): void
    {
        // Unchanged by TODO 71 - `/` was never a CORS path. It stays as the
        // control that the cases above do not pass merely because the
        // request reached a 404.
        $this->call('GET', '/', [], [], [], ['HTTP_ORIGIN' => self::ORIGIN])
            ->assertOk()
            ->assertHeaderMissing('Access-Control-Allow-Origin');
    }

    public function test_the_configuration_has_no_token_guard_and_no_cors_section(): void
    {
        $this->assertSame(['web'], array_keys(config('auth.guards')));
        $this->assertNull(config('cors'));
        $this->assertFileDoesNotExist(config_path('cors.php'));
        // HandleCors leaving the global stack is MiddlewareStackContractTest's.
    }
}
