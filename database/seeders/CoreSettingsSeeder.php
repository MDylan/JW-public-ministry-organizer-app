<?php

namespace Database\Seeders;

use App\Models\Settings;
use Illuminate\Database\Seeder;

/**
 * Az alkalmazás működéséhez szükséges alap Settings sorok.
 *
 * Az AppServiceProvider::boot() ezekből tölti fel a settings_* config
 * kulcsokat; hiányukban a beépített alapértékek lépnek életbe, de a
 * beállítás-kezelő admin felület üres listát mutat.
 *
 * Idempotens: többször is futtatható, meglévő értéket nem ír felül.
 */
class CoreSettingsSeeder extends Seeder
{
    public function run()
    {
        $defaultLanguage = config('app.locale', 'hu');

        $defaults = [
            'registration' => '1',
            'claim_group_creator' => '1',
            'default_language' => $defaultLanguage,
            'languages' => json_encode([$defaultLanguage => $defaultLanguage]),
            'show_homepage_alert' => '0',
            'homepage_message' => '',
            'weather' => '0',
            'terms_checkbox' => '0',
            'maintenance' => '0',
        ];

        foreach ($defaults as $name => $value) {
            Settings::firstOrCreate(['name' => $name], ['value' => $value]);
        }
    }
}
