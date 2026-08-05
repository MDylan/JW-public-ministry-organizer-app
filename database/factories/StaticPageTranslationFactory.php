<?php

namespace Database\Factories;

use App\Models\StaticPage;
use App\Models\StaticPageTranslation;
use Illuminate\Database\Eloquent\Factories\Factory;

class StaticPageTranslationFactory extends Factory
{
    protected $model = StaticPageTranslation::class;

    public function definition()
    {
        return [
            'static_page_id' => StaticPage::factory(),
            'locale' => config('app.locale', 'hu'),
            'title' => $this->faker->sentence(3),
            'content' => $this->faker->paragraph(),
        ];
    }

    public function locale(string $locale): static
    {
        return $this->state(['locale' => $locale]);
    }
}
