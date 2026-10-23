<?php

// The application's service providers (roadmap TODO 56; they used to be the
// tail of config/app.php's `providers` array, in this order). The framework's
// own providers come from ServiceProvider::defaultProviders(), and the
// packages' from auto-discovery.
//
// There is no RouteServiceProvider and no EventServiceProvider any more: the
// routes are loaded by bootstrap/app.php's withRouting(), the listeners are
// discovered from app/Listeners and the observers are registered in
// AppServiceProvider::boot().
return [
    App\Providers\AppServiceProvider::class,
    App\Providers\AuthServiceProvider::class,
    App\Providers\BladeComponentServiceProvider::class,
    MDylan\LaraUpdater\LaraUpdaterServiceProvider::class,
    App\Providers\FortifyServiceProvider::class,
];
