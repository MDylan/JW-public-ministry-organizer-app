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
        // TODO 38: lang_path(), never a literal. Laravel 9 moved the language
        // directory to the project root, and the framework resolves it from
        // what is on disk (Application::bindPathsInContainer():349-355) - so
        // spelling the path out here is how this screen would end up reading a
        // different directory than every trans() call around it.
        $filesInFolder = File::files(lang_path());
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
     * Unlocking the installer with the token placed on the server.
     *
     * The middleware generates the token into storage/app/installer-token.txt;
     * so only someone with access to the server's files can supply it. This is the
     * only check that makes sense during the installation phase - at this point
     * there is, by definition, no user to tie it to yet.
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
        // Writing the sentinel used to run unconditionally on a GET, so
        // anyone could close off the installer before the first administrator
        // was created - and from that point the `setup/*` group would no longer
        // register, meaning the installation could never be completed.
        //
        // The sentinel's meaning is "the installation is complete", and that's exactly
        // what having an administrator account means. That's what we check here.
        if (! User::where('role', 'mainAdmin')->exists()) {
            return redirect()
                ->route('setup.account')
                ->with('status', __('setup.token.account_required'));
        }

        Storage::put('installed.txt', "The program installed successfully at ".date("Y-m-d H:i:s").".\n\nPlease DO NOT DELETE this file, unless you want to reinstall this program.");

        // The token loses its meaning; it shouldn't remain on disk.
        EnsureInstallerToken::forget();

        return view('setup.complete');
    }
}