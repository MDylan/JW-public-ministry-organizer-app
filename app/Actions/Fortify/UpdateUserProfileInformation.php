<?php

namespace App\Actions\Fortify;

use Illuminate\Contracts\Auth\MustVerifyEmail;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\Rule;
use Laravel\Fortify\Contracts\UpdatesUserProfileInformation;

class UpdateUserProfileInformation implements UpdatesUserProfileInformation
{
    /**
     * Validate and update the given user's profile information.
     *
     * @param  mixed  $user
     * @param  array  $input
     * @return void
     */
    public function update($user, array $input)
    {
        $input['calendars_keys'] = [];
        if(isset($input['calendars'])) {
            $input['calendars_keys'] = array_keys($input['calendars']);
        }

        Validator::make($input, [
            'name' => ['required', 'string', 'max:50', 'min:2'],
            'email' => [
                'required',
                'string',
                'email:filter',
                'max:255',
                Rule::unique('users')->ignore($user->id),
            ],
            'phone_number' => ['numeric'],
            'calendars_keys' => ['sometimes', 'array', Rule::In(config('events.calendars'))]
        ])->validate(); //->validateWithBag('updateProfileInformation');

        if ($input['email'] !== $user->email &&
            $user instanceof MustVerifyEmail) {
            $this->updateVerifiedUser($user, $input);
        } else {
            $user->forceFill([
                'name' => $input['name'],
                'phone_number' => $input['phone_number'],
                'email' => $input['email'],
                'calendars' => isset($input['calendars']) ? $input['calendars'] : null
            ])->save();
        }
    }

    /**
     * Update the given verified user's profile information.
     *
     * @param  mixed  $user
     * @param  array  $input
     * @return void
     */
    protected function updateVerifiedUser($user, array $input)
    {
        $user->forceFill([
            'name' => $input['name'],
            'phone_number' => $input['phone_number'],
            'email' => $input['email'],
            'email_verified_at' => null,
        ])->save();

        $user->sendEmailVerificationNotification();
    }
}
