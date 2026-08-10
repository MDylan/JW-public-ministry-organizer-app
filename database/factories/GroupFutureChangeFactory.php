<?php

namespace Database\Factories;

use App\Models\Group;
use App\Models\GroupFutureChange;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

class GroupFutureChangeFactory extends Factory
{
    protected $model = GroupFutureChange::class;

    public function definition()
    {
        // The group/days/disabled_slots fields are under an 'array' cast
        // and are NOT NULL, so an empty array is the default here.
        return [
            'group_id' => Group::factory(),
            'user_id' => User::factory(),
            'change_date' => now()->addDays(7)->toDateString(),
            'group' => [
                'min_publishers' => 1,
                'max_publishers' => 3,
                'min_time' => 60,
                'max_time' => 240,
            ],
            'days' => [],
            'disabled_slots' => [],
        ];
    }

    public function onDate(string $date): static
    {
        return $this->state(['change_date' => $date]);
    }

    public function forGroup(Group $group): static
    {
        return $this->state(['group_id' => $group->id]);
    }
}
