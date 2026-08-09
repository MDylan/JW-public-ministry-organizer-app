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
            // Csak a tárolt fájlnév; a news_files disk tartalmát a teszt
            // állítja elő, ha a méret/letöltés viselkedése is számít.
            'file' => $file,
        ];
    }

    public function forNews(GroupNews $news): static
    {
        return $this->state(['group_new_id' => $news->id]);
    }
}
