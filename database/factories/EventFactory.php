<?php

namespace Database\Factories;

use App\Models\Event;
use App\Models\Group;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

class EventFactory extends Factory
{
    protected $model = Event::class;

    public function definition()
    {
        $start = now()->addDay()->startOfDay()->addHours(9);

        return [
            'group_id' => Group::factory(),
            'user_id' => User::factory(),
            'day' => $start->toDateString(),
            'start' => $start->format('Y-m-d H:i:s'),
            'end' => $start->copy()->addHour()->format('Y-m-d H:i:s'),
            'status' => 0,
        ];
    }
}
