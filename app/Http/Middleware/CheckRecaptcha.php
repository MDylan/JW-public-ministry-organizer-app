<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Lukeraymonddowning\Honey\Facades\Honey;

class CheckRecaptcha
{
    /**
     * Handle an incoming request.
     *
     * @param  \Illuminate\Http\Request  $request
     * @param  \Closure(\Illuminate\Http\Request): (\Illuminate\Http\Response|\Illuminate\Http\RedirectResponse)  $next
     * @return \Illuminate\Http\Response|\Illuminate\Http\RedirectResponse
     */
    public function handle(Request $request, Closure $next)
    {
        $check_needed = env('USE_RECAPTCHA', false);
        if($check_needed) {
            $token = request()->honey_recaptcha_token;
            $probablyABot = Honey::recaptcha()->checkToken($token)->isSpam();
        } else {
            $probablyABot = false;
        }
        if($probablyABot) {
            Honey::fail();
        } else {
            return $next($request);
        }
    }
}
