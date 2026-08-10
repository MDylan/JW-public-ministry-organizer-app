<?php

namespace App\Classes;

use Dotenv\Dotenv;
use Illuminate\Support\Facades\Artisan;

class setEnvironment {

    /**
     * The parsed contents of the .env file, cached within the request.
     *
     * @var array<string, string|null>|null
     */
    private static $parsed = null;

    /**
     * A SINGLE value from the .env file, read directly from the file.
     *
     * WHY NOT env() OR config()
     *
     * The screens that call this - the installer's forms and the admin
     * settings editor - edit the .env FILE itself. The value being edited
     * therefore has to be read from the file, not from the runtime environment:
     *
     *  - env() returns null when configuration is cached, because in that
     *    case Laravel doesn't even load the .env. The editor would therefore have shown
     *    EMPTY fields, and on save it would have written the empty values
     *    back into the file - meaning a single click would have wiped out the
     *    APP_NAME, APP_URL and MAIL_* settings;
     *  - config() returns the file's contents only indirectly, filtered
     *    through the config files, and with cached configuration it carries
     *    the value that was current AT CACHING TIME instead of the one current at
     *    the MOMENT OF EDITING.
     *
     * Dotenv's "array backed" reader performs the same quote and
     * escape parsing that Laravel does at load time, but it doesn't
     * touch the $_ENV/$_SERVER arrays or putenv().
     *
     * @param  string|null  $default
     * @return string|null
     */
    static function value(string $key, $default = null)
    {
        $values = self::values();

        return array_key_exists($key, $values) && $values[$key] !== null
            ? $values[$key]
            : $default;
    }

    /**
     * The full .env file as key-value pairs.
     *
     * @return array<string, string|null>
     */
    static function values(): array
    {
        if (self::$parsed !== null) {
            return self::$parsed;
        }

        $path = app()->environmentFilePath();

        if (! is_file($path)) {
            return self::$parsed = [];
        }

        return self::$parsed = Dotenv::createArrayBacked(dirname($path), basename($path))->safeLoad();
    }

    /** The file has changed; the next read should work off the disk again. */
    static function forgetParsedValues(): void
    {
        self::$parsed = null;
    }

    static function setEnvironmentValue(array $values)
    {
        if(
            (auth()->user()->role ?? null) !== "mainAdmin" && request()->routeIs('admin.settings')
            && (
                !request()->routeIs('setup.*')
            )
        ) {
            abort('403');
        }

        $envFile = app()->environmentFilePath();
        $str = file_get_contents($envFile);
    
        if (count($values) > 0) {
            foreach ($values as $envKey => $envValue) {
    
                $str .= "\n"; // In case the searched variable is in the last line without \n
                $keyPosition = strpos($str, "{$envKey}=");
                $endOfLinePosition = strpos($str, "\n", $keyPosition);
                $oldLine = substr($str, $keyPosition, $endOfLinePosition - $keyPosition);
                // If key does not exist, add it
                if ($keyPosition === false || !$endOfLinePosition || !$oldLine) {
                    $str .= "{$envKey}={$envValue}\n";
                } else {
                    $str = str_replace($oldLine, "{$envKey}={$envValue}", $str);
                }
    
            }
        }
    
        $str = substr($str, 0, -1);
        if (!file_put_contents($envFile, trim($str))) {
            return false;
        }
        // The file has changed: the parsed copy is stale.
        self::forgetParsedValues();
        Artisan::call('config:clear');
        return true;
    
    }
}