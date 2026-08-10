<?php

namespace Tests\Feature\Weather;

use App\Http\Livewire\Events\Events;
use App\Models\Group;
use App\Models\WeatherCity;
use Illuminate\Console\Scheduling\Schedule;
use Illuminate\Support\Facades\DB;
use Livewire\Livewire;
use Tests\Feature\FeatureTestCase;

/**
 * The eight gaps measured by TODO 20.1 - ALL CLOSED in the v1-patch C package.
 *
 * The file originally recorded the buggy behavior, so it would fail at the
 * moment of the fix, and that failure would be the reviewable diff - the
 * same discipline as the TODO 14 duplicate-route tripwires. That failure
 * occurred: all eight cases flipped, and the file now guards against the gap
 * reopening.
 *
 * The name is deliberately unchanged: this lets the git history track which
 * assertion took the place of which gap.
 */
class WeatherKnownGapsTest extends FeatureTestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        config(['weather' => 1]);
        config(['openweather.api_key' => '']);
    }

    // =========================================================================
    // 1. The refresh cycle - previously didn't exist
    // =========================================================================

    public function test_a_scheduled_task_refreshes_the_weather_cache(): void
    {
        // Previously NOT A SINGLE scheduled task refreshed the weather_cities
        // table. Rows only got data when a group admin saved the group's
        // form or pressed the check button - the calendar is a pure reader.
        // Publishers therefore saw arbitrarily old forecasts, potentially for weeks.
        $scheduled = collect(app(Schedule::class)->events())
            ->map(fn ($event) => (string) $event->command)
            ->filter(fn ($command) => stripos($command, 'weather:refresh') !== false);

        $this->assertCount(1, $scheduled, 'Exactly one weather:refresh task should be scheduled.');
    }

    public function test_the_refresh_command_only_visits_cities_that_are_actually_used(): void
    {
        // The plan dictates the frequency: the free tier is 1000 calls/day,
        // and refreshing one town costs 2 calls. That's why we visit,
        // DISTINCT, only the cities that have an enabled group attached.
        $used = WeatherCity::factory()->create(['city' => 'Szeged', 'country' => 'HU']);
        $unused = WeatherCity::factory()->create(['city' => 'Debrecen', 'country' => 'HU']);

        $this->createGroup(['weather_enabled' => 1, 'city_id' => $used->id]);
        $this->createGroup(['weather_enabled' => 0, 'city_id' => $unused->id]);

        // Without an API key, every lookup fails, but last_try still gets
        // the timestamp - that's how it shows which city the command visited.
        $this->artisan('weather:refresh')->assertExitCode(0);

        $this->assertNotNull($used->fresh()->last_try, 'It refreshes the used city.');
        $this->assertNull($unused->fresh()->last_try, 'But not the disabled group\'s city.');
    }

    // =========================================================================
    // 2. The unguarded dereference in the calendar
    // =========================================================================

    public function test_the_calendar_survives_weather_enabled_without_a_city(): void
    {
        // Previously threw a fatal: weather_enabled = 1 by itself doesn't
        // mean there's a city, the `weather` relation is null in that case,
        // and render() dereferenced it unguarded. This is exactly the state
        // the old failure branch produced when a failed API call nulled out city_id.
        $group = $this->createGroup(['weather_enabled' => 1, 'city_id' => null]);

        $user = $this->createUser(['email' => 'weather-nocity@example.test']);
        $this->attachUserToGroup($user, $group, 'member', true);
        session(['groupId' => $group->id]);

        Livewire::actingAs($user)
            ->test(Events::class, ['year' => 2026, 'month' => 8])
            ->assertOk();
    }

    public function test_the_calendar_survives_a_forecast_blob_without_a_list_key(): void
    {
        // The other unguarded spot: a truncated or malformed response blob
        // can be missing the `list` key, and the previous count() choked on this too.
        $city = WeatherCity::create([
            'city'             => 'Szeged',
            'country'          => 'HU',
            'current_weather'  => ['main' => ['temp' => 21.5], 'name' => 'Szeged'],
            'forecast_weather' => ['cod' => '429', 'message' => 'rate limited'],
            'last_try'         => now(),
        ]);

        $group = $this->createGroup(['weather_enabled' => 1, 'city_id' => $city->id]);

        $user = $this->createUser(['email' => 'weather-nolist@example.test']);
        $this->attachUserToGroup($user, $group, 'member', true);
        session(['groupId' => $group->id]);

        Livewire::actingAs($user)
            ->test(Events::class, ['year' => 2026, 'month' => 8])
            ->assertOk();
    }

    // =========================================================================
    // 3. The foreign key that was a silent no-op
    // =========================================================================

    public function test_the_groups_city_id_column_has_a_foreign_key(): void
    {
        // Migration 2024_12_04_194500 called ->constrained() after
        // unsignedBigInteger(); but that method pairs with foreignId(), so
        // it was a silent no-op. The foreign key was never created.
        $foreignKeys = DB::select(
            'SELECT CONSTRAINT_NAME, REFERENCED_TABLE_NAME
               FROM information_schema.KEY_COLUMN_USAGE
              WHERE TABLE_SCHEMA = DATABASE()
                AND TABLE_NAME = "groups"
                AND COLUMN_NAME = "city_id"
                AND REFERENCED_TABLE_NAME IS NOT NULL'
        );

        $this->assertCount(1, $foreignKeys, 'groups.city_id must have a foreign key.');
        $this->assertSame('weather_cities', $foreignKeys[0]->REFERENCED_TABLE_NAME);
    }

    public function test_deleting_a_city_nulls_the_reference_instead_of_orphaning_it(): void
    {
        // The substantive effect of onDelete('set null'): a deleted town does
        // not leave behind an orphaned reference that the calendar would later choke on.
        $city = WeatherCity::factory()->create(['city' => 'Szeged', 'country' => 'HU']);
        $group = $this->createGroup(['weather_enabled' => 1, 'city_id' => $city->id]);

        $city->delete();

        $this->assertNull($group->fresh()->city_id);
    }

    // =========================================================================
    // 4. The broken relation
    // =========================================================================

    public function test_the_weather_city_groups_relation_points_at_the_real_column(): void
    {
        // By default, hasMany(Group::class) looked for `weather_city_id` in
        // the groups table; no such column exists, its real name is
        // `city_id`. Every call to the relation would have errored - for
        // lack of use, this had not come to light until now.
        $city = WeatherCity::factory()->create(['city' => 'Szeged', 'country' => 'HU']);
        $group = $this->createGroup(['weather_enabled' => 1, 'city_id' => $city->id]);
        $this->createGroup(['weather_enabled' => 0, 'city_id' => null]);

        $related = $city->groups()->get();

        $this->assertCount(1, $related);
        $this->assertTrue($related->first()->is($group));
    }

    // =========================================================================
    // 5. The country code that only made it to one endpoint
    // =========================================================================

    public function test_both_endpoints_take_a_country_code(): void
    {
        // The package's `get3HourlyByCity(string $city)` signature accepted
        // ONE parameter, but the caller passed two: the 5-day forecast
        // resolved WITHOUT the country code, while the current weather resolved with it.
        //
        // The actual outgoing request is measured by OpenWeatherClientTest
        // with Http::fake(); here we pin down the signature contract, because
        // that was the source of the defect.
        foreach (['currentByCity', 'forecastByCity'] as $method) {
            $reflection = new \ReflectionMethod(\App\Support\Weather\OpenWeatherClient::class, $method);

            $this->assertSame(
                2,
                $reflection->getNumberOfParameters(),
                $method.': both the town AND the country code are parameters.'
            );
            $this->assertSame('country', $reflection->getParameters()[1]->getName());
        }
    }

    // =========================================================================
    // 6. The language and the translations
    // =========================================================================

    public function test_the_api_language_is_not_hardwired_to_english(): void
    {
        // The config previously hardwired 'en', which is why English weather
        // descriptions appeared in the Hungarian and German UI too.
        $this->assertSame('', (string) config('openweather.lang'), 'Empty by default: the locale decides.');
    }

    public function test_the_weather_translations_exist_in_every_maintained_locale(): void
    {
        // The group.weather.* block existed ONLY in hu, so raw keys appeared
        // in the UI under en and de.
        $keys = array_keys(__('group.weather', [], 'hu'));
        $this->assertNotEmpty($keys);

        foreach (['en', 'de'] as $locale) {
            $translated = __('group.weather', [], $locale);

            $this->assertIsArray($translated, $locale.': the weather block is missing.');

            foreach ($keys as $key) {
                $this->assertArrayHasKey($key, $translated, $locale.': missing key - '.$key);
                $this->assertNotSame(
                    __('group.weather.'.$key, [], 'hu'),
                    null,
                    $locale.': '.$key
                );
            }
        }
    }

    // =========================================================================
    // 7. The counter that nobody read
    // =========================================================================

    public function test_the_monthly_call_counter_is_gone(): void
    {
        // The weather_monthly_call row was incremented by every successful
        // lookup, and NOBODY read it - a superfluous updateOrCreate DB write
        // on every call. The decision: delete the write.
        $writers = [];

        $iterator = new \RecursiveIteratorIterator(
            new \RecursiveDirectoryIterator(app_path(), \RecursiveDirectoryIterator::SKIP_DOTS)
        );

        foreach ($iterator as $file) {
            if ($file->isFile()
                && $file->getExtension() === 'php'
                && str_contains(file_get_contents($file->getPathname()), 'weather_monthly_call')) {
                $writers[] = $file->getPathname();
            }
        }

        $this->assertSame([], $writers, 'Nobody may write to the weather_monthly_call counter.');
    }

    // =========================================================================
    // 8. The package that disappeared
    // =========================================================================

    public function test_the_vendor_package_is_gone(): void
    {
        // The two consumed endpoints moved into the App\Support\Weather
        // namespace. The package was removed because its WeatherClient
        // instantiated a Guzzle client without a container binding, so the
        // successful branch was not testable.
        $this->assertFalse(class_exists('RakibDevs\\Weather\\Weather'));

        $composer = json_decode(file_get_contents(base_path('composer.json')), true);
        $this->assertArrayNotHasKey('rakibdevs/openweather-laravel-api', $composer['require']);
    }
}
