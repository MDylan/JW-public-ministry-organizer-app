<?php

namespace Database\Factories;

use App\Models\Group;
use App\Models\GroupLiterature;
use Illuminate\Database\Eloquent\Factories\Factory;

class GroupLiteratureFactory extends Factory
{
    protected $model = GroupLiterature::class;

    public function definition()
    {
        return [
            'group_id' => Group::factory(),
            'name' => $this->faker->words(3, true),
        ];
    }

    public function forGroup(Group $group): static
    {
        return $this->state(['group_id' => $group->id]);
    }
}
