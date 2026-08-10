<?php

namespace App\Http\Middleware;

use App\Models\StaticPage;
use App\Support\Settings\ApplicationSettings;
use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Config;
use Illuminate\Support\Facades\Log;
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
        //
        // TODO 31: the maintenance switch is read from ApplicationSettings, not
        // from the config('settings_maintenance') key. In production the two
        // values are the same - the Config is filled by this very class during
        // boot - the difference is that this read is LAZY: it sees the setting
        // as of this moment, not as of boot. That is what made the mode
        // switchable at runtime, and what let the Config::set() workaround
        // disappear from the TODO 09 tests.
        if(Auth::check()) {
            if(app(ApplicationSettings::class)->get('maintenance') == 1 && $user->role !== "mainAdmin") {
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
            // TODO 31: this block used to be empty, and it caused two failures
            // at once. First, the original exception vanished without a trace.
            // Second, View::share never ran either - while FOUR Blade files
            // @foreach over the $sidemenu variable (components/footer,
            // components/side-static-pages, layouts/partials/footer,
            // public.blade.php), so a second, misleading error showed up in
            // place of the real one. Hence the log, and hence the empty
            // collection: the menu disappears, the page survives.
            Log::error('Loading the side menu failed, continuing with an empty menu.', [
                'exception' => $th,
            ]);

            View::share('sidemenu', collect());
        }


        return $next($request);
    }
}