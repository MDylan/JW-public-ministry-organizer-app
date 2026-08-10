<?php

namespace Tests\Feature\Weather;

use App\Http\Livewire\Groups\UpdateGroupForm as GroupEditComponent;
use App\Models\WeatherCity;
use Illuminate\Support\Facades\DB;
use Livewire\Livewire;
use Tests\Feature\FeatureTestCase;

/**
 * TODO 20.1: the weather cache, branch by branch.
 *
 * The measured situation: `pwbs_weather_api_call()`
 * (app/Helpers/helpers.php:84-164) is the ONLY place from which the project
 * uses the `rakibdevs/openweather-laravel-api` package, and this function is
 * simultaneously a cache, a throttle, and an API client. Before TODO 20's
 * assessment, not a single test exercised it.
 *
 * WHY THERE IS NO TEST FOR THE SUCCESSFUL API BRANCH, AND WHY THERE CANNOT BE.
 * The package's `WeatherClient::client()` method (vendor/.../WeatherClient.php:88-96)
 * instantiates a `GuzzleHttp\Client` locally, with no container binding and
 * no injection point. `Http::fake()` intercepts Laravel's OWN client
 * factory, which this never even reaches - see the pattern in
 * tests/Feature/Middleware/CheckRecaptchaTest.php, which fakes an outgoing
 * API exactly this way, and which does not apply here. The successful
 * branch is therefore only reachable with a real network call, which a test cannot make.
 *
 * This gap is the strongest argument for TODO 20's decision: once the
 * package is replaced (TODO 33.6), `Http::fake()` will work, and the
 * successful branch will become coverable too.
 *
 * What CAN be measured today is everything else: the disabled state, the
 * cache hit, the normalization, the 15-minute throttle, and the path for a
 * missing API key - the latter throws in the client's CONSTRUCTOR, before a socket opens.
 */
