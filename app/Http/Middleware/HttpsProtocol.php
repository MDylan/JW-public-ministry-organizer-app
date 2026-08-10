<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;

class HttpsProtocol
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
        // The condition used to be `env('USE_HTTPS', "false") == "true"`, i.e.
        // it compared against the LITERAL string "true". Laravel's env()
        // converts the words "true"/"false" to bool, but returns "1" as a
        // string - so the USE_HTTPS=1 commonly used in .env did NOT turn on
        // the redirect, even though the intent was obvious.
        //
        // filter_var() handles the "1", "true", "on" and "yes" forms
        // consistently, the same way Laravel treats its own config booleans.
        if (!$request->secure() && app()->environment('production') && $this->httpsEnforced()) {
            return redirect()->secure($request->getRequestUri());
        }

        return $next($request);
    }

    /**
     * Whether HTTPS enforcement is switched on.
     *
     * The switch USED TO come straight from env(). Laravel only loads the
     * .env file when there's no cached config, so after a
     * `php artisan config:cache`, env('USE_HTTPS') would have been null, and
     * HTTPS enforcement would silently switch off - on exactly the
     * deployment that was careful enough to cache its config.
     *
     * config/security.php performs the same filter_var() interpretation, so
     * the "1", "true", "on" and "yes" forms remain true.
     */
    private function httpsEnforced(): bool
    {
        return (bool) config('security.use_https', false);
    }
}
