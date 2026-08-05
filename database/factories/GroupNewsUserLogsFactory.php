<?php

namespace Database\Factories;

use App\Models\Group;
use App\Models\GroupNewsUserLogs;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

class GroupNewsUserLogsFactory extends Factory
{
    protected $model = GroupNewsUserLogs::class;

    public function definition()
    {
        return [
            'group_id' => Group::factory(),
            'user_id' => User::factory(),
        ];
    }

    public function forGroup(Group $group): static
    {
        return $this->state(['group_id' => $group->id]);
    }

    public function forUser(User $user): static
    {
        return $this->state(['user_id' => $user->id]);
    }
}
