<?php

namespace App\Providers;

use App\Actions\Fortify\CreateNewUser;
use App\Actions\Fortify\RedirectIfTwoFactorConfirmed;
use App\Actions\Fortify\ResetUserPassword;
use App\Actions\Fortify\UpdateUserPassword;
use App\Actions\Fortify\UpdateUserProfileInformation;
use Illuminate\Cache\RateLimiting\Limit;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\ServiceProvider;
use Illuminate\Support\Str;
use Laravel\Fortify\Fortify;
use Illuminate\Support\Facades\Route;
use Laravel\Fortify\Actions\AttemptToAuthenticate;
use Laravel\Fortify\Actions\EnsureLoginIsNotThrottled;
use Laravel\Fortify\Actions\PrepareAuthenticatedSession;
use Laravel\Fortify\Features;

class FortifyServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     *
     * @return void
     */
    public function register()
    {
        // based on: 
        // https://laracasts.com/discuss/channels/laravel/use-middleware-on-fortify-login-and-register-routes
        Fortify::ignoreRoutes();
    }

    /**
     * Bootstrap any application services.
     *
     * @return void
     */
    public function boot()
    {

        Fortify::createUsersUsing(CreateNewUser::class);
        Fortify::updateUserProfileInformationUsing(UpdateUserProfileInformation::class);
        Fortify::updateUserPasswordsUsing(UpdateUserPassword::class);
        Fortify::resetUserPasswordsUsing(ResetUserPassword::class);

        // The key USED TO BE the raw `email . ip` concatenation. It had two problems:
        //
        // 1. Non-normalized email. MySQL's default collation is
        //    case-insensitive, so `User@x.hu` and `user@x.hu`
        //    resolve to the SAME account - but the limiter saw them as two separate
        //    buckets, so the 5/minute limit could be multiplied at will
        //    with letter-case variants.
        // 2. No separator. `bob@x.hu` + `1.2.3.41` and `bob@x.hu1` +
        //    `.2.3.41` produce the same key - harmless on its own, but a
        //    key collision is never intentional.
        //
        // The second, IP-based limit catches the email-rotation attempt:
        // 20 login attempts per minute may go through from one IP, with any
        // number of different addresses.
        RateLimiter::for('login', function (Request $request) {
            $email = Str::lower(trim((string) $request->input('email')));

            return [
                Limit::perMinute(5)->by($email.'|'.$request->ip()),
                Limit::perMinute(20)->by($request->ip()),
            ];
        });

        RateLimiter::for('two-factor', function (Request $request) {
            return Limit::perMinute(5)->by($request->session()->get('login.id'));
        });
        Fortify::loginView(function () {
            return view('auth.login');
        });
        Fortify::registerView(function () {
            return view('auth.register'); 
        });
        Fortify::requestPasswordResetLinkView(function () {
            return view('auth.forgot-password');
        });
        Fortify::resetPasswordView(function ($request) {
            return view('auth.reset-password', ['request' => $request]);
        });
        Fortify::twoFactorChallengeView(function () {
            return view('auth.two-factor-challenge');
        });

        /* Based on:
        * https://github.com/laravel/fortify/issues/201
        * https://dev.to/nicolus/laravel-fortify-implement-2fa-in-a-way-that-won-t-let-users-lock-themselves-out-2ejk
        */        
        Fortify::authenticateThrough(function() {
            return array_filter([
                config('fortify.limiters.login') ? null : EnsureLoginIsNotThrottled::class,
            Features::enabled(Features::twoFactorAuthentication()) ? RedirectIfTwoFactorConfirmed::class : null,
                AttemptToAuthenticate::class,
                PrepareAuthenticatedSession::class,
            ]);
        });
        //https://dev.to/seivad/comment/1ff4e
        $this->app->singleton(
            \Laravel\Fortify\Actions\DisableTwoFactorAuthentication::class,
            \App\Actions\Fortify\DisableTwoFactorAuthentication::class
        );

        // Protection against TOTP replay. Fortify 1.11.2's own fix doesn't
        // reach its target because of google2fa's `findValidOTP()` returning `true` -
        // the detailed rationale is in the overriding class's header.
        //
        // Fortify lays down its OWN binding in register(), and this boot()
        // runs after every register(), so this one wins.
        $this->app->singleton(
            \Laravel\Fortify\Contracts\TwoFactorAuthenticationProvider::class,
            function ($app) {
                return new \App\Actions\Fortify\TwoFactorAuthenticationProvider(
                    $app->make(\PragmaRX\Google2FA\Google2FA::class),
                    $app->make(\Illuminate\Contracts\Cache\Repository::class)
                );
            }
        );
        
        $this->configureRoutes();
    }

        /**
     * Configure the routes offered by the application.
     *
     * @return void
     */
    protected function configureRoutes()
    {
        Route::group([
            'namespace' => 'Laravel\Fortify\Http\Controllers',
            'domain' => config('fortify.domain', null),
            'prefix' => config('fortify.prefix'),
        ], function () {
            $this->loadRoutesFrom(base_path('routes/fortify.php'));
        });  
    }
}
