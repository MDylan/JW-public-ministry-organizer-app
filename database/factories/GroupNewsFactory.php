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
        // The model is translatable: astrotomic Translatable::fill() picks
        // up the fields passed under the locale key and writes them into
        // the translation table.
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

    // --- Status states ---

    public function draft(): static
    {
        return $this->state(['status' => 0]);
    }

    public function published(): static
    {
        return $this->state(['status' => 1]);
    }

    // --- Binding helpers ---

    public function forGroup(Group $group): static
    {
        return $this->state(['group_id' => $group->id]);
    }

    public function byUser(User $user): static
    {
        return $this->state(['user_id' => $user->id]);
    }
}
