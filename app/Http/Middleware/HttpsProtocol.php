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
     * Igazra értékelődik "1", "true", "on" és "yes" esetén (kis- és nagybetűtől
     * függetlenül), minden másra hamisra - beleértve a hiányzó változót is.
     */
    private function httpsEnforced(): bool
    {
        return filter_var(env('USE_HTTPS', false), FILTER_VALIDATE_BOOLEAN);
    }
}
