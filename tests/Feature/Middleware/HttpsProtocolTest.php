<?php

namespace Tests\Feature\Middleware;

use App\Http\Middleware\HttpsProtocol;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Tests\Feature\FeatureTestCase;

/**
 * TODO 09: a HttpsProtocol middleware.
 *
 * A web csoport tagja (Kernel.php:47), tehát MINDEN webes kérésen lefut -
 * a törzse viszont három feltétel mögött van, és a jelenlegi
 * konfigurációval egyik sem teljesül maradéktalanul:
 *
 *     !$request->secure() && app()->environment('production') && $this->httpsEnforced()
 *
 * A harmadik feltétel a v1-patch B14-ig `env('USE_HTTPS', "false") == "true"`
 * volt, azaz a literális "true" sztringhez hasonlított; a TODO 28 óta pedig
 * nem is env()-ből jön, hanem a config('security.use_https') kulcsból, ami a
 * szokásos igaz alakokat filter_var()-ral értelmezi.
 *
 * Ezért nulla lefedettsége van, és a bekapcsolt állapota ismeretlen terület.
 * A production környezet HTTP-tesztből nem állítható, ezért itt közvetlenül
 * a handle()-t hívjuk.
 */
class HttpsProtocolTest extends FeatureTestCase
{
    /**
     * A middleware lefuttatása egy adott kérésre; a $next egyszerű
     * "ok" választ ad, hogy az átengedés megkülönböztethető legyen az
     * átirányítástól.
     */
    private function runMiddleware(Request $request)
    {
        return (new HttpsProtocol())->handle($request, fn () => response('ok'));
    }

    /**
     * Az Application::environment() a konténer 'env' kötését olvassa, tehát
     * a production környezet így szimulálható. A visszaállítás finally-ben.
     */
    private function inEnvironment(string $environment, callable $callback)
    {
        $original = $this->app['env'];
        $this->app['env'] = $environment;

        try {
            return $callback();
        } finally {
            $this->app['env'] = $original;
        }
    }

    private function insecureRequest(): Request
    {
        return Request::create('http://kozter.test/csoportok?szures=aktiv');
    }

    /**
     * A HTTPS-kapcsoló beállítása a hívás idejére.
     *
     * A TODO 28 előtt ez a $_SERVER tömböt írta (withEnvValue), mert a
     * middleware futásidőben olvasott env()-et. Most a konfiguráció dönt.
     */
    private function withHttps(bool $enabled, callable $callback)
    {
        $original = config('security.use_https');
        config(['security.use_https' => $enabled]);

        try {
            return $callback();
        } finally {
            config(['security.use_https' => $original]);
        }
    }

    // =========================================================================
    // 1. Miért nem fut ma soha
    // =========================================================================

    public function test_the_redirect_is_skipped_outside_production_even_when_https_is_enabled(): void
    {
        // A tesztkörnyezet 'testing', a fejlesztői 'local' - a middleware
        // tehát csak élesben aktív. Ez az oka annak, hogy a teljes suite
        // futása alatt egyetlen egyszer sem irányít át.
        $response = $this->withHttps(true, fn () => $this->runMiddleware($this->insecureRequest()));

        $this->assertSame('ok', $response->getContent());
    }

    public function test_the_redirect_is_skipped_in_production_when_https_is_disabled(): void
    {
        $response = $this->inEnvironment('production', fn () => $this->withHttps(
            false,
            fn () => $this->runMiddleware($this->insecureRequest())
        ));

        $this->assertSame('ok', $response->getContent());
    }

    // =========================================================================
    // 2. A bekapcsolt állapot
    // =========================================================================

    public function test_an_insecure_production_request_is_redirected_to_https(): void
    {
        $response = $this->inEnvironment('production', fn () => $this->withHttps(
            true,
            fn () => $this->runMiddleware($this->insecureRequest())
        ));

        $this->assertInstanceOf(RedirectResponse::class, $response);
        $this->assertSame(302, $response->getStatusCode());
        $this->assertStringStartsWith('https://', $response->getTargetUrl());
        $this->assertStringContainsString('/csoportok', $response->getTargetUrl());
        $this->assertStringContainsString('szures=aktiv', $response->getTargetUrl(), 'A query string nem veszhet el.');
    }

    public function test_an_already_secure_production_request_passes_through(): void
    {
        $response = $this->inEnvironment('production', fn () => $this->withHttps(
            true,
            fn () => $this->runMiddleware(Request::create('https://kozter.test/csoportok'))
        ));

        $this->assertSame('ok', $response->getContent());
    }

    // =========================================================================
    // 3. A sztring-összehasonlítás csapdája
    // =========================================================================

