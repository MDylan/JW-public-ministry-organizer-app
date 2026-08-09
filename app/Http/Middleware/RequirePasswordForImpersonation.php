<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;

/**
 * Require a recent password confirmation before starting impersonation.
 *
 * Laravel's generic RequirePassword middleware stores the current URL as the
 * intended destination. That is unsafe for this POST-only endpoint because the
 * confirmation flow would later revisit it as GET and produce a 405 response.
 */
class RequirePasswordForImpersonation
{
    /**
     * Handle an incoming request.
     *
     * @param  \Illuminate\Http\Request  $request
     * @param  \Closure  $next
     * @return mixed
     */
    public function handle(Request $request, Closure $next)
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

        // Do not save the POST URL as intended: password confirmation redirects
        // with GET. Return to the protected list and require an explicit retry.
        $request->session()->put('url.intended', route('admin.users'));

        return redirect()->route('password.confirm');
    }
}
