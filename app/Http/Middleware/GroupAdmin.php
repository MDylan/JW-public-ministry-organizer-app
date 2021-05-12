<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

/**
 * Ez a middleware ellenőrzi, hogy adott user jogosult e a csoport adatait szerkeszteni.
 */
class GroupAdmin
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
        $user = Auth::user();
        $groupId = $request->route('group');
        if($user->userGroupsEditable->contains('id', $groupId)) {
            return $next($request);
        } else {
            abort(403, __("This action is unauthorized."));
        }                
    }
}
