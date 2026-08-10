<?php

namespace App\Support\Settings;

use App\Models\Settings;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Config;
use Illuminate\Support\Facades\Log;

/**
 * TODO 31: the ONE AND ONLY reader of the application settings table.
 *
 * WHAT USED TO BE HERE
 *
 * AppServiceProvider::boot() ran a Settings::all() query on every request and
 * wrapped the whole thing - the query, the language blob decode, the ten
 * Config::set() calls and the debugbar branch - in a single empty
 * `catch (\Exception $e) {}`. That was two separate defects in one:
 *
 *  1. THE SILENT SWALLOW. If reading the settings failed for any reason, the
 *     application carried on quietly with default settings. On the Laravel
 *     8 -> 13 road that is not a theoretical risk: it is exactly the kind of
 *     failure a version jump produces, and it would have vanished without a
 *     trace.
 *  2. THE PER-REQUEST QUERY. A handful of rows that change rarely, re-read on
 *     every single request.
 *
 * The fix for both: the query moved here, behind a cache entry with no
 * expiry, and SettingsObserver binds the invalidation to WRITES of the table -
 * the same pattern StaticPageObserver already uses for the side menu. The
 * failure is logged.
 *
 * WHAT THE CACHE CHANGES, AND WHAT IT DOES NOT
 *
 * Nothing in production: boot still fills the Config within the same request,
 * it just reads from the cache now. What it does enable is a lazy read - a
 * caller using get() (today the maintenance branch of SetLocale) sees the value
 * as of this moment, not as of boot. That is what made maintenance mode
 * switchable at runtime; the TODO 09 tests used to work around its absence with
 * Config::set().
 *
 * THE FAILURE IS NOT CACHED. Only a successful read is stored, so a transient
 * database error cannot freeze the defaults behind an entry that never expires;
 * a failed state is memoized for the running request only, which also means at
 * most one log line per request. During installation - when the settings table
 * does not exist yet - that is the intended behaviour.
 */
class ApplicationSettings
{
    public const CACHE_KEY = 'application_settings';

    /**
     * The name => value map of the settings table. Null until read - which is
     * not the same as an empty array, because an empty table is a legitimate
     * result.
     */
    private ?array $rows = null;

    /**
     * The defaults the database rows are merged on top of.
     *
     * The 'maintenance' key is here on purpose even though the old code did not
     * know it: without a row, config('settings_maintenance') silently resolved
     * to null. Better to state the contract - same reasoning as the note on
     * group_data_retention.
     */
    public function defaults(): array
    {
        $defaultLanguage = Config::get('app.locale');

        return [
            'registration' => true,
            'claim_group_creator' => true,
            'default_language' => $defaultLanguage,
            'show_homepage_alert' => false,
            'homepage_message' => '',
            'weather' => false,
            'maintenance' => false,
            // v1-patch E: how long group data is kept, in months, with '0'
            // meaning disabled. Without a default the config key would not
            // resolve at all on a fresh install - RetentionWindow treats that
            // as disabled too, but let it be stated here.
            'group_data_retention' => '0',
        ];
    }

    /**
     * The effective settings: the defaults, overridden by the database rows.
     *
     * The 'languages' key is EXCLUDED, exactly as in the old code: it is not a
     * scalar setting but the JSON blob of the language registry - see
     * languages().
     */
    public function all(): array
    {
        $rows = $this->rows();
        unset($rows['languages']);

        return array_merge($this->defaults(), $rows);
    }

    public function get(string $name, $default = null)
    {
        return $this->all()[$name] ?? $default;
    }

    /**
     * The language registry: code => ['name' => ..., 'visible' => bool].
     *
     * A broken blob falls back to a single-entry list holding the default
     * language. The old code passed the null from json_decode straight on to
     * count(), which threw a TypeError - and the silent catch ate it, taking
     * the ENTIRE Config population down with it.
     */
    public function languages(): array
    {
        $defaultLanguage = Config::get('app.locale');
        $fallback = [$defaultLanguage => ['name' => $defaultLanguage, 'visible' => true]];

        $blob = $this->rows()['languages'] ?? null;

        if ($blob === null) {
            return $fallback;
        }

        $decoded = json_decode($blob, true);

        if (! is_array($decoded) || $decoded === []) {
            Log::warning('The settings.languages blob cannot be decoded, keeping the default language.', [
                'value' => $blob,
            ]);

            return $fallback;
        }

        return $decoded;
    }

    /**
     * The boot-time Config population. Identical in content to what
     * AppServiceProvider::boot() used to do - including the odd shape of the
     * $locales array (a string-keyed first element followed by numeric
     * appends), which is deliberately unchanged because that is the shape the
     * translatable package works with today.
     */
    public function applyToConfig(): void
    {
        $settings = $this->all();
        $languages = $this->languages();

        $defaultLanguage = Config::get('app.locale');
        $locales = [$defaultLanguage => $defaultLanguage];
        foreach ($languages as $code => $value) {
            $locales[] = $code;
        }

        Config::set([
            'available_languages' => $languages,
            'translatable.fallback_locale' => $settings['default_language'],
            'translatable.locales' => $locales,
            'show_homepage_alert' => $settings['show_homepage_alert'],
            'weather' => $settings['weather'],
            // 'app.fallback_locale' => $settings['default_language'],
        ]);

        if ($settings['show_homepage_alert'] == 1) {
            Config::set(['homepage_message' => $settings['homepage_message']]);
        }

        foreach ($settings as $key => $value) {
            Config::set(['settings_'.$key => $value]);
        }
    }

    /**
     * Clears the cache entry AND the in-request memo.
     *
     * Clearing the memo is not optional: SettingsObserver runs in the same
     * process, on this very singleton, so Cache::forget on its own would only
     * take effect on the next request.
     */
    public function flush(): void
    {
        $this->rows = null;
        Cache::forget(self::CACHE_KEY);
    }

    /**
     * The raw name => value map, cached.
     */
    private function rows(): array
    {
        if ($this->rows !== null) {
            return $this->rows;
        }

        try {
            return $this->rows = Cache::rememberForever(self::CACHE_KEY, function () {
                return Settings::all()->pluck('value', 'name')->all();
            });
        } catch (\Throwable $e) {
            // Catching \Throwable here is deliberate, and it does not fall back
            // into the TODO 25 trap: the branch that throws an Error on a
            // missing Debugbar class is NOT here, it is in
            // AppServiceProvider::boot(), outside this try/catch.
            Log::error('The application settings cannot be read, the defaults stay in effect.', [
                'exception' => $e,
            ]);

            return $this->rows = [];
        }
    }
}
