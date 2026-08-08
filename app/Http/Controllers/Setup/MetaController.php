<?php

namespace App\Http\Controllers\Setup;

use App\Http\Controllers\Controller;
use App\Http\Middleware\EnsureInstallerToken;
use App\Models\User;
use Illuminate\Contracts\View\View;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
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
            'languages' => $languages,
            'unlocked'  => EnsureInstallerToken::isUnlocked(request()),
        ]);
    }

    /**
     * A telepítő feloldása a szerveren elhelyezett tokennel.
     *
     * A tokent a middleware generálja a storage/app/installer-token.txt fájlba;
     * megadni tehát csak az tudja, aki a szerver fájljaihoz hozzáfér. Ez az
     * egyetlen ellenőrzés, ami a telepítés szakaszában értelmezhető - itt még
     * definíció szerint nincs felhasználó, akihez kötni lehetne.
     */
    public function unlock(Request $request): RedirectResponse
    {
        $request->validate(['token' => 'required|string']);

        if (! hash_equals(EnsureInstallerToken::currentToken(), trim($request->input('token')))) {
            return back()
                ->withErrors(['token' => __('setup.token.invalid')])
                ->withInput();
        }

        $request->session()->put(EnsureInstallerToken::SESSION_KEY, EnsureInstallerToken::currentToken());

        return redirect()->route('setup.requirements');
    }

    /**
     * Display a final screen after the setup was successful.
     *
     * @return View
     */
    public function complete()
    {
        // A sentinel kiírása KORÁBBAN feltétel nélkül futott egy GET-en, tehát
        // bárki lezárhatta a telepítőt, mielőtt az első adminisztrátor
        // létrejött volna - és onnantól a `setup/*` csoport nem is
        // regisztrálódik, vagyis a telepítés befejezhetetlenné vált.
        //
        // A sentinel jelentése "a telepítés befejeződött", és ez pontosan azt
        // jelenti, hogy van adminisztrátori fiók. Ezt ellenőrizzük.
        if (! User::where('role', 'mainAdmin')->exists()) {
            return redirect()
                ->route('setup.account')
                ->with('status', __('setup.token.account_required'));
        }

        Storage::put('installed.txt', "The program installed successfully at ".date("Y-m-d H:i:s").".\n\nPlease DO NOT DELETE this file, unless you want to reinstall this program.");

        // A token elveszti a jelentését; ne maradjon a lemezen.
        EnsureInstallerToken::forget();

        return view('setup.complete');
    }
}