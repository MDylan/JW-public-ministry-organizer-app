<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Session;
use Illuminate\Support\Facades\URL;

class LoginToUserController extends Controller
{
    public function login(User $user) {
        if(auth()->user()->role == 'mainAdmin') {
            $login_back_url = URL::temporarySignedRoute(
                'admin.loginback', now()->addHours(12), ['id' => auth()->user()->id]
            );
            session(['loginback_remember' => auth()->user()->getRememberToken() === null ? false : true]);
            Auth::logout(); 
            Auth::loginUsingId($user->id);
            Session::flash('message', __('user.logged_to', ['name' => $user->name]));
            session(['loginback_url' => $login_back_url]);
        } 
        return redirect(route('home.home'));
    }

    public function loginBack($id, Request $request) {
        if ($request->hasValidSignature()) {
            Session::forget('loginback_url');
            $remember = Session::get('loginback_remember') ?? false;
            Auth::logout(); 
            Auth::loginUsingId($id, $remember);
            Session::flash('message', __('user.logged_back'));
        } 
        return redirect(route('home.home'));
    }
}
