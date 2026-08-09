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

        // TODO 12.2: az utódlási feltétel a levélküldés ELŐTT dől el, hogy a
        // felhasználó azonnal megtudja, mit kell tennie. A GDPR-kérés nem
        // tűnhet el csendben.
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

        // Az aláírt link 60 órán át érvényes, közben változhat az állapot
        // (kiléphet mellőle a másik admin), ezért itt is ellenőrizni kell.
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
     * Az anonimizálást blokkoló ok szövege, vagy null, ha nincs ilyen.
     *
     * A szabály forrása az AnonymizationPolicy, ugyanaz, amit a napi parancsok
     * és a User::anonymize() használ - így a felhasználó ugyanazt a döntést
     * kapja mindenhol (TODO 12.2).
     */
    private function blockedReason(User $user): ?string
    {
        return AnonymizationPolicy::for($user)->reason();
    }

}