    /**
     * @dataProvider truthyFlagProvider
     */
    public function test_every_common_truthy_spelling_enables_the_redirect(string $value): void
    {
        // MEGFORDÍTVA előbb a v1-patch B14, majd a TODO 28 javításával.
        //
        // A feltétel `env('USE_HTTPS', "false") == "true"` volt, tehát a
        // KONKRÉT "true" sztringhez hasonlított. A Laravel env()-je a
        // "true"/"false" szavakat bool-lá alakítja, az "1"-et viszont
        // sztringként adja vissza - így az összehasonlítás "1" == "true"
        // alakot öltött, ami hamis. A .env-ben legszokásosabb USE_HTTPS=1
        // ezért hatástalan volt, és a HTTPS-kényszerítés némán nem működött.
        //
        // Az értelmezés a TODO 28 óta a config/security.php dolga, ezért a
        // vizsgálat is oda költözött: a fájlt a változó beállított értékével
        // töltjük be, és a belőle kijövő bool-t nézzük. A middleware maga
        // ugyanezt a bool-t kapja - lásd a 2. szakasz eseteit.
        $security = $this->withEnvValue('USE_HTTPS', $value, fn () => require config_path('security.php'));

        $this->assertTrue($security['use_https'], $value.': be kell kapcsolnia.');

        // És a bekapcsolt kulcs tényleg átirányít.
        $response = $this->inEnvironment('production', fn () => $this->withHttps(
            $security['use_https'],
            fn () => $this->runMiddleware($this->insecureRequest())
        ));

        $this->assertSame(302, $response->getStatusCode(), $value.': át kell irányítani.');
        $this->assertStringStartsWith('https://', $response->headers->get('Location'));
    }

    public static function truthyFlagProvider(): array
    {
        return [
            'true'  => ['true'],
            'one'   => ['1'],
            'on'    => ['on'],
            'yes'   => ['yes'],
            'TRUE'  => ['TRUE'],
        ];
    }

    /**
     * @dataProvider falsyFlagProvider
     */
    public function test_no_other_value_enables_the_redirect(string $value): void
    {
        // A B14 kontroll-kísérlete: a lazább értelmezés nem kapcsolhatja be a
        // kényszerítést ott, ahol senki nem kérte.
        $security = $this->withEnvValue('USE_HTTPS', $value, fn () => require config_path('security.php'));

        $this->assertFalse($security['use_https'], $value.': nem szabad bekapcsolnia.');

        $response = $this->inEnvironment('production', fn () => $this->withHttps(
            $security['use_https'],
            fn () => $this->runMiddleware($this->insecureRequest())
        ));

        $this->assertSame('ok', $response->getContent(), $value.': nem szabad átirányítani.');
    }

    public static function falsyFlagProvider(): array
    {
        return [
            'false'    => ['false'],
            'zero'     => ['0'],
            'off'      => ['off'],
            'no'       => ['no'],
            'empty'    => [''],
            'nonsense' => ['talan'],
        ];
    }

    public function test_the_default_is_off_when_the_variable_is_missing_entirely(): void
    {
        $security = $this->withEnvValue('USE_HTTPS', null, fn () => require config_path('security.php'));

        $this->assertFalse($security['use_https']);
    }

    // =========================================================================
    // 4. A kapcsoló forrása - TODO 28
    // =========================================================================

    public function test_the_flag_comes_from_configuration_not_from_the_environment(): void
    {
        // MEGFORDÍTVA a v1-patch TODO 28 javításával.
        //
        // Korábban ugyanaz a kérés két különböző $_SERVER['USE_HTTPS']
        // értékkel két különböző eredményt adott: a middleware közvetlenül a
        // környezetből olvasott. Egy `php artisan config:cache` után viszont a
        // Laravel be sem tölti a .env-et, az env() null-t ad - a HTTPS-re
        // kényszerítés tehát némán kikapcsolt volna, méghozzá pontosan azon a
        // telepítésen, amelyik elég gondos ahhoz, hogy gyorsítótárazza a
        // konfigurációt. Semmi nem jelezte volna.
        //
        // Most a környezeti változó önmagában nem mozdít semmit; a
        // konfiguráció dönt, az pedig gyorsítótárazható.
        $request = $this->insecureRequest();

        $this->inEnvironment('production', function () use ($request) {
            $envOnly = $this->withHttps(
                false,
                fn () => $this->withEnvValue('USE_HTTPS', 'true', fn () => $this->runMiddleware($request))
            );

            $this->assertSame(
                'ok',
                $envOnly->getContent(),
                'A környezeti változó már nem kapcsolhatja be az átirányítást a konfiguráció mögött.'
            );

            $configured = $this->withHttps(
                true,
                fn () => $this->withEnvValue('USE_HTTPS', 'false', fn () => $this->runMiddleware($request))
            );

            $this->assertInstanceOf(RedirectResponse::class, $configured);
        });
    }
}
