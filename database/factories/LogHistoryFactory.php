<?php

namespace Database\Factories;

use App\Models\Group;
use App\Models\LogHistory;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Database\Eloquent\Model;

class LogHistoryFactory extends Factory
{
    protected $model = LogHistory::class;

    public function definition()
    {
        // The morph target defaults to the group itself, not a
        // GroupLiterature: GroupLiteratureObserver writes its own
        // LogHistory row on the created event, which would duplicate every
        // factory call. GroupObserver only logs on updated, so Group is a
        // safe default.
        return [
            'event' => 'updated',
            'group_id' => Group::factory(),
            'causer_id' => User::factory(),
            'model_type' => Group::class,
            'model_id' => fn (array $attributes) => $attributes['group_id'],
            // The changes field is read back by the model as a raw JSON
            // string in getChangesArrayAttribute().
            'changes' => json_encode(['name' => ['old' => 'A', 'new' => 'B']]),
        ];
    }

    // --- Event states ---

    public function event(string $event): static
    {
        return $this->state(['event' => $event]);
    }

    // --- Morph binding ---

    public function forModel(Model $model): static
    {
        return $this->state([
            'model_type' => get_class($model),
            'model_id' => $model->getKey(),
        ]);
    }

    public function causedBy(User $user): static
    {
        return $this->state(['causer_id' => $user->id]);
    }

    public function forGroup(Group $group): static
    {
        return $this->state(['group_id' => $group->id]);
    }
}
