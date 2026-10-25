<?php

use Illuminate\Database\QueryException;
use Illuminate\Encryption\MissingAppKeyException;
use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Route;
use Illuminate\Support\Facades\Storage;

/*
| The Laravel 11 application skeleton (roadmap TODO 56). What used to live in
| app/Http/Kernel.php, app/Console/Kernel.php, app/Exceptions/Handler.php and
| App\Providers\RouteServiceProvider is configured here; the application's
| own service providers are listed in bootstrap/providers.php.
|
| The middleware below is a faithful port of the old HTTP kernel, not the
| framework's default stack: the application's own middleware subclasses
| stay, and MiddlewareStackContractTest pins the resolved result.
*/
$app = Application::configure(basePath: dirname(__DIR__))
    ->withRouting(
        web: __DIR__.'/../routes/web.php',
        // No `api:` routes: the stock /api/user stub went at roadmap TODO 71.
        commands: __DIR__.'/../routes/console.php',
        // No `health:` route: /up would be a new public URL.
        then: function () {
            // Fortify's routes come from the application's own file
            // (FortifyServiceProvider::register() calls ignoreRoutes()), and
            // they must load AFTER routes/web.php: when two files define the
            // same method + URI, the one loaded last wins. Until TODO 56 the
            // provider order in config/app.php guaranteed that; the
            // framework's RouteServiceProvider boots after every provider,
            // so the order is now stated here, where it happens.
            // RouteContractSnapshotTest::test_routes_web_is_loaded_before_routes_fortify.
            // No `namespace` option: the deprecated group setting went at
            // roadmap TODO 69, the file names every controller by its class.
            Route::group([
                'domain' => config('fortify.domain', null),
                'prefix' => config('fortify.prefix'),
            ], base_path('routes/fortify.php'));
        },
    )
    ->withMiddleware(function (Middleware $middleware) {
        $middleware->use([
            // \App\Http\Middleware\TrustHosts::class,
            \App\Http\Middleware\TrustProxies::class,
            // No HandleCors: nothing here is meant to be called cross-origin,
            // and config/cors.php went with the API routes (roadmap TODO 71).
            \App\Http\Middleware\PreventRequestsDuringMaintenance::class,
            \Illuminate\Foundation\Http\Middleware\ValidatePostSize::class,
            \App\Http\Middleware\TrimStrings::class,
            \Illuminate\Foundation\Http\Middleware\ConvertEmptyStringsToNull::class,
        ]);

        $middleware->group('web', [
            \App\Http\Middleware\EncryptCookies::class,
            \Illuminate\Cookie\Middleware\AddQueuedCookiesToResponse::class,
            \Illuminate\Session\Middleware\StartSession::class,
            \Illuminate\Session\Middleware\AuthenticateSession::class,
            \Illuminate\View\Middleware\ShareErrorsFromSession::class,
            \App\Http\Middleware\VerifyCsrfToken::class,
            \Illuminate\Routing\Middleware\SubstituteBindings::class,
            \App\Http\Middleware\SetLocale::class,
            \App\Http\Middleware\SetUserLastActivity::class,
            \App\Http\Middleware\HttpsProtocol::class,
        ]);

        // No `api` group of the application's own: the framework still adds
        // its default one, and no route carries it (roadmap TODO 71).

        // Laravel 11 always adds its own default aliases underneath these
        // (auth.session and precognitive are the two this list lacks); the
        // ones named here override the defaults of the same name.
        $middleware->alias([
            'auth' => \App\Http\Middleware\Authenticate::class,
            'auth.basic' => \Illuminate\Auth\Middleware\AuthenticateWithBasicAuth::class,
            'cache.headers' => \Illuminate\Http\Middleware\SetCacheHeaders::class,
            'can' => \Illuminate\Auth\Middleware\Authorize::class,
            'guest' => \App\Http\Middleware\RedirectIfAuthenticated::class,
            'password.confirm' => \Illuminate\Auth\Middleware\RequirePassword::class,
            // A password confirmation that returns to a page, not to the
            // POST/DELETE action itself (roadmap TODO 70; it began as the
            // impersonation guard, password.confirm.impersonation).
            'password.confirm.action' => \App\Http\Middleware\RequirePasswordBeforeAction::class,
            'installer' => \App\Http\Middleware\EnsureInstallerToken::class,
            'signed' => \Illuminate\Routing\Middleware\ValidateSignature::class,
            'throttle' => \Illuminate\Routing\Middleware\ThrottleRequests::class,
            'verified' => \Illuminate\Auth\Middleware\EnsureEmailIsVerified::class,
            'groupAdmin' => \App\Http\Middleware\GroupAdmin::class,
            'groupMember' => \App\Http\Middleware\GroupMember::class,
            'profileFull' => \App\Http\Middleware\ProfileFull::class,
            'setGuestLanguage' => \App\Http\Middleware\SetGuestLanguage::class,
            'checkRecaptcha' => \App\Http\Middleware\CheckRecaptcha::class,
            'strictEmail' => \App\Http\Middleware\EnsureWellFormedEmail::class,
        ]);
    })
    ->withExceptions(function (Exceptions $exceptions) {
        // The framework handler's $dontFlash default is the same three
        // password fields the old App\Exceptions\Handler listed.

        $exceptions->render(function (MissingAppKeyException $e) {
            $target = base_path() . "/.env";
            $from = base_path() . "/.env.example";
            if(!file_exists($target) && file_exists($from)) {
                copy($from, $target);
                Artisan::call("key:generate");
                return redirect()->route('setup.welcome');
            }

            // Otherwise we fall back to the built-in handler: once null is returned,
            // the framework renders the errors/500 view. Previously
            // there was a dd() here, which printed a raw message regardless of
            // APP_DEBUG and terminated with exit.
            return null;
        });

        $exceptions->render(function (QueryException $e) {
            if (!Storage::exists('installed.txt')) {
                return redirect()->route('setup.welcome');
            }

            // On an installed site, the normal error handling is the correct response:
            // report() has already run (Pipeline::handleException calls it before render()),
            // so the exception is logged, and the user
            // gets an error page instead of the raw database error message.
            return null;
        });
    })
    ->create();

/*
| Laravel 11 merges its own default config files underneath the application's.
| This application keeps complete config files from before that change, and
| some omissions in them are deliberate - the s3 disk was removed at roadmap
| TODO 37 - so merging would silently put them back. The configuration is
| exactly what config/ says. TODO 56 decided to keep it that way rather than
| adopt slim config files (TODO 55 records the measured difference).
*/
$app->dontMergeFrameworkConfiguration();

return $app;
