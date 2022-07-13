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
        $input['hidden_fields_keys'] = [];
        if(isset($input['hidden_fields'])) {
            $input['hidden_fields_keys'] = array_keys($input['hidden_fields']);
        }
        // dd($input['opted_out_of_notifications']);
        Validator::make($input, [
            'name' => ['required', 'string', 'max:50', 'min:2'],
            'email' => [
                'required',
                'string',
                'email:filter',
                'max:255',
                Rule::unique('users')->ignore($user->id),
            ],
            'phone_number' => ['nullable', 'numeric'],
            'calendars_keys' => ['sometimes', 'array', Rule::In(config('events.calendars'))],
            'hidden_fields_keys' => ['sometimes', 'array', Rule::In(['email', 'phone'])],
            'firstDay' => ['sometimes', 'numeric', 'in:0,1'],
            'opted_out_of_notifications' => ['sometimes', 'array']
        ])->validate(); //->validateWithBag('updateProfileInformation');

        if ($input['email'] !== $user->email &&
            $user instanceof MustVerifyEmail) {
            $this->updateVerifiedUser($user, $input);
        } else {
            $user->forceFill([
                'name' => $input['name'],
                'phone_number' => $input['phone_number'],
                'email' => $input['email'],
                'calendars' => isset($input['calendars']) ? $input['calendars'] : null,
                'hidden_fields' => isset($input['hidden_fields']) ? $input['hidden_fields'] : null,
                'firstDay' => isset($input['firstDay']) ? $input['firstDay'] : null,
                'opted_out_of_notifications' => isset($input['opted_out_of_notifications']) ? $input['opted_out_of_notifications'] : null,
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
