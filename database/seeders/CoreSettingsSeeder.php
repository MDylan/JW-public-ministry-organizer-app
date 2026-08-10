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
            // TODO 33.3: the shape matters. Every consumer of this blob -
            // the language switcher, the group form, the translation editor -
            // reads $value['visible'], and reaching that on a bare string is a
            // TypeError on PHP 8. The installer (Setup\MailController) always
            // wrote the object form; this seeder did not, so `db:seed` on a
            // fresh database produced a blob that would fatal on render.
            'languages' => json_encode([
                $defaultLanguage => ['name' => $defaultLanguage, 'visible' => true],
            ]),
            'show_homepage_alert' => '0',
            'homepage_message' => '',
            'weather' => '0',
            'terms_checkbox' => '0',
            'maintenance' => '0',
            // v1-patch E: a csoportadatok megőrzési ideje hónapban. A '0' a
            // kikapcsolt állapot - egy friss telepítés semmit nem töröl,
            // amíg az adminisztrátor tudatosan be nem kapcsolja.
            'group_data_retention' => '0',
        ];

        foreach ($defaults as $name => $value) {
            Settings::firstOrCreate(['name' => $name], ['value' => $value]);
        }
    }
}
