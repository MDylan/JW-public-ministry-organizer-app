<?php

namespace App\Support\Updates;

use MDylan\LaraUpdater\LaraUpdaterController;

/**
 * Meddig frissíthet a rendszer AUTOMATIKUSAN: a saját major ágán belül.
 *
 * A PROBLÉMA
 *
 * A laraupdater egyetlen feltétele az, hogy a csatorna újabbat hirdessen a
 * telepítettnél (`version_compare($remote, $local, '>')`). Sem PHP-, sem
 * Laravel-, sem major-verzió korlátja nincs. Amint a 2.x vonal megjelenik a
 * csatornán, minden 1.x telepítés EGY KATTINTÁSRA ráfrissülne - karbantartás
 * módban, `migrate --force`-szal -, holott a 2.x már PHP 8.3+-t és egy másik
 * Laravel majort feltételez. A `restore()` a felülírt fájlokat visszahozza, a
 * lefuttatott migrációkat NEM: a telepítés használhatatlan maradna.
 *
 * A SZABÁLY
 *
 * Major verziót automatikusan nem lépünk át. Ami az aktuális ágon van, az
 * változatlanul egy kattintás; ami magasabb majorra visz, arról a rendszer
 * csak ÉRTESÍT, és kézi frissítést kér.
 *
 * A plafon szándékosan DERIVÁLT, nem konfigurált: a version.txt majorjából
 * jön. Így nincs mit elfelejteni karbantartani - a 2.x vonal ugyanígy védve
 * lesz a 3.x-től -, és nincs olyan config érték, aminek a kiürítése némán
 * visszakapcsolná az automatikus major-ugrást.
 *
 * FAIL-CLOSED
 *
 * Ha bármelyik oldal majorja nem olvasható ki, a válasz `false`, azaz tiltás.
 * A két hibalehetőség nem egyenrangú: a téves tiltás annyit jelent, hogy az
 * adminnak kézzel kell frissítenie, a téves engedés viszont éles rendszert
 * tesz tönkre.
 *
 * AMI EZT KIVÜLRŐL IS BIZTOSÍTJA
 *
 * Ez a korlát csak azokon a telepítéseken véd, amelyeken MÁR FUT az őt
 * tartalmazó kiadás. A régebbieket a vendor `previous_version` lánca hozza
 * ide: ha a csatorna feje 2.0.0, és a `previous_version`-je az utolsó 1.x
 * kiadás, akkor egy régi telepítés előbb arra frissül fel - vagyis a korlátot
 * megkerülve senki nem juthat 2.0.0-ra. A manifeszt ilyen alakja tehát nem
 * kényelmi kérdés, hanem ennek a védelemnek a része (lásd release/README.md).
 */
final class UpdateBranch
{
    /**
     * Telepíthető-e ez a verzió automatikusan?
     *
     * A hívó a laraupdater check() eredményét adja át, tehát a verzió itt már
     * biztosan újabb a telepítettnél; egyedül a major számít.
     */
    public static function allows(string $version): bool
    {
        $current = self::currentMajor();
        $remote = self::majorOf($version);

        if ($current === null || $remote === null) {
            return false;
        }

        return $remote <= $current;
    }

    /**
     * A telepített verzió majorja.
     *
     * A verziót a laraupdater kontrollerétől kérjük, nem a version.txt
     * újraolvasásával: ott van a trim(), ami nélkül a fájl záró sortörése
     * minden összehasonlítást elront (lásd a UpdaterContractTest erre írt
     * külön tesztjét).
     */
    public static function currentMajor(): ?int
    {
        return self::majorOf((new LaraUpdaterController)->getCurrentVersion());
    }

    /**
     * Egy verziószám majorja, vagy null, ha nem olvasható ki.
     *
     * Megengedő a bemenetre ('v2.0.0', '2.0.0-beta1', ' 2.0 '), mert a
     * csatorna tartalmát nem mi validáljuk - de ami nem számjeggyel kezdődik,
     * az nem verzió, és a hívó oldalán tiltást jelent.
     */
    public static function majorOf(string $version): ?int
    {
        if (preg_match('/^\s*v?(\d+)/', $version, $matches) !== 1) {
            return null;
        }

        return (int) $matches[1];
    }
}
