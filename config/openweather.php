<?php

return [

    /**
     * OpenWeather API kulcs.
     * Ingyenes kulcs: https://openweathermap.org/price
     *
     * A kulcs neve KORÁBBAN OPENWAETHER_API_KEY volt - egy elírás, ami
     * következetesen szerepelt öt helyen (.env.example, ez a fájl, az admin
     * beállítás-komponens és a hozzá tartozó nézet), ezért működött. A helyes
     * név innentől OPENWEATHER_API_KEY.
     *
     * A régi név EGY KIADÁS EREJÉIG fallbackként megmarad, mert a telepített
     * hostok .env fájlja még azt hordozza. Amint a kiadás minden helyre
     * eljutott, a fallback törölhető.
     */
    'api_key' => env('OPENWEATHER_API_KEY', env('OPENWAETHER_API_KEY', '')),

    /**
     * A válasz nyelve.
     *
     * Üresen hagyva az alkalmazás aktuális lokálja dönt
     * (OpenWeatherClient::language()). Korábban bedrótozott 'en' volt, ezért a
     * magyar és német felületen is angol időjárás-leírások jelentek meg.
     */
    'lang' => env('OPENWEATHER_API_LANG', env('OPENWAETHER_API_LANG', '')),

    /**
     * Mértékegység-rendszer: metric (Celsius, m/s), imperial (Fahrenheit,
     * mérföld/óra) vagy standard (Kelvin).
     */
    'units' => env('OPENWEATHER_UNITS', 'metric'),

    /**
     * A megjelenítéshez használt dátumformátum.
     */
    'date_format' => 'Y-m-d',

];
