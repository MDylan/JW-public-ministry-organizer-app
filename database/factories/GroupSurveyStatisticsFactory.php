<?php

namespace Database\Factories;

use App\Models\GroupSurveyAnswer;
use App\Models\GroupSurveyStatistics;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

class GroupSurveyStatisticsFactory extends Factory
{
    protected $model = GroupSurveyStatistics::class;

    public function definition()
    {
        return [
            'user_id' => User::factory(),
            'group_survey_answer_id' => GroupSurveyAnswer::factory(),
        ];
    }

    public function forUser(User $user): static
    {
        return $this->state(['user_id' => $user->id]);
    }

    public function forAnswer(GroupSurveyAnswer $answer): static
    {
        return $this->state(['group_survey_answer_id' => $answer->id]);
    }
}
