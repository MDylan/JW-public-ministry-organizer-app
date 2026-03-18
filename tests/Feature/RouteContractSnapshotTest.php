<?php

namespace Tests\Feature;

use Tests\TestCase;

class RouteContractSnapshotTest extends TestCase
{
    public function test_named_route_contracts_match_the_snapshot(): void
    {
        $expected = json_decode(
            file_get_contents(base_path('tests/Fixtures/route-contracts.json')),
            true
        );

        $this->assertIsArray($expected);
        $this->assertSame(
            $expected,
            $this->currentRouteContracts(),
            'Route contracts changed. Regenerate tests/Fixtures/route-contracts.json when route updates are intentional.'
        );
    }

    private function currentRouteContracts(): array
    {
        $contracts = [];

        foreach (app('router')->getRoutes() as $route) {
            $name = $route->getName();
            if ($name === null) {
                continue;
            }

            if (str_starts_with($name, 'debugbar.') || str_starts_with($name, 'livewire.')) {
                continue;
            }

            if (! isset($contracts[$name])) {
                $contracts[$name] = [
                    'methods' => [],
                    'middleware' => [],
                ];
            }

            foreach ($route->methods() as $method) {
                $contracts[$name]['methods'][$method] = true;
            }

            foreach ($route->gatherMiddleware() as $middleware) {
                $contracts[$name]['middleware'][$middleware] = true;
            }
        }

        ksort($contracts);

        $normalized = [];
        foreach ($contracts as $name => $meta) {
            $methods = array_keys($meta['methods']);
            sort($methods);

            $middleware = array_keys($meta['middleware']);
            sort($middleware);

            $normalized[$name] = [
                'methods' => $methods,
                'middleware' => $middleware,
            ];
        }

        return $normalized;
    }
}
