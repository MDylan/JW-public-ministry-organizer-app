<?php

namespace App\Http\Controllers\Setup;

use App\Classes\setEnvironment;
use App\Http\Controllers\Controller;
use App\Http\Requests\SetupBasicsRequest;
use Illuminate\Contracts\View\View;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\File;

class BasicsController extends Controller
{

    /**
     * Display the form for configuration of the mail.
     *
     * @return View
     */
    public function index(): View
    {
        $timezone = date_default_timezone_get();

        $languages = $this->languages();

        return view('setup.basics', [
            'timezone' => $timezone,
            'languages' => $languages
        ]);
    }

    private function languages() {
        $filesInFolder = File::files(base_path('resources/lang'));
        $languages = ['en'];
        foreach($filesInFolder as $path) { 
              $file = pathinfo($path);
              $languages[] = $file['filename'];
        }
        asort($languages);
        return $languages;
    }

    /**
     * Handle the test and configuration of a new mail connection.
     *
     * @param SetupBasicsRequest $request
     * @return RedirectResponse
     */
    public function configure(SetupBasicsRequest $request): RedirectResponse
    {
        $config = [
            'APP_NAME' => '"'.addslashes(trim($request->input('APP_NAME'))).'"',
            'APP_URL' => '"'.addslashes(trim($request->input('APP_URL'))).'"',
            'TIMEZONE' => '"'.addslashes(trim($request->input('TIMEZONE'))).'"',
            'APP_LANG' => '"'.addslashes(trim($request->input('APP_LANG'))).'"',
        ];

        setEnvironment::setEnvironmentValue($config);

        return redirect()->route('setup.database');
    }

}
