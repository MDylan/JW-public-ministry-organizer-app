<?php

namespace Database\Factories;

use App\Models\AdminNewsletter;
use App\Models\AdminNewsletterTranslation;
use Illuminate\Database\Eloquent\Factories\Factory;

class AdminNewsletterTranslationFactory extends Factory
{
    protected $model = AdminNewsletterTranslation::class;

    public function definition()
    {
        // The AdminNewsletter factory already creates a translation in
        // the main locale, so the fallback locale is the default here.
        return [
            'admin_newsletter_id' => AdminNewsletter::factory(),
            'locale' => config('app.fallback_locale', 'en'),
            'subject' => $this->faker->sentence(4),
            'content' => $this->faker->paragraph(),
        ];
    }

    public function locale(string $locale): static
    {
        return $this->state(['locale' => $locale]);
    }
}
