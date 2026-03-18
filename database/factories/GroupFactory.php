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
}
