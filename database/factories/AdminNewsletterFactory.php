<?php

namespace Database\Factories;

use App\Models\AdminNewsletter;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

class AdminNewsletterFactory extends Factory
{
    protected $model = AdminNewsletter::class;

    public function definition()
    {
        // Translatable modell: a locale kulcs alatti mezőket az astrotomic
        // Translatable::fill() irányítja a fordítástáblába.
        $locale = config('app.locale', 'hu');

        return [
            'user_id' => User::factory(),
            'date' => now()->toDateString(),
            'status' => 0,
            'send_newsletter' => 0,
            'send_to' => 'all',
            'sent_time' => null,
            $locale => [
                'subject' => $this->faker->sentence(4),
                'content' => $this->faker->paragraph(),
            ],
        ];
    }

    // --- Állapot state-ek ---

    public function published(): static
    {
        return $this->state(['status' => 1]);
    }

    public function sent(): static
    {
        return $this->state([
            'status' => 1,
            'send_newsletter' => 1,
            'sent_time' => now(),
        ]);
    }

    public function sendTo(string $target): static
    {
        return $this->state(['send_to' => $target]);
    }
}
