<?php

namespace App\Http\Livewire\Concerns;

use App\Models\GroupUser;
use Illuminate\Support\Facades\Auth;

/**
 * Group authorization for Livewire components, checked on every request.
 *
 * WHY THE ROUTE MIDDLEWARE IS NOT ENOUGH (roadmap TODO 70)
 *
 * A component action does not travel over the page's route: it POSTs to
 * livewire/update, and Livewire re-applies only the middleware classes on
 * its persistent list (Authenticate, Authorize, ...). `groupMember`,
 * `groupAdmin`, `profileFull` and `password.confirm` are not on it, so they
 * guard the first render and nothing after. And every public property the
 * component does not lock is writable by the client: a `$groupId` that was
 * the user's own group at mount can name any group by the next request.
 *
 * So a component that acts on a group asks here, on each action, with the
 * group it is about to touch - and gets the CURRENT user's CURRENT role,
 * read from the database, never from a property. Only an accepted,
 * non-removed membership counts; a pending invitation is not a membership.
 */
trait AuthorizesGroupAccess
{
    /**
     * The current user's role in the group, or null without an accepted
     * membership.
     */
    protected function acceptedGroupRole(mixed $groupId): ?string
    {
        if (! Auth::check() || ! is_numeric($groupId)) {
            return null;
        }

        return GroupUser::query()
            ->where('user_id', Auth::id())
            ->where('group_id', (int) $groupId)
            ->whereNotNull('accepted_at')
            ->value('group_role');
    }

    /**
     * Aborts with 403 unless the current user holds one of $roles in the
     * group (any accepted role when $roles is empty). Returns the role.
     *
     * @param  list<string>  $roles
     */
    protected function authorizeGroupRole(mixed $groupId, array $roles = []): string
    {
        $role = $this->acceptedGroupRole($groupId);

        if ($role === null || ($roles !== [] && ! in_array($role, $roles, true))) {
            abort(403, __('This action is unauthorized.'));
        }

        return $role;
    }

    protected function authorizeGroupMember(mixed $groupId): string
    {
        return $this->authorizeGroupRole($groupId);
    }

    /**
     * The `groupAdmin` middleware's rule (User::userGroupsEditable): an
     * accepted admin or roler.
     */
    protected function authorizeGroupEditor(mixed $groupId): string
    {
        return $this->authorizeGroupRole($groupId, ['admin', 'roler']);
    }
}
