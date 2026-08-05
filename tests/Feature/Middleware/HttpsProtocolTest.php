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
 *     !$request->secure() && app()->environment('production') && env('USE_HTTPS', "false") == "true"
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

    // =========================================================================
    // 1. Miért nem fut ma soha
    // =========================================================================

    public function test_the_redirect_is_skipped_outside_production_even_when_https_is_enabled(): void
    {
        // A tesztkörnyezet 'testing', a fejlesztői 'local' - a middleware
        // tehát csak élesben aktív. Ez az oka annak, hogy a teljes suite
        // futása alatt egyetlen egyszer sem irányít át.
        $response = $this->withEnvValue('USE_HTTPS', 'true', fn () => $this->runMiddleware($this->insecureRequest()));

        $this->assertSame('ok', $response->getContent());
    }

    public function test_the_redirect_is_skipped_in_production_when_https_is_disabled(): void
    {
        $response = $this->inEnvironment('production', fn () => $this->withEnvValue(
            'USE_HTTPS',
            'false',
            fn () => $this->runMiddleware($this->insecureRequest())
        ));

        $this->assertSame('ok', $response->getContent());
    }

    // =========================================================================
    // 2. A bekapcsolt állapot
    // =========================================================================

    public function test_an_insecure_production_request_is_redirected_to_https(): void
    {
        $response = $this->inEnvironment('production', fn () => $this->withEnvValue(
            'USE_HTTPS',
            'true',
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
        $response = $this->inEnvironment('production', fn () => $this->withEnvValue(
            'USE_HTTPS',
            'true',
            fn () => $this->runMiddleware(Request::create('https://kozter.test/csoportok'))
        ));

        $this->assertSame('ok', $response->getContent());
    }

    // =========================================================================
    // 3. A sztring-összehasonlítás csapdája
    // =========================================================================

    public function test_a_truthy_but_non_string_true_value_does_not_enable_the_redirect(): void
    {
        // KARAKTERIZÁLÓ TESZT. A feltétel env('USE_HTTPS', "false") == "true",
        // vagyis a KONKRÉT "true" sztringhez hasonlít. A .env-ben szokásos
        // USE_HTTPS=1 tehát NEM kapcsolja be az átirányítást, pedig a
        // szándék nyilvánvalóan az lenne.
        //
        // A Laravel env()-je a "true"/"false" sztringeket bool-lá alakítja,
        // az "1"-et viszont sztringként adja vissza - így az összehasonlítás
        // "1" == "true" alakot ölt, ami hamis.
        $response = $this->inEnvironment('production', fn () => $this->withEnvValue(
            'USE_HTTPS',
            '1',
            fn () => $this->runMiddleware($this->insecureRequest())
        ));

        $this->assertSame('ok', $response->getContent(), 'Az USE_HTTPS=1 hatástalan.');
    }

    public function test_the_default_is_off_when_the_variable_is_missing_entirely(): void
    {
        $response = $this->inEnvironment('production', fn () => $this->withEnvValue(
            'USE_HTTPS',
            null,
            fn () => $this->runMiddleware($this->insecureRequest())
        ));

        $this->assertSame('ok', $response->getContent());
    }

    // =========================================================================
    // 4. A futásidejű env() olvasás - TODO 28
    // =========================================================================

    public function test_the_flag_is_read_from_the_environment_on_every_request(): void
    {
        // A middleware nem konfigurációból, hanem közvetlenül env()-ből
        // olvas. Ez a teszt azt bizonyítja, hogy a viselkedés kéréstől
        // kérésre változik a környezeti változóval - vagyis egy
        // `php artisan config:cache` NEM fagyasztja be, viszont a TODO 28
        // config-ba mozgatása után ennek a tesztnek az alapja megváltozik.
        $request = $this->insecureRequest();

        $this->inEnvironment('production', function () use ($request) {
            $off = $this->withEnvValue('USE_HTTPS', 'false', fn () => $this->runMiddleware($request));
            $on = $this->withEnvValue('USE_HTTPS', 'true', fn () => $this->runMiddleware($request));

            $this->assertSame('ok', $off->getContent());
            $this->assertInstanceOf(RedirectResponse::class, $on);
        });
    }
}
