<?php

namespace App\Console\Commands;

use App\Models\Group;
use App\Support\Weather\WeatherCache;
use Illuminate\Console\Command;

/**
 * Az időjárás-gyorsítótár frissítése az időjárást használó csoportok
 * településeire.
 *
 * MIÉRT LÉTEZIK
 *
 * A funkció legnagyobb hiányossága az volt, hogy SEMMI nem frissítette a
 * gyorsítótárat. A `weather_cities` sorok kizárólag akkor íródtak, amikor egy
 * csoportadmin elmentette a csoport űrlapját vagy megnyomta az ellenőrzés
 * gombot; a naptár tiszta olvasó. A benne lévő 59 perces frissesség-szabály
 * ezért soha nem futott le a megjelenítési úton, és a hírnökök tetszőlegesen
 * régi előrejelzést láttak - akár hetekig, ha közben senki nem nyúlt a csoport
 * beállításaihoz.
 *
 * A NAPTÁR TISZTA OLVASÓ MARAD. Renderelés közben semmilyen HTTP-hívás nem
 * indul; a frissítés kizárólag itt, az ütemezőn keresztül történik.
 *
 * KERET ÉS ÜTEMEZÉS
 *
 * Az ingyenes szint 1000 hívás/nap és 60 hívás/perc. Egy frissítés
 * TELEPÜLÉSENKÉNT 2 hívás (jelenlegi időjárás + előrejelzés). Az ütemezés
 * `0 * /3 * * *`, azaz háromóránként: 2 hívás/település/futás mellett ez
 * nagyjából 60 települést támogat, bőven a napi kereten belül. Az előrejelzés
 * maga is csak 3 óránkénti felbontású, tehát sűrűbb frissítés nem adna többet.
 * A WeatherCache 15 perces `last_try` korlátja marad a végső fék.
 *
 * A településeket DISTINCT módon járjuk be: több csoport is használhatja
 * ugyanazt, és nem akarunk kétszer fizetni érte.
 */
class RefreshWeatherCache extends Command
{
    protected $signature = 'weather:refresh';

    protected $description = 'Refresh the cached weather for every city used by a weather-enabled group';

    public function handle(WeatherCache $cache)
    {
        if (config('weather') != 1) {
            $this->info('Weather is disabled; nothing to refresh.');

            return self::SUCCESS;
        }

        $cities = Group::query()
            ->where('weather_enabled', 1)
            ->whereNotNull('city_id')
            ->join('weather_cities', 'groups.city_id', '=', 'weather_cities.id')
            ->select('weather_cities.city', 'weather_cities.country')
            ->distinct()
            ->get();

        if ($cities->isEmpty()) {
            $this->info('No weather-enabled group has a city; nothing to refresh.');

            return self::SUCCESS;
        }

        $refreshed = 0;
        $failed = 0;

        foreach ($cities as $city) {
            $result = $cache->refreshCity($city->city, $city->country);

            if (isset($result['error'])) {
                $failed++;
                $this->warn(sprintf('%s, %s: %s', $city->city, $city->country, $result['error']));

                continue;
            }

            $refreshed++;
        }

        $this->info("Refreshed {$refreshed} city/cities.");

        if ($failed > 0) {
            $this->warn("{$failed} city/cities could not be refreshed.");
        }

        // A részleges sikertelenség NEM hiba: egy ismeretlen település vagy egy
        // átmeneti API-kimaradás nem szabad, hogy riasztást váltson ki az
        // ütemezőben. Az érintett sorok üzenete a naplóban ott van.
        return self::SUCCESS;
    }
}
