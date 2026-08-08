<?php

namespace App\Observers;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Cache;

/**
 * A statikus oldalak oldalmenü-gyorsítótárának érvénytelenítése.
 *
 * MIÉRT LÉTEZIK
 *
 * A SetLocale middleware Cache::rememberForever('sidemenu_auth' / 'sidemenu_guest')
 * hívással építi az oldalmenüt, tehát a bejegyzésnek NINCS lejárata. Az ürítés
 * korábban mindössze két helyen történt meg kézzel: Admin\StaticPageEdit és a
 * telepítő AccountController. Bármi más - seeder, artisan parancs, import,
 * közvetlen modellírás, egy jövőbeli másik szerkesztő - létrehozhatott vagy
 * módosíthatott oldalt úgy, hogy az SOHA nem jelent meg a menüben.
 *
 * Az observer ezt a menü FORRÁSÁHOZ köti a hívási helyek helyett: ha a
 * StaticPage vagy a fordítása változik, a gyorsítótár ürül. A két kézi
 * forget() hívás ezért törölhető lett.
 *
 * A fordításokra is figyelünk, mert a menü a címeket jeleníti meg, azok pedig a
 * static_page_translations táblában élnek - egy puszta címátírás a StaticPage
 * sorát nem is érinti.
 *
 * Ugyanez az osztály szolgálja ki mindkét modellt, ezért a paraméter Model és
 * nem konkrét típus.
 */
class StaticPageObserver
{
    public function saved(Model $model)
    {
        self::flush();
    }

    public function deleted(Model $model)
    {
        self::flush();
    }

    /**
     * A modell-eseményeken kívüli ürítés belépési pontja.
     */
    public static function flush(): void
    {
        Cache::forget('sidemenu_auth');
        Cache::forget('sidemenu_guest');
    }
}
