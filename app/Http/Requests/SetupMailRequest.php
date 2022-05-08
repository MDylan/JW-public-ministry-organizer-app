<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class SetupMailRequest extends FormRequest
{
    /**
     * Determine if the user is authorized to make this request.
     *
     * @return bool
     */
    public function authorize()
    {
        return true;
    }

    /**
     * Get the validation rules that apply to the request.
     *
     * @return array
     */
    public function rules()
    {
        return [
            'MAIL_MAILER' => 'required|in:smtp,sendmail,phpmail',
            'MAIL_HOST' => 'required_if:MAIL_MAILER,smtp',
            'MAIL_PORT' => 'required_if:MAIL_MAILER,smtp',
            'MAIL_ENCRYPTION' => 'required_if:MAIL_MAILER,smtp',
            'MAIL_USERNAME' => 'required_if:MAIL_MAILER,smtp',
            'MAIL_PASSWORD' => 'required_if:MAIL_MAILER,smtp',
            'MAIL_FROM_ADDRESS' => 'required|email', 
        ];
    }

    /**
     * Get custom attributes for validator errors.
     *
     * @return array
     */
    public function attributes()
    {
        return [
            'MAIL_MAILER' =>  trans('setup.mail_validation.MAIL_MAILER'),
            'MAIL_HOST' =>  trans('setup.mail_validation.MAIL_HOST'),
            'MAIL_PORT' =>  trans('setup.mail_validation.MAIL_PORT'),
            'MAIL_ENCRYPTION' =>  trans('setup.mail_validation.MAIL_ENCRYPTION'),
            'MAIL_USERNAME' => trans('setup.mail_validation.MAIL_USERNAME'),
            'MAIL_PASSWORD' => trans('Password'),
            'MAIL_FROM_ADDRESS' => trans('settings.mail_from_address')
        ];
    }
}
