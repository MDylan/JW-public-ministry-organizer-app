<?php

namespace App\Providers;

use App\Models\Settings as ModelsSettings;
use Illuminate\Support\Facades\App;
use Illuminate\Support\Facades\Config;
use Illuminate\Support\ServiceProvider;
use Laravel\Fortify\Fortify;

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
        
        
        $default_language = config('app.locale');
        $available_languages = [$default_language => $default_language];
        $defaults = [
            'registration'  => true,
            'claim_group_creator' => true,
            'default_language' => $default_language
        ];
        
        $settings = ModelsSettings::all();
        if(count($settings)) {
            foreach($settings as $setting) {
                if($setting->name == 'languages') {
                    $available_languages = json_decode($setting->value, true);
                } else {
                    $defaults[$setting->name] = $setting->value;
                }
                // if($setting->name == 'default_language') {
                //     $default_language = $setting->value;
                // }
            }
        } 
        //overwrite default config values from database
        Config::set([
            'available_languages' => $available_languages,
            'translatable.fallback_locale' => $defaults['default_language'],
            'translatable.locales' => array_keys($available_languages),
        ]);
        foreach($defaults as $key => $value) {
            Config::set(['settings_'.$key => $value]);
        }
        // if(!$defaults['registration']) {
        //     Fortify::routes(['register' => false]);
        // }
    }
}
