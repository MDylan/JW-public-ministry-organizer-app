<?php

namespace App\Support\Weather;

use App\Models\WeatherCity;

/**
 * A `weather_cities` tábla mint gyorsítótár az OpenWeather hívások elé.
 *
 * A logika a helpers.php `pwbs_weather_api_call()` függvényéből költözött ide;
 * az továbbra is létezik, egysoros átjáróként, hogy a két Livewire hívási hely
 * ne mozduljon ugyanabban a változtatásban.
 *
 * A KÉT IDŐKORLÁT
 *
 * - `updated_at` 59 perc: ennél frissebb adatot nem kérünk le újra.
 * - `last_try` 15 perc: sikertelen kísérlet után ennyi ideig nem próbálkozunk.
 *
 * Mindkettő megmarad. Az érdemi különbség a korábbi kódhoz képest, hogy a
 * sikertelen lekérés MOST IS visszaadja a városazonosítót és a legutóbbi ismert
 * adatot, ha van - lásd a `failure()` magyarázatát.
 *
 * A TÁROLT ALAK
 *
 * A `WeatherCity` modell `current_weather` és `forecast_weather` mezői `json`
 * castot használnak, tehát tömböt várnak és tömböt adnak vissza. A korábbi kód
 * ezek MELLETT kézzel is `json_encode`-olt, így a mező kétszer kódolt sztringet
 * tárolt, és minden olvasónak kézzel kellett dekódolnia. Innentől a cast az
 * egyetlen kódolási pont - a tárolt érték valódi JSON objektum, nem
 * JSON-ba csomagolt sztring.
 */
class WeatherCache
{
    /** Ennyi ideig tekintjük frissnek a tárolt adatot. */
    private const FRESH_MINUTES = 59;

    /** Sikertelen kísérlet után ennyi ideig nem hívjuk újra az API-t. */
    private const RETRY_MINUTES = 15;

    private OpenWeatherClient $client;

    public function __construct(?OpenWeatherClient $client = null)
    {
        $this->client = $client ?? new OpenWeatherClient();
    }

    /**
     * A megadott település időjárása, gyorsítótárral.
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
     * Feltétel nélküli frissítés - a `weather:refresh` parancs használja.
     *
     * A `last_try` korlát ITT IS érvényes, mert az az API védelme, nem a
     * hívóé: enélkül egy sűrűn futó ütemezés minden körben újrapróbálná a
     * hibás településeket.
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
     * A tárolt alak normalizálása: a település nagybetűvel kezdve, az
     * országkód csupa nagybetűvel. Ez a kulcsa a `weather_cities` sornak.
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
            // A sikertelen kísérlet is időbélyeget kap, különben minden
            // oldalletöltés újrapróbálná - a 15 perces fék korábban éppen ezért
            // nem kapcsolt be rosszul konfigurált kulcsnál.
            //
            // Az `updated_at` viszont NEM mozdulhat: azt a 59 perces
            // frissesség-szabály olvassa, és egy sikertelen frissítés nem teheti
            // frissé a régi adatot. Ezért firstOrNew + kikapcsolt időbélyeg,
            // nem updateOrCreate.
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
                // Nyers tömb: a json cast végzi a kódolást, egyszer.
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
     * Hibás lekérés eredménye.
     *
     * A `city_id` SZÁNDÉKOSAN benne van. Korábban a hívó (UpdateGroupForm) a
     * hibaágon nullázta a `city_id`-t, miközben a validáció megkövetelte
     * (`required_if:weather_enabled,1`) - aki bekapcsolta az időjárást és az
     * API épp nem válaszolt, egyáltalán nem tudta menteni a csoportot. A
     * mentésnek nem szabad egy külső szolgáltatás elérhetőségén múlnia.
     *
     * A legutóbbi ismert adatot is visszaadjuk, ha van: egy órás késésű
     * előrejelzés használhatóbb, mint a semmi.
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
