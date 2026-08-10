<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

/**
 * This middleware checks whether the given user is authorized to edit the group's data.
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
