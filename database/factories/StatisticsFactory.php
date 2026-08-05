<?php

namespace Database\Factories;

use App\Models\Statistics;
use Illuminate\Database\Eloquent\Factories\Factory;

class StatisticsFactory extends Factory
{
    protected $model = Statistics::class;

    public function definition()
    {
        return [
            'type' => 'users',
            'date' => now()->format('Y-m-d H:i:s'),
            'number' => $this->faker->numberBetween(1, 100),
        ];
    }

    public function ofType(string $type): static
    {
        return $this->state(['type' => $type]);
    }

    public function onDate(string $date): static
    {
        return $this->state(['date' => $date]);
    }
}
