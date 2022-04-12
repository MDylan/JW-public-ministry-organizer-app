<?php

namespace App\Providers;

use App\Models\Settings as ModelsSettings;
use Illuminate\Support\Facades\Config;
use Illuminate\Support\ServiceProvider;


class AppServiceProvider extends ServiceProvider
{

    /**
     * Register any application services.
     *
     * @return void
     */
    public function register()
    {
        //
    }

    /**
     * Bootstrap any application services.
     *
     * @return void
     */
    public function boot()
    {
        /**
         * Set Language settings
         */
        
        $default_language = config('app.locale');
        $available_languages = [$default_language => [
            'name' => $default_language,
            'visible' => true
        ]];
        $defaults = [
            'registration'  => true,
            'claim_group_creator' => true,
            'default_language' => $default_language,
        ];
        
        $settings = ModelsSettings::all();
        $langs = [];
        if(count($settings)) {
            foreach($settings as $setting) {
                if($setting->name == 'languages') {
                    $langs = json_decode($setting->value, true);
                    $available_languages = $langs;
                } else {
                    $defaults[$setting->name] = $setting->value;
                }
            }
        } 
        $locales = array($default_language => $default_language);
        if(count($langs)) {
            foreach($langs as $lang => $value) {
                $locales[] = $lang;
            }   
        }
        //overwrite default config values from database
        Config::set([
            'available_languages' => $available_languages,
            'translatable.fallback_locale' => $defaults['default_language'],
            'translatable.locales' => $locales,
            'app.fallback_locale' => $defaults['default_language'],
        ]);

        if(isset($defaults['debugbar'])) {
            if($defaults['debugbar'] == 1)
                \Debugbar::enable();
        }

        foreach($defaults as $key => $value) {
            Config::set(['settings_'.$key => $value]);
        }
    }
}
