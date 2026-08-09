<?php

namespace Database\Factories;

use App\Models\Group;
use Illuminate\Database\Eloquent\Factories\Factory;

class GroupFactory extends Factory
{
    protected $model = Group::class;

    public function definition()
    {
        return [
            'name' => $this->faker->company(),
            'max_extend_days' => 30,
            'min_publishers' => 1,
            'max_publishers' => 3,
            'min_time' => 60,
            'max_time' => 240,
            'need_approval' => 0,
            'auto_approval' => 0,
            'auto_back' => 0,
            'replyTo' => 'noreply@example.test',
            'messages_on' => 1,
            'messages_write' => 1,
            'messages_priority' => 1,
        ];
    }

    public function requiresApproval(): static
    {
        return $this->state([
            'need_approval' => 1,
            'auto_approval' => 0,
        ]);
    }

    public function withAutoApproval(): static
    {
        return $this->state([
            'need_approval' => 1,
            'auto_approval' => 1,
        ]);
    }

    public function asChildOf(Group $parent): static
    {
        return $this->state([
            'parent_group_id' => $parent->id,
        ]);
    }

    public function withMessagingDisabled(): static
    {
        return $this->state([
            'messages_on'    => 0,
            'messages_write' => 0,
        ]);
    }

    public function withStrictPublisherLimits(): static
    {
        return $this->state([
            'min_publishers' => 2,
            'max_publishers' => 2,
            'min_time'       => 30,
            'max_time'       => 60,
        ]);
    }
}
