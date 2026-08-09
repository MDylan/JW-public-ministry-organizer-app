<?php

namespace Tests\Feature;

use App\Classes\setEnvironment;
use Illuminate\Support\Facades\Blade;
use RecursiveDirectoryIterator;
use RecursiveIteratorIterator;
use SplFileInfo;
use Tests\TestCase;

/**
 * TODO 28: a `config:cache` biztonságos-e.
 *
 * A PROBLÉMA, AMIT EZ AZ ŐR VÉD
 *
 * A Laravel a `.env` fájlt CSAK akkor tölti be, ha nincs gyorsítótárazott
 * konfiguráció. Egy `php artisan config:cache` - és vele az `artisan optimize`
 * - után tehát a config-fájlokon KÍVÜLI minden `env()` hívás `null`-t ad.
 * Némán: nincs hibaüzenet, nincs kivétel, csak más viselkedés.
 *
 * Ezen a kódbázison ez 24 helyet érintett, és a következményük nem volt
 * egyforma:
 *
 *  - `USE_HTTPS` -> a HTTPS-re kényszerítés kikapcsol;
 *  - `USE_RECAPTCHA` -> a botvédelem kikapcsol, és a hat Blade nézet a
 *    captcha mezőt sem teszi ki, tehát semmi nem árulkodik;
 *  - `MAIL_FROM_ADDRESS` -> öt értesítés küldése MEGHIÚSUL
 *    (`Swift_RfcComplianceException` az üres címen);
 *  - `APP_NAME` -> a levél kimegy, üres alkalmazásnévvel;
 *  - az admin `.env`-szerkesztője üres mezőket mutat, és mentéskor az
 *    üreseket írja vissza a fájlba.
 *
 * Az `optimize` ráadásul évekig el sem jutott a `config:cache`-ig, mert a
 * duplikált `password.confirm` route-név megbuktatta a `route:cache`-t
 * (v1-patch A7). Ahogy az elhárult, ez a csapda élessé vált - ezért kellett
 * ugyanabban a kiadásban rendezni.
 */
class ConfigCacheSafetyTest extends TestCase
{
    /**
     * A pásztázott könyvtárak. A `config/` szándékosan NINCS köztük: ott az
     * `env()` a helyén van, az az egyetlen réteg, amit a gyorsítótár rögzít.
     * A `tests/` sem: a tesztek épp azt vizsgálják, mi történik a változóval.
     */
    private const SCANNED = [
        'app',
        'routes',
        'database',
        'resources/views',
    ];

    public function test_no_runtime_code_reads_the_environment_directly(): void
    {
        $offenders = [];

        foreach (self::SCANNED as $directory) {
            foreach ($this->filesIn(base_path($directory)) as $file) {
                $path = str_replace('\\', '/', substr($file->getPathname(), strlen(base_path()) + 1));

                if ($this->readsTheEnvironment($file->getPathname())) {
                    foreach ($this->likelyLines($file->getPathname()) as $line) {
                        $offenders[] = $path.':'.$line;
                    }
                }
            }
        }

        sort($offenders);

        $this->assertSame(
            [],
            $offenders,
            "Futásidejű env()/getenv() hívás a config/ könyvtáron kívül. Egy `php artisan config:cache` "
            ."után ezek null-t adnak, némán. A .env FÁJLT szerkesztő képernyők a "
            .'App\Classes\setEnvironment::value() metódust használják helyette.'
        );
    }

    public function test_the_two_security_flags_exist_as_configuration(): void
    {
        // Ha ezek a kulcsok eltűnnek, a middleware-ek némán hamisat kapnak -
        // ugyanaz a hiba, csak más okból.
        $this->assertIsBool(config('security.use_https'));
        $this->assertIsBool(config('security.use_recaptcha'));
    }

    public function test_the_env_file_reader_reads_the_file_and_not_the_environment(): void
    {
        // A .env-szerkesztő képernyők ezen keresztül olvasnak. A kulcs a
        // fájlban van, de a $_SERVER/$_ENV/putenv hármasból kivéve is meg kell
        // kapnunk - ez a különbség env() és e között.
        $expected = setEnvironment::value('APP_NAME');

        $this->assertNotNull($expected, 'Az APP_NAME-nek benne kell lennie a teszt .env fájljában.');

        $hadServer = array_key_exists('APP_NAME', $_SERVER);
        $server = $_SERVER['APP_NAME'] ?? null;
        unset($_SERVER['APP_NAME'], $_ENV['APP_NAME']);
        putenv('APP_NAME');

        try {
            setEnvironment::forgetParsedValues();

            $this->assertSame($expected, setEnvironment::value('APP_NAME'));
        } finally {
            if ($hadServer) {
                $_SERVER['APP_NAME'] = $server;
            }
            setEnvironment::forgetParsedValues();
        }
    }

