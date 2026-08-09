<?php


error_reporting(E_ERROR | E_PARSE);

define('LARAVEL_START', microtime(true));

require_once __DIR__ . '/../autoload.php';

class LaravelVsCode
{
    public static function relativePath($path)
    {
        if (!str_contains($path, base_path())) {
            return (string) $path;
        }

        return ltrim(str_replace(base_path(), '', realpath($path) ?: $path), DIRECTORY_SEPARATOR);
    }

    public static function isVendor($path)
    {
        return str_contains($path, base_path("vendor"));
    }

    public static function outputMarker($key)
    {
        return '__VSCODE_LARAVEL_' . $key . '__';
    }

    public static function startupError(\Throwable $e)
    {
        throw new Error(self::outputMarker('STARTUP_ERROR') . ': ' . $e->getMessage());
    }
}

try {
    $app = require_once __DIR__ . '/../../bootstrap/app.php';
} catch (\Throwable $e) {
    LaravelVsCode::startupError($e);
    exit(1);
}

$app->register(new class($app) extends \Illuminate\Support\ServiceProvider
{
    public function boot()
    {
        config([
            'logging.channels.null' => [
                'driver' => 'monolog',
                'handler' => \Monolog\Handler\NullHandler::class,
            ],
            'logging.default' => 'null',
        ]);
    }
});

try {
    $kernel = $app->make(Illuminate\Contracts\Console\Kernel::class);
    $kernel->bootstrap();
} catch (\Throwable $e) {
    LaravelVsCode::startupError($e);
    exit(1);
}

echo LaravelVsCode::outputMarker('START_OUTPUT');

$routes = new class {
    public function all()
    {
        return collect(app('router')->getRoutes()->getRoutes())
            ->map(fn(\Illuminate\Routing\Route $route) => $this->getRoute($route))
            ->merge($this->getFolioRoutes());
    }

    protected function getFolioRoutes()
    {
        try {
            $output = new \Symfony\Component\Console\Output\BufferedOutput();

            \Illuminate\Support\Facades\Artisan::call("folio:list", ["--json" => true], $output);

            $mountPaths = collect(app(\Laravel\Folio\FolioManager::class)->mountPaths());

            return collect(json_decode($output->fetch(), true))->map(fn($route) => $this->getFolioRoute($route, $mountPaths));
        } catch (\Exception | \Throwable $e) {
            return [];
        }
    }

    protected function getFolioRoute($route, $mountPaths)
    {
        if ($mountPaths->count() === 1) {
            $mountPath = $mountPaths[0];
        } else {
            $mountPath = $mountPaths->first(fn($mp) => file_exists($mp->path . DIRECTORY_SEPARATOR . $route['view']));
        }

        $path = $route['view'];

        if ($mountPath) {
            $path = $mountPath->path . DIRECTORY_SEPARATOR . $path;
        }

        return [
            'method' => $route['method'],
            'uri' => $route['uri'],
            'name' => $route['name'],
            'action' => null,
            'parameters' => [],
            'filename' => LaravelVsCode::relativePath($path),
            'line' => 0,
        ];
    }

    protected function getRoute(\Illuminate\Routing\Route $route)
    {
        try {
            $reflection = $this->getRouteReflection($route);
        } catch (\Throwable $e) {
            $reflection = null;
        }

        return [
            'method' => collect($route->methods())
                ->filter(fn($method) => $method !== 'HEAD')
                ->implode('|'),
            'uri' => $route->uri(),
            'name' => $route->getName(),
            'action' => $route->getActionName(),
            'parameters' => $route->parameterNames(),
            'filename' => $reflection ? LaravelVsCode::relativePath($reflection->getFileName()) : null,
            'line' => $reflection ? $reflection->getStartLine() : null,
            'livewire' => $this->getLivewireView($route),
        ];
    }

    protected function getLivewireView(\Illuminate\Routing\Route $route): ?string
    {
        if ($route->getActionName() !== 'Livewire\Features\SupportRouting\LivewirePageController') {
            return null;
        }

        return $route->defaults['_livewire_component'] ?? null;
    }

    protected function getRouteReflection(\Illuminate\Routing\Route $route)
    {
        if ($route->getActionName() === 'Closure') {
            return new \ReflectionFunction($route->getAction()['uses']);
        }

        if (!str_contains($route->getActionName(), '@')) {
            return new \ReflectionClass($route->getActionName());
        }

        try {
            return new \ReflectionMethod($route->getControllerClass(), $route->getActionMethod());
        } catch (\Throwable $e) {
            $namespace = app(\Illuminate\Routing\UrlGenerator::class)->getRootControllerNamespace()
                ?? (app()->getNamespace() . 'Http\Controllers');

            return new \ReflectionMethod(
                $namespace . '\\' . ltrim($route->getControllerClass(), '\\'),
                $route->getActionMethod(),
            );
        }
    }
};

echo $routes->all()->toJson();

echo LaravelVsCode::outputMarker('END_OUTPUT');

exit(0);
