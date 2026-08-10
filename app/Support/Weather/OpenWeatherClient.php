<?php

namespace App\Support\Weather;

use Illuminate\Http\Client\ConnectionException;
use Illuminate\Support\Facades\Http;

/**
 * The OpenWeather API's two endpoints, directly through the Http facade.
 *
 * WHY NOT THE PACKAGE
 *
 * The entire consumed surface of `rakibdevs/openweather-laravel-api` was two GET
 * calls (`data/2.5/weather` and `data/2.5/forecast`), but its WeatherClient
 * instantiated `new GuzzleHttp\Client(...)` inside its method,
 * without container binding. Because of this, `Http::fake()` couldn't intercept it, so the
 * success path was NOT testable AT ALL - even though this is the project's only
 * outbound HTTP call. The replacement fixes this, not the package's size.
 *
 * WHAT ELSE THIS FIXES
 *
 * - The country code goes to BOTH endpoints. The package's `get3HourlyByCity(string $city)`
 *   signature accepted a single parameter, while the caller passed two:
 *   the 5-day forecast resolved WITHOUT the country code, while the current weather
 *   resolved with it - the two could refer to two different cities.
 * - There is an explicit timeout. The package had none, so a stuck endpoint
 *   held the PHP worker hostage.
 * - The language comes from the application's current locale, not a hardcoded 'en'.
 */
class OpenWeatherClient
{
    private const BASE_URL = 'https://api.openweathermap.org';

    /** Seconds. The package had no timeout at all. */
    private const TIMEOUT = 10;

    /**
     * The current weather for a city.
     *
     * @throws WeatherException
     */
    public function currentByCity(string $city, string $country): array
    {
        return $this->get('data/2.5/weather', $city, $country);
    }

    /**
     * The 5-day, 3-hourly forecast for a city.
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
            // Network error, DNS, timeout: the Http facade throws this as an exception,
            // not as a response. We convert it into a project exception so the caller only
            // has to handle a single type.
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
     * OpenWeather expects a two-letter language code. The application's locale is
     * already in that form ('hu', 'en', 'de'), but a value in 'hu_HU' format can also
     * occur, so we truncate it to the first segment.
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
