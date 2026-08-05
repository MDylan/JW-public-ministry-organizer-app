<?php

namespace Tests\Feature\Middleware;

use App\Http\Middleware\CheckRecaptcha;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Http;
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
 * lefedetlen. A middleware futásidőben olvas env()-et (:20), ezért a
 * bekapcsoláshoz a $_SERVER-t kell írni (withEnvValue), és ez egyben a
 * TODO 28-as env()-függés bizonyítéka is.
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

    private function runMiddleware(?string $token = 'teszt-token')
    {
        $request = Request::create('/login', 'POST', ['recaptcha_token' => $token]);

        return (new CheckRecaptcha())->handle($request, fn () => response('atengedve'));
    }

    /** Bekapcsolt recaptcha mellett futtatja a middleware-t. */
    private function runEnabled(?string $token = 'teszt-token')
    {
        return $this->withEnvValue('USE_RECAPTCHA', 'true', fn () => $this->runMiddleware($token));
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

        $response = $this->withEnvValue('USE_RECAPTCHA', 'false', fn () => $this->runMiddleware());

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

    public function test_a_connection_failure_escapes_the_middleware_as_a_fatal_error(): void
    {
        // KARAKTERIZÁLÓ TESZT egy éles üzemeltetési kockázatról.
        //
        // A Http::asForm()->post() (:22) NINCS try/catch-ben. HTTP-hibakódra
        // a Laravel Response-t ad - azt a kód kezeli (lásd a fenti 500-as
        // tesztet) -, KAPCSOLATHIBÁRA viszont ConnectionException-t dob,
        // amit itt senki nem kap el.
        //
        // Következmény: ha a Google elérhetetlen (hálózati hiba, timeout,
        // DNS), a POST /login, POST /register és POST /forgot-password
        // 500-as hibát ad. Egy külső szolgáltatás kimaradása tehát teljesen
        // kizárná a bejelentkezést.
        //
        // Ma alszik a USE_RECAPTCHA=false miatt. Javítás: roadmap TODO 33.1 -
        // a recaptcha bekapcsolása előtt kötelező.
        Http::fake(function () {
            throw new ConnectionException('cURL error 28: Operation timed out');
        });

        $this->expectException(ConnectionException::class);

        $this->runEnabled();
    }

    // =========================================================================
    // 5. A futásidejű env() olvasás - TODO 28
    // =========================================================================

    public function test_the_flag_is_read_from_the_environment_on_every_request(): void
    {
        // Ugyanaz a kérés, két különböző környezeti értékkel, két különböző
        // eredmény - a middleware nem konfigurációból dolgozik. A TODO 28
        // config-ba mozgatása után ez a teszt írandó át.
        $this->fakeGoogle(['success' => true, 'score' => 0.1]);

        $disabled = $this->withEnvValue('USE_RECAPTCHA', 'false', fn () => $this->runMiddleware());
        $enabled = $this->withEnvValue('USE_RECAPTCHA', 'true', fn () => $this->runMiddleware());

        $this->assertSame('atengedve', $disabled->getContent());
        $this->assertInstanceOf(RedirectResponse::class, $enabled);
    }
}
