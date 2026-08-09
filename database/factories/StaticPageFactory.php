<?php

namespace Database\Factories;

use App\Models\StaticPage;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

class StaticPageFactory extends Factory
{
    protected $model = StaticPage::class;

    public function definition()
    {
        return [
            'slug' => $this->faker->unique()->slug(),
            'position' => 'hidden',
            'status' => 1,
            'user_id' => User::factory(),
        ];
    }
}