class WeatherCacheTest extends FeatureTestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        // The feature is off by default (CoreSettingsSeeder.php:30 => '0'),
        // and AppServiceProvider loads config('weather') from the settings
        // table at boot time. The test therefore sets the config directly.
        config(['weather' => 1]);

        // A safety pin, not decoration: with an empty key, OpenWeatherClient
        // throws BEFORE any connection opens. This guarantees that this file
        // will not fire a real network request even if someone later puts
        // OPENWEATHER_API_KEY in .env. The successful branch is covered by
        // WeatherClientTest, with Http::fake().
        config(['openweather.api_key' => '']);
    }

    /**
     * A cache row in the production shape.
     *
     * Before the v1-patch C package, the save encoded TWICE: manual
     * json_encode() ALONGSIDE the model's `json` cast, so the column held a
     * JSON string wrapped in JSON, and every reader had to decode it by
     * hand. From here on the cast is the single encoding point, so the fixture writes a raw array.
     */
    private function cacheRow(string $city, string $country, ?array $current = null, ?array $forecast = null): WeatherCity
    {
        return WeatherCity::create([
            'city'             => $city,
            'country'          => $country,
            'current_weather'  => $current,
            'forecast_weather' => $forecast,
            'last_try'         => now(),
        ]);
    }

    /** Ages the row, bypassing the cast and the observers. */
    private function age(WeatherCity $row, ?string $updatedAt = null, ?string $lastTry = null): void
    {
        $payload = [];

        if ($updatedAt !== null) {
            $payload['updated_at'] = $updatedAt;
        }

        if ($lastTry !== null) {
            $payload['last_try'] = $lastTry;
        }

        DB::table('weather_cities')->where('id', $row->getKey())->update($payload);
    }

    // =========================================================================
    // 1. The disabled base state
    // =========================================================================

    public function test_the_helper_returns_null_and_writes_nothing_when_the_feature_is_off(): void
    {
        config(['weather' => 0]);

        $this->assertNull(pwbs_weather_api_call('Szeged', 'HU'));
        $this->assertSame(0, WeatherCity::count(), 'The disabled gate (helpers.php:86) returns before everything else.');
    }

    // =========================================================================
    // 2. The cache hit
    // =========================================================================

    public function test_a_row_newer_than_59_minutes_is_served_from_the_cache(): void
    {
        $row = $this->cacheRow(
            'Szeged',
            'HU',
            ['main' => ['temp' => 21.5, 'humidity' => 60], 'name' => 'Szeged'],
            ['list' => [['dt_txt' => '2026-08-08 09:00:00']]]
        );

        $result = pwbs_weather_api_call('Szeged', 'HU');

        $this->assertSame($row->id, $result['city_id']);
        $this->assertArrayNotHasKey('error', $result);

        // The returned value is an ARRAY - the `json` cast gives it this
        // way, in a single decode. Previously the save encoded twice, so the
        // helper had to manually decode it once more.
        $this->assertSame(21.5, $result['current_weather']['main']['temp']);
        $this->assertSame('Szeged', $result['current_weather']['name']);
        $this->assertSame('2026-08-08 09:00:00', $result['forecast_weather']['list'][0]['dt_txt']);

        $this->assertSame(1, WeatherCity::count(), 'A hit writes nothing.');
    }

    public function test_the_lookup_is_case_insensitive_and_trims_the_input(): void
    {
        $row = $this->cacheRow('Szeged', 'HU', ['main' => ['temp' => 21.5]]);

        // `trim()` is the helper's job (helpers.php:88-89); case
        // insensitivity, however, is NOT: that comes from the
        // `utf8mb4_*_ci` collation of the `weather_cities` columns. Measured
        // consequence: `ucfirst()` does not decide the hit, it decides in
        // what form the FIRST write records the city, and in what form the
        // name goes out to OpenWeather.
        foreach (['  szeged  ', 'SZEGED', 'sZeGeD', 'Szeged'] as $input) {
            $result = pwbs_weather_api_call($input, 'hu');

            $this->assertSame($row->id, $result['city_id'], "Input '{$input}' matched the same row.");
            $this->assertSame(21.5, $result['current_weather']['main']['temp']);
        }

        $this->assertSame(1, WeatherCity::count(), 'Not a single row was created again.');
    }

    // =========================================================================
    // 3. The 15-minute throttle
    // =========================================================================

    public function test_a_stale_row_whose_last_try_is_recent_returns_the_throttle_error(): void
    {
        $row = $this->cacheRow('Szeged', 'HU', ['main' => ['temp' => 21.5]]);

        // Older than 59 minutes -> no hit; but we tried within the last 15 minutes.
        $this->age(
            $row,
            now()->subHours(2)->toDateTimeString(),
            now()->subMinutes(5)->toDateTimeString()
        );

        $result = pwbs_weather_api_call('Szeged', 'HU');

        $this->assertSame($row->id, $result['city_id']);
        $this->assertSame(__('group.weather.too_many_requests'), $result['error']);

        // Since v1-patch C, ALONGSIDE the throttle we also get back the most
        // recently known data, if there is any. A forecast delayed by an
        // hour is more usable than nothing, and the calendar doesn't empty
        // out just because we happen to be throttling.
        $this->assertSame(21.5, $result['current_weather']['main']['temp']);
    }

    // =========================================================================
    // 4. The path for a missing API key
    // =========================================================================

    public function test_a_missing_api_key_surfaces_as_a_real_message(): void
    {
        // FLIPPED by the v1-patch C package.
        //
        // The package's `InvalidConfiguration` exception was instantiated
        // WITHOUT A MESSAGE, so the helper returned `['error' => '']`: the
        // group admin got an empty error panel, and nothing revealed that
        // the key was missing. Every factory method of WeatherException names the cause.
        $result = pwbs_weather_api_call('Szeged', 'HU');

        $this->assertArrayHasKey('error', $result);
        $this->assertNotSame('', $result['error']);
        $this->assertStringContainsString('OPENWEATHER_API_KEY', $result['error']);

        // The caller ALSO gets a city_id: the failed attempt creates the row
        // too, otherwise saving the group would be blocked - see below.
        $this->assertArrayHasKey('city_id', $result);
        $this->assertSame(1, WeatherCity::count());
    }

    public function test_the_missing_key_path_records_a_last_try_so_the_throttle_engages(): void
    {
        // FLIPPED by the v1-patch C package.
        //
        // The exception previously occurred BEFORE WeatherCity::updateOrCreate(),
        // so last_try was never updated: with a misconfigured key, the
        // 15-minute throttle NEVER engaged, and every page load retried.
        $row = $this->cacheRow('Szeged', 'HU', ['main' => ['temp' => 21.5]]);
        $this->age(
            $row,
            now()->subHours(2)->toDateTimeString(),
            now()->subHours(2)->toDateTimeString()
        );

        pwbs_weather_api_call('Szeged', 'HU');

        $this->assertTrue(
            $row->fresh()->last_try->gt(now()->subMinute()),
            'The failed attempt gets a timestamp too.'
        );

        $second = pwbs_weather_api_call('Szeged', 'HU');
        $this->assertSame(__('group.weather.too_many_requests'), $second['error'] ?? null);
    }

    // =========================================================================
    // 5. The group save that a failing call used to cripple
    // =========================================================================

    public function test_a_failing_call_no_longer_blocks_the_group_save(): void
    {
        // FLIPPED by the v1-patch C package.
        //
        // The failure branch previously NULLED OUT city_id
        // (UpdateGroupForm.php:219), while :251 validates
        // required_if:weather_enabled,1 against it. So anyone who turned on
        // weather while the API happened not to respond could NOT save the
        // group AT ALL - and the error, on top of that, hit a field that has
        // no input element on the form. An external service's
        // unavailability blocked the entire form, including fields that
        // have nothing to do with weather.
        //
        // The failed attempt also creates the weather_cities row, so there
        // is a city_id, and the save can proceed; the user sees the error on
        // the weather_messages panel.
        $city = $this->cacheRow('Szeged', 'HU', ['main' => ['temp' => 21.5]]);

        $group = $this->createGroup(['weather_enabled' => 1, 'city_id' => $city->id]);
        $editor = $this->createUser(['email' => 'weather-save-block@example.test']);
        $this->attachUserToGroup($editor, $group, 'roler', true);

        Livewire::actingAs($editor)
            ->test(GroupEditComponent::class, ['group' => $group->fresh()])
            ->set('state.name', 'Weather Group')
            ->set('state.max_extend_days', 30)
            ->set('state.need_approval', 1)
            ->set('state.min_publishers', 2)
            ->set('state.max_publishers', 4)
            ->set('state.min_time', 60)
            ->set('state.max_time', 180)
            ->set('state.showPhone', 1)
            ->set('state.messages_on', 1)
            ->set('state.messages_write', 1)
            ->set('state.messages_priority', 1)
            ->set('state.weather_enabled', 1)
            // A different town: no cache row for it, so it runs into the API
            // branch, and errors for lack of an API key.
            ->set('weather.city', 'Debrecen')
            ->set('weather.country', 'HU')
            ->set('days.1.day_number', '1')
            ->set('days.1.start_time', '08:00')
            ->set('days.1.end_time', '10:00')
            ->set('change_date', now()->toDateString())
            ->call('updateGroup')
            ->assertHasNoErrors();

        $fresh = $group->fresh();
        $this->assertSame('Weather Group', $fresh->name, 'The save went through.');
        $this->assertNotNull($fresh->city_id, 'city_id is not nulled out by a failed lookup.');
        $this->assertNotSame($city->id, (int) $fresh->city_id, 'And it points to the new town.');
    }

    public function test_a_warm_cache_lets_the_group_save_and_writes_city_id(): void
    {
        // The cache-hit branch does NOT call the API (helpers.php:93-98), so
        // this save runs to completion without a network - and this is the
        // only test that proves, from the positive direction, that the call
        // site (UpdateGroupForm.php:215) exists at all. If the call were
        // removed, city_id would remain null, and this test would fail.
        $city = $this->cacheRow('Szeged', 'HU', ['main' => ['temp' => 21.5]]);

        $group = $this->createGroup();
        $editor = $this->createUser(['email' => 'weather-save-ok@example.test']);
        $this->attachUserToGroup($editor, $group, 'roler', true);

        Livewire::actingAs($editor)
            ->test(GroupEditComponent::class, ['group' => $group->fresh()])
            ->set('state.name', 'Warm Cache Group')
            ->set('state.max_extend_days', 30)
            ->set('state.need_approval', 1)
            ->set('state.min_publishers', 2)
            ->set('state.max_publishers', 4)
            ->set('state.min_time', 60)
            ->set('state.max_time', 180)
            ->set('state.showPhone', 1)
            ->set('state.messages_on', 1)
            ->set('state.messages_write', 1)
            ->set('state.messages_priority', 1)
            ->set('state.weather_enabled', 1)
            ->set('weather.city', 'Szeged')
            ->set('weather.country', 'HU')
            ->set('days.1.day_number', '1')
            ->set('days.1.start_time', '08:00')
            ->set('days.1.end_time', '10:00')
            ->set('change_date', now()->toDateString())
            ->call('updateGroup')
            ->assertHasNoErrors();

        $fresh = $group->fresh();
        $this->assertSame($city->id, (int) $fresh->city_id, 'The hit\'s city_id was written to the group.');
        $this->assertSame(1, (int) $fresh->weather_enabled);
        $this->assertSame('Warm Cache Group', $fresh->name);
    }

    // =========================================================================
    // 6. The production column shape
    // =========================================================================

    public function test_what_the_helper_writes_reads_back_from_the_cast_as_an_array(): void
    {
        // FLIPPED by the v1-patch C package.
        //
        // The save encoded TWICE: a manual json_encode() ALONGSIDE the
        // model's `json` cast. The column therefore held a JSON string
        // wrapped in JSON; the cast decoded once and returned a string -
        // every reader had to decode it by hand once more
        // (helpers.php:96-97, Events.php:297,300). From here on the cast is
        // the single encoding point.
        $row = $this->cacheRow('Szeged', 'HU', ['main' => ['temp' => 21.5]]);

        $this->assertIsArray($row->fresh()->current_weather);

        // The factory writes this same shape - previously the two were
        // MUTUALLY EXCLUSIVE: the factory an array, production a
        // double-encoded string, so ModelFactoryTest asserted a shape that
        // production code could never produce. From here on there is a single shape.
        $fromFactory = WeatherCity::factory()->withWeatherData()->create();
        $this->assertIsArray($fromFactory->fresh()->current_weather);
        $this->assertIsArray($fromFactory->fresh()->forecast_weather);
        $this->assertArrayHasKey('list', $fromFactory->fresh()->forecast_weather);
    }
}
