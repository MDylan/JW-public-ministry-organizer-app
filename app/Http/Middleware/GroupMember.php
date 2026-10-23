<?php

namespace App\Http\Middleware;

use App\Models\Group;
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
        // Livewire 3 binds a page component's mount() parameters before route
        // middleware runs, so {group} is a Group model on routes whose component
        // type-hints it, and the raw id everywhere else (TODO 43.2).
        $group = $request->route('group');
        $groupId = $group instanceof Group ? $group->getKey() : $group;
        if($user->groupsAccepted->contains('id', $groupId)) {
            return $next($request);
        } else {
            abort(403, __("This action is unauthorized."));
        }  
    }
}
