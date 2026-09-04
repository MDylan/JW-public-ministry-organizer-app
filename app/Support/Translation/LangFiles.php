<?php

namespace App\Support\Translation;

use App\Support\Settings\ApplicationSettings;
use Illuminate\Support\Facades\App;
use InvalidArgumentException;
use RuntimeException;
use Throwable;

/**
 * TODO 33.3: the language file repository behind the in-house translation
 * editor, replacing JoeDixon\Translation\Drivers\File.
 *
 * Read TODO 17 in upgrade-roadmap.md for why the package went. This docblock
 * records the three things this class does differently, because each one is a
 * measurement rather than a preference.
 *
 * 1. IT NEVER FLATTENS WITH Arr::dot()
 *
 * The roadmap specified Arr::dot() for read/write. Measuring the language tree
 * killed that: a number of keys in this project CONTAIN a literal dot, most of
 * them in the hu tree - which is the only complete locale. Laravel's Arr::get()
 * checks the literal key before splitting on dots, so those keys resolve today;
 * Arr::set() always splits, so it is not the inverse of Arr::dot(). Feeding the
 * tree through dot() and back restructures several files.
 *
 * So read() returns a PATH (an array of segments) next to each entry and
 * write() writes along that path. The dotted string an entry also carries is a
 * display label and a search target - never an address.
 *
 * 2. IT WRITES THE PROJECT'S OWN FORMAT, PRESERVING KEY ORDER
 *
 * The package wrote var_export() output after a ksort(): old array() syntax,
 * two-space indent, and every file alphabetised on every save. The hu tree is
 * hand-written with short array syntax and four-space indent, and it is the
 * locale everything else is translated from - reordering it on a one-key edit
 * would be destructive to a file people actually read.
 *
 * This writer keeps the file's own key order and emits short array syntax with
 * four-space indent. var_export() is still used per key and per value, so
 * escaping is correct and integer keys stay integers - which matters for the
 * list-shaped entries such as weekday names.
 *
 * WHAT IS LOST: comments. The file is regenerated from its parsed array, so a
 * comment block in a saved file does not survive. Several hu files carry one.
 * Preserving them would mean a real PHP parser, which is a dependency this
 * replacement exists to avoid. Saving is therefore deliberately narrow: write()
 * rewrites nothing when the value is unchanged.
 *
 * 3. THE LOCALE LIST COMES FROM THE APPLICATION, NOT THE FILESYSTEM
 *
 * The package treated every directory under the language path as a locale, and
 * knew nothing about settings.languages - so a locale added in the admin UI had
 * no files, and a locale added in the translation UI never appeared in the
 * language switcher. This class reads the registry through
 * ApplicationSettings::languages(), the same cached reader the rest of the
 * application uses, and Admin\Settings::languageAdd() now calls ensureLocale()
 * so the other half is created at the same moment.
 *
 * CONSEQUENCE, and it is deliberate: a directory that exists on disk but is not
 * registered does not appear in the editor. Registering it in the admin
 * settings makes it editable with its existing files intact - ensureLocale()
 * never touches a directory that is already there.
 *
 * PATH RESOLUTION: always App::langPath(), never a literal. TODO 38 carried
 * out the Laravel 9 move of the language directory - resources/lang became
 * lang/ at the project root - and this class needed no change at all, which
 * is what that choice was for. The constructor takes an override, which is
 * what the tests bind: the editor writes real files, and under
 * APP_ENV=testing the real path is the application's own language tree.
 */
class LangFiles
{
    /**
     * The pseudo-group standing for the root {locale}.json file.
     */
    public const JSON_GROUP = 'json';

    /**
     * How many times the final rename() is attempted. See replace().
     */
    private const REPLACE_ATTEMPTS = 5;

    private ApplicationSettings $settings;

    private string $basePath;

    public function __construct(ApplicationSettings $settings, ?string $basePath = null)
    {
        $this->settings = $settings;
        $this->basePath = rtrim($basePath ?? App::langPath(), DIRECTORY_SEPARATOR);
    }

    public function basePath(): string
    {
        return $this->basePath;
    }

