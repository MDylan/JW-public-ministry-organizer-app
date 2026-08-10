<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

/**
 * TODO 33.2: moved in from Dialect\Gdpr\Http\Requests\GdprDownload.
 *
 * It is the reason the two rejections on the export differ, and both are
 * pinned by tests/Feature/Gdpr/DataExportTest.php: a MISSING password fails
 * here, so the caller gets a 302 with a validation error, while a WRONG one
 * reaches GdprController::download() and is rejected by its abort_unless()
 * with a 403.
 */
class GdprDownload extends FormRequest
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
            'password' => 'required|string',
        ];
    }
}
