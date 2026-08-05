<?php

namespace Database\Factories;

use App\Models\GroupNews;
use App\Models\GroupNewsTranslation;
use Illuminate\Database\Eloquent\Factories\Factory;

class GroupNewsTranslationFactory extends Factory
{
    protected $model = GroupNewsTranslation::class;

    public function definition()
    {
        // A GroupNews factory már létrehoz egy fordítást a fő locale-on,
        // ezért itt a fallback locale az alapértelmezés - így az önálló
        // GroupNewsTranslation::factory()->create() nem ütközik a
        // (group_news_id, locale) unique kulcsba.
        return [
            'group_news_id' => GroupNews::factory(),
            'locale' => config('app.fallback_locale', 'en'),
            'title' => $this->faker->sentence(4),
            'content' => $this->faker->paragraph(),
        ];
    }

    public function locale(string $locale): static
    {
        return $this->state(['locale' => $locale]);
    }

    public function forNews(GroupNews $news): static
    {
        return $this->state(['group_news_id' => $news->id]);
    }
}
