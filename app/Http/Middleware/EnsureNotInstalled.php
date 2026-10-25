<?php

namespace App\Http\Middleware;

use App\Support\Install\InstallState;
use Closure;
use Illuminate\Http\Request;

/**
 * Closes the `setup/*` group once the installation is complete.
 *
 * The group is always registered (roadmap TODO 72); an installed host
 * answers 404 here, as it did when the routes were simply missing. It runs
 * before `installer`, so an installed host never generates an installer
 * token either.
 */
class EnsureNotInstalled
{
    public function handle(Request $request, Closure $next)
    {
        if (InstallState::isInstalled()) {
            abort(404);
        }

        return $next($request);
    }
}
