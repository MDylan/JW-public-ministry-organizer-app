<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Session;

/**
 * Administrator impersonation.
 *
 * The way back USED TO BE a 12-hour signed URL that `login()` generated with the
 * admin's own ID, and `loginBack()` only checked the signature -
 * not whether the `{id}` in the URL was the CURRENT session's original admin
 * user, nor whether that ID belonged to `mainAdmin`.
 * This turned the URL into a privilege-transfer token: stored in the session, it showed
 * up in the navigation bar, could carry an arbitrary user ID, and whoever
 * obtained it could replay it at any time for 12 hours.
 *
 * From now on the ID lives EXCLUSIVELY in the server-side session, the URL carries no
 * identity whatsoever, both transitions are POST + CSRF, and the way back is usable
 * only once.
 */
class LoginToUserController extends Controller
{
    /**
     * The session key holding the impersonating admin's ID.
     */
    public const SESSION_KEY = 'impersonator_id';

    public function login(User $user)
    {
        $admin = auth()->user();

        // Only a main admin, only one level deep, and not onto themselves. Chained
        // impersonation is forbidden because it would overwrite the stored original
        // ID, and the way back would then point to the intermediate user.
        if ($admin->role !== 'mainAdmin'
            || Session::has(self::SESSION_KEY)
            || $admin->id === $user->id) {
            return redirect(route('home.home'));
        }

        $adminId = $admin->id;
        Auth::logout();
        Auth::loginUsingId($user->id, false);

        // Auth::login() internally calls session->migrate(true), so the session
        // ID gets regenerated; we DELIBERATELY write the keys afterward, to
        // make sure they end up in the fresh session.
        Session::put(self::SESSION_KEY, $adminId);
        $this->forgetPasswordConfirmation();

        Session::flash('message', __('user.logged_to', ['name' => $user->name]));

        return redirect(route('home.home'));
    }

    public function loginBack(Request $request)
    {
        $adminId = Session::get(self::SESSION_KEY);

        // The key is dropped BEFORE logging in: the way back is usable
        // only once, and even an interrupted request leaves no usable leftover.
        Session::forget(self::SESSION_KEY);

        if ($adminId === null) {
            return redirect(route('home.home'));
        }

        // We check the role AGAIN: since the impersonation started, the original account
        // may have lost main admin rights, or been deleted.
        $admin = User::find($adminId);

        if ($admin === null || $admin->role !== 'mainAdmin') {
            return redirect(route('home.home'));
        }

        Auth::logout();
        Auth::loginUsingId($admin->id, false);
        $this->forgetPasswordConfirmation();

        Session::flash('message', __('user.logged_back'));

        return redirect(route('home.home'));
    }

    /**
     * Password confirmation must not carry over across an identity switch: otherwise
     * the pages behind `password.confirm` would open for the new user
     * using the PREVIOUS user's confirmation.
     */
    private function forgetPasswordConfirmation(): void
    {
        Session::forget('auth.password_confirmed_at');
    }
}
