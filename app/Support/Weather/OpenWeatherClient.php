<?php

namespace App\Support\Weather;

use Illuminate\Http\Client\ConnectionException;
use Illuminate\Support\Facades\Http;

/**
 * Az OpenWeather API két végpontja, közvetlenül a Http fakadon keresztül.
 *
 * MIÉRT NEM A CSOMAG
 *
 * A `rakibdevs/openweather-laravel-api` teljes fogyasztott felülete két GET
 * hívás volt (`data/2.5/weather` és `data/2.5/forecast`), a WeatherClientje
 * viszont `new GuzzleHttp\Client(...)`-ot példányosított a metódusa belsejében,
 * konténer-kötés nélkül. Emiatt a `Http::fake()` nem tudta elkapni, tehát a
 * sikeres ág egyáltalán NEM volt tesztelhető - miközben ez a projekt egyetlen
 * kifelé menő HTTP-hívása. A csere ezt oldja meg, nem a csomag mérete.
 *
 * MIT JAVÍT MÉG
 *
 * - Az országkód MINDKÉT végpontra elmegy. A csomag `get3HourlyByCity(string $city)`
 *   szignatúrája egyetlen paramétert fogadott, a hívó viszont kettőt adott át:
 *   az 5 napos előrejelzés országkód NÉLKÜL oldódott fel, a jelenlegi időjárás
 *   viszont vele - két különböző település is lehetett a kettő mögött.
 * - Van explicit timeout. A csomagnak nem volt, tehát egy beragadt végpont a
 *   PHP workert tartotta fogva.
 * - A nyelv az alkalmazás aktuális lokáljából jön, nem bedrótozott 'en'-ből.
 */
class OpenWeatherClient
{
    private const BASE_URL = 'https://api.openweathermap.org';

    /** Másodperc. A csomagnak semmilyen időkorlátja nem volt. */
    private const TIMEOUT = 10;

    /**
     * A jelenlegi időjárás egy településre.
     *
     * @throws WeatherException
     */
    public function currentByCity(string $city, string $country): array
    {
        return $this->get('data/2.5/weather', $city, $country);
    }

    /**
     * Az 5 napos, 3 óránkénti előrejelzés egy településre.
     *
     * @throws WeatherException
     */
    public function forecastByCity(string $city, string $country): array
    {
        return $this->get('data/2.5/forecast', $city, $country);
    }

    /**
     * @throws WeatherException
     */
    private function get(string $path, string $city, string $country): array
    {
        $apiKey = (string) config('openweather.api_key');

        if ($apiKey === '') {
            throw WeatherException::missingApiKey();
        }

        try {
            $response = Http::baseUrl(self::BASE_URL)
                ->timeout(self::TIMEOUT)
                ->get($path, [
                    'q'     => $city.','.$country,
                    'appid' => $apiKey,
                    'units' => config('openweather.units', 'metric'),
                    'lang'  => $this->language(),
                ]);
        } catch (ConnectionException $e) {
            // Hálózati hiba, DNS, timeout: a Http fakad ezt kivételként dobja,
            // nem válaszként. Projekt-kivétellé alakítjuk, hogy a hívó egyetlen
            // típust kelljen kezeljen.
            throw WeatherException::unreachable($e->getMessage());
        }

        if ($response->successful()) {
            return (array) $response->json();
        }

        throw $this->exceptionFor($response->status(), $city, $country);
    }

    private function exceptionFor(int $status, string $city, string $country): WeatherException
    {
        switch ($status) {
            case 401:
                return WeatherException::unauthorized();
            case 404:
                return WeatherException::cityNotFound($city, $country);
            case 429:
                return WeatherException::rateLimited();
            default:
                return WeatherException::requestFailed($status);
        }
    }

    /**
     * Az OpenWeather kétbetűs nyelvkódot vár. Az alkalmazás lokálja már ilyen
     * alakú ('hu', 'en', 'de'), de egy 'hu_HU' formátumú érték is előfordulhat,
     * ezért levágjuk az első szegmensre.
     */
    private function language(): string
    {
        $configured = config('openweather.lang');

        if (! empty($configured)) {
            return (string) $configured;
        }

        return substr(str_replace('_', '-', app()->getLocale()), 0, 2);
    }
}