    /**
     * The locales the application offers, normalized.
     *
     * Three writers produce the settings.languages blob and they do not agree
     * on its shape: the installer writes ['name' => '', 'visible' => true], the
     * admin screen writes a filled-in name, and CoreSettingsSeeder used to
     * write a bare string. A bare string reaching $value['visible'] is a
     * TypeError on PHP 8, so this method flattens all of them to one shape and
     * falls back to the code when the name is empty.
     *
     * @return array<string, array{name: string, visible: bool}>
     */
    public function locales(): array
    {
        $locales = [];

        foreach ($this->settings->languages() as $code => $value) {
            $code = (string) $code;

            if (is_array($value)) {
                $name = (string) ($value['name'] ?? '');
                $visible = (bool) ($value['visible'] ?? true);
            } elseif (is_string($value)) {
                $name = $value;
                $visible = true;
            } else {
                $name = '';
                $visible = true;
            }

            $locales[$code] = [
                'name' => $name !== '' ? $name : $code,
                'visible' => $visible,
            ];
        }

        return $locales;
    }

    public function hasLocale(string $locale): bool
    {
        return array_key_exists($locale, $this->locales());
    }

    /**
     * The editable groups of a locale: every {locale}/{group}.php basename plus
     * the json pseudo-group for the root {locale}.json.
     *
     * The json group is offered even when the file does not exist yet, so a key
     * can be added to a locale that has never had one.
     *
     * @return array<int, string>
     */
    public function groups(string $locale): array
    {
        $this->guardSegment($locale, 'locale');

        $groups = [];
        $directory = $this->basePath.DIRECTORY_SEPARATOR.$locale;

        if (is_dir($directory)) {
            foreach (glob($directory.DIRECTORY_SEPARATOR.'*.php') ?: [] as $file) {
                $groups[] = basename($file, '.php');
            }
        }

        sort($groups);
        $groups[] = self::JSON_GROUP;

        return $groups;
    }

    /**
     * The raw array behind a locale and group, exactly as stored.
     */
    public function raw(string $locale, string $group): array
    {
        $path = $this->filePath($locale, $group);

        if (! is_file($path)) {
            return [];
        }

        if ($group === self::JSON_GROUP) {
            $decoded = json_decode((string) file_get_contents($path), true);

            return is_array($decoded) ? $decoded : [];
        }

        $data = require $path;

        return is_array($data) ? $data : [];
    }

    /**
     * The entries of a locale and group, in file order.
     *
     * Each entry carries the structural path it was found at. That path is what
     * write() addresses; the dotted key next to it is a label, and it is NOT
     * safe to split back into a path - see the class docblock.
     *
     * @return array<int, array{key: string, path: array<int, string|int>, value: string}>
     */
    public function read(string $locale, string $group): array
    {
        $entries = [];
        $this->collect($this->raw($locale, $group), [], $entries);

        return $entries;
    }

    /**
     * Write one value at one path, leaving everything else alone.
     *
     * Returns false when nothing was written, which is the common case while
     * someone tabs through a form: an unchanged value must not cost the file
     * its comments.
     *
     * @param  array<int, string|int>  $path
     */
    public function write(string $locale, string $group, array $path, ?string $value): bool
    {
        $this->guardPath($path);

        $data = $this->raw($locale, $group);

        if ($this->pathExists($data, $path) && $this->valueAt($data, $path) === $value) {
            return false;
        }

        $this->setAt($data, $path, $value);
        $this->put($locale, $group, $data);

        return true;
    }

    /**
     * Add a key that does not exist yet, splitting on dots the way Laravel's
     * own translator resolves them.
     *
     * Note the asymmetry with read(): a key entered here is SPLIT, while a key
     * read from a file keeps whatever structure it already had. That is
     * deliberate - the nested form is the convention, and the literal-dot keys
     * that exist today were not created through a UI.
     *
     * @return array<int, string|int> the path the key was written to
     */
    public function addKey(string $locale, string $group, string $key, ?string $value): array
    {
        $key = trim($key);

        if ($key === '') {
            throw new InvalidArgumentException('A translation key cannot be empty.');
        }

        $path = $group === self::JSON_GROUP ? [$key] : explode('.', $key);

        foreach ($path as $segment) {
            if (trim((string) $segment) === '') {
                throw new InvalidArgumentException('A translation key cannot contain an empty segment.');
            }
        }

        $data = $this->raw($locale, $group);

        if ($this->pathExists($data, $path)) {
            throw new InvalidArgumentException('The translation key already exists.');
        }

        $this->setAt($data, $path, $value);
        $this->put($locale, $group, $data);

        return $path;
    }

