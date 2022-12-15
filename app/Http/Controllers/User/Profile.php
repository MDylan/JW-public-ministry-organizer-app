<?php

namespace App\Http\Controllers\User;

use App\Http\Controllers\Controller;
use Illuminate\Support\Facades\Session;

class Profile extends Controller
{

    public function getUserFirstDay() {
        $firstDay = auth()->user()->firstDay;
        if($firstDay === null) {
            $first_day_name = date('l', strtotime("this week"));
            $firstDay = ($first_day_name == "Monday") ? 1 : 0;
        } 
        return $firstDay;
    }

    public function resendNewEmailVerification() {
        if(auth()->user()->getPendingEmail()) {
            auth()->user()->resendPendingEmailVerificationMail();
            Session::flash('success', __('user.newEmail.verify_sent_again'));
        } else {
            Session::flash('profile_message', __('user.newEmail.not_pending'));
        }
        return redirect()->route('user.profile');
    }

    /**
     * Redirect user after email verification.
     */
    public function redirectAfterNewEmailVerification() {
        if(session()->has('verified')) {
            if(auth()->check()) {
                return redirect()->route('home.home')->with('verified', true);
            } else {
                return redirect()->route('login')->with('verified', true);
            }
        } else {
            return redirect()->route('home.home');
        }
    }

    public function __invoke()
    {        
        return view('user.profile', [
            'firstday' => $this->getUserFirstDay(),
            'notifications' => [
                'EventDeletedAdminsNotification',
                'EventDeletedNotification',
                'UserProfileChangedNotification',
                'GroupPriorityMessageNotification'
            ]
        ]);
    }
}
