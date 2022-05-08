<?php

namespace App\Http\Controllers\Setup;

use App\Http\Controllers\Controller;
use Illuminate\Contracts\View\View;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Storage;

class MetaController extends Controller
{
    /**
     * Display a very simple welcome screen to start the setup process.
     *
     * @return View
     */
    public function welcome(): View
    {
        $filesInFolder = File::files(base_path('resources/lang'));
        $languages = ['en'];
        foreach($filesInFolder as $path) { 
              $file = pathinfo($path);
              $languages[] = $file['filename'];
        }
        asort($languages);

        if (request('lang')) {
            $new_language = request('lang');
            if(in_array($new_language, $languages)) {
                session()->put('language', $new_language);
                app()->setLocale($new_language);
            }
        }

        return view('setup.welcome', [
            'languages' => $languages
        ]);
    }

    /**
     * Display a final screen after the setup was successful.
     *
     * @return View
     */
    public function complete(): View
    {
        Storage::put('installed.txt', "The program installed successfully at ".date("Y-m-d H:i:s").".\n\nPlease DO NOT DELETE this file, unless you want to reinstall this program.");

        return view('setup.complete');
    }
}