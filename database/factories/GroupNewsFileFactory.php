<?php

namespace Database\Factories;

use App\Models\GroupNews;
use App\Models\GroupNewsFile;
use Illuminate\Database\Eloquent\Factories\Factory;

class GroupNewsFileFactory extends Factory
{
    protected $model = GroupNewsFile::class;

    public function definition()
    {
        $file = $this->faker->unique()->lexify('??????????').'.pdf';

        return [
            'group_new_id' => GroupNews::factory(),
            'name' => 'melleklet.pdf',
            // Just the stored filename; the test sets up the news_files
            // disk's content when the size/download behaviour also matters.
            'file' => $file,
        ];
    }

    public function forNews(GroupNews $news): static
    {
        return $this->state(['group_new_id' => $news->id]);
    }
}
