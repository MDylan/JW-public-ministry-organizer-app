<?php

// The application's service providers (roadmap TODO 56; they used to be the
// tail of config/app.php's `providers` array, in this order). The framework's
// own providers come from ServiceProvider::defaultProviders(), and the
// packages' from auto-discovery.
//
// There is no RouteServiceProvider and no EventServiceProvider any more: the
// routes are loaded by bootstrap/app.php's withRouting(), the listeners are
// discovered from app/Listeners and the observers are registered in
// AppServiceProvider::boot(). Nor is there a BladeComponentServiceProvider
// (TODO 59): `<x-admin-layout>` is the anonymous component
// resources/views/components/admin-layout.blade.php, found without an alias.
return [
    App\Providers\AppServiceProvider::class,
    App\Providers\AuthServiceProvider::class,
    MDylan\LaraUpdater\LaraUpdaterServiceProvider::class,
    App\Providers\FortifyServiceProvider::class,
];
