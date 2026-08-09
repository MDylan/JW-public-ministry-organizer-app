<?php

use Illuminate\Support\Facades\Route;
use Laravel\Fortify\Features;
use Laravel\Fortify\Http\Controllers\AuthenticatedSessionController;
use Laravel\Fortify\Http\Controllers\ConfirmablePasswordController;
use Laravel\Fortify\Http\Controllers\ConfirmedPasswordStatusController;
use Laravel\Fortify\Http\Controllers\EmailVerificationNotificationController;
use Laravel\Fortify\Http\Controllers\EmailVerificationPromptController;
use Laravel\Fortify\Http\Controllers\NewPasswordController;
use Laravel\Fortify\Http\Controllers\PasswordController;
use Laravel\Fortify\Http\Controllers\PasswordResetLinkController;
use Laravel\Fortify\Http\Controllers\ProfileInformationController;
use Laravel\Fortify\Http\Controllers\RecoveryCodeController;
use Laravel\Fortify\Http\Controllers\RegisteredUserController;
use Laravel\Fortify\Http\Controllers\TwoFactorAuthenticatedSessionController;
use Laravel\Fortify\Http\Controllers\TwoFactorAuthenticationController;
use Laravel\Fortify\Http\Controllers\TwoFactorQrCodeController;
use Laravel\Fortify\Http\Controllers\VerifyEmailController;

