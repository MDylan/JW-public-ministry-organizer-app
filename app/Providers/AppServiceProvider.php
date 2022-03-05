<?php

namespace App\Providers;

use App\Models\Settings as ModelsSettings;
use App\Models\StaticPage;
use DebugBar\DebugBar;
use Illuminate\Contracts\View\View;
// use Illuminate\Support\Facades\App;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Config;
use Illuminate\Support\ServiceProvider;
// use Laravel\Fortify\Fortify;
use Illuminate\Pagination\Paginator;


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
            'gdpr' => false,
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
        // dd($defaults);
        // dd(Config::get('cookie-consent.enabled'));
        Config::set([
            'available_languages' => $available_languages,
            'translatable.fallback_locale' => $defaults['default_language'],
            'translatable.locales' => $locales, //array_keys($available_languages),
            'app.fallback_locale' => $defaults['default_language'],
            'cookie-consent.enabled' => $defaults['gdpr'],
            'gdpr.enabled' => $defaults['gdpr']
            // 'debugbar.enabled'  => isset($defaults['debugbar']) ? $defaults['debugbar'] : false
        ]);
        // dd(Config::get('cookie-consent.enabled'));
        // dd($locales);
        // dd(auth()->user()->id);
        if(isset($defaults['debugbar'])) {
            if($defaults['debugbar'] == 1)
                \Debugbar::enable();
        }
        // dd(Config::get('debugbar.enabled'));
        foreach($defaults as $key => $value) {
            Config::set(['settings_'.$key => $value]);
        }
        /**
         * Set Static menus into view
        */
        // View()->share('sidemenu', StaticPage::whereIn('status', (Auth::id() ? [0,1,3] : [1,2]))->get());

    }
}
