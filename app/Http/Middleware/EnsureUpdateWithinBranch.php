<?php

namespace App\Http\Middleware;

use App\Support\Updates\UpdateBranch;
use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use MDylan\LaraUpdater\LaraUpdaterController;

/**
 * A frissítő végpont kapuja: major verziót nem enged átlépni.
 *
 * MIÉRT KELL, HA A FELÜLET ÚGYIS ELREJTI A GOMBOT
 *
 * Mert a gomb elrejtése nem védelem. Az `updater.update` egy sima GET cím,
 * amit könyvtárjelzőből, előzményekből vagy kézzel is meg lehet nyitni, és
 * onnantól a `update()` letölt, karbantartás módba kapcsol és migrál. A
 * korlátnak ott kell lennie, ahol a művelet elindul.
 *
 * MIÉRT CSAK AZ UPDATE VÉGPONTON
 *
 * A middleware a config('laraupdater.middleware') tömbön keresztül MINDHÁROM
 * végpontra rákerül, de a `check` és a `currentVersion` írásmentes lekérdezés
 * - azokat elzárni értelmetlen lenne, hiszen épp az elérhető verzió
 * megmutatása a cél.
 *
 * MIÉRT A CACHE ÜRÍTÉSÉVEL
 *
 * Az `update()` szándékosan cache-t megkerülve olvassa a manifesztet
 * (`$this->cache = false`), mert egy 15 perces cache-bejegyzésből indítani
 * telepítést kockázatos. Ha a kapu a cache-elt állapotot nézné, a kettő
 * elcsúszhatna: a guard egy régi 1.x verziót látna, az update() pedig már a
 * frissen kirakott 2.0.0-t telepítené. Ezért itt is friss olvasás történik,
 * és a check() rögtön újra is tölti a cache-t a következő oldalrendereléshez.
 *
 * A SORREND A CONFIGBAN SZÁMÍT
 *
 * Ez a middleware az `auth` és a `can:is-admin` MÖGÖTT áll. Így a kimenő
 * hálózati kérés csak bejelentkezett adminnál fut le - vendéget és nem-admint
 * továbbra is az előtte álló két réteg utasít el, még a csatorna megkérdezése
 * előtt.
 */
class EnsureUpdateWithinBranch
{
    public function handle(Request $request, Closure $next)
    {
        if (! $request->routeIs('laraupdater.update')) {
            return $next($request);
        }

        Cache::forget('laraupdater_lastversion');

        $version = (new LaraUpdaterController)->check();

        if ($version !== '' && ! UpdateBranch::allows($version)) {
            abort(403, __('app.update_manual_blocked', ['version' => $version]));
        }

        return $next($request);
    }
}
