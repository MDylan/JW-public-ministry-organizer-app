<?php

namespace App\Http\Middleware;

use App\Support\Updates\UpdateBranch;
use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use MDylan\LaraUpdater\LaraUpdaterController;

/**
 * Gate for the update endpoint: does not allow crossing a major version.
 *
 * WHY IS THIS NEEDED IF THE UI ALREADY HIDES THE BUTTON
 *
 * Because hiding the button isn't protection. `updater.update` is a plain GET
 * URL, which can be opened from a bookmark, browser history, or by hand, and
 * from there `update()` downloads, switches to maintenance mode, and
 * migrates. The limit needs to be where the action actually starts.
 *
 * WHY ONLY ON THE UPDATE ENDPOINT
 *
 * The middleware gets attached to ALL THREE endpoints via the
 * config('laraupdater.middleware') array, but `check` and `currentVersion`
 * are read-only queries - locking those down would be pointless, since their
 * whole purpose is to show the available version.
 *
 * WHY BY FLUSHING THE CACHE
 *
 * `update()` deliberately bypasses the cache to read the manifest
 * (`$this->cache = false`), because starting an installation from a
 * 15-minute-old cache entry is risky. If the gate looked at the cached
 * state, the two could drift apart: the guard would see an old 1.x version,
 * while update() would already be installing the freshly published 2.0.0.
 * So a fresh read happens here too, and check() immediately refills the
 * cache for the next page render.
 *
 * ORDER IN THE CONFIG MATTERS
 *
 * This middleware sits BEHIND `auth` and `can:is-admin`. So the outbound
 * network request only runs for a logged-in admin - guests and non-admins
 * are still rejected by the two layers ahead of it, before the channel is
 * even queried.
 */
class EnsureUpdateWithinBranch
{
    public function handle(Request $request, Closure $next)
    {
        if (! $request->routeIs('laraupdater.update')) {
            return $next($request);
        }

        Cache::forget('laraupdater_lastversion');

        $version = (new LaraUpdaterController)->check();

        if ($version !== '' && ! UpdateBranch::allows($version)) {
            abort(403, __('app.update_manual_blocked', ['version' => $version]));
        }

        return $next($request);
    }
}
