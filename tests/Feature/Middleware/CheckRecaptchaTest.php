<?php

namespace Tests\Feature\Middleware;

use App\Http\Middleware\CheckRecaptcha;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Tests\Feature\FeatureTestCase;

/**
 * TODO 09: a CheckRecaptcha middleware.
 *
 * Route middleware, és három publikus végponton ül (routes/fortify.php):
 * POST /login, POST /register, POST /forgot-password. Vagyis a bejelentkezés
 * és a regisztráció áll vagy bukik rajta.
 *
 * Ma soha nem fut le érdemben, mert a phpunit.xml és a .env.testing egyaránt
 * USE_RECAPTCHA=false értéket ad - a bekapcsolt állapot tehát teljesen
 * lefedetlen.
 *
 * A middleware a v1-patch TODO 28-ig FUTÁSIDŐBEN olvasott env()-et, ezért a
 * bekapcsoláshoz a $_SERVER tömböt kellett írni. Most a
 * config('security.use_recaptcha') kulcsot olvassa, tehát a tesztek is azt
 * állítják - és ez az igazi különbség: a régi olvasás egy config:cache után
 * NÉMÁN hamisra váltott volna, vagyis a botvédelem eltűnik anélkül, hogy
 * bármi jelezné.
 *
 * A Http::fake() ebben a suite-ban itt jelenik meg először.
 */
class CheckRecaptchaTest extends FeatureTestCase
{
    private const MIN_SCORE = 0.5;

    protected function setUp(): void
    {
        parent::setUp();

        config(['services.recaptcha.secret_key' => 'teszt-titkos-kulcs']);
        config(['services.recaptcha.min_score' => self::MIN_SCORE]);
    }

    private function runMiddleware(?string $token = 'teszt-token', ?string $expectedAction = null)
    {
        $request = Request::create('/login', 'POST', ['recaptcha_token' => $token]);

        return (new CheckRecaptcha())->handle($request, fn () => response('atengedve'), $expectedAction);
    }

    /** Bekapcsolt recaptcha mellett futtatja a middleware-t. */
    private function runEnabled(?string $token = 'teszt-token', ?string $expectedAction = null)
    {
        config(['security.use_recaptcha' => true]);

        return $this->runMiddleware($token, $expectedAction);
    }

    private function fakeGoogle(array $body, int $status = 200): void
    {
        Http::fake([
            'www.google.com/recaptcha/*' => Http::response($body, $status),
        ]);
    }

    // =========================================================================
    // 1. A kikapcsolt alapállapot
    // =========================================================================

    public function test_the_request_passes_through_without_any_network_call_when_disabled(): void
    {
        Http::fake();

        config(['security.use_recaptcha' => false]);

        $response = $this->runMiddleware();

        $this->assertSame('atengedve', $response->getContent());
        Http::assertNothingSent();
    }

    public function test_the_login_endpoint_does_not_call_out_with_the_current_configuration(): void
    {
        // Valódi HTTP-kérés a tényleges bekötés igazolására: a fortify
        // login route-ján rajta van a checkRecaptcha, de a kikapcsolt
        // állapotban nem keletkezik hálózati forgalom.
        Http::fake();

        $this->post(route('login'), [
            'email'    => 'nincs-ilyen@example.test',
            'password' => 'rossz-jelszo',
        ]);

        Http::assertNothingSent();
    }

    // =========================================================================
    // 2. A bekapcsolt állapot - sikeres ellenőrzés
    // =========================================================================

    public function test_a_high_score_lets_the_request_through(): void
    {
        $this->fakeGoogle(['success' => true, 'score' => 0.9]);

        $response = $this->runEnabled();

        $this->assertSame('atengedve', $response->getContent());

        Http::assertSent(function ($request) {
            return $request->url() === 'https://www.google.com/recaptcha/api/siteverify'
                && $request['secret'] === 'teszt-titkos-kulcs'
                && $request['response'] === 'teszt-token';
        });
    }

    // =========================================================================
    // 3. A bekapcsolt állapot - elutasítás
    // =========================================================================

    public function test_a_low_score_is_treated_as_a_bot(): void
    {
        $this->fakeGoogle(['success' => true, 'score' => 0.1]);

        $response = $this->runEnabled();

        $this->assertInstanceOf(RedirectResponse::class, $response);
        $this->assertSame(
            __('user.captcha_error'),
            $response->getSession()->get('status')
        );
    }

    public function test_a_score_exactly_at_the_threshold_is_treated_as_a_bot(): void
    {
        // A feltétel szigorú: score > min_score (:28). A küszöbbel PONTOSAN
        // egyenlő pontszám tehát elbukik - a konfigurációs érték nem
        // "megengedett minimum", hanem "e fölött".
        $this->fakeGoogle(['success' => true, 'score' => self::MIN_SCORE]);

        $this->assertInstanceOf(RedirectResponse::class, $this->runEnabled());
    }

