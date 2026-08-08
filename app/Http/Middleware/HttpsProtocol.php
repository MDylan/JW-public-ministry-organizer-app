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
        // A feltétel korábban `env('USE_HTTPS', "false") == "true"` volt, tehát
        // a LITERÁLIS "true" sztringhez hasonlított. A Laravel env()-je a
        // "true"/"false" szavakat bool-lá alakítja, az "1"-et viszont
        // sztringként adja vissza - a .env-ben szokásos USE_HTTPS=1 ezért NEM
        // kapcsolta be az átirányítást, holott a szándék nyilvánvaló.
        //
        // A filter_var() a "1", "true", "on" és "yes" alakokat egységesen
        // kezeli, ahogy a Laravel saját konfigurációs bool-jait is.
        if (!$request->secure() && app()->environment('production') && $this->httpsEnforced()) {
            return redirect()->secure($request->getRequestUri());
        }

        return $next($request);
    }

    /**
     * Be van-e kapcsolva a HTTPS-kényszerítés.
     *
     * A kapcsoló KORÁBBAN közvetlenül env()-ből jött. A Laravel a .env fájlt
     * csak akkor tölti be, ha nincs gyorsítótárazott konfiguráció, tehát egy
     * `php artisan config:cache` után az env('USE_HTTPS') null lett volna, és a
     * HTTPS-kényszerítés némán kikapcsol - pontosan azon a telepítésen, amelyik
     * elég gondos volt ahhoz, hogy gyorsítótárazza a konfigurációt.
     *
     * A config/security.php ugyanazt a filter_var() értelmezést végzi, tehát a
     * "1", "true", "on" és "yes" alakok továbbra is igazak.
     */
    private function httpsEnforced(): bool
    {
        return (bool) config('security.use_https', false);
    }
}
