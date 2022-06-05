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
        $user->update([
            'role' => 'mainAdmin',
            'email_verified_at' => now()
        ]);

        (new DatabaseSeeder())->callWith(\Database\Seeders\StaticPagesSetupSeeder::class, [
            'user_id' => $user->id
        ]);
        cache()->forget('sidemenu_auth');
        cache()->forget('sidemenu_guest');

        Auth::login($user, true);

        return redirect()->route('setup.complete');
    }
}