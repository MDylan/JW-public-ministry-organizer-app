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

function vsCodeGetReflectionMethod(ReflectionClass $reflected): ReflectionMethod {
  return match (true) {
    $reflected->hasMethod('__invoke') => $reflected->getMethod('__invoke'),
    default => $reflected->getMethod('handle'),
  };
}

echo collect(app("Illuminate\Contracts\Http\Kernel")->getMiddlewareGroups())
  ->merge(app("Illuminate\Contracts\Http\Kernel")->getRouteMiddleware())
  ->merge(app("router")->getMiddleware())
  ->map(function ($middleware, $key) {
    $result = [
      "class" => null,
      "path" => null,
      "line" => null,
      "parameters" => null,
      "groups" => [],
    ];

    if (is_array($middleware)) {
      $result["groups"] = collect($middleware)->map(function ($m) {
        if (!class_exists($m)) {
          return [
            "class" => $m,
            "path" => null,
            "line" => null
          ];
        }

        $reflected = new ReflectionClass($m);
        $reflectedMethod = vsCodeGetReflectionMethod($reflected);

        return [
          "class" => $m,
          "path" => LaravelVsCode::relativePath($reflected->getFileName()),
          "line" =>
              $reflectedMethod->getFileName() === $reflected->getFileName()
              ? $reflectedMethod->getStartLine()
              : null
        ];
      })->all();

      return $result;
    }

    $reflected = new ReflectionClass($middleware);
    $reflectedMethod = vsCodeGetReflectionMethod($reflected);

    $result = array_merge($result, [
      "class" => $middleware,
      "path" => LaravelVsCode::relativePath($reflected->getFileName()),
      "line" => $reflectedMethod->getStartLine(),
    ]);

    $parameters = collect($reflectedMethod->getParameters())
      ->filter(function ($rc) {
        return $rc->getName() !== "request" && $rc->getName() !== "next";
      })
      ->map(function ($rc) {
        return $rc->getName() . ($rc->isVariadic() ? "..." : "");
      });

    if ($parameters->isEmpty()) {
      return $result;
    }

    return array_merge($result, [
      "parameters" => $parameters->implode(",")
    ]);
  })
  ->toJson();

echo LaravelVsCode::outputMarker('END_OUTPUT');

exit(0);
