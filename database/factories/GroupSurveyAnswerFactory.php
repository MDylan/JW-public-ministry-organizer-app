<?php

namespace Database\Factories;

use App\Models\GroupSurvey;
use App\Models\GroupSurveyAnswer;
use Illuminate\Database\Eloquent\Factories\Factory;

class GroupSurveyAnswerFactory extends Factory
{
    protected $model = GroupSurveyAnswer::class;

    public function definition()
    {
        return [
            'group_survey_id' => GroupSurvey::factory(),
            'answer' => $this->faker->words(3, true),
        ];
    }

    public function forSurvey(GroupSurvey $survey): static
    {
        return $this->state(['group_survey_id' => $survey->id]);
    }
}
