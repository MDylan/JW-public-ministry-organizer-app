<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

class GroupMember
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
        if($user->groupsAccepted->contains('id', $groupId)) {
            return $next($request);
        } else {
            abort(403, __("This action is unauthorized."));
        }  
    }
}
