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
 * A v1-patch C csomagja: az OpenWeather kliens sikeres és hibás ága.
 *
 * MIÉRT NEM LÉTEZHETETT EZ KORÁBBAN
 *
 * A `rakibdevs/openweather-laravel-api` WeatherClientje `new GuzzleHttp\Client(...)`-ot
 * példányosított a metódusa belsejében, konténer-kötés nélkül. A `Http::fake()`
 * ezért nem tudta elkapni, tehát a projekt EGYETLEN kifelé menő HTTP-hívásának a
 * sikeres ága egyáltalán nem volt tesztelhető - a TODO 20.1 készlete csak a
 * gyorsítótár- és hibaágakat tudta lefedni. Ez a fájl az, amiért a cserét
 * egyáltalán érdemes volt megcsinálni.
 *
 * A `Http::fake()` az egész suite-ban garantálja, hogy valódi hálózati kérés nem
 * indul; a `Http::assertSent()` pedig a KIMENŐ kérés alakját rögzíti, ami a
 * mérhető szerződés az API felé.
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
    // 1. A sikeres ág - eddig nem volt mérhető
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
        // A MÉRT HIBA, amit ez a csomag javít: a csomag
        // `get3HourlyByCity(string $city)` szignatúrája EGY paramétert fogadott,
        // a hívó viszont kettőt adott át - az 5 napos előrejelzés tehát
        // országkód NÉLKÜL oldódott fel, miközben a jelenlegi időjárás vele.
        // Két különböző település is állhatott a kettő mögött.
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
        // A csomag bedrótozott 'en'-t küldött, ezért a magyar és a német
        // felületen is angol időjárás-leírások jelentek meg.
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
    // 2. A hibaágak - mindegyik VALÓDI üzenettel
    // =========================================================================

    public function test_a_missing_key_never_reaches_the_network(): void
    {
        Http::fake();
        config(['openweather.api_key' => '']);

        try {
            (new OpenWeatherClient())->currentByCity('Szeged', 'HU');
            $this->fail('A hiányzó kulcsnak kivételt kell dobnia.');
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
            $this->fail('A '.$status.' státusznak kivételt kell dobnia.');
        } catch (WeatherException $e) {
            $this->assertNotSame('', $e->getMessage(), 'Üres üzenet nem elfogadható.');
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
        // A Http fakad a hálózati hibát KIVÉTELKÉNT dobja, nem válaszként. A
        // hívónak egyetlen típust kell kezelnie, ezért itt alakítjuk át.
        Http::fake(fn () => throw new ConnectionException('cURL error 28: timed out'));

        $this->expectException(WeatherException::class);
        $this->expectExceptionMessageMatches('/nem érhető el/');

        (new OpenWeatherClient())->currentByCity('Szeged', 'HU');
    }

    // =========================================================================
    // 3. A gyorsítótár és a kliens együtt
    // =========================================================================

    public function test_a_successful_lookup_stores_plain_arrays(): void
    {
        $this->fakeSuccess();

        $result = (new WeatherCache())->forCity('szeged', 'hu');

        $row = WeatherCity::firstOrFail();

        $this->assertSame('Szeged', $row->city, 'A tárolt alak normalizált.');
        $this->assertSame('HU', $row->country);

        // Egyetlen kódolási pont: a `json` cast. Korábban a mentés ez MELLETT
        // kézzel is json_encode-olt, ezért az oszlopban kétszer kódolt sztring
        // állt, és minden olvasónak kézzel kellett dekódolnia.
        $this->assertIsArray($row->current_weather);
        $this->assertIsArray($row->forecast_weather);
        $this->assertSame(21.5, $row->current_weather['main']['temp']);

        $this->assertSame($row->id, $result['city_id']);
        $this->assertArrayNotHasKey('error', $result);
    }

    public function test_a_failed_refresh_does_not_make_stale_data_look_fresh(): void
    {
        // Az `updated_at` a 59 perces frissesség-szabály bemenete. Ha a
        // sikertelen kísérlet is felfrissítené, egy elérhetetlen API egy órára
        // "frissnek" jelölné a régi adatot, és a következő próbálkozás is
        // elmaradna.
        // A meglévő sort közvetlenül állítjuk elő, nem egy sikeres hívásból: a
        // Http::fake() ismételt hívása HOZZÁFŰZ a korábbi stubokhoz, tehát egy
        // sikeres fake után nem lehet tiszta hibaágat felvenni.
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
        $this->assertTrue($fresh->last_try->gt(now()->subMinute()), 'A last_try mozdul.');
        $this->assertTrue($fresh->updated_at->lt(now()->subHour()), 'Az updated_at NEM mozdul.');

        // A hibaág a legutóbbi ismert adatot is visszaadja: egy órás késésű
        // előrejelzés használhatóbb, mint a semmi.
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
