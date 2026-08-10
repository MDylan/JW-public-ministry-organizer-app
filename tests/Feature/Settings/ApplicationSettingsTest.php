<?php

namespace Tests\Feature\Settings;

use App\Models\Settings;
use App\Support\Settings\ApplicationSettings;
use Illuminate\Database\QueryException;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Config;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Tests\Feature\FeatureTestCase;

/**
 * TODO 31: ApplicationSettings, branch by branch.
 *
 * The measured starting point: AppServiceProvider::boot() ran a Settings::all()
 * query on every request and an empty `catch (\Exception $e) {}` swallowed the
 * whole block. Not one test exercised any of it - not the query, not the Config
 * population, not the failure branch.
 *
 * This file covers all three, and states outright the two properties the item
 * was written for: the query runs ONCE per request, and a failure does NOT
 * disappear without a trace.
 */
class ApplicationSettingsTest extends FeatureTestCase
{
    private function settings(): ApplicationSettings
    {
        return app(ApplicationSettings::class);
    }

    /**
     * Only the queries that touch the settings table - the query log records
     * everything else too (fixture writes, session, and so on).
     */
    private function settingsQueryCount(): int
    {
        return collect(DB::getQueryLog())
            ->filter(fn ($query) => str_contains($query['query'], '`settings`'))
            ->count();
    }

    // =========================================================================
    // 1. Defaults and overrides
    // =========================================================================

    public function test_an_empty_table_yields_the_documented_defaults(): void
    {
        // A mass delete goes through the query builder, so it fires NO model
        // events - the observer does not help here, we flush by hand.
        Settings::query()->delete();
        $this->settings()->flush();

        $settings = $this->settings()->all();

        $this->assertTrue($settings['registration']);
        $this->assertTrue($settings['claim_group_creator']);
        $this->assertFalse($settings['show_homepage_alert']);
        $this->assertFalse($settings['weather']);
        $this->assertSame('0', $settings['group_data_retention']);
        $this->assertSame(Config::get('app.locale'), $settings['default_language']);
    }

    public function test_the_maintenance_key_resolves_even_without_a_row(): void
    {
        // This is NEW relative to the old code, and deliberately so: the
        // 'maintenance' key was not among the defaults, so without a row
        // config('settings_maintenance') silently resolved to null. Let the
        // contract be stated - false, i.e. switched off.
        Settings::query()->delete();
        $this->settings()->flush();

        $this->assertFalse($this->settings()->get('maintenance'));
        $this->assertSame(0, (int) $this->settings()->get('maintenance'));
    }

    public function test_a_database_row_overrides_the_default(): void
    {
        Settings::updateOrCreate(['name' => 'registration'], ['value' => '0']);

        $this->assertSame('0', $this->settings()->get('registration'));
    }

    public function test_an_unknown_key_falls_back_to_the_given_default(): void
    {
        $this->assertNull($this->settings()->get('no_such_key'));
        $this->assertSame('x', $this->settings()->get('no_such_key', 'x'));
    }

    public function test_the_languages_blob_never_leaks_into_the_scalar_settings(): void
    {
        // The old code handled the 'languages' row on a separate branch, so
        // config('settings_languages') NEVER existed. If the raw JSON blob
        // leaked in among the scalar settings, the Config population would be
        // handed an uninterpretable string.
        Settings::updateOrCreate(['name' => 'languages'], ['value' => '{"hu":{"name":"Magyar","visible":true}}']);

        $this->assertArrayNotHasKey('languages', $this->settings()->all());
    }

    // =========================================================================
    // 2. The cache and its invalidation
    // =========================================================================

    public function test_the_table_is_read_once_and_then_served_from_the_cache(): void
    {
        $this->settings()->flush();
        DB::enableQueryLog();

        $this->settings()->all();
        $this->assertSame(1, $this->settingsQueryCount(), 'The first read queries.');

        // A fresh instance, so the in-request memo cannot mask the
        // measurement: if the cache were not working, this would fire a second
        // query.
        (new ApplicationSettings())->all();
        $this->assertSame(1, $this->settingsQueryCount(), 'The second read comes from the cache.');

        DB::disableQueryLog();
    }

    public function test_writing_a_setting_invalidates_the_cache_through_the_observer(): void
    {
        $this->assertNotSame('0', $this->settings()->get('registration'));

        Settings::updateOrCreate(['name' => 'registration'], ['value' => '0']);

        $this->assertSame(
            '0',
            $this->settings()->get('registration'),
            'SettingsObserver flushes the cache, so the write is visible immediately.'
        );
    }

    public function test_the_observer_also_clears_the_in_request_memo(): void
    {
        // Cache::forget on its own would not be enough: the observer runs in
        // the same process, on this very singleton, so without clearing the
        // memo the already-read array would live on until the end of the
        // request.
        $instance = $this->settings();
        $instance->all();

        Settings::updateOrCreate(['name' => 'registration'], ['value' => '0']);

        $this->assertFalse(Cache::has(ApplicationSettings::CACHE_KEY));
        $this->assertSame('0', $instance->get('registration'));
    }

    public function test_deleting_a_setting_also_invalidates_the_cache(): void
    {
        Settings::updateOrCreate(['name' => 'registration'], ['value' => '0']);
        $this->assertSame('0', $this->settings()->get('registration'));

        Settings::where('name', 'registration')->first()->delete();

        $this->assertTrue($this->settings()->get('registration'), 'Once the row is gone, the default returns.');
    }

