<?php

namespace App\Console\Commands;

use App\Models\Group;
use App\Support\Weather\WeatherCache;
use Illuminate\Console\Command;

/**
 * Refreshing the weather cache for the cities of groups that use the
 * weather feature.
 *
 * WHY IT EXISTS
 *
 * The feature's biggest shortcoming was that NOTHING refreshed the
 * cache. The `weather_cities` rows were only ever written when a
 * group admin saved the group's form or pressed the check
 * button; the calendar is a pure reader. The 59-minute freshness rule inside it
 * therefore never ran on the display path, and publishers saw an arbitrarily
 * old forecast - even for weeks, if nobody touched the group's
 * settings in the meantime.
 *
 * THE CALENDAR STAYS A PURE READER. No HTTP call starts during
 * rendering; refreshing happens exclusively here, via the scheduler.
 *
 * QUOTA AND SCHEDULE
 *
 * The free tier is 1000 calls/day and 60 calls/minute. One refresh is
 * 2 calls PER CITY (current weather + forecast). The schedule is
 * `0 * /3 * * *`, i.e. every three hours: at 2 calls/city/run this
 * supports roughly 60 cities, comfortably within the daily quota. The forecast
 * itself is only resolved to 3-hour buckets anyway, so a more frequent refresh wouldn't add anything.
 * WeatherCache's 15-minute `last_try` limit remains the final safeguard.
 *
 * We iterate over the cities DISTINCT: multiple groups can use
 * the same one, and we don't want to pay for it twice.
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

        // A partial failure is NOT an error: an unknown city or a
        // transient API outage should not trigger an alert in the
        // scheduler. The affected rows' messages are there in the log.
        return self::SUCCESS;
    }
}
