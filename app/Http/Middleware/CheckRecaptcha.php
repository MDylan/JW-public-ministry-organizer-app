<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class CheckRecaptcha
{
    /**
     * Seconds.
     *
     * There used to be no timeout at all on the call, so a stuck Google
     * endpoint would hold the PHP worker hostage - on the login route, where
     * this exhausts the available processes the fastest.
     */
    private const TIMEOUT = 5;

    /**
     * Handle an incoming request.
     *
     * @param  \Illuminate\Http\Request  $request
     * @param  \Closure(\Illuminate\Http\Request): (\Illuminate\Http\Response|\Illuminate\Http\RedirectResponse)  $next
     * @return \Illuminate\Http\Response|\Illuminate\Http\RedirectResponse
     */
    public function handle(Request $request, Closure $next, ?string $expectedAction = null)
    {
        // The switch USED TO come straight from env(), so after a
        // `php artisan config:cache` it would have flipped to false, and
        // reCAPTCHA checking would silently switch off. The six Blade views
        // read the same switch, likewise from env(), so the captcha field
        // wouldn't have been rendered either - meaning nothing would have
        // signaled that bot protection was gone.
        $check_needed = config('security.use_recaptcha', false);
        if($check_needed) {
            try {
                $response = Http::asForm()
                    ->timeout(self::TIMEOUT)
                    ->post("https://www.google.com/recaptcha/api/siteverify", [
                        'secret' => config('services.recaptcha.secret_key'),
                        'response' => $request->recaptcha_token,
                        // The Google API expects the client's address under the
                        // name `remoteip`. The field USED TO be `ip`, which the
                        // endpoint silently dropped: the request still
                        // succeeded, only the address check never happened.
                        'remoteip' => $request->ip(),
                    ]);
            } catch (ConnectionException $e) {
                // FAIL-OPEN, as a deliberate decision.
                //
                // The call used to run WITHOUT a try/catch. On an HTTP error
                // code Laravel returns a Response, which the branch below
                // handles correctly, but on a CONNECTION ERROR (timeout, DNS,
                // network) a ConnectionException is thrown, which nobody
                // caught. This middleware guards the POST /login,
                // POST /register and POST /forgot-password endpoints, so a
                // Google outage returned 500 on all three: nobody could log
                // in, register, or reset their password until Google came
                // back.
                //
                // The choice is availability versus bot protection. Here
                // availability wins: the request goes through, the error is
                // logged. Fail-closed would be just as defensible (a captcha
                // error message instead of the 500), but the previous
                // behavior was neither.
                //
                // The window this leaves open is now closed by route
                // throttling: /login, /register and /forgot-password each
                // carry their own rate limit, so a Google outage doesn't make
                // these endpoints freely automatable.
                Log::warning('reCAPTCHA verification unreachable, letting the request through', [
                    'exception' => $e->getMessage(),
                    'ip'        => $request->ip(),
                    'path'      => $request->path(),
                ]);

                return $next($request);
            }

            // Google explicitly recommends server-side verification of
            // `action` in its v3 documentation. Without it, a token collected
            // on ANOTHER form (or another page, with the same site key) can
            // be used on any endpoint protected here. The client USED TO
            // request the `register` action on all three forms, and the
            // server never even looked at the field it got back.
            $actionMatches = $expectedAction === null
                || $response->json('action') === $expectedAction;

            if ($response->successful()
                && $response->json('success')
                && $actionMatches
                && $response->json('score') > config('services.recaptcha.min_score')) {
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
