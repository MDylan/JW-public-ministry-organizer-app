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
        
        $available_languages = [];
        $default_language = config('app.locale');
        
        $settings = ModelsSettings::whereIn('name', ['languages', 'default_language'])->get();
        if(count($settings)) {
            foreach($settings as $setting) {
                if($setting->name == 'languages') {
                    $available_languages = json_decode($setting->value, true);
                }
                if($setting->name == 'default_language') {
                    $default_language = $setting->value;
                }
            }
        } 
        Config::set(['available_languages' => $available_languages]);
        Config::set(['default_language' => $default_language]);
    }
}
