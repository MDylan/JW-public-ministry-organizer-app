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

            echo json_encode([
                [
                    'key' => 'base_path',
                    'path' => base_path(),
                ],
                [
                    'key' => 'resource_path',
                    'path' => resource_path(),
                ],
                [
                    'key' => 'config_path',
                    'path' => config_path(),
                ],
                [
                    'key' => 'app_path',
                    'path' => app_path(),
                ],
                [
                    'key' => 'database_path',
                    'path' => database_path(),
                ],
                [
                    'key' => 'lang_path',
                    'path' => lang_path(),
                ],
                [
                    'key' => 'public_path',
                    'path' => public_path(),
                ],
                [
                    'key' => 'storage_path',
                    'path' => storage_path(),
                ],
        ]);
        
echo LaravelVsCode::outputMarker('END_OUTPUT');

exit(0);
