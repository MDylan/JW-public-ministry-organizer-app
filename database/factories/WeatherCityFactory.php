<?php

namespace Database\Factories;

use App\Models\WeatherCity;
use Illuminate\Database\Eloquent\Factories\Factory;

class WeatherCityFactory extends Factory
{
    protected $model = WeatherCity::class;

    public function definition()
    {
        return [
            'city' => $this->faker->city(),
            'country' => 'HU',
            // A két időjárás mező 'json' cast alatt van.
            'current_weather' => null,
            'forecast_weather' => null,
            'last_try' => null,
        ];
    }

    /**
     * Az OpenWeather válaszainak valódi alakja, leszűkítve arra, amit a projekt
     * ténylegesen olvas.
     *
     * A korábbi állapot nem volt reprodukálható éles adatból: a mentés kézzel
     * is json_encode-olt a `json` cast MELLETT, tehát a `current_weather`
     * oszlopban kétszer kódolt SZTRING állt, miközben ez a factory tömböt írt -
     * a teszt olyan alakot állított, amit a termelés nem tudott előállítani.
     * A dupla kódolás megszűnt (v1-patch C), így itt már a valódi szerkezet áll.
     *
     * A `forecast_weather` a `list` kulcs alatt hordozza a 3 óránkénti
     * bontást, `dt_txt` és `main.temp` mezőkkel - ezt olvassa az
     * Events\Events::render().
     */
    public function withWeatherData(): static
    {
        $day = now()->addDay()->format('Y-m-d');

        return $this->state([
            'current_weather' => [
                'weather' => [
                    ['icon' => '01d', 'description' => 'clear sky'],
                ],
                'main' => [
                    'temp'     => 21.5,
                    'humidity' => 55,
                ],
                'wind' => ['speed' => 3.2],
                'name' => 'Budapest',
            ],
            'forecast_weather' => [
                'list' => [
                    [
                        'dt_txt'  => $day.' 09:00:00',
                        'main'    => ['temp' => 18.0, 'humidity' => 60],
                        'weather' => [['icon' => '02d', 'description' => 'few clouds']],
                        'wind'    => ['speed' => 2.5],
                    ],
                    [
                        'dt_txt'  => $day.' 12:00:00',
                        'main'    => ['temp' => 22.0, 'humidity' => 50],
                        'weather' => [['icon' => '01d', 'description' => 'clear sky']],
                        'wind'    => ['speed' => 3.0],
                    ],
                ],
            ],
            'last_try' => now(),
        ]);
    }

    public function staleTry(): static
    {
        return $this->state(['last_try' => now()->subDay()]);
    }
}
