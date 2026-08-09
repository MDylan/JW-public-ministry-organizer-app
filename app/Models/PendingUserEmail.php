<?php

namespace App\Models;

use ProtoneMedia\LaravelVerifyNewEmail\PendingUserEmail as VendorPendingUserEmail;

/**
 * A `protonemedia/laravel-verify-new-email` PendingUserEmail modelljének
 * projekt-oldali leszármazottja.
 *
 * MIÉRT LÉTEZIK
 *
 * A csomag `activate()` metódusa nem nézi meg, hogy a felhasználó időközben
 * anonimizálva lett-e. A GDPR-anonimizálás a users.email mezőt lecseréli
 * (User::getAnonymizedEmail()), a pending_user_emails sorát viszont senki nem
 * takarította el - ezért a kiküldött aláírt link a lejáratáig VISSZAÍRTA a
 * valódi címet az anonimizált felhasználóra, ráadásul verifikáltnak jelölve.
 * Az anonimizálás így visszafordíthatóvá vált egy e-mailben ülő linkkel.
 *
 * A csomag `config/verify-new-email.php` `model` kulcsán keresztül cserélhető,
 * ezért nem kellett a csomagot lecserélni ahhoz, hogy ez a lyuk bezáruljon.
 * A teljes kiváltás a TODO 33.5 feladata a v2 vonalon.
 *
 * A másik fele - hogy az anonimizálás egyáltalán ne hagyjon hátra függő címet -
 * a User::anonymize()-ban van (v1-patch B12).
 */
class PendingUserEmail extends VendorPendingUserEmail
{
    protected $table = 'pending_user_emails';

    /**
     * Anonimizált felhasználóra nem aktiválunk címet.
     *
     * A sort ilyenkor el is dobjuk: a link már nem érhet célt, és a valódi cím
     * nem maradhat a táblában. Ezzel a lejáratra várás sem szükséges.
     */
    public function activate()
    {
        $user = $this->user;

        if ($user === null || (int) $user->isAnonymized === 1) {
            static::whereEmail($this->email)->get()->each->delete();

            return;
        }

        parent::activate();
    }
}
