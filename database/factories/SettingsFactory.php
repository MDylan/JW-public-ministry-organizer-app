<?php

namespace Database\Factories;

use App\Models\Settings;
use Illuminate\Database\Eloquent\Factories\Factory;

class SettingsFactory extends Factory
{
    protected $model = Settings::class;

    public function definition()
    {
        return [
            'name' => $this->faker->unique()->slug(2),
            'value' => '1',
            'comment' => null,
        ];
    }

    public function named(string $name, $value = '1'): static
    {
        return $this->state([
            'name' => $name,
            'value' => $value,
        ]);
    }
}
