<?php

namespace App\Http\Middleware;

// use App\Models\Settings as ModelsSettings;
use App\Models\StaticPage;
use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Config;
// use Illuminate\Support\Facades\Session;
use Illuminate\Support\Facades\View;
// use DebugBar\DebugBar;

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
        $user = Auth::user();
        if (request('language')) {            
            $new_language = request('language');
            if(isset($languages[$new_language])) {
                if($languages[$new_language]['visible'] == true) {
                    session()->put('language', $new_language);
                    $language = $new_language;    
                } else {
                    //only admins can see this language
                    if($user !== null) {
                        if($user->role == "mainAdmin") {
                            session()->put('language', $new_language);
                            $language = $new_language;
                        }
                    } 
                }
            }
        } elseif (session('language')) {
            $language = session('language');
        }
        app()->setLocale($language);

        //If maintenance mod active and user is not admin, logout and redirect
        if(Auth::check()) {
            if(Config::get('settings_maintenance') == 1 && $user->role !== "mainAdmin") {
                return redirect('login')->with(Auth::logout());
            }
        }
        
        // $debugbar = Config::get('settings_debugbar');
        
        // if(isset($debugbar)) {
        //     if($debugbar == 1 && Auth::user()->role == "mainAdmin") {
        //         \Debugbar::enable();
        //     }
        // }
        // \Debugbar::enable();

        //share menus content to views
        View::share('sidemenu', StaticPage::whereIn('status', (Auth::id() ? [0,1,3] : [1,2]))->get());

        return $next($request);
    }
}