    public function test_the_env_file_reader_falls_back_for_an_unknown_key(): void
    {
        $this->assertSame('alapertelmezes', setEnvironment::value('NINCS_ILYEN_KULCS', 'alapertelmezes'));
        $this->assertNull(setEnvironment::value('NINCS_ILYEN_KULCS'));
    }

    /**
     * @return \Generator<SplFileInfo>
     */
    private function filesIn(string $directory): \Generator
    {
        if (! is_dir($directory)) {
            return;
        }

        $files = new RecursiveIteratorIterator(
            new RecursiveDirectoryIterator($directory, RecursiveDirectoryIterator::SKIP_DOTS)
        );

        /** @var SplFileInfo $file */
        foreach ($files as $file) {
            if ($file->isFile() && in_array($file->getExtension(), ['php'], true)) {
                yield $file;
            }
        }
    }

    /**
     * Hív-e a fájl `env()`-et vagy `getenv()`-et.
     *
     * Tokenizálva, nem regexszel: a javítások MAGYARÁZATA több helyen leírja
     * a régi `env('USE_HTTPS')` alakot, és egy szöveges keresés azokra a
     * kommentekre is rácsapna. A `->env(` alakú metódushívásokat is ki kell
     * zárni, mert azok nem a segédfüggvényt jelentik.
     *
     * A Blade nézeteket ELŐBB LEFORDÍTJUK. Fordítatlanul a `{{ ... }}` és a
     * `@if (...)` egyaránt T_INLINE_HTML, tehát a tokenizáló nem lát bennük
     * semmit - egy kontroll-kísérlet ezt meg is mutatta: a nézetbe tett
     * `@if (env('PROBE'))` átcsúszott az őrön. A lefordított alak viszont
     * pontosan az, ami futni fog.
     */
    private function readsTheEnvironment(string $path): bool
    {
        $source = file_get_contents($path);

        if (str_ends_with($path, '.blade.php')) {
            $source = Blade::compileString($source);
        }

        $tokens = token_get_all($source);

        foreach ($tokens as $index => $token) {
            if (! is_array($token) || $token[0] !== T_STRING) {
                continue;
            }

            if (! in_array($token[1], ['env', 'getenv'], true)) {
                continue;
            }

            // Metódus- vagy statikus hívás: nem a segédfüggvény.
            $previous = $this->significantToken($tokens, $index, -1);
            if (is_array($previous) && in_array($previous[0], [T_OBJECT_OPERATOR, T_DOUBLE_COLON, T_FUNCTION], true)) {
                continue;
            }

            // Csak a ténylegesen meghívott alak számít.
            $next = $this->significantToken($tokens, $index, 1);
            if ($next !== '(') {
                continue;
            }

            return true;
        }

        return false;
    }

    /**
     * A hívás valószínű sorai az EREDETI fájlban.
     *
     * A Blade fordítása elmozdítja a sorszámokat, ezért a hibaüzenethez a
     * nyers forrásban keressük meg a helyet. Ez csak jelentés, nem detektálás
     * - a döntést a readsTheEnvironment() hozza.
     *
     * @return int[]
     */
    private function likelyLines(string $path): array
    {
        $lines = [];

        foreach (file($path) as $index => $line) {
            if (preg_match('/(?<![\w>$])(get)?env\s*\(/', $line)) {
                $lines[] = $index + 1;
            }
        }

        return $lines === [] ? [0] : $lines;
    }

    /**
     * A szomszédos token, a térközöket átugorva.
     *
     * @param  array<int, mixed>  $tokens
     * @return mixed
     */
    private function significantToken(array $tokens, int $index, int $direction)
    {
        for ($i = $index + $direction; isset($tokens[$i]); $i += $direction) {
            if (is_array($tokens[$i]) && in_array($tokens[$i][0], [T_WHITESPACE, T_COMMENT, T_DOC_COMMENT], true)) {
                continue;
            }

            return $tokens[$i];
        }

        return null;
    }
}
