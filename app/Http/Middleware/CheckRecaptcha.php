<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Http;

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
            $response = Http::asForm()->post("https://www.google.com/recaptcha/api/siteverify", [
                'secret' => config('services.recaptcha.secret_key'),
                'response' => $request->recaptcha_token,
                'ip' => request()->ip(),
            ]);

            if ($response->successful() && $response->json('success') && $response->json('score') > config('services.recaptcha.min_score')) {
                $probablyABot = false;
            } else {
                $probablyABot = true;
            }
        } else {
            $probablyABot = false;
        }
        if($probablyABot) {
            return back()->with('status', __('user.captcha_error'));
        } else {
            return $next($request);
        }
    }
}
