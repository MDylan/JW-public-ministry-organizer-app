<?php

namespace Tests\Feature\Middleware;

use App\Http\Middleware\TrustProxies;
use Illuminate\Http\Request;
use Tests\TestCase;

/**
 * TODO 35: what TrustProxies actually decides on this installation.
 *
 * WHY THIS EXISTS
 *
 * TODO 35 moves the parent class from `Fideloper\Proxy\TrustProxies` to the
 * framework-native `Illuminate\Http\Middleware\TrustProxies`. Like HandleCors,
 * it lives in the kernel's GLOBAL `$middleware` stack, which neither route
 * snapshot can see - so nothing measured it before this file, and a swap that
 * changed proxy trust would have been invisible.
 *
 * These assertions were written against the OLD package first and are expected
 * to pass unchanged afterwards.
 *
 * THE MEASUREMENT THAT SHAPED THE SWAP
 *
 * The roadmap entry warned to "re-check the $headers constants, whose AWS ELB
 * handling differs". Read side by side, the AWS ELB handling is identical -
 * and the real finding is sharper. Both implementations resolve $headers
 * through a single-value branch (fideloper a `switch`, the framework a
 * `match`), and the value the application set was a COMBINED bitmask:
 *
 *     HEADER_X_FORWARDED_FOR | HOST | PORT | PROTO | AWS_ELB
 *
 * A combined mask equals no single constant, so both fell through to their
 * `default` arm. The override therefore never had any effect under either
 * parent, which is why TODO 35 deleted it rather than porting it. The only
 * difference between the two defaults is that the framework's also contains
 * HEADER_X_FORWARDED_PREFIX.
 *
 * And that difference cannot surface here either, for the reason the first
 * test below pins: `$proxies` is null and the application ships no
 * `config/trustedproxy.php`, so no proxy is ever trusted, `isFromTrustedProxy()`
 * is always false, and the trusted-header set is never consulted at all.
 */
class TrustProxiesTest extends TestCase
{
    /**
     * Request::setTrustedProxies() writes STATIC state on the Symfony request
     * class, and the middleware writes it on every handle() call. Left behind,
     * it would follow the process into every later test class and quietly
     * change how isSecure(), getHost() and getClientIp() answer there.
     *
     * -1 and [] are Symfony's own initial values (http-foundation/Request.php:69
     * and :218), so this restores the untouched state rather than a guess.
     */
    protected function tearDown(): void
    {
        Request::setTrustedProxies([], -1);

        parent::tearDown();
    }

    /**
     * A plain HTTP request that claims, through proxy headers, to have arrived
     * over HTTPS from a different host. REMOTE_ADDR is Request::create()'s
     * default 127.0.0.1, i.e. the "proxy" is the caller itself.
     */
    private function forwardedRequest(): Request
    {
        return Request::create('http://kozter.test/csoportok', 'GET', [], [], [], [
            'HTTP_X_FORWARDED_PROTO' => 'https',
            'HTTP_X_FORWARDED_HOST' => 'proxy.example.test',
        ]);
    }

    /**
     * Global middleware is resolved by the kernel through the container, and
     * so is resolved here - deliberately, not for convenience.
     *
     * The two parent classes disagree on their constructor:
     * `Fideloper\Proxy\TrustProxies::__construct(Repository $config)` needs the
     * config repository, `Illuminate\Http\Middleware\TrustProxies` declares no
     * constructor at all and reads config() directly. `new TrustProxies()`
     * therefore works on exactly one side of the TODO 35 swap. The container
     * spans both, which is also why the swap needs no kernel change beyond the
     * class name.
     */
    private function middleware(string $class = TrustProxies::class): TrustProxies
    {
        return $this->app->make($class);
    }

    private function runMiddleware(TrustProxies $middleware, Request $request): Request
    {
        $middleware->handle($request, fn (Request $passed) => $passed);

        return $request;
    }

    // =========================================================================
    // 1. The configured state: nothing is trusted
    // =========================================================================

    public function test_forwarded_headers_are_ignored_because_no_proxy_is_trusted(): void
    {
        // `$proxies` is null on App\Http\Middleware\TrustProxies, and there is
        // no config/trustedproxy.php in the application - the value that used
        // to answer config('trustedproxy.proxies') came from the package's own
        // published default, which is also null. So the removal of
        // fideloper/proxy takes away a null and leaves a missing key, which
        // reads as null too.
        //
        // This is the assertion that makes the whole swap a no-op on this
        // installation: with no trusted proxy, the forwarded headers are
        // attacker-controlled input and Symfony discards them.
        $this->assertNull(config('trustedproxy.proxies'));

        $request = $this->runMiddleware($this->middleware(), $this->forwardedRequest());

        $this->assertFalse($request->isSecure(), 'Nem megbízható forrás X-Forwarded-Proto fejléce nem tehet HTTPS-t.');
        $this->assertSame('kozter.test', $request->getHost());
        $this->assertFalse($request->isFromTrustedProxy());
    }

    // =========================================================================
    // 2. The control experiment: with a trusted proxy the same headers DO pass
    // =========================================================================

    public function test_the_same_headers_are_honoured_once_the_caller_is_a_trusted_proxy(): void
    {
        // Without this case, section 1 would also pass if the middleware never
        // ran at all - an untouched request is not secure either. Here the only
        // thing that changes is $proxies, so a difference in the outcome can
        // only have come from the middleware doing its job.
        $request = $this->runMiddleware(
            $this->middleware(TrustsEveryCallingProxy::class),
            $this->forwardedRequest()
        );

        $this->assertTrue($request->isFromTrustedProxy());
        $this->assertTrue($request->isSecure(), 'Megbízható proxy mögül az X-Forwarded-Proto fejlécnek át kell ütnie.');
        $this->assertSame('proxy.example.test', $request->getHost());
    }

    public function test_a_specific_trusted_ip_list_only_trusts_the_addresses_it_names(): void
    {
        // The caller is 127.0.0.1, which is not on the list.
        $request = $this->runMiddleware(
            $this->middleware(TrustsOneNamedAddress::class),
            $this->forwardedRequest()
        );

        $this->assertFalse($request->isFromTrustedProxy());
        $this->assertFalse($request->isSecure());
    }

    // =========================================================================
    // 3. The reset the middleware performs on every request
    // =========================================================================

    public function test_each_request_starts_from_an_empty_trusted_proxy_list(): void
    {
        // Both implementations open handle() with
        // `$request::setTrustedProxies([], ...)`. The state is static and
        // therefore process-wide, so without that line one request that
        // trusted a proxy would leave the next one trusting it too - in a
        // queue worker or an Octane-style long-lived process that is a real
        // request-to-request leak, not a theoretical one.
        $this->runMiddleware(
            $this->middleware(TrustsEveryCallingProxy::class),
            $this->forwardedRequest()
        );

        // Same class as production uses, i.e. $proxies is null again.
        $second = $this->runMiddleware($this->middleware(), $this->forwardedRequest());

        $this->assertFalse($second->isFromTrustedProxy(), 'Az előző kérés bizalmi állapota nem szivároghat át.');
        $this->assertFalse($second->isSecure());
    }
}

/**
 * Trusts whatever address is calling, the "*" form of the setting.
 *
 * These two live here rather than as anonymous classes so that the container
 * can resolve them by name - see middleware() above for why that matters.
 */
class TrustsEveryCallingProxy extends TrustProxies
{
    protected $proxies = '*';
}

/**
 * Trusts one address that is deliberately NOT the caller.
 */
class TrustsOneNamedAddress extends TrustProxies
{
    protected $proxies = ['10.0.0.1'];
}
