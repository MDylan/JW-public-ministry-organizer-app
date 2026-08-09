<?php
/*
* @author: Pietro Cinaglia
* 	.website: http://linkedin.com/in/pietrocinaglia
*
* Fork maintained by David Molnar (https://github.com/MDylan/laraupdater).
*/

use Illuminate\Support\Facades\Route;
use MDylan\LaraUpdater\LaraUpdaterController;

/*
* All three endpoints sit behind the configured middleware stack.
*
* Up to v1 only `updater.update` was guarded: `updater.check` and
* `updater.currentVersion` carried no middleware at all - not even `web` - so
* any anonymous visitor could read the installed version straight off
* version.txt. With `allow_users_id => false` (the recommended setup when the
* middleware stack already gates on a role) the in-controller id check is off
* too, which left nothing in front of them.
*
* The URIs are unchanged from v1 on purpose, so existing links keep working.
* They are now named, so callers can generate them with route() instead of
* hardcoding a root-relative path.
*/
Route::middleware(config('laraupdater.middleware'))->group(function () {
    Route::get('updater.check', [LaraUpdaterController::class, 'check'])
        ->name('laraupdater.check');

    Route::get('updater.currentVersion', [LaraUpdaterController::class, 'getCurrentVersion'])
        ->name('laraupdater.currentVersion');

    Route::get('updater.update', [LaraUpdaterController::class, 'update'])
        ->name('laraupdater.update');
});
