<?php

namespace Database\Factories;

use App\Models\Group;
use App\Models\GroupSurvey;
use Illuminate\Database\Eloquent\Factories\Factory;

class GroupSurveyFactory extends Factory
{
    protected $model = GroupSurvey::class;

    public function definition()
    {
        return [
            'group_id' => Group::factory(),
            'question' => $this->faker->sentence().'?',
            'start_at' => now()->toDateString(),
            'end_at' => now()->addDays(7)->toDateString(),
        ];
    }

    public function running(): static
    {
        return $this->state([
            'start_at' => now()->subDay()->toDateString(),
            'end_at' => now()->addDay()->toDateString(),
        ]);
    }

    public function closed(): static
    {
        return $this->state([
            'start_at' => now()->subDays(10)->toDateString(),
            'end_at' => now()->subDay()->toDateString(),
        ]);
    }

    public function forGroup(Group $group): static
    {
        return $this->state(['group_id' => $group->id]);
    }
}
