<?php

namespace App\Http\Controllers\Setup;

use App\Actions\Fortify\CreateNewUser;
use App\Http\Controllers\Controller;
use Database\Seeders\DatabaseSeeder;
use Illuminate\Contracts\View\View;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Auth;
use Illuminate\Validation\ValidationException;

class AccountController extends Controller
{
    protected function redirectTo(): string
    {
        return route('setup.complete');
    }

    /**
     * Display the registration form for the first user account.
     *
     * @return View
     */
    public function index(): View
    {
        return view('setup.account');
    }

    /**
     * Validate and create the new user, then login him, and redirect him to the dashboard
     *
     * @param Request $request
     * @return RedirectResponse
     * @throws ValidationException
     */
    protected function register(Request $request): RedirectResponse
    {
        $user = (new CreateNewUser())->create($request->input());
        // The installer creates the user without Fortify, so no Registered
        // event fires and the framework's listener never sends the mail.
        // CreateNewUser used to send it itself; since TODO 55.3 that is the
        // listener's job, so this path sends it explicitly, as before.
        $user->sendEmailVerificationNotification();
        $user->update([
            'role' => 'mainAdmin',
            'email_verified_at' => now()
        ]);

        (new DatabaseSeeder())->callWith(\Database\Seeders\StaticPagesSetupSeeder::class, [
            'user_id' => $user->id
        ]);
        // Clearing the cache is done by StaticPageObserver (v1-patch B4).

        Auth::login($user, true);

        return redirect()->route('setup.complete');
    }
}