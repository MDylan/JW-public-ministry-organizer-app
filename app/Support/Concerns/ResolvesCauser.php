<?php

namespace App\Support\Concerns;

use App\Models\User;

/**
 * Közös "ki okozta a változást" szerződés observereknek, joboknak és a
 * hozzájuk tartozó szolgáltatás-osztályoknak.
 *
 * A modell-események és a háttérfeldolgozás nem csak HTTP-kérésből indulhat:
 * ütemezett parancs, sorkezelő, konzol vagy seeder is írhat modellt. Ilyenkor
 * nincs bejelentkezett felhasználó.
 *
 * Korábban háromféle viselkedés élt egymás mellett ugyanerre a helyzetre -
 * volt, ahol kimaradt a naplóbejegyzés, volt, ahol 0 került bele, és volt,
 * ahol az auth()->user()->id fatalt adott. A TODO 10 egységesítette:
 *
 *     causer_id = 0  jelentése: a változást a RENDSZER okozta, nem ember.
 *
 * A log_histories.causer_id oszlopon nincs idegen kulcs, ezért a 0 biztonságos;
 * a LogHistory::user() reláció ilyenkor egyszerűen null-t ad vissza.
 *
 * FONTOS: a 0 azonosítót a fogadó oldalnak is kezelnie kell. A jobok és a
 * CalculateDatesEvents ezért a causerNameFor()-t használják, nem közvetlen
 * User::find()-ot - egy sorkezelőben az auth() sosem ad felhasználót, és a
 * User::find(0) mindig null.
 */
trait ResolvesCauser
{
    /**
     * A változást okozó felhasználó azonosítója, vagy 0, ha a rendszer.
     */
    protected function causerId(): int
    {
        return auth()->user()?->id ?? 0;
    }

    /**
     * A változást okozó neve az értesítésekhez. Rendszer esetén "SYSTEM" -
     * ezt a szöveget használta az EventObserver::updated() már korábban is.
     */
    protected function causerName(): string
    {
        return static::causerNameFor(auth()->user()?->id);
    }

    /**
     * Egy KORÁBBAN RÖGZÍTETT okozó-azonosítóhoz tartozó név.
     *
     * A jobok a dispatch pillanatában kapják meg az azonosítót, és később,
     * egy sorkezelőben futnak le - ott az auth() már nem használható
     * tartaléknak. A 0, a false és a null egyaránt rendszer-okozót jelent.
     *
     * Statikus, mert a CalculateDatesEvents::generate() is statikus.
     */
    protected static function causerNameFor($userId): string
    {
        if (empty($userId)) {
            return 'SYSTEM';
        }

        return User::find($userId)?->name ?? 'SYSTEM';
    }
}
