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

        // A kulcs KORÁBBAN a nyers `email . ip` összefűzés volt. Két baja volt:
        //
        // 1. Normalizálatlan e-mail. A MySQL alapértelmezett collationje
        //    kis-/nagybetűre érzéketlen, tehát a `User@x.hu` és a `user@x.hu`
        //    UGYANAZT a fiókot találja meg - a limiter viszont két külön
        //    vödörnek látta őket, így az 5/perc korlát a betűváltozatokkal
        //    tetszőlegesen sokszorozható volt.
        // 2. Nincs elválasztó. A `bob@x.hu` + `1.2.3.41` és a `bob@x.hu1` +
        //    `.2.3.41` ugyanazt a kulcsot adja - önmagában ártalmatlan, de a
        //    kulcsütközés soha nem szándékos.
        //
        // A második, IP-alapú korlát az e-mail-forgatásos próbálkozást fogja
        // meg: egy IP-ről percenként 20 bejelentkezési kísérlet mehet, akárhány
        // különböző címmel.
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

        // A TOTP-visszajátszás elleni védelem. A Fortify 1.11.2 saját javítása
        // nem ér célba a google2fa `findValidOTP()` `true` visszatérése miatt -
        // a részletes indoklás a felüldefiniált osztály fejlécében áll.
        //
        // A Fortify a SAJÁT kötését register()-ben teszi le, ez a boot() pedig
        // minden register() után fut, tehát ez nyer.
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
