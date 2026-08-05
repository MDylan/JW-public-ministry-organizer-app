<?php

namespace App\Observers\Concerns;

/**
 * Az observerek közös "ki okozta a változást" szerződése.
 *
 * Az observerek modell-eseményekre futnak, azok pedig nemcsak HTTP-kérésből
 * indulhatnak: ütemezett parancs, sorkezelő, konzol vagy seeder is írhat
 * modellt. Ilyenkor nincs bejelentkezett felhasználó.
 *
 * Korábban háromféle viselkedés élt egymás mellett ugyanerre a helyzetre -
 * volt, ahol kimaradt a naplóbejegyzés, volt, ahol 0 került bele, és volt,
 * ahol az auth()->user()->id fatalt adott. A TODO 10 egységesítette:
 *
 *     causer_id = 0  jelentése: a változást a RENDSZER okozta, nem ember.
 *
 * A log_histories.causer_id oszlopon nincs idegen kulcs, ezért a 0 biztonságos;
 * a LogHistory::user() reláció ilyenkor egyszerűen null-t ad vissza.
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
        return auth()->user()?->name ?? 'SYSTEM';
    }
}
