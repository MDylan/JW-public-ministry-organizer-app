<?php

namespace App\Http\Controllers;

use App\Actions\Fortify\PasswordValidationRules;
use App\Models\User;
use App\Notifications\FinishRegistrationSuccessNotification;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\URL;
use Illuminate\Support\Facades\Validator;
use Laravel\Fortify\Rules\Password;

class FinishRegistration extends Controller
{
    use PasswordValidationRules;

    static function index($id) {
        $user = User::where('id', '=', $id)
        ->where('role', '=', 'registered')
        ->firstOrFail();

        // session('language', $user->language);
        
        $cancelUrl = URL::temporarySignedRoute(
            'finish_registration_cancel', now()->addMinutes(60), ['id' => $user->id]
        );
        
        return view('auth.finish-registration', [
            'user' => $user,
            'id' => $user->id,
            'cancelUrl' => $cancelUrl
        ]);
    }

    public function register($id, Request $request) {

        Validator::make($request->all(), [
            'name' => ['required', 'string', 'max:50', 'min:2'],
            'phone_number' => ['numeric'],
            'password' => $this->passwordRules(),
            'terms' => ['required']
        ])->validate();

        User::where('id', '=', $id)
        ->where('role', '=', 'registered')
        ->update([
                'name' =>  $request->input('name'),
                'phone_number' =>  $request->input('phone_number'),
                'password' => Hash::make( $request->input('password')),
                'role' => 'activated',
                'email_verified_at' => now()
        ]);

        $user = User::findOrFail($id);
        $credentials = [
            'email' => $user->email,
            'password' => $request->input('password')
        ];

        if (Auth::attempt($credentials)) {
            $user->notify(
                new FinishRegistrationSuccessNotification()
            );
            $request->session()->flash('status', __('user.finish.done'));
            $request->session()->regenerate();
            return redirect('groups');
        }
    }

    public function cancel($id, Request $request) {

        User::where('id', '=', $id)
                ->whereNull('email_verified_at')
                ->where('role', '=', 'registered')->delete();

        $request->session()->flash('status', __('user.finish.cancelDone'));

        return redirect('/');
    }
}
