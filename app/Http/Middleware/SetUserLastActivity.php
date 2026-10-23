<?php

namespace App\Http\Middleware;

use App\Models\User;
use Closure;
use Illuminate\Http\Request;
use Carbon\Carbon;
use Illuminate\Support\Facades\Auth;

class SetUserLastActivity
{
    /**
     * Handle an incoming request.
     * This is update last_activity field for users
     *
     * @param  \Illuminate\Http\Request  $request
     * @param  \Closure  $next
     * @return mixed
     */
    public function handle(Request $request, Closure $next)
    {
        if (Auth::check()) {
            // `true` asks for the absolute difference. Carbon 2 returns it by
            // default; Carbon 3 (Laravel 11) returns a SIGNED one, which is
            // negative for a past timestamp, so without it the condition never
            // holds and last_activity stops updating (roadmap TODO 55.1).
            if(Carbon::now()->diffInSeconds(auth()->user()->last_activity, true) >= 60 || auth()->user()->last_activity == null) {
                //we want to keep low system resources so update if it's needed
                //Last Seen
                User::where('id', Auth::user()->id)->update(['last_activity' => Carbon::now()]);
            }
        }
        return $next($request);
    }
}
