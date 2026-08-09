<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;
use Laravel\Fortify\Fortify;

/**
 * Szigorú e-mail-formátum a Fortify saját, vendorban élő végpontjain.
 *
 * A Laravel alapértelmezett `email` szabálya RFCValidation-t használ, ami
 * ELFOGADJA a CR/LF karaktereket a címben (GHSA-5vg9-5847-vvmq, high). Onnan a
 * cím levélfejlécbe kerül, ahol a sortörés új fejlécet nyit: a támadó
 * befolyásolhatja a levél tartalmát, más címzettnek kézbesíttetheti, vagy a
 * levelezőt idegen üzenetek küldésére bírhatja. A javítás csak a 12.60.0-ban
 * van meg, a Laravel 8-ra nincs backport, ezért ez a réteg a válasz.
 *
 * Az alkalmazás SAJÁT validációi mind `email:filter`-t használnak
 * (CreateNewUser, UpdateUserProfileInformation, Admin\Users\ListUsers,
 * Groups\ListUsers), ami `filter_var(FILTER_VALIDATE_EMAIL)`-lel dolgozik és a
 * CRLF-et eleve elutasítja - ott nincs teendő. Két végpont maradt csupasz
 * `email` szabállyal, és mindkettő a VENDORBAN van, ahol a szabály nem
 * szerkeszthető anélkül, hogy a következő `composer update` felülírná:
 *
 * - `POST /forgot-password` - ANONIM, és a megadott címre levelet küld;
 * - `POST /reset-password` - a jelszó-visszaállító tokent oldja fel cím szerint.
 *
 * A middleware ugyanazt a szabályt futtatja rájuk, mint az alkalmazás a saját
 * űrlapjaira, tehát a hibaüzenet és az űrlapra való visszatérés is a megszokott.
 * A mező neve paraméterezhető; alapból a `Fortify::email()` beállítást követi.
 */
class EnsureWellFormedEmail
{
    public function handle(Request $request, Closure $next, ?string $field = null)
    {
        $field = $field ?: Fortify::email();

        Validator::make($request->all(), [
            $field => ['required', 'string', 'email:filter'],
        ])->validate();

        return $next($request);
    }
}
