<?php

namespace App\Http\Middleware;

use App\Models\Settings as ModelsSettings;
use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Config;
use Illuminate\Support\Facades\Session;

class SetLocale
{
    /**
     * Handle an incoming request.
     *
     * @param  \Illuminate\Http\Request  $request
     * @param  \Closure  $next
     * @return mixed
     */
    public function handle(Request $request, Closure $next)
    {
        // $language = 'en'; // default
        $language = Config::get('settings_default_language');   //default
        $languages = Config::get('available_languages');

        if (request('language')) {            
            $new_language = request('language');
            if(isset($languages[$new_language])) {                
                session()->put('language', $new_language);
                $language = $new_language;
            }
        } elseif (session('language')) {
            $language = session('language');
        }
        app()->setLocale($language);

        return $next($request);
    }
}