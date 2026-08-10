<?php

namespace App\Http\Controllers;

use App\Classes\GroupUserMoves;
use App\Models\User;
use App\Notifications\deletePersonalDataNotification;
use App\Support\Gdpr\AnonymizationPolicy;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Session;
use Illuminate\Support\Facades\URL;

class deletePersonalDataController extends Controller
{
    /**
     * Send an email to user, to confirm personal data deletion
     */
    public function asktodelete() {

        $u = User::findOrFail(Auth::id());

        // TODO 12.2: the succession condition is decided BEFORE the email is sent, so
        // the user immediately knows what to do. The GDPR request must not
        // silently disappear.
        if ($blocked = $this->blockedReason($u)) {
            Session::flash('profile_message', $blocked);

            return redirect()->route('user.profile');
        }

        $url = URL::temporarySignedRoute(
            'user.deletepersonaldata', now()->addMinutes(60 * 60), ['id' => Auth::id()]
        );
        $u->notify(
            new deletePersonalDataNotification([
                'url' => $url
            ])
        );

        Session::flash('profile_message', __('user.delete.verify_needed'));

        return redirect()->route('user.profile');
    }

    /**
     * Delete all personal data, and remove user from all groups
     */
    public function deletePersonalData($id) {
        if(Auth::id() != $id) {
            abort('403');
        }
        // dd('itt');
        $user = User::findOrFail(Auth::id());

        // The signed link is valid for 60 hours, during which the state can change
        // (the other admin may leave the group), so it must be checked here too.
        if ($blocked = $this->blockedReason($user)) {
            Session::flash('profile_message', $blocked);

            return redirect()->route('user.profile');
        }

        $user->anonymize();

        $groups = $user->userGroups()->get(['groups.id'])->toArray();
        foreach($groups as $group) {
            $logout = new GroupUserMoves($group['id'], Auth::id());
            $logout->detach();
        }        

        Session::flush();
        
        Auth::logout();
        Session::flash('status', __('user.delete.success'));

        return redirect('login');
    }

    /**
     * The text of the reason blocking anonymization, or null if there is none.
     *
     * The rule comes from AnonymizationPolicy, the same one used by the daily
     * commands and User::anonymize() - so the user gets the same decision
     * everywhere (TODO 12.2).
     */
    private function blockedReason(User $user): ?string
    {
        return AnonymizationPolicy::for($user)->reason();
    }

}
