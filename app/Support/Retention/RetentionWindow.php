<?php

namespace App\Support\Retention;

use Carbon\Carbon;

/**
 * v1-patch E: meddig visszamenőleg őrzünk adatot.
 *
 * Ez az EGYETLEN hely, ahol retenciós határnap keletkezik. A két törlő
 * parancs és a négy felületi korlát ugyanezeket a metódusokat hívja, mert
 * a kettőnek nem szabad elsodródnia egymástól: ha a naptár olyan hónapot
 * enged megnyitni, amit a purge már kiürített, a felület nem üres táblát
 * mutat, hanem hamis adatot - nullákat egy valóban ledolgozott időszakra.
 *
 * KÉT BUKTATÓ, AMI ITT VAN EGYSZER MEGOLDVA
 *
 * 1. A csoportadat-beállítás értékét CASTOLNI TILOS. A settings táblába a
 *    Livewire Admin\Settings validáció nélkül ír, a $state pedig publikus
 *    property - egy odacsempészett 'x' érték (int)-tel nulla lenne,
 *    subMonths(0) pedig a MAI napra tenné a padlót, azaz a parancs az
 *    egész day_stats és group_dates táblát letarolná. Ezért whitelist
 *    (config('retention.group_data_options')), nem konverzió, és minden
 *    ismeretlen érték - a null is - kikapcsolt állapotot jelent.
 *
 * 2. subMonthsNoOverflow(), és nap pontosságú összehasonlítás. A Carbon
 *    subMonths() alapból túlcsordul (2026-03-31 mínusz 13 hónap nála
 *    2025-03-03, nem 2025-02-28), az events.day és a day_stats.day pedig
 *    DATE oszlop: időponttal összevetve a határnapi sorok attól függően
 *    törlődnének vagy maradnának, hogy hány órakor futott a scheduler.
 *    A padló ezért mindig nap kezdete, és toDateString()-gel kell
 *    összehasonlítani.
 *
 * A visszaadott Carbon minden hívásnál friss példány, tehát a hívó
 * nyugodtan mutálhatja (startOfMonth(), format() stb.).
 */
class RetentionWindow
{
    /**
     * Az események padlója. Null, ha a GDPR-kezelés ki van kapcsolva -
     * ilyenkor semmilyen esemény nem törlődik.
     */
    public static function eventsFloor(): ?Carbon
    {
        if (! config('gdpr.enabled')) {
            return null;
        }

        return self::floor((int) config('retention.events_months'));
    }

    /**
     * A csoportadatok (day_stats, group_dates) padlója. Null, ha az admin
     * felületi beállítás kikapcsolt vagy ismeretlen értéken áll.
     *
     * Szándékosan NEM a gdpr.enabled kapcsolja: ezekben a táblákban nincs
     * személyes adat (csoport, nap, idősáv, darabszám), a törlésüket a
     * méret indokolja.
     */
    public static function groupDataFloor(): ?Carbon
    {
        $months = config('settings_group_data_retention');

        if (! is_scalar($months)) {
            return null;
        }

        $months = (string) $months;

        if (! in_array($months, config('retention.group_data_options', ['0']), true)) {
            return null;
        }

        if ($months === '0') {
            return null;
        }

        return self::floor((int) $months);
    }

    /**
     * A felületeken megjeleníthető legkorábbi nap: a két aktív padló közül
     * a KÉSŐBBI.
     *
     * Azért a későbbi, mert egy nézet annyit tud megbízhatóan mutatni,
     * amennyihez minden forrása megvan. Egy naptár, ami eseményt és
     * day_stats-ot is renderel, csak addig hiteles, ameddig mindkettő él.
     *
     * Vigyázat: csak azoknak a nézeteknek jó, amelyek MINDKÉT adatforrást
     * használják. Ami csak eseményt kérdez (Events\LastEvents), annak az
     * eventsFloor() való - a displayFloor() ott létező, szerkeszthető
     * eseményeket rejtene el.
     */
    public static function displayFloor(): ?Carbon
    {
        $events = self::eventsFloor();
        $groupData = self::groupDataFloor();

        if ($events === null) {
            return $groupData;
        }

        if ($groupData === null) {
            return $events;
        }

        return $events->greaterThan($groupData) ? $events : $groupData;
    }

    private static function floor(int $months): Carbon
    {
        return Carbon::today()->subMonthsNoOverflow($months)->startOfDay();
    }
}
