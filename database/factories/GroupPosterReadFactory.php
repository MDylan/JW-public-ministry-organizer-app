<?php

namespace Database\Factories;

use App\Models\GroupPosterRead;
use App\Models\GroupPosters;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

class GroupPosterReadFactory extends Factory
{
    protected $model = GroupPosterRead::class;

    public function definition()
    {
        return [
            'user_id' => User::factory(),
            'poster_id' => GroupPosters::factory(),
        ];
    }

    public function forUser(User $user): static
    {
        return $this->state(['user_id' => $user->id]);
    }

    public function forPoster(GroupPosters $poster): static
    {
        return $this->state(['poster_id' => $poster->id]);
    }
}
