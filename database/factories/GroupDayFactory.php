<?php

namespace Database\Factories;

use App\Models\Group;
use App\Models\GroupDay;
use Illuminate\Database\Eloquent\Factories\Factory;

class GroupDayFactory extends Factory
{
    protected $model = GroupDay::class;

    public function definition()
    {
        return [
            'group_id' => Group::factory(),
            'day_number' => 1,
            'start_time' => '08:00',
            'end_time' => '12:00',
        ];
    }
}
