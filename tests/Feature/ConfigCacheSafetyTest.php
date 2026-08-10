<?php

namespace Tests\Feature;

use App\Classes\setEnvironment;
use Illuminate\Support\Facades\Blade;
use RecursiveDirectoryIterator;
use RecursiveIteratorIterator;
use SplFileInfo;
use Tests\TestCase;

/**
 * TODO 28: is `config:cache` safe.
 *
 * THE PROBLEM THIS GUARD PROTECTS AGAINST
 *
 * Laravel only loads the `.env` file if there is no cached configuration. So
 * after a `php artisan config:cache` - and with it `artisan optimize` -
 * every `env()` call OUTSIDE the config files returns `null`. Silently: no
 * error message, no exception, just different behaviour.
 *
 * In this codebase this affected 24 locations, and their consequences were
 * not uniform:
 *
 *  - `USE_HTTPS` -> forcing HTTPS turns off;
 *  - `USE_RECAPTCHA` -> bot protection turns off, and the six Blade views
 *    do not even render the captcha field, so nothing gives it away;
 *  - `MAIL_FROM_ADDRESS` -> sending five notifications FAILS
 *    (`Swift_RfcComplianceException` on the empty address);
 *  - `APP_NAME` -> the mail goes out with an empty application name;
 *  - the admin's `.env` editor shows empty fields, and on save writes the
 *    empty values back to the file.
 *
 * On top of that, `optimize` did not even reach `config:cache` for years,
 * because the duplicated `password.confirm` route name broke `route:cache`
 * (v1-patch A7). Once that was cleared, this trap became live - which is
 * why it had to be fixed in the same release.
 */
class ConfigCacheSafetyTest extends TestCase
{
    /**
     * The scanned directories. `config/` is deliberately NOT among them:
     * that is where `env()` belongs, it is the one layer the cache
     * captures. Neither is `tests/`: the tests are precisely what examine
     * what happens with the variable.
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
        // If these keys disappear, the middlewares silently get false -
        // the same bug, just for a different reason.
        $this->assertIsBool(config('security.use_https'));
        $this->assertIsBool(config('security.use_recaptcha'));
    }

    public function test_the_env_file_reader_reads_the_file_and_not_the_environment(): void
    {
        // The .env editor screens read through this. The key is in the
        // file, but we must still get it even with it removed from the
        // $_SERVER/$_ENV/putenv trio - this is the difference between
        // env() and this.
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
     * Does the file call `env()` or `getenv()`.
     *
     * Tokenized, not with a regex: the EXPLANATION of the fixes describes
     * the old `env('USE_HTTPS')` form in several places, and a text search
     * would also catch those comments. Method calls of the form `->env(`
     * must also be excluded, because those are not the helper function.
     *
     * The Blade views are compiled FIRST. Uncompiled, both `{{ ... }}` and
     * `@if (...)` are T_INLINE_HTML, so the tokenizer sees nothing in them -
     * a control experiment demonstrated exactly this: an `@if
     * (env('PROBE'))` placed in a view slipped past the guard. The compiled
     * form, however, is exactly what will run.
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

            // A method or static call: not the helper function.
            $previous = $this->significantToken($tokens, $index, -1);
            if (is_array($previous) && in_array($previous[0], [T_OBJECT_OPERATOR, T_DOUBLE_COLON, T_FUNCTION], true)) {
                continue;
            }

            // Only the form that is actually called counts.
            $next = $this->significantToken($tokens, $index, 1);
            if ($next !== '(') {
                continue;
            }

            return true;
        }

        return false;
    }

    /**
     * The call's likely lines in the ORIGINAL file.
     *
     * Compiling Blade shifts the line numbers, so for the error message we
     * look up the location in the raw source. This is only reporting, not
     * detection - the decision is made by readsTheEnvironment().
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
     * The neighbouring token, skipping over whitespace.
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