    // =========================================================================
    // 3. The failure branch - the actual subject of this item
    // =========================================================================

    public function test_a_failed_read_is_logged_and_falls_back_to_the_defaults(): void
    {
        // The exception is injected at the cache boundary, because that is
        // where the database read lives: Settings::all() runs inside the
        // rememberForever closure, so a QueryException raised there surfaces at
        // exactly this point. This is the branch that used to run into an EMPTY
        // catch.
        Log::spy();
        Cache::shouldReceive('rememberForever')
            ->once()
            ->andThrow(new QueryException('select * from `settings`', [], new \Exception('Table not found')));

        $settings = (new ApplicationSettings())->all();

        $this->assertTrue($settings['registration'], 'On failure the defaults stay in effect.');
        Log::shouldHaveReceived('error')->once();
    }

    public function test_a_successful_read_logs_nothing(): void
    {
        // Control experiment: the log assertion above only measures anything if
        // the successful path is silent. Without this, an implementation that
        // logs unconditionally would pass too.
        Log::spy();

        (new ApplicationSettings())->all();

        Log::shouldNotHaveReceived('error');
    }

    public function test_a_failed_read_writes_nothing_into_the_cache(): void
    {
        // A transient database error must not freeze the defaults: the failure
        // branch is FORBIDDEN to store its fallback value, because an entry
        // with no expiry could then only be dislodged by a write or a manual
        // flush. The failed state is memoized for the running request only.
        Cache::shouldReceive('rememberForever')
            ->once()
            ->andThrow(new QueryException('select * from `settings`', [], new \Exception('Table not found')));
        Cache::shouldNotReceive('put');
        Cache::shouldNotReceive('forever');

        (new ApplicationSettings())->all();
    }

    // =========================================================================
    // 4. The language registry
    // =========================================================================

    public function test_the_languages_blob_is_decoded(): void
    {
        Settings::updateOrCreate(
            ['name' => 'languages'],
            ['value' => '{"hu":{"name":"Magyar","visible":true},"de":{"name":"Deutsch","visible":false}}']
        );

        $languages = $this->settings()->languages();

        $this->assertSame(['hu', 'de'], array_keys($languages));
        $this->assertFalse($languages['de']['visible']);
    }

    public function test_a_broken_languages_blob_falls_back_to_the_default_language(): void
    {
        // The old code handed the null from json_decode to count(), which threw
        // a TypeError - and the silent catch ate that, TAKING THE ENTIRE Config
        // population down with it. One malformed language blob therefore killed
        // every other setting as well.
        Log::spy();
        Settings::updateOrCreate(['name' => 'languages'], ['value' => 'not json']);

        $languages = $this->settings()->languages();

        $this->assertSame([Config::get('app.locale')], array_keys($languages));
        Log::shouldHaveReceived('warning')->once();
    }

    public function test_a_broken_languages_blob_no_longer_takes_the_other_settings_with_it(): void
    {
        Settings::updateOrCreate(['name' => 'languages'], ['value' => 'not json']);
        Settings::updateOrCreate(['name' => 'registration'], ['value' => '0']);

        $this->settings()->applyToConfig();

        $this->assertSame('0', Config::get('settings_registration'));
    }

    // =========================================================================
    // 5. The Config population
    // =========================================================================

    public function test_apply_to_config_sets_the_same_keys_the_boot_used_to_set(): void
    {
        Settings::updateOrCreate(['name' => 'languages'], ['value' => '{"hu":{"name":"Magyar","visible":true}}']);
        Settings::updateOrCreate(['name' => 'default_language'], ['value' => 'hu']);
        Settings::updateOrCreate(['name' => 'weather'], ['value' => '1']);

        $this->settings()->applyToConfig();

        $this->assertSame(['hu' => ['name' => 'Magyar', 'visible' => true]], Config::get('available_languages'));
        $this->assertSame('hu', Config::get('translatable.fallback_locale'));
        $this->assertSame('1', Config::get('weather'));
        $this->assertSame('1', Config::get('settings_weather'));
        $this->assertSame('hu', Config::get('settings_default_language'));
    }

    public function test_the_homepage_message_is_only_published_when_the_alert_is_on(): void
    {
        Config::set('homepage_message', null);

        Settings::updateOrCreate(['name' => 'show_homepage_alert'], ['value' => '0']);
        Settings::updateOrCreate(['name' => 'homepage_message'], ['value' => 'Message']);
        $this->settings()->applyToConfig();

        $this->assertNull(Config::get('homepage_message'), 'With the alert off, the text is not published.');

        Settings::updateOrCreate(['name' => 'show_homepage_alert'], ['value' => '1']);
        $this->settings()->applyToConfig();

        $this->assertSame('Message', Config::get('homepage_message'));
    }

    public function test_the_locales_list_keeps_its_historical_shape(): void
    {
        // This is the shape the translatable package works with today: the
        // first element is string-keyed (app.locale), the rest are appended
        // numerically. Ugly, but reshaping it would fall outside TODO 31 - this
        // test records that the refactor did not change it.
        Settings::updateOrCreate(['name' => 'languages'], ['value' => '{"hu":{"name":"Magyar","visible":true},"en":{"name":"English","visible":true}}']);

        $this->settings()->applyToConfig();

        $locale = Config::get('app.locale');
        $this->assertSame([$locale => $locale, 0 => 'hu', 1 => 'en'], Config::get('translatable.locales'));
    }
}
