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
 * TODO 09: the CheckRecaptcha middleware.
 *
 * A route middleware, sitting on three public endpoints (routes/fortify.php):
 * POST /login, POST /register, POST /forgot-password. In other words, login
 * and registration stand or fall on it.
 *
 * It currently never runs in earnest, because both phpunit.xml and
 * .env.testing set USE_RECAPTCHA=false - so the enabled state is completely
 * uncovered.
 *
 * Until v1-patch TODO 28, the middleware read env() AT RUNTIME, so enabling
 * it required writing the $_SERVER array. Now it reads the
 * config('security.use_recaptcha') key, so the tests set that too - and this
 * is the real difference: the old read would have SILENTLY flipped to false
 * after a config:cache, meaning bot protection disappears without anything
 * signaling it.
 *
 * Http::fake() makes its first appearance in this suite here.
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

    /** Runs the middleware with recaptcha enabled. */
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
    // 1. The disabled default state
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
        // A real HTTP request to prove the actual wiring: checkRecaptcha
        // sits on fortify's login route, but in the disabled state no
        // network traffic is generated.
        Http::fake();

        $this->post(route('login'), [
            'email'    => 'nincs-ilyen@example.test',
            'password' => 'rossz-jelszo',
        ]);

        Http::assertNothingSent();
    }

    // =========================================================================
    // 2. The enabled state - successful verification
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
    // 3. The enabled state - rejection
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
        // The condition is strict: score > min_score (:28). A score EXACTLY
        // equal to the threshold therefore fails - the configuration value
        // is not an "allowed minimum" but "above this".
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
        // successful() is only true for 2xx, so a 500 from Google leads to
        // rejection - the user cannot log in.
        $this->fakeGoogle(['message' => 'internal error'], 500);

        $this->assertInstanceOf(RedirectResponse::class, $this->runEnabled());
    }

    public function test_a_missing_token_is_treated_as_a_bot(): void
    {
        $this->fakeGoogle(['success' => false]);

        $this->assertInstanceOf(RedirectResponse::class, $this->runEnabled(null));
    }

    // =========================================================================
    // 4. The connection failure - a latent defect
    // =========================================================================

    public function test_a_connection_failure_lets_the_request_through(): void
    {
        // REVERSED by the v1-patch D3 fix, with the user's approval.
        //
        // Http::asForm()->post() ran WITHOUT a try/catch. For an HTTP error
        // code, Laravel gives a Response - the code handles that correctly
        // (see the 500 test above) - but for a CONNECTION FAILURE (timeout,
        // DNS, network), a ConnectionException is thrown instead, which
        // nobody caught.
        //
        // Consequence: a Google outage produced a 500 on the POST /login,
        // POST /register, and POST /forgot-password endpoints - nobody could
        // log in, register, or reset their password until Google came back.
        // It is dormant today because of USE_RECAPTCHA=false, but before
        // recaptcha is enabled this would have been a blocking defect.
        //
        // The chosen policy is FAIL-OPEN: availability over bot protection.
        // The request goes through, the error is logged. Fail-closed would
        // have been equally defensible (a captcha error message instead of
        // the 500); the previous behavior was neither.
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
        // The call previously had NO timeout at all, so a stuck Google
        // endpoint held the PHP worker hostage - precisely on the login
        // path, where this exhausts processes the fastest.
        $reflection = new \ReflectionClass(CheckRecaptcha::class);

        $this->assertTrue(
            $reflection->hasConstant('TIMEOUT'),
            'A middleware-nek explicit időkorláttal kell hívnia.'
        );
        $this->assertGreaterThan(0, $reflection->getConstant('TIMEOUT'));
    }

    // =========================================================================
    // 5. The source of the flag - TODO 28
    // =========================================================================

    public function test_the_flag_comes_from_configuration_not_from_the_environment(): void
    {
        // REVERSED by the v1-patch TODO 28 fix.
        //
        // Previously the same request with two different
        // $_SERVER['USE_RECAPTCHA'] values gave two different results - i.e.
        // the middleware read directly from the environment. A
        // `php artisan config:cache` would have frozen exactly this in
        // place: .env does not even get loaded then, env() returns null, and
        // bot protection silently turns off.
        //
        // Now the environment variable by itself moves nothing; the
        // configuration decides, and that can be cached.
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
    // 6. The shape of the request sent to Google - v1-patch H
    // =========================================================================

    public function test_the_client_ip_is_sent_under_the_field_name_google_expects(): void
    {
        // The field was PREVIOUSLY `ip`. The siteverify endpoint expects
        // `remoteip`, and it silently discards the unknown key: the call
        // stayed successful, only the IP check never actually happened -
        // and nothing signaled it.
        $this->fakeGoogle(['success' => true, 'score' => 0.9]);

        $this->runEnabled();

        Http::assertSent(function ($request) {
            return isset($request['remoteip']) && ! isset($request['ip']);
        });
    }

    public function test_a_token_issued_for_another_action_is_rejected(): void
    {
        // Google's v3 documentation explicitly asks for server-side
        // verification of the action. Without it, a token collected on
        // ANOTHER form (or another page, with the same site key) can be used
        // on any endpoint protected here. The client previously requested
        // the `register` action on all three forms, and the server did not
        // even look at the field.
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
        // The middleware can only verify if the route tells it what to
        // expect. This test guards against the parameter falling off any
        // endpoint during a later edit - without the parameter, the
        // middleware would silently fall back to an unverified state.
        $expected = [
            'password.email' => 'checkRecaptcha:password_reset',
        ];

        foreach ($expected as $name => $middleware) {
            $route = app('router')->getRoutes()->getByName($name);

            $this->assertNotNull($route, $name.': a route-nak léteznie kell.');
            $this->assertContains($middleware, $route->gatherMiddleware(), $name);
        }

        // /login and /register are unnamed, so we look them up by URI.
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
        // /register and /forgot-password PREVIOUSLY carried no route
        // throttle at all: bot protection was provided solely by reCAPTCHA,
        // which is deliberately fail-open on a connection failure. So during
        // a Google outage, both were unboundedly automatable.
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
        // The .env -> config path itself must not get lost: config/security.php
        // reads the variable when it loads, and interprets the usual truthy
        // spellings consistently.
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
