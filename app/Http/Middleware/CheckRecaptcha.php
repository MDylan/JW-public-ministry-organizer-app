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
     * Másodperc.
     *
     * Korábban semmilyen időkorlát nem volt a híváson, tehát egy beragadt
     * Google-végpont a PHP workert tartotta fogva - a bejelentkezési útvonalon,
     * ahol ez a leggyorsabban meríti ki a rendelkezésre álló processzeket.
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
        // A kapcsoló KORÁBBAN közvetlenül env()-ből jött, tehát egy
        // `php artisan config:cache` után hamisra váltott volna, és a
        // reCAPTCHA-ellenőrzés némán kikapcsol. A hat Blade nézet ugyanezt a
        // kapcsolót olvasta, ugyanúgy env()-ből, így a captcha mező sem került
        // volna ki - vagyis semmi nem jelezte volna, hogy a botvédelem eltűnt.
        $check_needed = config('security.use_recaptcha', false);
        if($check_needed) {
            try {
                $response = Http::asForm()
                    ->timeout(self::TIMEOUT)
                    ->post("https://www.google.com/recaptcha/api/siteverify", [
                        'secret' => config('services.recaptcha.secret_key'),
                        'response' => $request->recaptcha_token,
                        // A Google API `remoteip` néven várja a kliens címét.
                        // A mező KORÁBBAN `ip` volt, amit a végpont némán
                        // eldobott: a kérés attól még sikeres volt, csak a
                        // címellenőrzés nem történt meg soha.
                        'remoteip' => $request->ip(),
                    ]);
            } catch (ConnectionException $e) {
                // FAIL-OPEN, kimondott döntéssel.
                //
                // A hívás korábban try/catch NÉLKÜL futott. HTTP-hibakódra a
                // Laravel Response-t ad, azt a lenti ág helyesen kezeli, de
                // KAPCSOLATHIBÁRA (timeout, DNS, hálózat) ConnectionException
                // száll fel, amit senki nem kapott el. Ez a middleware a
                // POST /login, POST /register és POST /forgot-password
                // végpontokat őrzi, tehát egy Google-kimaradás mind a hármon
                // 500-at adott: senki nem tudott belépni, regisztrálni vagy
                // jelszót visszaállítani, amíg a Google vissza nem jött.
                //
                // A választás rendelkezésre állás kontra botvédelem. Itt a
                // rendelkezésre állás nyer: a kérés átmegy, a hiba naplózódik.
                // A fail-closed ugyanolyan védhető lenne (captcha-hibaüzenet az
                // 500 helyett), de a korábbi viselkedés egyik sem volt.
                //
                // A nyitva maradó ablakot mostantól route-throttle zárja: a
                // /login, /register és /forgot-password mindegyike visel saját
                // sebességkorlátot, tehát egy Google-kimaradás nem teszi
                // korlátlanul automatizálhatóvá ezeket a végpontokat.
                Log::warning('reCAPTCHA verification unreachable, letting the request through', [
                    'exception' => $e->getMessage(),
                    'ip'        => $request->ip(),
                    'path'      => $request->path(),
                ]);

                return $next($request);
            }

            // Az `action` szerveroldali ellenőrzését a Google kifejezetten
            // javasolja a v3 dokumentációjában. Nélküle egy MÁSIK űrlapon
            // (vagy egy másik oldalon, ugyanazzal a site key-jel) begyűjtött
            // token bármelyik itt védett végponton felhasználható. A kliens
            // KORÁBBAN mindhárom űrlapon `register` actiont kért, a szerver
            // pedig a visszakapott mezőt meg sem nézte.
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
