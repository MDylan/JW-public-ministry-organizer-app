<?php

namespace Database\Factories;

use App\Models\Group;
use App\Models\GroupDate;
use Illuminate\Database\Eloquent\Factories\Factory;

class GroupDateFactory extends Factory
{
    protected $model = GroupDate::class;

    public function definition()
    {
        $date = now()->addDay()->toDateString();

        return [
            'group_id' => Group::factory(),
            'date' => $date,
            'date_start' => $date.' 08:00:00',
            'date_end' => $date.' 12:00:00',
            'date_status' => 1,
            'date_min_publishers' => 1,
            'date_max_publishers' => 3,
            'date_min_time' => 60,
            'date_max_time' => 240,
            'run_job' => 0,
            'disabled_slots' => null,
        ];
    }
}
