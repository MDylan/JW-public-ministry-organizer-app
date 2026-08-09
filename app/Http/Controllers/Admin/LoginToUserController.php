<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Session;

/**
 * Adminisztrátori megszemélyesítés.
 *
 * A visszaút KORÁBBAN egy 12 órás aláírt URL volt, amit a `login()` gyártott az
 * admin saját azonosítójával, és a `loginBack()` mindössze az aláírást nézte -
 * azt nem, hogy az URL-ben álló `{id}` a JELEN munkamenet eredeti admin
 * felhasználója-e, sem azt, hogy az az azonosító `mainAdmin`-hoz tartozik-e.
 * Az URL ezzel jogosultságátadási tokenné vált: a session-ben tárolva megjelent
 * a navigációs sávban, tetszőleges felhasználói azonosítót hordozhatott, és aki
 * megszerezte, 12 órán át bármikor visszajátszhatta.
 *
 * Az azonosító innentől KIZÁRÓLAG szerveroldali sessionben él, az URL semmilyen
 * identitást nem hordoz, mindkét váltás POST + CSRF, és a visszaút egyszer
 * használható.
 */
class LoginToUserController extends Controller
{
    /**
     * A megszemélyesített admin azonosítóját tartó session-kulcs.
     */
    public const SESSION_KEY = 'impersonator_id';

    public function login(User $user)
    {
        $admin = auth()->user();

        // Csak főadmin, csak egyszeres mélységben, és nem önmagára. A láncolt
        // megszemélyesítés azért tilos, mert felülírná az eltárolt eredeti
        // azonosítót, és a visszaút a köztes felhasználóra mutatna.
        if ($admin->role !== 'mainAdmin'
            || Session::has(self::SESSION_KEY)
            || $admin->id === $user->id) {
            return redirect(route('home.home'));
        }

        $adminId = $admin->id;
        Auth::logout();
        Auth::loginUsingId($user->id, false);

        // Az Auth::login() belül session->migrate(true)-t hív, tehát a session
        // azonosító regenerálódik; a kulcsokat SZÁNDÉKOSAN utána írjuk, hogy
        // biztosan a friss munkamenetbe kerüljenek.
        Session::put(self::SESSION_KEY, $adminId);
        $this->forgetPasswordConfirmation();

        Session::flash('message', __('user.logged_to', ['name' => $user->name]));

        return redirect(route('home.home'));
    }

    public function loginBack(Request $request)
    {
        $adminId = Session::get(self::SESSION_KEY);

        // A kulcsot a beléptetés ELŐTT dobjuk el: a visszaút egyszer
        // használható, és egy félbeszakadt kérés sem hagy használható maradékot.
        Session::forget(self::SESSION_KEY);

        if ($adminId === null) {
            return redirect(route('home.home'));
        }

        // A szerepet ÚJRA ellenőrizzük: a megszemélyesítés óta az eredeti fiók
        // elveszíthette a főadmin jogot, vagy törölhették.
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
     * A jelszó-megerősítés nem öröklődhet át egy identitásváltáson: a
     * `password.confirm` mögötti oldalak különben az ELŐZŐ felhasználó
     * megerősítésével nyílnának meg az újnak.
     */
    private function forgetPasswordConfirmation(): void
    {
        Session::forget('auth.password_confirmed_at');
    }
}