    public function test_an_unsuccessful_verification_is_treated_as_a_bot(): void
    {
        $this->fakeGoogle(['success' => false, 'error-codes' => ['invalid-input-response']]);

        $this->assertInstanceOf(RedirectResponse::class, $this->runEnabled());
    }

    public function test_a_server_error_from_google_is_treated_as_a_bot(): void
    {
        // A successful() csak 2xx-re igaz, tehát a Google 500-asa
        // elutasításhoz vezet - a felhasználó nem tud belépni.
        $this->fakeGoogle(['message' => 'internal error'], 500);

        $this->assertInstanceOf(RedirectResponse::class, $this->runEnabled());
    }

    public function test_a_missing_token_is_treated_as_a_bot(): void
    {
        $this->fakeGoogle(['success' => false]);

        $this->assertInstanceOf(RedirectResponse::class, $this->runEnabled(null));
    }

    // =========================================================================
    // 4. A kapcsolathiba - látens hiba
    // =========================================================================

    public function test_a_connection_failure_lets_the_request_through(): void
    {
        // MEGFORDÍTVA a v1-patch D3 javításával, a felhasználó jóváhagyásával.
        //
        // A Http::asForm()->post() try/catch NÉLKÜL futott. HTTP-hibakódra a
        // Laravel Response-t ad - azt a kód helyesen kezeli (lásd a fenti
        // 500-as tesztet) -, KAPCSOLATHIBÁRA (timeout, DNS, hálózat) viszont
        // ConnectionException száll fel, amit senki nem kapott el.
        //
        // Következmény: egy Google-kimaradás 500-at adott a POST /login, a
        // POST /register és a POST /forgot-password végponton - senki nem
        // tudott belépni, regisztrálni vagy jelszót visszaállítani, amíg a
        // Google vissza nem jött. Ma alszik a USE_RECAPTCHA=false miatt, de a
        // recaptcha bekapcsolása előtt ez blokkoló hiba lett volna.
        //
        // A választott politika FAIL-OPEN: rendelkezésre állás a botvédelem
        // előtt. A kérés átmegy, a hiba naplózódik. A fail-closed ugyanennyire
        // védhető lett volna (captcha-hibaüzenet az 500 helyett); a korábbi
        // viselkedés egyik sem volt.
        Http::fake(function () {
            throw new ConnectionException('cURL error 28: Operation timed out');
        });

        Log::spy();

        $response = $this->runEnabled();

        $this->assertSame('atengedve', $response->getContent(), 'A kérés átmegy.');

        Log::shouldHaveReceived('warning')
            ->once()
            ->withArgs(fn (string $message) => str_contains($message, 'reCAPTCHA'));
    }

    public function test_the_verification_call_carries_an_explicit_timeout(): void
    {
        // A híváson korábban SEMMILYEN időkorlát nem volt, tehát egy beragadt
        // Google-végpont a PHP workert tartotta fogva - épp a bejelentkezési
        // útvonalon, ahol ez meríti ki leggyorsabban a processzeket.
        $reflection = new \ReflectionClass(CheckRecaptcha::class);

        $this->assertTrue(
            $reflection->hasConstant('TIMEOUT'),
            'A middleware-nek explicit időkorláttal kell hívnia.'
        );
        $this->assertGreaterThan(0, $reflection->getConstant('TIMEOUT'));
    }

    // =========================================================================
    // 5. A kapcsoló forrása - TODO 28
    // =========================================================================

    public function test_the_flag_comes_from_configuration_not_from_the_environment(): void
    {
        // MEGFORDÍTVA a v1-patch TODO 28 javításával.
        //
        // Korábban ugyanaz a kérés két különböző $_SERVER['USE_RECAPTCHA']
        // értékkel két különböző eredményt adott - vagyis a middleware
        // közvetlenül a környezetből olvasott. Pontosan ezt fagyasztotta volna
        // be egy `php artisan config:cache`: a .env olyankor be sem töltődik,
        // az env() null-t ad, és a botvédelem némán kikapcsol.
        //
        // Most a környezeti változó önmagában semmit nem mozdít; a
        // konfiguráció dönt, az pedig gyorsítótárazható.
        $this->fakeGoogle(['success' => true, 'score' => 0.1]);

        config(['security.use_recaptcha' => false]);
        $envSaysYes = $this->withEnvValue('USE_RECAPTCHA', 'true', fn () => $this->runMiddleware());

        $this->assertSame(
            'atengedve',
            $envSaysYes->getContent(),
            'A környezeti változó már nem kapcsolhatja be a middleware-t a konfiguráció mögött.'
        );

        config(['security.use_recaptcha' => true]);
        $configSaysYes = $this->withEnvValue('USE_RECAPTCHA', 'false', fn () => $this->runMiddleware());

        $this->assertInstanceOf(RedirectResponse::class, $configSaysYes);
    }

    // =========================================================================
    // 6. A Google-nak küldött kérés alakja - v1-patch H
    // =========================================================================

