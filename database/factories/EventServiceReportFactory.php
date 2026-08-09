<?php

namespace Database\Factories;

use App\Models\Event;
use App\Models\EventServiceReport;
use App\Models\GroupLiterature;
use Illuminate\Database\Eloquent\Factories\Factory;

class EventServiceReportFactory extends Factory
{
    protected $model = EventServiceReport::class;

    public function definition()
    {
        return [
            'event_id' => Event::factory(),
            'group_literature_id' => GroupLiterature::factory(),
            'placements' => 0,
            'videos' => 0,
            'return_visits' => 0,
            'bible_studies' => 0,
            'note' => null,
        ];
    }

    public function withActivity(): static
    {
        return $this->state([
            'placements' => 3,
            'videos' => 1,
            'return_visits' => 2,
            'bible_studies' => 1,
        ]);
    }

    public function forEvent(Event $event): static
    {
        return $this->state(['event_id' => $event->id]);
    }
}
