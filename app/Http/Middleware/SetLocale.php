<?php

namespace App\Http\Middleware;

use App\Models\StaticPage;
use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Config;
use Illuminate\Support\Facades\View;
use Illuminate\Support\Facades\Cache;

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
        $language = Config::get('settings_default_language');   //default
        $languages = Config::get('available_languages');
        $user = Auth::user();
        if (request('lang')) {      
            $new_language = request('lang');
            if(isset($languages[$new_language])) {
                if($languages[$new_language]['visible'] == true) {
                    session()->put('language', $new_language);
                    $language = $new_language;
                    if($user !== null) {
                        //save new language
                        $user->language = $language;
                        $user->save();
                    }
                } else {
                    //only admins can see this language
                    if($user !== null) {
                        if(in_array($user->role, ["mainAdmin", "translator"])) {
                            session()->put('language', $new_language);
                            $language = $new_language;
                            //save new language
                            $user->language = $new_language;
                            $user->save();
                        }
                    } 
                }
            }
        } elseif (session('language')) {
            $language = session('language');
        }
        if($language) {
            app()->setLocale($language);
        }

        //If maintenance mod active and user is not admin, logout and redirect
        if(Auth::check()) {
            if(Config::get('settings_maintenance') == 1 && $user->role !== "mainAdmin") {
                return redirect('login')->with(Auth::logout());
            }
        }
        
        try {
            //share menus content to views
            if(Auth()->check()) {
                $staticpages = Cache::rememberForever('sidemenu_auth', function () {
                    return StaticPage::whereIn('status', [0,1,3])->get();
                });
            } else {
                $staticpages = Cache::rememberForever('sidemenu_guest', function () {
                    return StaticPage::whereIn('status', [1,2])->get();
                });
            }
            View::share('sidemenu', $staticpages);
        } catch (\Throwable $th) {
            //throw $th;
        }


        return $next($request);
    }
}