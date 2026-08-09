<?php

namespace Database\Factories;

use App\Models\Group;
use App\Models\GroupNews;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

class GroupNewsFactory extends Factory
{
    protected $model = GroupNews::class;

    public function definition()
    {
        // A modell translatable: a locale kulcs alatt átadott mezőket
        // az astrotomic Translatable::fill() szedi ki és a fordítástáblába írja.
        $locale = config('app.locale', 'hu');

        return [
            'group_id' => Group::factory(),
            'user_id' => User::factory(),
            'status' => 1,
            'date' => now()->toDateString(),
            $locale => [
                'title' => $this->faker->sentence(4),
                'content' => $this->faker->paragraph(),
            ],
        ];
    }

    // --- Státusz state-ek ---

    public function draft(): static
    {
        return $this->state(['status' => 0]);
    }

    public function published(): static
    {
        return $this->state(['status' => 1]);
    }

    // --- Kötési segédek ---

    public function forGroup(Group $group): static
    {
        return $this->state(['group_id' => $group->id]);
    }

    public function byUser(User $user): static
    {
        return $this->state(['user_id' => $user->id]);
    }
}
