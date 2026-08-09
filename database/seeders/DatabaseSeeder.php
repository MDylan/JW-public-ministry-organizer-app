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
        // Csak az alap beállítássorokat töltjük be. A statikus oldalakat a
        // telepítő hívja külön (StaticPagesSetupSeeder), mert azoknak
        // tulajdonos felhasználóra van szükségük.
        $this->call([
            CoreSettingsSeeder::class,
        ]);
    }
}
