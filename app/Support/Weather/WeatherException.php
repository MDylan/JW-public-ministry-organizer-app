<?php

namespace App\Support\Weather;

use RuntimeException;

/**
 * Weather-lookup errors, with a REAL message.
 *
 * The previously used package signaled a bad API key with a message-less
 * `InvalidConfiguration` exception, so `['error' => '']` resulted at the
 * call site: the group admin got an empty error panel, and
 * nothing revealed whether the key was missing or wrong.
 *
 * Every factory method here names the reason.
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
