<?php

namespace App\Http\Controllers;

use App\Http\Controllers\Controller;
use App\Http\Requests\UserPasswordRequest;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Session;

class LogoutOtherDevicesController extends Controller
{
    public function logout(UserPasswordRequest $request) {

        $credentials = [
            $request->user()->getAuthIdentifierName() => $request->user()->getAuthIdentifier(),
            'password'                                => $request->input('password'),
        ];

        abort_unless(Auth::attempt($credentials), 403);

        $currentPassword = $request->input('password');
        $res = Auth::logoutOtherDevices($currentPassword);

        if($res) {
            Session::flash('message', __('user.logout_other_devices_success'));
        } else {
            Session::flash('message', __('user.logout_other_devices_error')); 
        }

        return redirect()->route('user.profile');
    }
}
