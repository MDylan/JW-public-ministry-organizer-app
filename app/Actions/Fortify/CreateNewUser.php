<?php

namespace App\Actions\Fortify;

use App\Models\User;
use App\Notifications\UserRegisteredNotification;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\Rule;
use Laravel\Fortify\Contracts\CreatesNewUsers;
use Illuminate\Auth\Events\Registered;

class CreateNewUser implements CreatesNewUsers
{
    use PasswordValidationRules;

    /**
     * Validate and create a newly registered user.
     *
     * @param  array  $input
     * @return \App\Models\User
     */
    public function create(array $input)
    {
        if(config('settings_registration') == 1) {
            Validator::make($input, [
                'first_name' => ['required', 'string', 'max:50', 'min:2'],
                'last_name' => ['required', 'string', 'max:50', 'min:2'],
                'email' => [
                    'required',
                    'string',
                    'email',
                    'max:255',
                    Rule::unique(User::class),
                ],
                'phone' => ['numeric'],
                'password' => $this->passwordRules(),
                'terms' => ['required']
            ])->validate();

            $user = User::create([
                'first_name' => $input['first_name'],
                'last_name' => $input['last_name'],
                'email' => $input['email'],
                'phone' => $input['phone'],
                'password' => Hash::make($input['password']),
            ]);        
            // event(new Registered($user));
            $user->notify(new UserRegisteredNotification());
            
            return $user;
        } else {
            abort('403');
        }
    }
}
