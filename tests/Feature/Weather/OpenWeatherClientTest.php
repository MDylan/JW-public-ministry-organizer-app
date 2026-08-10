<?php

namespace Tests\Feature\Weather;

use App\Models\WeatherCity;
use App\Support\Weather\OpenWeatherClient;
use App\Support\Weather\WeatherCache;
use App\Support\Weather\WeatherException;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Http\Client\Request;
use Illuminate\Support\Facades\Http;
use Tests\Feature\FeatureTestCase;

/**
 * The v1-patch C package: the OpenWeather client's successful and failing branches.
 *
 * WHY THIS COULD NOT HAVE EXISTED BEFORE
 *
 * `rakibdevs/openweather-laravel-api`'s WeatherClient instantiated
 * `new GuzzleHttp\Client(...)` inside its method, with no container binding.
 * `Http::fake()` therefore could not intercept it, so the successful branch
 * of the project's ONLY outgoing HTTP call was not testable at all -
 * TODO 20.1's suite could only cover the cache and failure branches. This
 * file is the reason the swap was worth doing at all.
 *
 * `Http::fake()` guarantees throughout the whole suite that no real network
 * request goes out; `Http::assertSent()` records the shape of the OUTGOING
 * request, which is the measurable contract with the API.
 */
class OpenWeatherClientTest extends FeatureTestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        config([
            'weather'              => 1,
            'openweather.api_key'  => 'test-key',
            'openweather.lang'     => '',
            'openweather.units'    => 'metric',
        ]);
    }

    private function currentBody(): array
    {
        return [
            'name'    => 'Szeged',
            'main'    => ['temp' => 21.5, 'humidity' => 60],
            'wind'    => ['speed' => 3.0],
            'weather' => [['description' => 'clear sky', 'icon' => '01d']],
        ];
    }

    private function forecastBody(): array
    {
        return [
            'list' => [
                [
                    'dt_txt'  => '2026-08-12 09:00:00',
                    'main'    => ['temp' => 18.0],
                    'wind'    => ['speed' => 2.0],
                    'weather' => [['description' => 'few clouds', 'icon' => '02d']],
                ],
            ],
        ];
    }

    private function fakeSuccess(): void
    {
        Http::fake([
            '*/data/2.5/weather*'  => Http::response($this->currentBody(), 200),
            '*/data/2.5/forecast*' => Http::response($this->forecastBody(), 200),
        ]);
    }

    // =========================================================================
    // 1. The successful branch - not measurable until now
    // =========================================================================

    public function test_the_current_endpoint_returns_the_decoded_body(): void
    {
        $this->fakeSuccess();

        $result = (new OpenWeatherClient())->currentByCity('Szeged', 'HU');

        $this->assertSame('Szeged', $result['name']);
        $this->assertSame(21.5, $result['main']['temp']);
    }

    public function test_the_forecast_endpoint_returns_the_decoded_body(): void
    {
        $this->fakeSuccess();

        $result = (new OpenWeatherClient())->forecastByCity('Szeged', 'HU');

        $this->assertSame('2026-08-12 09:00:00', $result['list'][0]['dt_txt']);
    }

    public function test_both_endpoints_receive_the_country_code(): void
    {
        // The MEASURED DEFECT that this package fixes: the package's
        // `get3HourlyByCity(string $city)` signature accepted ONE parameter,
        // but the caller passed two - the 5-day forecast therefore resolved
        // WITHOUT the country code, while the current weather resolved with
        // it. Two different towns could have ended up behind the two calls.
        $this->fakeSuccess();

        $client = new OpenWeatherClient();
        $client->currentByCity('Szeged', 'HU');
        $client->forecastByCity('Szeged', 'HU');

        foreach (['data/2.5/weather', 'data/2.5/forecast'] as $path) {
            Http::assertSent(function (Request $request) use ($path) {
                return str_contains($request->url(), $path)
                    && $request->data()['q'] === 'Szeged,HU';
            });
        }
    }

    public function test_the_request_carries_the_key_units_and_language(): void
    {
        $this->fakeSuccess();

        (new OpenWeatherClient())->currentByCity('Szeged', 'HU');

        Http::assertSent(function (Request $request) {
            $data = $request->data();

            return $data['appid'] === 'test-key'
                && $data['units'] === 'metric'
                && $data['lang'] === 'hu';
        });
    }

    public function test_the_language_follows_the_application_locale(): void
    {
        // The package sent a hardwired 'en', which is why English weather
        // descriptions appeared in the Hungarian and German UI too.
        $this->fakeSuccess();
        $this->app->setLocale('de');

        (new OpenWeatherClient())->currentByCity('Szeged', 'HU');

        Http::assertSent(fn (Request $request) => $request->data()['lang'] === 'de');
    }

    public function test_an_explicit_configured_language_wins_over_the_locale(): void
    {
        $this->fakeSuccess();
        config(['openweather.lang' => 'en']);
        $this->app->setLocale('hu');

        (new OpenWeatherClient())->currentByCity('Szeged', 'HU');

        Http::assertSent(fn (Request $request) => $request->data()['lang'] === 'en');
    }

    // =========================================================================
    // 2. The failure branches - each with a REAL message
    // =========================================================================

    public function test_a_missing_key_never_reaches_the_network(): void
    {
        Http::fake();
        config(['openweather.api_key' => '']);

        try {
            (new OpenWeatherClient())->currentByCity('Szeged', 'HU');
            $this->fail('A missing key must throw an exception.');
        } catch (WeatherException $e) {
            $this->assertStringContainsString('OPENWEATHER_API_KEY', $e->getMessage());
        }

        Http::assertNothingSent();
    }

    /**
     * @dataProvider errorStatusProvider
     */
    public function test_each_error_status_carries_its_own_message(int $status, string $needle): void
    {
        Http::fake(['*' => Http::response([], $status)]);

        try {
            (new OpenWeatherClient())->currentByCity('Szeged', 'HU');
            $this->fail('Status '.$status.' must throw an exception.');
        } catch (WeatherException $e) {
            $this->assertNotSame('', $e->getMessage(), 'An empty message is not acceptable.');
            $this->assertStringContainsString($needle, $e->getMessage());
        }
    }

    public static function errorStatusProvider(): array
    {
        return [
            'unauthorized' => [401, 'OPENWEATHER_API_KEY'],
            'not found'    => [404, 'Szeged'],
            'rate limited' => [429, '429'],
            'server error' => [500, '500'],
        ];
    }

    public function test_a_connection_failure_becomes_a_project_exception(): void
    {
        // Http's fake throws the network error AS AN EXCEPTION, not as a
        // response. The caller needs to handle a single type, so we convert it here.
        Http::fake(fn () => throw new ConnectionException('cURL error 28: timed out'));

        $this->expectException(WeatherException::class);
        $this->expectExceptionMessageMatches('/nem érhető el/');

        (new OpenWeatherClient())->currentByCity('Szeged', 'HU');
    }

    // =========================================================================
    // 3. The cache and the client together
    // =========================================================================

    public function test_a_successful_lookup_stores_plain_arrays(): void
    {
        $this->fakeSuccess();

        $result = (new WeatherCache())->forCity('szeged', 'hu');

        $row = WeatherCity::firstOrFail();

        $this->assertSame('Szeged', $row->city, 'The stored form is normalized.');
        $this->assertSame('HU', $row->country);

        // A single encoding point: the `json` cast. Previously the save also
        // manually json_encoded ALONGSIDE this, so the column held a
        // double-encoded string, and every reader had to decode it by hand.
        $this->assertIsArray($row->current_weather);
        $this->assertIsArray($row->forecast_weather);
        $this->assertSame(21.5, $row->current_weather['main']['temp']);

        $this->assertSame($row->id, $result['city_id']);
        $this->assertArrayNotHasKey('error', $result);
    }

    public function test_a_failed_refresh_does_not_make_stale_data_look_fresh(): void
    {
        // `updated_at` is the input to the 59-minute freshness rule. If a
        // failed attempt also refreshed it, an unreachable API would mark
        // the old data "fresh" for an hour, and the next attempt would be
        // skipped too.
        // We produce the existing row directly, not from a successful call:
        // a repeated call to Http::fake() APPENDS to the previous stubs, so
        // after a successful fake it's not possible to set up a clean failure branch.
        $row = WeatherCity::factory()->withWeatherData()->create([
            'city'    => 'Szeged',
            'country' => 'HU',
        ]);

        WeatherCity::where('id', $row->id)->update([
            'updated_at' => now()->subHours(2)->toDateTimeString(),
            'last_try'   => now()->subHours(2)->toDateTimeString(),
        ]);

        Http::fake(['*' => Http::response([], 500)]);

        $result = (new WeatherCache())->refreshCity('Szeged', 'HU');

        $fresh = $row->fresh();

        $this->assertArrayHasKey('error', $result);
        $this->assertTrue($fresh->last_try->gt(now()->subMinute()), 'last_try moves.');
        $this->assertTrue($fresh->updated_at->lt(now()->subHour()), 'updated_at does NOT move.');

        // The failure branch also returns the most recently known data: a
        // forecast delayed by an hour is more usable than nothing.
        $this->assertSame(21.5, $result['current_weather']['main']['temp']);
    }

    public function test_a_fresh_row_is_served_without_touching_the_network(): void
    {
        $this->fakeSuccess();
        $cache = new WeatherCache();
        $cache->forCity('Szeged', 'HU');

        Http::fake();

        $cache->forCity('Szeged', 'HU');

        Http::assertNothingSent();
    }
}
