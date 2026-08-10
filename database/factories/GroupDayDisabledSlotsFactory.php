<?php

namespace Database\Factories;

use App\Models\Group;
use App\Models\GroupDayDisabledSlots;
use Illuminate\Database\Eloquent\Factories\Factory;

class GroupDayDisabledSlotsFactory extends Factory
{
    protected $model = GroupDayDisabledSlots::class;

    public function definition()
    {
        return [
            'group_id' => Group::factory(),
            // day_number: the day of the week, consistent with the group_days table.
            'day_number' => 1,
            'slot' => '09:00',
        ];
    }

    public function onDayNumber(int $dayNumber): static
    {
        return $this->state(['day_number' => $dayNumber]);
    }

    public function atSlot(string $slot): static
    {
        return $this->state(['slot' => $slot]);
    }

    public function forGroup(Group $group): static
    {
        return $this->state(['group_id' => $group->id]);
    }
}
