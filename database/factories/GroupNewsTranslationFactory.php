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
        // The GroupNews factory already creates a translation in the main
        // locale, so the fallback locale is the default here - this way a
        // standalone GroupNewsTranslation::factory()->create() does not
        // collide with the (group_news_id, locale) unique key.
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
