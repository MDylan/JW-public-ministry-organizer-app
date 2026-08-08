<?php

namespace App\Support\Weather;

use RuntimeException;

/**
 * Az időjárás-lekérdezés hibái, VALÓDI üzenettel.
 *
 * A korábban használt csomag a hibás API-kulcsot egy üzenet nélküli
 * `InvalidConfiguration` kivétellel jelezte, ezért a hívó helyen
 * `['error' => '']` keletkezett: a csoportadmin üres hibapanelt kapott, és
 * semmi nem árulta el, hogy a kulcs hiányzik vagy rossz.
 *
 * Az itteni gyártó metódusok mindegyike megnevezi az okot.
 */
class WeatherException extends RuntimeException
{
    public static function missingApiKey(): self
    {
        return new self(
            'Az OpenWeather API-kulcs nincs beállítva (OPENWEATHER_API_KEY vagy az admin felület).'
        );
    }

    public static function unauthorized(): self
    {
        return new self(
            'Az OpenWeather API elutasította a kulcsot (401). Ellenőrizd az OPENWEATHER_API_KEY értékét.'
        );
    }

    public static function cityNotFound(string $city, string $country): self
    {
        return new self(sprintf(
            'Az OpenWeather nem ismeri ezt a települést: %s, %s.',
            $city,
            $country
        ));
    }

    public static function rateLimited(): self
    {
        return new self('Az OpenWeather elutasította a kérést (429): túl sok hívás.');
    }

    public static function requestFailed(int $status): self
    {
        return new self(sprintf('Az OpenWeather API %d státusszal válaszolt.', $status));
    }

    public static function unreachable(string $reason): self
    {
        return new self('Az OpenWeather API nem érhető el: '.$reason);
    }
}
