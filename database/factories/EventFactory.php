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

    // --- Status states ---

    public function accepted(): static
    {
        return $this->state(function (array $attributes) {
            return [
                'status'      => 1,
                'accepted_by' => $attributes['user_id'],
                'accepted_at' => now(),
            ];
        });
    }

    public function pending(): static
    {
        return $this->state([
            'status'      => 0,
            'accepted_by' => null,
            'accepted_at' => null,
        ]);
    }

    // --- Time states ---

    public function onDate(string $date): static
    {
        return $this->state(function () use ($date) {
            $start = \Carbon\Carbon::parse($date)->setTime(9, 0);

            return [
                'day'   => $date,
                'start' => $start->format('Y-m-d H:i:s'),
                'end'   => $start->copy()->addHour()->format('Y-m-d H:i:s'),
            ];
        });
    }

    public function inThePast(): static
    {
        return $this->state(function () {
            $start = now()->subDay()->setTime(9, 0);

            return [
                'day'   => $start->toDateString(),
                'start' => $start->format('Y-m-d H:i:s'),
                'end'   => $start->copy()->addHour()->format('Y-m-d H:i:s'),
            ];
        });
    }

    // --- Binding helpers ---

    public function forUser(User $user): static
    {
        return $this->state(['user_id' => $user->id]);
    }

    public function forGroup(Group $group): static
    {
        return $this->state(['group_id' => $group->id]);
    }

    public function acceptedBy(User $acceptor): static
    {
        return $this->state([
            'status'      => 1,
            'accepted_by' => $acceptor->id,
            'accepted_at' => now(),
        ]);
    }
}
