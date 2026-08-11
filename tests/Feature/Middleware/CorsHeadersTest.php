<?php

namespace Tests\Feature\Middleware;

use Tests\Feature\FeatureTestCase;

/**
 * TODO 35: the CORS middleware's observable contract.
 *
 * WHY THIS EXISTS
 *
 * TODO 35 swaps `Fruitcake\Cors\HandleCors` (from the abandoned
 * `fruitcake/laravel-cors`) for the framework-native
 * `Illuminate\Http\Middleware\HandleCors`. Nothing in the suite measured CORS
 * before this file: the two route snapshots read `$route->gatherMiddleware()`,
 * which returns route and group middleware only - the kernel's GLOBAL
 * `$middleware` stack is invisible to them. So `HandleCors` could have been
 * deleted from `app/Http/Kernel.php` outright and all 1361 tests would have
 * stayed green.
 *
 * These assertions were therefore written and run against the OLD package
 * FIRST, and are expected to pass unchanged after the swap. That is the point:
 * a green suite after a middleware replacement only means "nothing broke" if
 * something was measuring the thing that was replaced.
 *
 * WHAT THE TWO IMPLEMENTATIONS SHARE, AND WHERE THEY PART
 *
 * Both read `config/cors.php`, both gate on the same `paths` matching, both
 * answer a preflight themselves. The package converted the snake_case config
 * keys to camelCase in its service provider; the framework hands the array
 * straight to `Fruitcake\Cors\CorsService::setOptions()`, which accepts BOTH
 * spellings - so `config/cors.php` needs no edit, and this file deliberately
 * does not touch it.
 *
 * Three differences exist and none is observable here: the package validated
 * the config shape (a lost RuntimeException, not a lost behaviour), it
 * registered a `RequestHandled` listener as an exception fallback (moot -
 * `Illuminate\Routing\Pipeline::handleException()` turns the exception into a
 * response INSIDE the pipeline, so the middleware's after-code still runs),
 * and it skipped its headers when `Access-Control-Allow-Origin` was already
 * present (nothing in this application sets that header by hand).
 */
class CorsHeadersTest extends FeatureTestCase
{
    /**
     * The only application path covered by `config/cors.php`'s `paths`.
     *
     * `paths` is `['api/*', 'sanctum/csrf-cookie']`; Sanctum is not installed,
     * and `routes/api.php` holds exactly one route, mounted under the `api`
     * prefix by RouteServiceProvider.
     */
    private const CORS_PATH = '/api/user';

    private const ORIGIN = 'https://kliens.example.test';

    // =========================================================================
    // 1. The preflight, which the middleware answers on its own
    // =========================================================================

    public function test_a_preflight_request_is_answered_by_the_middleware(): void
    {
        // A preflight never reaches routing: HandleCors is GLOBAL middleware
        // and returns before $next(). So the `auth:api` guard on the route
        // plays no part in this response - which is exactly why a preflight is
        // the cleanest possible probe of the middleware itself.
        $response = $this->call('OPTIONS', self::CORS_PATH, [], [], [], [
            'HTTP_ORIGIN' => self::ORIGIN,
            'HTTP_ACCESS_CONTROL_REQUEST_METHOD' => 'GET',
        ]);

        $response->assertNoContent(204);
        $response->assertHeader('Access-Control-Allow-Origin', '*');

        $this->assertStringContainsString(
            'GET',
            (string) $response->headers->get('Access-Control-Allow-Methods'),
            'A preflight válasznak engedélyeznie kell a kért metódust.'
        );
    }

    public function test_the_preflight_response_varies_on_the_request_method(): void
    {
        // Both implementations call varyHeader() on the preflight response.
        // Without it a shared cache could serve one preflight's answer for a
        // different requested method.
        $response = $this->call('OPTIONS', self::CORS_PATH, [], [], [], [
            'HTTP_ORIGIN' => self::ORIGIN,
            'HTTP_ACCESS_CONTROL_REQUEST_METHOD' => 'POST',
        ]);

        $this->assertStringContainsString(
            'Access-Control-Request-Method',
            (string) $response->headers->get('Vary')
        );
    }

