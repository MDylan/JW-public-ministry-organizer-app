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
            // Both weather fields are under a 'json' cast.
            'current_weather' => null,
            'forecast_weather' => null,
            'last_try' => null,
        ];
    }

    /**
     * The real shape of OpenWeather's responses, narrowed down to what the
     * project actually reads.
     *
     * The earlier state was not reproducible from live data: the save also
     * called json_encode by hand ON TOP OF the `json` cast, so the
     * `current_weather` column held a doubly-encoded STRING while this
     * factory wrote an array - the test asserted a shape production could
     * never produce. The double encoding is gone (v1-patch C), so the real
     * structure is used here now.
     *
     * `forecast_weather` carries the 3-hourly breakdown under the `list`
     * key, with `dt_txt` and `main.temp` fields - this is what
     * Events\Events::render() reads.
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
