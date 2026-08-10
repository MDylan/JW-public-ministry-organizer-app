<?php

namespace App\Support\Weather;

use App\Models\WeatherCity;

/**
 * The `weather_cities` table as a cache in front of the OpenWeather calls.
 *
 * The logic moved here from the helpers.php `pwbs_weather_api_call()` function;
 * that still exists, as a one-line pass-through, so the two Livewire call sites
 * don't have to move in the same change.
 *
 * THE TWO TIME LIMITS
 *
 * - `updated_at` 59 minutes: we don't re-fetch data fresher than this.
 * - `last_try` 15 minutes: after a failed attempt we don't retry for this long.
 *
 * Both are kept. The material difference from the previous code is that a
 * failed fetch STILL returns the city id and the latest known
 * data, if any - see the `failure()` explanation.
 *
 * THE STORED SHAPE
 *
 * The `WeatherCity` model's `current_weather` and `forecast_weather` fields use
 * a `json` cast, so they expect and return an array. The previous code ALSO
 * `json_encode`d these by hand on top of that, so the field stored a doubly
 * encoded string, and every reader had to decode it by hand. From here on, the
 * cast is the single encoding point - the stored value is a genuine JSON object, not
 * a JSON-wrapped string.
 */
class WeatherCache
{
    /** How long the stored data is considered fresh. */
    private const FRESH_MINUTES = 59;

    /** How long we don't call the API again after a failed attempt. */
    private const RETRY_MINUTES = 15;

    private OpenWeatherClient $client;

    public function __construct(?OpenWeatherClient $client = null)
    {
        $this->client = $client ?? new OpenWeatherClient();
    }

    /**
     * The weather for the given city, with caching.
     *
     * @return array{city_id?: int, current_weather?: array, forecast_weather?: array, error?: string}
     */
    public function forCity(string $city, string $country): array
    {
        [$city, $country] = self::normalize($city, $country);

        $cached = WeatherCity::where('city', $city)->where('country', $country)->first();

        if ($cached !== null && $this->isFresh($cached)) {
            return $this->payload($cached);
        }

        if ($cached !== null && $this->isThrottled($cached)) {
            return $this->failure($cached, __('group.weather.too_many_requests'));
        }

        return $this->refresh($city, $country, $cached);
    }

    /**
     * Unconditional refresh - used by the `weather:refresh` command.
     *
     * The `last_try` limit applies HERE TOO, because it protects the API, not the
     * caller: without it, a frequently running schedule would retry the
     * failing cities on every run.
     *
     * @return array{city_id?: int, current_weather?: array, forecast_weather?: array, error?: string}
     */
    public function refreshCity(string $city, string $country): array
    {
        [$city, $country] = self::normalize($city, $country);

        $cached = WeatherCity::where('city', $city)->where('country', $country)->first();

        if ($cached !== null && $this->isThrottled($cached)) {
            return $this->failure($cached, __('group.weather.too_many_requests'));
        }

        return $this->refresh($city, $country, $cached);
    }

    /**
     * Normalizing the stored shape: the city starting with a capital letter, the
     * country code all uppercase. This is the key of the `weather_cities` row.
     *
     * @return array{0: string, 1: string}
     */
    public static function normalize(string $city, string $country): array
    {
        return [ucfirst(trim($city)), strtoupper(trim($country))];
    }

    private function isFresh(WeatherCity $cached): bool
    {
        return $cached->current_weather !== null
            && $cached->updated_at > now()->subMinutes(self::FRESH_MINUTES);
    }

    private function isThrottled(WeatherCity $cached): bool
    {
        return $cached->last_try !== null
            && $cached->last_try > now()->subMinutes(self::RETRY_MINUTES);
    }

    /**
     * @return array{city_id?: int, current_weather?: array, forecast_weather?: array, error?: string}
     */
    private function refresh(string $city, string $country, ?WeatherCity $cached): array
    {
        try {
            $current = $this->client->currentByCity($city, $country);
            $forecast = $this->client->forecastByCity($city, $country);
        } catch (WeatherException $e) {
            // The failed attempt also gets a timestamp, otherwise every
            // page load would retry it - which is exactly why the 15-minute brake
            // previously didn't kick in for a misconfigured key.
            //
            // `updated_at`, however, must NOT move: it's read by the 59-minute
            // freshness rule, and a failed refresh must not make the
            // stale data look fresh. Hence firstOrNew + a disabled timestamp,
            // not updateOrCreate.
            $row = WeatherCity::firstOrNew(['city' => $city, 'country' => $country]);
            $row->last_try = now();

            if ($row->exists) {
                $row->timestamps = false;
            }

            $row->save();
            $row->timestamps = true;

            return $this->failure($row, $e->getMessage());
        }

        $row = WeatherCity::updateOrCreate(
            ['city' => $city, 'country' => $country],
            [
                // Raw array: the json cast does the encoding, once.
                'current_weather'  => $current,
                'forecast_weather' => $forecast,
                'last_try'         => now(),
            ]
        );

        return $this->payload($row);
    }

    /**
     * @return array{city_id: int, current_weather?: array, forecast_weather?: array}
     */
    private function payload(WeatherCity $row): array
    {
        return [
            'city_id'          => $row->id,
            'current_weather'  => $row->current_weather,
            'forecast_weather' => $row->forecast_weather,
        ];
    }

    /**
     * The result of a failed fetch.
     *
     * `city_id` is DELIBERATELY included. Previously the caller (UpdateGroupForm)
     * nulled out `city_id` on the error branch, while validation required it
     * (`required_if:weather_enabled,1`) - anyone who enabled the weather feature while the
     * API wasn't responding couldn't save the group at all. Saving must not
     * depend on the availability of an external service.
     *
     * We also return the latest known data, if any: an hour-stale
     * forecast is more usable than nothing.
     *
     * @return array{city_id: int, error: string, current_weather?: array, forecast_weather?: array}
     */
    private function failure(WeatherCity $row, string $message): array
    {
        $payload = [
            'city_id' => $row->id,
            'error'   => $message,
        ];

        if ($row->current_weather !== null) {
            $payload['current_weather'] = $row->current_weather;
            $payload['forecast_weather'] = $row->forecast_weather;
        }

        return $payload;
    }
}
