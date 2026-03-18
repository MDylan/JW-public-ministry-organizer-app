<?php

namespace Database\Factories;

use App\Models\Group;
use App\Models\GroupUser;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

class GroupUserFactory extends Factory
{
    protected $model = GroupUser::class;

    public function definition(): array
    {
        return [
            'user_id'               => User::factory(),
            'group_id'              => Group::factory(),
            'group_role'            => 'member',
            'accepted_at'           => now(),
            'note'                  => null,
            'hidden'                => 0,
            'signs'                 => null,
            'list_order'            => 0,
            'message_use'           => 1,
            'message_send_priority' => 0,
        ];
    }

    // --- Csoportszerep state-ek ---

    public function asMember(): static
    {
        return $this->state(['group_role' => 'member']);
    }

    public function asHelper(): static
    {
        return $this->state(['group_role' => 'helper']);
    }

    public function asRoler(): static
    {
        return $this->state(['group_role' => 'roler']);
    }

    public function asGroupAdmin(): static
    {
        return $this->state(['group_role' => 'admin']);
    }

    // --- Tagság elfogadási státusz ---

    public function accepted(): static
    {
        return $this->state(['accepted_at' => now()]);
    }

    public function pending(): static
    {
        return $this->state(['accepted_at' => null]);
    }

    public function withdrawn(): static
    {
        return $this->state(['deleted_at' => now()]);
    }

    // --- Láthatóság ---

    public function hidden(): static
    {
        return $this->state(['hidden' => 1]);
    }

    // --- Üzenetkezelés ---

    public function withMessaging(): static
    {
        return $this->state([
            'message_use'           => 1,
            'message_send_priority' => 1,
        ]);
    }

    public function withoutMessaging(): static
    {
        return $this->state([
            'message_use'           => 0,
            'message_send_priority' => 0,
        ]);
    }

    // --- Kötési segédek ---

    public function forUser(User $user): static
    {
        return $this->state(['user_id' => $user->id]);
    }

    public function forGroup(Group $group): static
    {
        return $this->state(['group_id' => $group->id]);
    }
}
