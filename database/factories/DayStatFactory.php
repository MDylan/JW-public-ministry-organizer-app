<?php

namespace Database\Factories;

use App\Models\DayStat;
use App\Models\Group;
use Illuminate\Database\Eloquent\Factories\Factory;

class DayStatFactory extends Factory
{
    protected $model = DayStat::class;

    public function definition()
    {
        $day = now()->toDateString();

        return [
            'group_id' => Group::factory(),
            'day' => $day,
            'time_slot' => $day.' 09:00:00',
            'events' => 1,
        ];
    }

    public function onDay(string $day, string $time = '09:00:00'): static
    {
        return $this->state([
            'day' => $day,
            'time_slot' => $day.' '.$time,
        ]);
    }

    public function withEvents(int $events): static
    {
        return $this->state(['events' => $events]);
    }

    public function forGroup(Group $group): static
    {
        return $this->state(['group_id' => $group->id]);
    }
}
