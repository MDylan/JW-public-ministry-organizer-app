<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

/**
 * Access protection for the installer.
 *
 * THE PROBLEM
 *
 * The `setup/*` route group carried no authorization check whatsoever: no
 * `auth`, no gate, no signature. During the installation window, therefore,
 * ANYONE who knew the URL could create a `mainAdmin` account - repeatedly, as
 * many times as they wanted -, and anyone could lock the installer, because
 * `setup.complete` wrote out the sentinel file on a plain GET. A fresh
 * install stood completely open from the DNS cutover to the creation of the
 * first admin.
 *
 * Tying it to login isn't possible: installation is PRECISELY the phase when
 * there's no user yet. The industry solution is therefore a "prove you have
 * access to the server's filesystem" check, and that's what this middleware
 * does too.
 *
 * HOW IT WORKS
 *
 * On the installer's first open, we generate a random token and write it to
 * the `storage/app/installer-token.txt` file. The person performing the
 * install has to enter it on the welcome screen; from then on the session
 * carries it. So the token can only be supplied by someone who has access to
 * the server's files - and anyone who has that access isn't blocked by this
 * protection anyway.
 *
 * The token is deleted, together with the sentinel file, when
 * `setup.complete` runs.
 *
 * EXCEPTION
 *
 * The welcome screen (`setup.welcome`) and the token submission itself
 * cannot be behind the gate, otherwise there'd be nowhere to enter it. These
 * two routes therefore stay open; neither performs any meaningful action.
 */
class EnsureInstallerToken
{
    /** The file carrying the token, on the `local` disk (storage/app). */
    public const TOKEN_FILE = 'installer-token.txt';

    /** The session key where the already-submitted token lives. */
    public const SESSION_KEY = 'installer_token';

    public function handle(Request $request, Closure $next)
    {
        if (self::isUnlocked($request)) {
            return $next($request);
        }

        return redirect()
            ->route('setup.welcome')
            ->with('status', __('setup.token.required'));
    }

    public static function isUnlocked(Request $request): bool
    {
        return $request->session()->get(self::SESSION_KEY) === self::currentToken();
    }

    /**
     * The current token; generates one if it doesn't exist yet.
     *
     * The generation is deliberately lazy: the file is only created when
     * someone actually opens the installer.
     */
    public static function currentToken(): string
    {
        if (! Storage::exists(self::TOKEN_FILE)) {
            Storage::put(self::TOKEN_FILE, Str::random(32));
        }

        return trim((string) Storage::get(self::TOKEN_FILE));
    }

    /** At the end of installation the token loses its meaning. */
    public static function forget(): void
    {
        if (Storage::exists(self::TOKEN_FILE)) {
            Storage::delete(self::TOKEN_FILE);
        }
    }
}