    // =========================================================================
    // 2. The actual request, where the headers are added on the way out
    // =========================================================================

    public function test_an_actual_request_to_a_covered_path_carries_the_cors_header(): void
    {
        // GET /api/user sits behind `auth:api`, so the status code depends on
        // the guard, not on CORS - and it is deliberately not asserted here.
        // What matters is that the header is on whatever response comes back,
        // because that is added by the middleware's after-code, i.e. after
        // $next() has already produced a response.
        $response = $this->call('GET', self::CORS_PATH, [], [], [], [
            'HTTP_ORIGIN' => self::ORIGIN,
            'HTTP_ACCEPT' => 'application/json',
        ]);

        $response->assertHeader('Access-Control-Allow-Origin', '*');
    }

    // =========================================================================
    // 3. The negative control - the path gate really gates
    // =========================================================================

    public function test_a_path_outside_the_cors_configuration_gets_no_cors_header(): void
    {
        // Without this case the file would also pass if the middleware added
        // its headers to EVERY response - which is a different, and wider,
        // behaviour than the one being preserved. `/` is a web route and is
        // not matched by `cors.paths`, so hasMatchingPath() must return false
        // and the middleware must pass the request straight through.
        $response = $this->call('GET', '/', [], [], [], [
            'HTTP_ORIGIN' => self::ORIGIN,
        ]);

        $response->assertOk();
        $response->assertHeaderMissing('Access-Control-Allow-Origin');
    }

    public function test_a_covered_path_answers_with_the_wildcard_even_without_an_origin(): void
    {
        // This case was written the other way round first - "no Origin, no
        // header" - and it FAILED on the old package, which is how the actual
        // rule got measured instead of assumed.
        //
        // With `allowed_origins => ['*']` and `supports_credentials => false`,
        // both CorsService implementations normalise the wildcard to "allow
        // everything" and then set a STATIC `Access-Control-Allow-Origin: *`,
        // without ever looking at the request's Origin. asm89/stack-cors does
        // it in normalizeOptions() (array('*') becomes true); fruitcake/php-cors
        // does it in normalizeOptions() too, via its allowAllOrigins flag. The
        // request's Origin is only consulted on the ELSE branch, i.e. when the
        // allowed list is a real list or holds patterns.
        //
        // So on this configuration the header is a constant, and the only thing
        // that decides whether it appears at all is the path gate - which is
        // what the previous case measures.
        $response = $this->call('GET', self::CORS_PATH, [], [], [], [
            'HTTP_ACCEPT' => 'application/json',
        ]);

        $response->assertHeader('Access-Control-Allow-Origin', '*');
    }

    // =========================================================================
    // 4. The configuration this contract rests on
    // =========================================================================

    public function test_the_cors_configuration_is_the_one_these_assertions_assume(): void
    {
        // If someone narrows `allowed_origins` or drops `api/*` from `paths`,
        // the cases above would start failing for a reason that has nothing to
        // do with the middleware class. This makes that diagnosis immediate.
        //
        // It also records the state TODO 35 deliberately did NOT change: the
        // `sanctum/csrf-cookie` entry survives although Sanctum is not
        // installed, and `allowed_origins` is a wildcard. Both are policy
        // questions and belong to the security review (TODO 74), not to a
        // middleware replacement.
        $this->assertSame(['api/*', 'sanctum/csrf-cookie'], config('cors.paths'));
        $this->assertSame(['*'], config('cors.allowed_origins'));
        $this->assertSame(['*'], config('cors.allowed_methods'));
        $this->assertFalse(config('cors.supports_credentials'));

        $this->assertArrayNotHasKey(
            'laravel/sanctum',
            json_decode(file_get_contents(base_path('composer.json')), true)['require'],
            'A sanctum/csrf-cookie path azért lóg a levegőben, mert a Sanctum nincs telepítve.'
        );
    }
}
