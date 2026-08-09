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

use Pest\Expectation;
use Pest\TestSuite;

$pest = new class {
    public function __construct()
    {
        if ($this->isInstalled()) {
            $this->boot();
        }
    }

    public function isInstalled(): bool
    {
        return class_exists(TestSuite::class);
    }

    protected function boot(): void
    {
        require_once base_path('vendor/pestphp/pest/overrides/Runner/TestSuiteLoader.php');

        TestSuite::getInstance(base_path(), 'tests');

        if (file_exists($pestFile = base_path('tests/Pest.php'))) {
            require_once $pestFile;
        }
    }

    public function config(): ?array
    {
        if (! $this->isInstalled()) {
            return null;
        }

        return [
            'uses' => $this->uses(),
            'expectations' => $this->expectations(),
        ];
    }

    protected function uses(): array
    {
        if (is_null($instance = TestSuite::getInstance())) {
            return [];
        }

        $reflection = new ReflectionProperty($instance->tests, 'uses');
        $uses = $reflection->getValue($instance->tests);

        return collect($uses)->map(function (array $use, string $path) {
            [$classOrTraits] = $use;

            return [
                'path' => LaravelVsCode::relativePath($path),
                'classes' => array_values(array_filter($classOrTraits, fn ($c) => class_exists($c))),
                'traits' => array_values(array_filter($classOrTraits, fn ($c) => trait_exists($c))),
            ];
        })->values()->all();
    }

    protected function expectations(): array
    {
        $reflection = new ReflectionProperty(Expectation::class, 'extends');
        $extends = $reflection->getValue();

        return collect($extends)->map(function (Closure $closure, string $name) {
            $parameters = collect((new ReflectionFunction($closure))->getParameters())
                ->map(function (ReflectionParameter $param) {
                    $type = $param->hasType() ? $param->getType() . ' ' : '';

                    $default = $param->isOptional() && $param->isDefaultValueAvailable()
                        ? ' = ' . var_export($param->getDefaultValue(), true)
                        : '';

                    return $type . "$" . $param->getName() . $default;
                })
                ->join(', ');

            return compact('name', 'parameters');
        })->values()->all();
    }
};

echo json_encode($pest->config());

echo LaravelVsCode::outputMarker('END_OUTPUT');

exit(0);
