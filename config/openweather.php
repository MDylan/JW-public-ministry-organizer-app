<?php

return [

    /**
     * OpenWeather API key.
     * Free key: https://openweathermap.org/price
     *
     * The key's name was PREVIOUSLY OPENWAETHER_API_KEY - a typo that
     * appeared consistently in five places (.env.example, this file, the
     * admin settings component and its view), which is why it worked. The
     * correct name from now on is OPENWEATHER_API_KEY.
     *
     * The old name stays as a fallback FOR ONE RELEASE, because installed
     * hosts' .env files still carry it. Once the release has reached every
     * install, the fallback can be removed.
     */
    'api_key' => env('OPENWEATHER_API_KEY', env('OPENWAETHER_API_KEY', '')),

    /**
     * The response language.
     *
     * Left empty, the application's current locale decides
     * (OpenWeatherClient::language()). It used to be hardcoded to 'en',
     * which is why English weather descriptions appeared on the Hungarian
     * and German UI as well.
     */
    'lang' => env('OPENWEATHER_API_LANG', env('OPENWAETHER_API_LANG', '')),

    /**
     * Unit system: metric (Celsius, m/s), imperial (Fahrenheit, mph) or
     * standard (Kelvin).
     */
    'units' => env('OPENWEATHER_UNITS', 'metric'),

    /**
     * The date format used for display.
     */
    'date_format' => 'Y-m-d',

];