    /**
     * Create the locale directory if it is missing.
     *
     * Never touches a directory that already exists, and never generates a
     * file. A locale registered after its files were already on disk therefore
     * arrives with its translations intact - which is the whole point, because
     * this project has locale directories that predate the registry.
     */
    public function ensureLocale(string $locale): void
    {
        $this->guardSegment($locale, 'locale');

        $directory = $this->basePath.DIRECTORY_SEPARATOR.$locale;

        if (! is_dir($directory)) {
            mkdir($directory, 0755, true);
        }
    }

    /**
     * Whether a path resolves to something in this group.
     *
     * @param  array<int, string|int>  $path
     */
    public function has(string $locale, string $group, array $path): bool
    {
        return $this->pathExists($this->raw($locale, $group), $path);
    }

    // =========================================================================
    // Internals
    // =========================================================================

    private function filePath(string $locale, string $group): string
    {
        $this->guardSegment($locale, 'locale');

        if ($group === self::JSON_GROUP) {
            return $this->basePath.DIRECTORY_SEPARATOR.$locale.'.json';
        }

        $this->guardSegment($group, 'group');

        return $this->basePath.DIRECTORY_SEPARATOR.$locale.DIRECTORY_SEPARATOR.$group.'.php';
    }

    /**
     * Locale and group names reach this class from Livewire state, i.e. from
     * the browser. They become filesystem paths, so they are checked rather
     * than trusted - a segment is a plain name, never a traversal.
     */
    private function guardSegment(string $segment, string $what): void
    {
        if (! preg_match('/^[A-Za-z0-9_-]+$/', $segment)) {
            throw new InvalidArgumentException("Invalid {$what} name: {$segment}");
        }
    }

    /**
     * @param  array<int, string|int>  $path
     */
    private function guardPath(array $path): void
    {
        if ($path === []) {
            throw new InvalidArgumentException('A translation path cannot be empty.');
        }
    }

    /**
     * @param  array<int, array{key: string, path: array<int, string|int>, value: string}>  $entries
     * @param  array<int, string|int>  $prefix
     */
    private function collect(array $data, array $prefix, array &$entries): void
    {
        foreach ($data as $key => $value) {
            $path = array_merge($prefix, [$key]);

            if (is_array($value)) {
                $this->collect($value, $path, $entries);

                continue;
            }

            $entries[] = [
                'key' => implode('.', array_map('strval', $path)),
                'path' => $path,
                'value' => $value === null ? '' : (string) $value,
            ];
        }
    }

    /**
     * @param  array<int, string|int>  $path
     */
    private function pathExists(array $data, array $path): bool
    {
        foreach ($path as $segment) {
            if (! is_array($data) || ! array_key_exists($segment, $data)) {
                return false;
            }

            $data = $data[$segment];
        }

        return true;
    }

    /**
     * @param  array<int, string|int>  $path
     * @return mixed
     */
    private function valueAt(array $data, array $path)
    {
        foreach ($path as $segment) {
            if (! is_array($data) || ! array_key_exists($segment, $data)) {
                return null;
            }

            $data = $data[$segment];
        }

        return $data;
    }

    /**
     * @param  array<int, string|int>  $path
     * @param  mixed  $value
     */
    private function setAt(array &$data, array $path, $value): void
    {
        $cursor = &$data;

        foreach ($path as $segment) {
            // An intermediate segment that is not an array is replaced by one.
            // On the LAST segment this is immediately overwritten by the value
            // below, which is what makes a plain assignment work here.
            if (! isset($cursor[$segment]) || ! is_array($cursor[$segment])) {
                $cursor[$segment] = [];
            }

            $cursor = &$cursor[$segment];
        }

        $cursor = $value;
    }

