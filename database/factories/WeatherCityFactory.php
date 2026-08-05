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

    public function withWeatherData(): static
    {
        return $this->state([
            'current_weather' => ['temp' => 21.5, 'description' => 'clear sky'],
            'forecast_weather' => [['temp' => 22.0], ['temp' => 19.5]],
            'last_try' => now(),
        ]);
    }

    public function staleTry(): static
    {
        return $this->state(['last_try' => now()->subDay()]);
    }
}
