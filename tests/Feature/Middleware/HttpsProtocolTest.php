<?php

namespace Tests\Feature\Middleware;

use App\Http\Middleware\HttpsProtocol;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Tests\Feature\FeatureTestCase;

/**
 * TODO 09: the HttpsProtocol middleware.
 *
 * It is a member of the web group (Kernel.php:47), so it runs on EVERY web
 * request - its body, however, sits behind three conditions, and with the
 * current configuration not one of them is unconditionally satisfied:
 *
 *     !$request->secure() && app()->environment('production') && $this->httpsEnforced()
 *
 * Until v1-patch B14, the third condition was
 * `env('USE_HTTPS', "false") == "true"`, i.e. it compared against the
 * literal "true" string; since TODO 28 it no longer even comes from env(),
 * but from the config('security.use_https') key, which interprets the usual
 * truthy spellings with filter_var().
 *
 * That is why it has zero coverage, and its enabled state is unknown
 * territory. The production environment cannot be set up from an HTTP test,
 * so here we call handle() directly.
 */
class HttpsProtocolTest extends FeatureTestCase
{
    /**
     * Runs the middleware for a given request; $next gives a simple
     * "ok" response so that passing through can be distinguished from a
     * redirect.
     */
    private function runMiddleware(Request $request)
    {
        return (new HttpsProtocol())->handle($request, fn () => response('ok'));
    }

    /**
     * Application::environment() reads the container's 'env' binding, so the
     * production environment can be simulated this way. Restored in a finally block.
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
     * Sets the HTTPS flag for the duration of the call.
     *
     * Before TODO 28 this wrote the $_SERVER array (withEnvValue), because
     * the middleware read env() at runtime. Now the configuration decides.
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
    // 1. Why it never runs today
    // =========================================================================

    public function test_the_redirect_is_skipped_outside_production_even_when_https_is_enabled(): void
    {
        // The test environment is 'testing', the development one is 'local' -
        // so the middleware is only active in production. That is why it
        // does not redirect a single time during the entire suite's run.
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
    // 2. The enabled state
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
    // 3. The string-comparison trap
    // =========================================================================

    /**
     * @dataProvider truthyFlagProvider
     */
    public function test_every_common_truthy_spelling_enables_the_redirect(string $value): void
    {
        // REVERSED first by the v1-patch B14 fix, then by the TODO 28 fix.
        //
        // The condition was `env('USE_HTTPS', "false") == "true"`, so it
        // compared against the SPECIFIC "true" string. Laravel's env()
        // converts the words "true"/"false" to bool, but returns "1" as a
        // string - so the comparison took the form "1" == "true", which is
        // false. The most common .env setting, USE_HTTPS=1, was therefore
        // ineffective, and HTTPS enforcement silently did not work.
        //
        // Since TODO 28, interpretation is config/security.php's job, so the
        // check moved there too: we load the file with the variable set to
        // the given value, and look at the bool that comes out of it. The
        // middleware itself receives this same bool - see section 2's cases.
        $security = $this->withEnvValue('USE_HTTPS', $value, fn () => require config_path('security.php'));

        $this->assertTrue($security['use_https'], $value.': be kell kapcsolnia.');

        // And the enabled key really does redirect.
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
        // B14's control experiment: the looser interpretation must not
        // enable enforcement where nobody asked for it.
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
    // 4. The source of the flag - TODO 28
    // =========================================================================

    public function test_the_flag_comes_from_configuration_not_from_the_environment(): void
    {
        // REVERSED by the v1-patch TODO 28 fix.
        //
        // Previously the same request with two different
        // $_SERVER['USE_HTTPS'] values gave two different results: the
        // middleware read directly from the environment. But after a
        // `php artisan config:cache`, Laravel does not even load .env, env()
        // returns null - so HTTPS enforcement would have silently turned
        // off, and precisely on the deployment careful enough to cache its
        // configuration. Nothing would have signaled it.
        //
        // Now the environment variable by itself moves nothing; the
        // configuration decides, and that can be cached.
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
