<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;

/**
 * Require a recent password confirmation before a POST/DELETE action.
 *
 * Laravel's generic RequirePassword middleware stores the current URL as the
 * intended destination, and after the confirmation redirect()->intended()
 * revisits it as a GET - a 405 for an action route. This one sends the user
 * back to the PAGE the action belongs to instead (the route named by the
 * middleware parameter, given the current route's matching parameters), so
 * the action is retried explicitly.
 *
 * It started as the impersonation guard (admin.users.login, v1-patch H).
 * Roadmap TODO 70 (and 70.1 on the v1 line) made it general when leaving a group and deleting a news
 * item stopped being GET requests: a state-changing GET could be triggered
 * by a link from another site within the confirmation window.
 *
 * Usage: `password.confirm.action:<route name>`.
 */
class RequirePasswordBeforeAction
{
    public function handle(Request $request, Closure $next, string $returnTo = 'admin.users')
    {
        $confirmedAt = time() - $request->session()->get('auth.password_confirmed_at', 0);

        if ($confirmedAt <= config('auth.password_timeout', 10800)) {
            return $next($request);
        }

        if ($request->expectsJson()) {
            return response()->json([
                'message' => 'Password confirmation required.',
            ], 423);
        }

        // Not the action URL: the confirmation redirects with GET. Return to
        // the page and require an explicit retry.
        $request->session()->put('url.intended', route($returnTo, $this->parametersFor($request, $returnTo)));

        return redirect()->route('password.confirm');
    }

    /**
     * The current route's parameters that the return route also takes.
     *
     * @return array<string, mixed>
     */
    private function parametersFor(Request $request, string $routeName): array
    {
        $target = app('router')->getRoutes()->getByName($routeName);
        $current = $request->route()?->parameters() ?? [];

        if ($target === null) {
            return [];
        }

        return array_intersect_key($current, array_flip($target->parameterNames()));
    }
}
