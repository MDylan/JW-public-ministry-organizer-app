<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Session;

class ProfileFull
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
        $user = Auth::user();
        if($user->name === null 
        || $user->phone_number === null) {
            Session::flash('profile_message', __('user.profile_empty')); 
            return redirect()->route('user.profile');
        } else {
            return $next($request);
        }
    }
}
