<?php

namespace App\Observers;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Cache;

/**
 * Invalidates the side-menu cache for static pages.
 *
 * WHY IT EXISTS
 *
 * The SetLocale middleware builds the side menu with
 * Cache::rememberForever('sidemenu_auth' / 'sidemenu_guest'), so the entry has
 * NO expiry. Flushing used to happen manually in only two places:
 * Admin\StaticPageEdit and the installer's AccountController. Anything else -
 * a seeder, an artisan command, an import, a direct model write, a future
 * other editor - could create or modify a page such that it NEVER showed up
 * in the menu.
 *
 * The observer ties this to the menu's SOURCE instead of the call sites: if a
 * StaticPage or its translation changes, the cache is flushed. The two manual
 * forget() calls could therefore be removed.
 *
 * We also watch translations, because the menu displays titles, and those
 * live in the static_page_translations table - a plain title edit doesn't
 * touch the StaticPage row itself.
 *
 * The same class serves both models, which is why the parameter is Model and
 * not a concrete type.
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
     * Entry point for flushing outside of model events.
     */
    public static function flush(): void
    {
        Cache::forget('sidemenu_auth');
        Cache::forget('sidemenu_guest');
    }
}