Route::group(['middleware' => config('fortify.middleware', ['web'])], function () {
    $enableViews = config('fortify.views', true);

    // Authentication...
    if ($enableViews) {
        Route::get('/login', [AuthenticatedSessionController::class, 'create'])
            ->middleware(['guest:'.config('fortify.guard')])
            ->name('login');
    }

    $limiter = config('fortify.limiters.login');
    $twoFactorLimiter = config('fortify.limiters.two-factor');
    $verificationLimiter = config('fortify.limiters.verification', '6,1');

    Route::post('/login', [AuthenticatedSessionController::class, 'store'])
        ->middleware(array_filter([
            'guest:'.config('fortify.guard'),
            $limiter ? 'throttle:'.$limiter : null,
            'checkRecaptcha:login'
        ]));

    Route::post('/logout', [AuthenticatedSessionController::class, 'destroy'])
        ->name('logout');

    // Password Reset...
    if (Features::enabled(Features::resetPasswords())) {
        if ($enableViews) {
            Route::get('/forgot-password', [PasswordResetLinkController::class, 'create'])
                ->middleware(['guest:'.config('fortify.guard')])
                ->name('password.request');

            Route::get('/reset-password/{token}', [NewPasswordController::class, 'create'])
                ->middleware(['guest:'.config('fortify.guard')])
                ->name('password.reset');
        }

        // A `throttle` KORÁBBAN hiányzott innen és a /register-ről is: a
        // botvédelmet egyedül a reCAPTCHA adta, ami kapcsolathibán szándékosan
        // fail-open (lásd App\Http\Middleware\CheckRecaptcha). Egy Google-
        // kimaradás alatt tehát mindkét végpont korlátlanul automatizálható
        // volt. A /login ugyanezt a config('fortify.limiters.login')-ból kapja.
        // A `strictEmail` a Laravel 8 alapértelmezett `email` szabályát pótolja,
        // ami elfogadja a CR/LF-et a címben (GHSA-5vg9-5847-vvmq). Mindkét
        // controller a vendorban él és `required|email`-t validál, tehát a
        // szabály ott nem szerkeszthető maradandóan. Az ellenőrzés a
        // reCAPTCHA ELŐTT fut: egy nyilvánvalóan rossz cím ne kerüljön a
        // Google felé indított körbe.
        Route::post('/forgot-password', [PasswordResetLinkController::class, 'store'])
            ->middleware(['guest:'.config('fortify.guard'), 'throttle:5,1', 'strictEmail', 'checkRecaptcha:password_reset'])
            ->name('password.email');

        Route::post('/reset-password', [NewPasswordController::class, 'store'])
            ->middleware(['guest:'.config('fortify.guard'), 'strictEmail'])
            ->name('password.update');
    }

    // Registration...
    if (Features::enabled(Features::registration())) {
        if ($enableViews) {
            Route::get('/register', [RegisteredUserController::class, 'create'])
                ->middleware(['guest:'.config('fortify.guard')])
                ->name('register');
        }

        Route::post('/register', [RegisteredUserController::class, 'store'])
            ->middleware(['guest:'.config('fortify.guard'), 'throttle:5,1', 'checkRecaptcha:register' ]);
    }

    // Email Verification...
    if (Features::enabled(Features::emailVerification())) {
        // disabled, it's generate problem
        // if ($enableViews) {        //     
        //     Route::get('/email/verify', [EmailVerificationPromptController::class, '__invoke'])
        //         ->middleware([config('fortify.auth_middleware', 'auth').':'.config('fortify.guard')])
        //         ->name('verification.notice');
        // }

        Route::get('/email/verify/{id}/{hash}', [VerifyEmailController::class, '__invoke'])
            ->middleware([config('fortify.auth_middleware', 'auth').':'.config('fortify.guard'), 'signed', 'throttle:'.$verificationLimiter])
            ->name('verification.verify');

        Route::post('/email/verification-notification', [EmailVerificationNotificationController::class, 'store'])
            ->middleware([config('fortify.auth_middleware', 'auth').':'.config('fortify.guard'), 'throttle:'.$verificationLimiter])
            ->name('verification.send');
    }

    // Profile Information...
    if (Features::enabled(Features::updateProfileInformation())) {
        Route::put('/user/profile-information', [ProfileInformationController::class, 'update'])
            ->middleware([config('fortify.auth_middleware', 'auth').':'.config('fortify.guard')])
            ->name('user-profile-information.update');
    }

    // Passwords...
    if (Features::enabled(Features::updatePasswords())) {
        Route::put('/user/password', [PasswordController::class, 'update'])
            ->middleware([config('fortify.auth_middleware', 'auth').':'.config('fortify.guard')])
            ->name('user-password.update');
    }

    // Password Confirmation...
    // if ($enableViews) {
    //     Route::get('/user/confirm-password', [ConfirmablePasswordController::class, 'show'])
    //         ->middleware([config('fortify.auth_middleware', 'auth').':'.config('fortify.guard')])
    //         ->name('password.confirm');
    // }

    Route::get('/user/confirmed-password-status', [ConfirmedPasswordStatusController::class, 'show'])
        ->middleware([config('fortify.auth_middleware', 'auth').':'.config('fortify.guard')])
        ->name('password.confirmation');

    // TÖRÖLVE. Ez a végpont csak `auth:web`-et viselt, sebességkorlátot nem,
    // vagyis egy ellopott munkamenettel korlátlanul lehetett rajta a felhasználó
    // jelszavát próbálgatni. Az alkalmazás a SAJÁT, `throttle:6,1`-gyel védett
    // ágát használja (`password.confirm.store`, routes/web.php), ide semmi nem
    // postolt - a fenti GET pár pedig már korábban ki volt kommentelve.
    //
    // A Fortify 1.11.2 vendor route-fájljában ez a definíció ráadásul megkapta a
    // `password.confirm` NEVET (a GET-ről költözött át); átvéve duplikált nevet
    // csinálna a routes/web.php GET ágával, és a `route:cache` ugyanazzal a
    // LogicExceptionnel bukna el, amit a TODO 26 zárt le.
    //
    // Route::post('/user/confirm-password', [ConfirmablePasswordController::class, 'store'])
    //     ->middleware([config('fortify.auth_middleware', 'auth').':'.config('fortify.guard')]);

    // Two Factor Authentication...
    if (Features::enabled(Features::twoFactorAuthentication())) {
        if ($enableViews) {
            Route::get('/two-factor-challenge', [TwoFactorAuthenticatedSessionController::class, 'create'])
                ->middleware(['guest:'.config('fortify.guard')])
                ->name('two-factor.login');
        }

        Route::post('/two-factor-challenge', [TwoFactorAuthenticatedSessionController::class, 'store'])
            ->middleware(array_filter([
                'guest:'.config('fortify.guard'),
                $twoFactorLimiter ? 'throttle:'.$twoFactorLimiter : null,
            ]));

        $twoFactorMiddleware = Features::optionEnabled(Features::twoFactorAuthentication(), 'confirmPassword')
            ? [config('fortify.auth_middleware', 'auth').':'.config('fortify.guard'), 'password.confirm']
            : [config('fortify.auth_middleware', 'auth').':'.config('fortify.guard')];

        Route::post('/user/two-factor-authentication', [TwoFactorAuthenticationController::class, 'store'])
            ->middleware($twoFactorMiddleware)
            ->name('two-factor.enable');

        Route::delete('/user/two-factor-authentication', [TwoFactorAuthenticationController::class, 'destroy'])
            ->middleware($twoFactorMiddleware)
            ->name('two-factor.disable');

        Route::get('/user/two-factor-qr-code', [TwoFactorQrCodeController::class, 'show'])
            ->middleware($twoFactorMiddleware)
            ->name('two-factor.qr-code');

        Route::get('/user/two-factor-recovery-codes', [RecoveryCodeController::class, 'index'])
            ->middleware($twoFactorMiddleware)
            ->name('two-factor.recovery-codes');

        Route::post('/user/two-factor-recovery-codes', [RecoveryCodeController::class, 'store'])
            ->middleware($twoFactorMiddleware);
    }
});
