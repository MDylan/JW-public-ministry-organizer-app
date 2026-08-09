<?php

namespace Database\Factories;

use App\Models\Group;
use App\Models\GroupPosters;
use Illuminate\Database\Eloquent\Factories\Factory;

class GroupPostersFactory extends Factory
{
    protected $model = GroupPosters::class;

    public function definition()
    {
        return [
            'group_id' => Group::factory(),
            // Az info mező 'encrypted' cast alatt van, itt sima szöveget adunk.
            'info' => $this->faker->sentence(),
            'show_date' => now()->toDateString(),
            'hide_date' => null,
        ];
    }

    // --- Láthatóság state-ek ---

    public function visible(): static
    {
        return $this->state([
            'show_date' => now()->subDay()->toDateString(),
            'hide_date' => now()->addDay()->toDateString(),
        ]);
    }

    public function expired(): static
    {
        return $this->state([
            'show_date' => now()->subDays(10)->toDateString(),
            'hide_date' => now()->subDay()->toDateString(),
        ]);
    }

    public function upcoming(): static
    {
        return $this->state([
            'show_date' => now()->addDays(3)->toDateString(),
            'hide_date' => null,
        ]);
    }

    // --- Kötési segéd ---

    public function forGroup(Group $group): static
    {
        return $this->state(['group_id' => $group->id]);
    }
}
