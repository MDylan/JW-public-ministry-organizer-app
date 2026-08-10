<?php

namespace App\Observers;

use App\Models\Settings;
use App\Support\Settings\ApplicationSettings;

/**
 * TODO 31: invalidation of the application settings cache.
 *
 * WHY IT EXISTS
 *
 * ApplicationSettings reads the settings table behind
 * Cache::rememberForever, so the entry has NO expiry. Without invalidation a
 * switch flipped on the admin screen would never take effect.
 *
 * The invalidation is therefore bound to the SOURCE of the settings - not to
 * the call sites that write them - following StaticPageObserver. It fits even
 * better here than it does for the side menu: every write in the application
 * goes through Settings::updateOrCreate() (Admin\Settings in seven places,
 * CoreSettingsSeeder, the installer), so a single saved/deleted hook covers all
 * of them. A future seeder, artisan command or import is caught the same way,
 * without anyone having to know the cache exists.
 *
 * WHAT IT DOES NOT COVER: a write that bypasses the model, straight in SQL.
 * There is none in the application today; if one appears, it has to flush by
 * hand.
 */
class SettingsObserver
{
    public function saved(Settings $settings)
    {
        self::flush();
    }

    public function deleted(Settings $settings)
    {
        self::flush();
    }

    /**
     * Entry point for flushing outside the model events.
     */
    public static function flush(): void
    {
        app(ApplicationSettings::class)->flush();
    }
}
