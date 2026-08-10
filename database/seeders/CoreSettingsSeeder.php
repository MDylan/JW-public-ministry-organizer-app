<?php

namespace Database\Seeders;

use App\Models\Settings;
use Illuminate\Database\Seeder;

/**
 * The base Settings rows the application needs to function.
 *
 * AppServiceProvider::boot() populates the settings_* config keys from
 * these; without them the built-in defaults kick in, but the settings
 * admin UI shows an empty list.
 *
 * Idempotent: safe to run repeatedly, never overwrites an existing value.
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
            // v1-patch E: the group data retention period, in months. '0' is
            // the disabled state - a fresh install deletes nothing until an
            // administrator deliberately turns it on.
            'group_data_retention' => '0',
        ];

        foreach ($defaults as $name => $value) {
            Settings::firstOrCreate(['name' => $name], ['value' => $value]);
        }
    }
}