    /**
     * Replace a language file, or leave it exactly as it was.
     *
     * A language file is loaded on every request that renders a translated
     * string, so a half-written or unparseable one is not a bad save - it is an
     * outage. Three things stand between an edit and that:
     *
     *  1. The content is generated in full before anything is touched.
     *  2. It goes to a temporary file, which is then read back and compared to
     *     the array it was built from. A file only ever replaces a working one
     *     if it parses AND round-trips.
     *  3. The swap is a rename(), which is atomic on the same filesystem, so a
     *     concurrent reader sees either the old file or the new one - never a
     *     partial write.
     *
     * Any failure removes the temporary file and throws, leaving the original
     * in place. The editor turns that into an error message; nothing is lost.
     */
    private function put(string $locale, string $group, array $data): void
    {
        $path = $this->filePath($locale, $group);

        $directory = dirname($path);
        if (! is_dir($directory)) {
            mkdir($directory, 0755, true);
        }

        $contents = $group === self::JSON_GROUP
            ? $this->renderJson($data)
            : $this->renderPhp($data);

        $temporary = $path.'.'.bin2hex(random_bytes(6)).'.tmp';

        try {
            if (file_put_contents($temporary, $contents) !== strlen($contents)) {
                throw new RuntimeException("The language file {$path} could not be written in full.");
            }

            $this->verifyRoundTrip($temporary, $group, $data);

            $this->replace($temporary, $path);
        } catch (Throwable $e) {
            if (is_file($temporary)) {
                @unlink($temporary);
            }

            throw $e;
        }
    }

    /**
     * Put the verified file in place of the original.
     *
     * rename() is the whole point of the temporary file: on the same filesystem
     * it is atomic, so a concurrent reader gets the old file or the new one and
     * never a half-written one.
     *
     * The retries are not defensive programming, they are a measurement. On
     * Windows the call fails intermittently with "access denied" even when
     * nothing is wrong - a scanner holding the freshly written temporary file
     * for a moment is enough, and this project's own roadmap records that such
     * a scanner runs on the development workstation (TODO 33.4). Measured here:
     * a plain rename fails on a few percent of attempts, which would make
     * roughly one save in thirty fail for no reason; with a retry it has never
     * needed more than a second attempt.
     */
    private function replace(string $temporary, string $path): void
    {
        for ($attempt = 1; $attempt <= self::REPLACE_ATTEMPTS; $attempt++) {
            if (@rename($temporary, $path)) {
                return;
            }

            usleep(20000);
        }

        throw new RuntimeException("The language file {$path} could not be replaced.");
    }

    /**
     * Read the generated file back and require it to be exactly what was meant.
     *
     * The temporary name is unique per write, so this is a genuine parse rather
     * than a cached include. A ParseError from a malformed file is a Throwable
     * and is handled by the caller like any other failure.
     */
    private function verifyRoundTrip(string $temporary, string $group, array $expected): void
    {
        $decoded = $group === self::JSON_GROUP
            ? json_decode((string) file_get_contents($temporary), true)
            : require $temporary;

        if ($decoded !== $expected) {
            throw new RuntimeException(
                'The generated language file did not round-trip, so the original was left in place.'
            );
        }
    }

    /**
     * Short array syntax, four-space indent, original key order.
     */
    private function renderPhp(array $data): string
    {
        return "<?php\n\nreturn ".$this->renderArray($data, 0).";\n";
    }

    private function renderArray(array $data, int $depth): string
    {
        if ($data === []) {
            return '[]';
        }

        $indent = str_repeat('    ', $depth + 1);
        $closing = str_repeat('    ', $depth);
        $lines = '';

        foreach ($data as $key => $value) {
            $lines .= $indent.var_export($key, true).' => '.(is_array($value)
                ? $this->renderArray($value, $depth + 1)
                : var_export($value, true)).",\n";
        }

        return "[\n".$lines.$closing.']';
    }

    /**
     * The shape the existing root JSON files already have.
     *
     * JSON_UNESCAPED_SLASHES is deliberately absent: the files in this project
     * carry escaped slashes, so adding it would rewrite every one of them on
     * the first save.
     */
    private function renderJson(array $data): string
    {
        $encoded = json_encode($data, JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT);

        // json_encode() returns FALSE rather than throwing - on malformed UTF-8
        // above all. Concatenating that gives an empty file, which for a root
        // JSON language file means every key in it, gone; and because the next
        // read then sees nothing, the save after that would "restore" a file
        // holding a single key. Refusing the save is the only safe answer.
        if ($encoded === false) {
            throw new RuntimeException(
                'The translation could not be encoded as JSON: '.json_last_error_msg()
            );
        }

        return $encoded."\n";
    }
}
