<?php

namespace App\Http\Controllers;

use App\Classes\GroupUserMoves;
use App\Models\User;
use App\Notifications\deletePersonalDataNotification;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Config;
use Illuminate\Support\Facades\Session;
use Illuminate\Support\Facades\URL;

class deletePersonalDataController extends Controller
{
    /**
     * Send an email to user, to confirm personal data deletion
     */
    public function asktodelete() {

        $u = User::findOrFail(Auth::id());
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

}
