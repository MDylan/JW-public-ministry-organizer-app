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
        // A morph cél alapból maga a csoport, nem egy GroupLiterature:
        // a GroupLiteratureObserver a created eseményre saját LogHistory
        // sort ír, ami minden factory-hívást megduplázna. A GroupObserver
        // csak updated-re logol, ezért a Group biztonságos alapértelmezés.
        return [
            'event' => 'updated',
            'group_id' => Group::factory(),
            'causer_id' => User::factory(),
            'model_type' => Group::class,
            'model_id' => fn (array $attributes) => $attributes['group_id'],
            // A changes mezőt a modell nyers JSON stringként olvassa
            // vissza a getChangesArrayAttribute()-ban.
            'changes' => json_encode(['name' => ['old' => 'A', 'new' => 'B']]),
        ];
    }

    // --- Esemény state-ek ---

    public function event(string $event): static
    {
        return $this->state(['event' => $event]);
    }

    // --- Morph kötés ---

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
