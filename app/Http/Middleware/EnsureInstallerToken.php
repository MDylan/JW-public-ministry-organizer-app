<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

/**
 * A telepítő hozzáférés-védelme.
 *
 * A PROBLÉMA
 *
 * A `setup/*` útvonalcsoport egyetlen jogosultsági ellenőrzést sem hordozott:
 * se `auth`, se gate, se aláírás. A telepítési ablakban tehát BÁRKI, aki
 * ismerte a címet, létrehozhatott `mainAdmin` fiókot - ismételten, ahányszor
 * csak akart -, és bárki lezárhatta a telepítőt, mert a `setup.complete`
 * egyszerű GET-en írta ki a sentinel fájlt. Egy friss telepítés a
 * DNS-átállástól az első admin létrehozásáig teljesen nyitva állt.
 *
 * Bejelentkezéshez kötni nem lehet: a telepítés PONTOSAN az a szakasz, amikor
 * még nincs felhasználó. Az iparági megoldás ezért a "bizonyítsd, hogy hozzáférsz
 * a szerver fájlrendszeréhez" ellenőrzés, és ez a middleware is azt csinálja.
 *
 * HOGYAN MŰKÖDIK
 *
 * A telepítő első megnyitásakor generálunk egy véletlen tokent, és kiírjuk a
 * `storage/app/installer-token.txt` fájlba. A telepítést végző személynek ezt
 * kell beírnia a nyitóképernyőn; onnantól a munkamenet hordozza. A tokent tehát
 * csak az tudja megadni, aki a szerver fájljaihoz hozzáfér - aki pedig hozzáfér,
 * annak ez a védelem amúgy sem akadály.
 *
 * A token a `setup.complete` lefutásakor törlődik, a sentinel fájllal együtt.
 *
 * KIVÉTEL
 *
 * A nyitóképernyő (`setup.welcome`) és a token beküldése MAGA nem lehet a kapun
 * belül, különben nincs hova beírni. Ez a két útvonal ezért nyitva marad; érdemi
 * műveletet egyik sem végez.
 */
class EnsureInstallerToken
{
    /** A tokent hordozó fájl a `local` diszken (storage/app). */
    public const TOKEN_FILE = 'installer-token.txt';

    /** A munkamenet kulcsa, ahol a már megadott token él. */
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
     * A hatályos token; ha még nincs, generál egyet.
     *
     * A generálás szándékosan lusta: a fájl csak akkor keletkezik, amikor
     * valaki tényleg megnyitja a telepítőt.
     */
    public static function currentToken(): string
    {
        if (! Storage::exists(self::TOKEN_FILE)) {
            Storage::put(self::TOKEN_FILE, Str::random(32));
        }

        return trim((string) Storage::get(self::TOKEN_FILE));
    }

    /** A telepítés végén a token elveszti a jelentését. */
    public static function forget(): void
    {
        if (Storage::exists(self::TOKEN_FILE)) {
            Storage::delete(self::TOKEN_FILE);
        }
    }
}
