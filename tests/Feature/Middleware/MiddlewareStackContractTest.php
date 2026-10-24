<?php

namespace Tests\Feature\Middleware;

use Illuminate\Contracts\Http\Kernel;
use Tests\TestCase;

/**
 * Roadmap TODO 56: the resolved middleware stack, pinned before the skeleton
 * move.
 *
 * The roadmap named the route contract snapshot as "the primary
 * verification that the middleware stacks came across intact". Measured, it
 * cannot be: route-contracts.json records the names a route carries - `web`,
 * `auth`, `verified` - and not what those names resolve to. Dropping
 * SetLocale from the `web` group, or pointing `auth` at the framework's
 * Authenticate instead of the application's, would leave it byte-identical.
 *
 * This file pins the resolved side instead, read from the running kernel
 * and router: the global stack, both groups, the whole alias map and the
 * priority list. The lists are what the application declares plus what the
 * packages push at boot (Livewire's back-button middleware), in the order
 * the request sees them - a package entry moving relative to the
 * application's is a change too. Debugbar's injector used to sit in both
 * the global stack and the web group; Debugbar 4 injects from a
 * RequestHandled listener instead and pushes no middleware (roadmap TODO 66).
 *
 * It replaces MiddlewareAliasContractTest's "the kernel's declaration is the
 * whole truth" from TODO 41, which needed an App\Http\Kernel to reflect on.
 */
class MiddlewareStackContractTest extends TestCase
{
    public function test_the_global_stack(): void
    {
        $this->assertSame([
            'App\Http\Middleware\TrustProxies',
            'Illuminate\Http\Middleware\HandleCors',
            'App\Http\Middleware\PreventRequestsDuringMaintenance',
            'Illuminate\Foundation\Http\Middleware\ValidatePostSize',
            'App\Http\Middleware\TrimStrings',
            'Illuminate\Foundation\Http\Middleware\ConvertEmptyStringsToNull',
            'Livewire\Features\SupportDisablingBackButtonCache\DisableBackButtonCacheMiddleware',
        ], $this->kernel()->getGlobalMiddleware());
    }

    public function test_the_web_group(): void
    {
        $this->assertSame([
            'App\Http\Middleware\EncryptCookies',
            'Illuminate\Cookie\Middleware\AddQueuedCookiesToResponse',
            'Illuminate\Session\Middleware\StartSession',
            'Illuminate\Session\Middleware\AuthenticateSession',
            'Illuminate\View\Middleware\ShareErrorsFromSession',
            'App\Http\Middleware\VerifyCsrfToken',
            'Illuminate\Routing\Middleware\SubstituteBindings',
            'App\Http\Middleware\SetLocale',
            'App\Http\Middleware\SetUserLastActivity',
            'App\Http\Middleware\HttpsProtocol',
        ], $this->app['router']->getMiddlewareGroups()['web']);
    }

    public function test_the_api_group(): void
    {
        $this->assertSame([
            'throttle:api',
            'Illuminate\Routing\Middleware\SubstituteBindings',
        ], $this->app['router']->getMiddlewareGroups()['api']);

        $this->assertSame(['api', 'web'], $this->sortedKeys($this->app['router']->getMiddlewareGroups()));
    }

    public function test_the_alias_map(): void
    {
        $aliases = $this->app['router']->getMiddleware();
        ksort($aliases);

        $this->assertSame([
            'auth' => 'App\Http\Middleware\Authenticate',
            'auth.basic' => 'Illuminate\Auth\Middleware\AuthenticateWithBasicAuth',
            // TODO 56: Laravel 11's own default aliases are always added
            // under the application's, and cannot be taken away. These two
            // are the ones the old kernel did not declare; no route uses
            // either (route-contracts.json).
            'auth.session' => 'Illuminate\Session\Middleware\AuthenticateSession',
            'cache.headers' => 'Illuminate\Http\Middleware\SetCacheHeaders',
            'can' => 'Illuminate\Auth\Middleware\Authorize',
            'checkRecaptcha' => 'App\Http\Middleware\CheckRecaptcha',
            'groupAdmin' => 'App\Http\Middleware\GroupAdmin',
            'groupMember' => 'App\Http\Middleware\GroupMember',
            'guest' => 'App\Http\Middleware\RedirectIfAuthenticated',
            'installer' => 'App\Http\Middleware\EnsureInstallerToken',
            'password.confirm' => 'Illuminate\Auth\Middleware\RequirePassword',
            'password.confirm.impersonation' => 'App\Http\Middleware\RequirePasswordForImpersonation',
            'precognitive' => 'Illuminate\Foundation\Http\Middleware\HandlePrecognitiveRequests',
            'profileFull' => 'App\Http\Middleware\ProfileFull',
            'setGuestLanguage' => 'App\Http\Middleware\SetGuestLanguage',
            'signed' => 'Illuminate\Routing\Middleware\ValidateSignature',
            'strictEmail' => 'App\Http\Middleware\EnsureWellFormedEmail',
            'throttle' => 'Illuminate\Routing\Middleware\ThrottleRequests',
            'verified' => 'Illuminate\Auth\Middleware\EnsureEmailIsVerified',
        ], $aliases);
    }

    public function test_the_priority_list(): void
    {
        $expected = [
            'Illuminate\Foundation\Http\Middleware\HandlePrecognitiveRequests',
            'Illuminate\Cookie\Middleware\EncryptCookies',
            'Illuminate\Cookie\Middleware\AddQueuedCookiesToResponse',
            'Illuminate\Session\Middleware\StartSession',
            'Illuminate\View\Middleware\ShareErrorsFromSession',
            'Illuminate\Contracts\Auth\Middleware\AuthenticatesRequests',
            'Illuminate\Routing\Middleware\ThrottleRequests',
            'Illuminate\Routing\Middleware\ThrottleRequestsWithRedis',
            'Illuminate\Contracts\Session\Middleware\AuthenticatesSessions',
            'Illuminate\Routing\Middleware\SubstituteBindings',
            'Illuminate\Auth\Middleware\Authorize',
        ];

        $this->assertSame($expected, $this->kernel()->getMiddlewarePriority());
        $this->assertSame($expected, $this->app['router']->middlewarePriority);
    }

    private function kernel(): \Illuminate\Foundation\Http\Kernel
    {
        return $this->app->make(Kernel::class);
    }

    private function sortedKeys(array $array): array
    {
        $keys = array_keys($array);
        sort($keys);

        return $keys;
    }
}
