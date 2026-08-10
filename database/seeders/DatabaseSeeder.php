<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;

class DatabaseSeeder extends Seeder
{
    /**
     * Seed the application's database.
     *
     * @return void
     */
    public function run()
    {
        // Only the base settings rows are seeded here. The static pages are
        // called separately by the installer (StaticPagesSetupSeeder),
        // because those need an owner user.
        $this->call([
            CoreSettingsSeeder::class,
        ]);
    }
}
