<?php

namespace App\Classes;

use Dotenv\Dotenv;
use Illuminate\Support\Facades\Artisan;

class setEnvironment {

    /**
     * A .env fájl beolvasott tartalma, kérésen belül gyorsítótárazva.
     *
     * @var array<string, string|null>|null
     */
    private static $parsed = null;

    /**
     * A .env fájl EGYETLEN értéke, közvetlenül a fájlból.
     *
     * MIÉRT NEM env() VAGY config()
     *
     * Az ezt hívó képernyők - a telepítő űrlapjai és az admin
     * beállításszerkesztője - magát a .env FÁJLT szerkesztik. A szerkesztendő
     * értéket tehát a fájlból kell kiolvasni, nem a futásidejű környezetből:
     *
     *  - az env() a gyorsítótárazott konfiguráció mellett null-t ad, mert
     *    olyankor a Laravel be sem tölti a .env-et. A szerkesztő ezért ÜRES
     *    mezőket mutatott volna, és mentéskor az üres értékeket írta volna
     *    vissza a fájlba - vagyis egyetlen kattintás kitörölte volna az
     *    APP_NAME, APP_URL és MAIL_* beállításokat;
     *  - a config() a fájl tartalmát csak közvetve, a config-fájlok
     *    szűrőjén át adja vissza, és a gyorsítótárazott konfiguráció a
     *    szerkesztés PILLANATÁBAN érvényes fájl helyett a gyorsítótárazáskor
     *    érvényeset hordozza.
     *
     * A Dotenv "array backed" olvasója ugyanazt az idézőjel- és
     * escape-értelmezést végzi, mint amit a Laravel a betöltéskor, de nem
     * nyúl a $_ENV/$_SERVER tömbökhöz és a putenv()-hez.
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
     * A teljes .env fájl kulcs-érték párokként.
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

    /** A fájl megváltozott; a következő olvasás újra a lemezről dolgozzon. */
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
        // A fájl megváltozott: a beolvasott másolat elavult.
        self::forgetParsedValues();
        Artisan::call('config:clear');
        return true;
    
    }
}