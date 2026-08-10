<?php

namespace Database\Factories;

use App\Models\Group;
use App\Models\GroupMessage;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

class GroupMessageFactory extends Factory
{
    protected $model = GroupMessage::class;

    public function definition()
    {
        return [
            'group_id' => Group::factory(),
            'user_id' => User::factory(),
            // The message field is under an 'encrypted' cast.
            'message' => $this->faker->sentence(),
            'priority' => 0,
        ];
    }

    public function priority(): static
    {
        return $this->state(['priority' => 1]);
    }

    public function forGroup(Group $group): static
    {
        return $this->state(['group_id' => $group->id]);
    }

    public function fromUser(User $user): static
    {
        return $this->state(['user_id' => $user->id]);
    }
}
