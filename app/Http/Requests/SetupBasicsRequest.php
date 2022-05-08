<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class SetupBasicsRequest extends FormRequest
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
            'APP_NAME' => 'required|string|min:3',
            'APP_URL' => 'required|url',
            'TIMEZONE' => 'required|string',
            'APP_LANG' => 'required|string',
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
            'APP_NAME' => trans('settings.app_name'),
            'APP_URL' => trans('settings.app_url'),
            'TIMEZONE' => trans('settings.timezone'),
            'APP_LANG' => trans('settings.languages.default'),
        ];
    }
}