    public function test_the_client_ip_is_sent_under_the_field_name_google_expects(): void
    {
        // A mező KORÁBBAN `ip` volt. A siteverify végpont `remoteip`-et vár, az
        // ismeretlen kulcsot pedig némán eldobja: a hívás sikeres maradt, csak
        // a címellenőrzés nem történt meg soha - és semmi nem jelezte.
        $this->fakeGoogle(['success' => true, 'score' => 0.9]);

        $this->runEnabled();

        Http::assertSent(function ($request) {
            return isset($request['remoteip']) && ! isset($request['ip']);
        });
    }

    public function test_a_token_issued_for_another_action_is_rejected(): void
    {
        // A Google v3 dokumentációja kifejezetten kéri az action szerveroldali
        // ellenőrzését. Nélküle egy MÁSIK űrlapon (vagy egy másik oldalon,
        // ugyanazzal a site key-jel) begyűjtött token bármelyik itt védett
        // végponton felhasználható. A kliens korábban mindhárom űrlapon
        // `register` actiont kért, a szerver pedig meg sem nézte a mezőt.
        $this->fakeGoogle(['success' => true, 'score' => 0.9, 'action' => 'register']);

        $this->assertInstanceOf(RedirectResponse::class, $this->runEnabled('teszt-token', 'login'));
    }

    public function test_a_token_issued_for_the_expected_action_passes(): void
    {
        $this->fakeGoogle(['success' => true, 'score' => 0.9, 'action' => 'login']);

        $this->assertSame('atengedve', $this->runEnabled('teszt-token', 'login')->getContent());
    }

    public function test_a_missing_action_is_rejected_when_one_is_expected(): void
    {
        $this->fakeGoogle(['success' => true, 'score' => 0.9]);

        $this->assertInstanceOf(RedirectResponse::class, $this->runEnabled('teszt-token', 'login'));
    }

    public function test_the_three_public_endpoints_declare_their_own_action(): void
    {
        // A middleware csak akkor tud ellenőrizni, ha a route megmondja, mit
        // várjon. Ez a teszt azt őrzi, hogy egyik végpontról se essen le a
        // paraméter egy későbbi szerkesztéskor - paraméter nélkül a
        // middleware csendben visszaesne az ellenőrzés nélküli állapotba.
        $expected = [
            'password.email' => 'checkRecaptcha:password_reset',
        ];

        foreach ($expected as $name => $middleware) {
            $route = app('router')->getRoutes()->getByName($name);

            $this->assertNotNull($route, $name.': a route-nak léteznie kell.');
            $this->assertContains($middleware, $route->gatherMiddleware(), $name);
        }

        // A /login és a /register névtelen, ezért URI szerint keressük.
        $byUri = [
            'login' => 'checkRecaptcha:login',
            'register' => 'checkRecaptcha:register',
        ];

        foreach ($byUri as $uri => $middleware) {
            $matches = [];

            foreach (app('router')->getRoutes() as $route) {
                if ($route->uri() === $uri && in_array('POST', $route->methods(), true)) {
                    $matches[] = $route;
                }
            }

            $this->assertCount(1, $matches, $uri.': pontosan egy POST definíció.');
            $this->assertContains($middleware, $matches[0]->gatherMiddleware(), $uri);
        }
    }

    public function test_the_two_previously_unthrottled_endpoints_now_carry_a_rate_limit(): void
    {
        // A /register és a /forgot-password KORÁBBAN semmilyen route-throttle-t
        // nem viselt: a botvédelmet egyedül a reCAPTCHA adta, ami
        // kapcsolathibán szándékosan fail-open. Egy Google-kimaradás alatt
        // tehát mindkettő korlátlanul automatizálható volt.
        $passwordEmail = app('router')->getRoutes()->getByName('password.email');
        $this->assertContains('throttle:5,1', $passwordEmail->gatherMiddleware());

        foreach (app('router')->getRoutes() as $route) {
            if ($route->uri() === 'register' && in_array('POST', $route->methods(), true)) {
                $this->assertContains('throttle:5,1', $route->gatherMiddleware());
            }
        }
    }

    public function test_the_configuration_file_still_reads_the_environment_variable(): void
    {
        // A .env -> config út maga nem veszhet el: a config/security.php a
        // betöltésekor olvassa a változót, és a szokásos igaz alakokat
        // egységesen értelmezi.
        foreach (['true', '1', 'on', 'yes'] as $value) {
            $security = $this->withEnvValue('USE_RECAPTCHA', $value, fn () => require config_path('security.php'));
            $this->assertTrue($security['use_recaptcha'], $value.': be kell kapcsolnia.');
        }

        foreach (['false', '0', 'off', '', 'talan'] as $value) {
            $security = $this->withEnvValue('USE_RECAPTCHA', $value, fn () => require config_path('security.php'));
            $this->assertFalse($security['use_recaptcha'], $value.': nem szabad bekapcsolnia.');
        }
    }
}
